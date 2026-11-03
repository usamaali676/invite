<?php

namespace App\Http\Controllers;

use App\Enums\ActorType;
use App\Enums\ClientStatus;
use App\Enums\ConnectionType;
use App\Exceptions\GoogleApiException;
use App\Models\GbpClient;
use App\Models\GbpGoogleConnection;
use App\Models\GbpOnboardingLink;
use App\Services\Google\GoogleAccountService;
use App\Services\Google\GoogleOAuthService;
use App\Services\Google\GoogleTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The single OAuth redirect URI. The stored, single-use state decides whether
 * the returning browser belongs to an FTS admin (agency) or to a client.
 */
class GoogleCallbackController extends Controller
{
    public function __construct(
        private GoogleOAuthService $oauth,
        private GoogleTokenService $tokens,
        private GoogleAccountService $accounts,
    ) {}

    public function __invoke(Request $request): RedirectResponse|Response
    {
        $flow = $this->oauth->consumeState($request, $request->query('state'));

        if ($flow === null) {
            return $request->user()?->can('manage-gbp')
                ? redirect()->route('admin.settings.edit')->with('error', 'The Google sign-in expired or could not be verified. Please start again.')
                : response()->view('onboarding.invalid', [], 400);
        }

        return $flow['type'] === ConnectionType::Agency
            ? $this->handleAgency($request, $flow['context'])
            : $this->handleClient($request, $flow['context']);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function handleAgency(Request $request, array $context): RedirectResponse
    {
        $settings = redirect()->route('admin.settings.edit');

        if (! $request->user()?->can('manage-gbp') || (int) ($context['user_id'] ?? 0) !== $request->user()->id) {
            return redirect()->route('login');
        }

        if ($request->query('error') || ! $request->query('code')) {
            return $settings->with('error', 'Google did not grant access, so the FTS account was not connected.');
        }

        try {
            $tokens = $this->oauth->exchangeCode((string) $request->query('code'));
        } catch (GoogleApiException $e) {
            return $settings->with('error', $e->adminMessage());
        }

        if (! $this->grantedBusinessScope($tokens)) {
            return $settings->with('error', 'The Business Profile permission was not ticked on the Google screen. Connect again and keep it ticked.');
        }

        $connection = $this->tokens->storeConnection(ConnectionType::Agency, null, $tokens);
        $this->rememberIdentity($connection, $tokens['access_token'] ?? '');

        return $settings->with('status', 'FTS Google account connected.');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function handleClient(Request $request, array $context): RedirectResponse|Response
    {
        $link = GbpOnboardingLink::query()->find($context['link_id'] ?? 0);
        $token = $link?->plainToken();

        if ($link === null || $token === null || ! $link->isUsable() || $link->gbp_client_id !== ($context['client_id'] ?? null)) {
            return response()->view('onboarding.invalid', [], 404);
        }

        $client = $link->client;
        $back = redirect()->route('onboarding.show', ['token' => $token]);

        if ($request->query('error') || ! $request->query('code')) {
            $client->logEvent('oauth.denied', ActorType::Client, 'The client did not allow access on the Google screen.');

            return $back->with('oauth_denied', true);
        }

        try {
            $tokens = $this->oauth->exchangeCode((string) $request->query('code'));
        } catch (GoogleApiException) {
            $client->logEvent('oauth.failed', ActorType::Client, 'Google sign-in could not be completed.');

            return $back->with('problem', true);
        }

        if (! $this->grantedBusinessScope($tokens)) {
            $client->logEvent('oauth.scope_missing', ActorType::Client, 'The Business Profile permission was not granted.');

            return $back->with('oauth_denied', true);
        }

        $connection = $this->tokens->storeConnection(ConnectionType::Client, $client, $tokens);
        $this->rememberIdentity($connection, $tokens['access_token'] ?? '');

        $request->session()->put('gbp.connection.'.$link->id, $connection->id);
        $this->moveToConnected($client);
        $client->logEvent('oauth.connected', ActorType::Client, 'The client signed in with Google and allowed access.');

        return redirect()->route('onboarding.profiles', ['token' => $token]);
    }

    private function moveToConnected(GbpClient $client): void
    {
        $client->tryTransitionTo(ClientStatus::LinkOpened);
        $client->tryTransitionTo(ClientStatus::OauthConnected);
    }

    /**
     * @param  array<string, mixed>  $tokens
     */
    private function grantedBusinessScope(array $tokens): bool
    {
        return str_contains((string) ($tokens['scope'] ?? ''), $this->oauth->scope());
    }

    private function rememberIdentity(GbpGoogleConnection $connection, string $accessToken): void
    {
        try {
            $personal = $this->accounts->personalAccount($this->accounts->listAccounts($accessToken));
        } catch (GoogleApiException) {
            return;
        }

        if ($personal !== null) {
            $connection->forceFill([
                'google_account_id' => $personal['id'],
                'google_account_name' => $personal['accountName'],
            ])->save();
        }
    }
}
