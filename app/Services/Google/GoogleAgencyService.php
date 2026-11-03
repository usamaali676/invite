<?php

namespace App\Services\Google;

use App\Enums\ApiApprovalStatus;
use App\Enums\GoogleErrorType;
use App\Exceptions\GoogleApiException;
use App\Models\GbpAgencySetting;
use App\Models\GbpGoogleConnection;

/**
 * Everything that happens with FTS's own (agency) Google authorization:
 * readiness checks, discovering the Location Group, and handling invitations
 * received by the Location Group.
 */
class GoogleAgencyService
{
    public function __construct(
        private GoogleOAuthService $oauth,
        private GoogleTokenService $tokens,
        private GoogleAccountService $accounts,
        private GoogleApiClient $api,
    ) {}

    public function settings(): GbpAgencySetting
    {
        return GbpAgencySetting::current();
    }

    public function connection(): ?GbpGoogleConnection
    {
        return GbpGoogleConnection::activeAgency();
    }

    /**
     * Whether automatic (API) onboarding can be offered to clients right now. Clients are only
     * sent to Google once the project is marked as approved; before that every API call would
     * fail after they had already given consent.
     */
    public function canRunAutomaticFlow(): bool
    {
        $settings = $this->settings();

        return $this->oauth->isConfigured()
            && $settings->hasLocationGroup()
            && $settings->api_approval_status === ApiApprovalStatus::Approved;
    }

    /**
     * Checklist shown on the Settings page.
     *
     * @return array<int, array{label: string, ok: bool, hint: string}>
     */
    public function readiness(): array
    {
        $settings = $this->settings();
        $production = app()->environment('production');

        return [
            [
                'label' => 'Google OAuth credentials',
                'ok' => $this->oauth->isConfigured(),
                'hint' => 'Set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in .env (OAuth client of type "Web application").',
            ],
            [
                'label' => 'Secure site address (HTTPS)',
                'ok' => str_starts_with((string) config('app.url'), 'https://') || ! $production,
                'hint' => 'APP_URL must start with https:// in production. Google requires HTTPS redirect URIs for verified apps.',
            ],
            [
                'label' => 'Business Profile API access approved',
                'ok' => $settings->api_approval_status === ApiApprovalStatus::Approved,
                'hint' => 'Submit the "Application for Basic API Access" form, then set the status to Approved once the project quota shows 300 QPM.',
            ],
            [
                'label' => 'Google Cloud project number saved',
                'ok' => filled($settings->api_project_number),
                'hint' => 'Copy the project number from the Google Cloud dashboard.',
            ],
            [
                'label' => 'FTS Location Group ID saved',
                'ok' => $settings->hasLocationGroup(),
                'hint' => 'Enter the Location Group account ID, or connect the FTS Google account and pick it from the detected list.',
            ],
            [
                'label' => 'FTS Google account connected',
                'ok' => $this->connection() !== null,
                'hint' => 'Optional but recommended: lets FTS accept invitations and verify access from the dashboard.',
            ],
        ];
    }

    /**
     * Accounts visible to the connected FTS Google account that can act as the agency
     * Location Group (type LOCATION_GROUP) or belong to the organization.
     *
     * @return array<int, array{name: string, id: string, accountName: string, type: string, role: ?string}>
     *
     * @throws GoogleApiException
     */
    public function detectAgencyAccounts(): array
    {
        $connection = $this->requireConnection();
        $token = $this->tokens->validAccessToken($connection);
        $all = $this->accounts->listAccounts($token);

        $this->rememberAgencyIdentity($connection, $all);

        return array_values(array_filter(
            $all,
            fn (array $account) => in_array($account['type'], ['LOCATION_GROUP', 'ORGANIZATION', 'USER_GROUP'], true),
        ));
    }

    /**
     * Call a harmless endpoint to prove credentials, approval and quota work.
     *
     * @return array{ok: bool, message: string}
     */
    public function testApiConnection(): array
    {
        try {
            $connection = $this->requireConnection();
            $accounts = $this->accounts->listAccounts($this->tokens->validAccessToken($connection));
            $this->rememberAgencyIdentity($connection, $accounts);
        } catch (GoogleApiException $e) {
            return ['ok' => false, 'message' => $e->adminMessage()];
        }

        return ['ok' => true, 'message' => 'Google accepted the request. '.count($accounts).' account(s) are visible to the connected FTS Google user.'];
    }

    /**
     * Invitations waiting for the Location Group to approve.
     *
     * @return array<int, array{name: string, role: ?string, location: string, address: string}>
     *
     * @throws GoogleApiException
     */
    public function pendingInvitations(): array
    {
        $account = $this->settings()->locationGroupResourceName();

        if ($account === null) {
            throw new GoogleApiException(GoogleErrorType::NotConfigured);
        }

        $token = $this->tokens->validAccessToken($this->requireConnection());
        $response = $this->api->get($this->api->accountManagementUrl($account.'/invitations'), $token);

        return collect($response['invitations'] ?? [])->filter(fn (array $invitation) => isset($invitation['targetLocation']))->map(fn (array $invitation) => [
            'name' => (string) ($invitation['name'] ?? ''),
            'role' => $invitation['role'] ?? null,
            'location' => (string) ($invitation['targetLocation']['locationName'] ?? ''),
            'address' => (string) ($invitation['targetLocation']['address'] ?? ''),
        ])->filter(fn (array $row) => $row['name'] !== '')->values()->all();
    }

    /**
     * Approve a pending invitation on behalf of the Location Group.
     *
     * @throws GoogleApiException
     */
    public function acceptInvitation(string $invitationName): void
    {
        $token = $this->tokens->validAccessToken($this->requireConnection());

        $this->api->post($this->api->accountManagementUrl($invitationName.':accept'), $token);
    }

    public function disconnect(): void
    {
        if ($connection = $this->connection()) {
            $this->tokens->disconnect($connection);
        }
    }

    /**
     * @throws GoogleApiException
     */
    private function requireConnection(): GbpGoogleConnection
    {
        return $this->connection() ?? throw new GoogleApiException(GoogleErrorType::TokenRevoked);
    }

    /**
     * @param  array<int, array{type: string, id: string, accountName: string}>  $accounts
     */
    private function rememberAgencyIdentity(GbpGoogleConnection $connection, array $accounts): void
    {
        $personal = $this->accounts->personalAccount($accounts);

        if ($personal !== null) {
            $connection->forceFill([
                'google_account_id' => $personal['id'],
                'google_account_name' => $personal['accountName'],
            ])->save();
        }
    }
}
