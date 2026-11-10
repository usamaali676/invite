<?php

namespace App\Services\Google;

use App\Enums\AccessMethod;
use App\Enums\AccessRequestStatus;
use App\Enums\ActorType;
use App\Enums\ClientStatus;
use App\Enums\GoogleErrorType;
use App\Enums\VerificationState;
use App\Exceptions\GoogleApiException;
use App\Models\GbpAccessRequest;
use App\Models\GbpGoogleConnection;
use Illuminate\Support\Str;

/**
 * Decides, from Google's own data, whether the FTS Location Group really holds
 * access. A successful invitation POST is never treated as proof of access.
 */
class GoogleAccessVerificationService
{
    private const ACCEPTED_ROLES = ['MANAGER', 'OWNER', 'PRIMARY_OWNER'];

    /** A missing invitation only counts as "rejected" after this many minutes, to allow for Google propagation delay. */
    private const MISSING_GRACE_MINUTES = 5;

    public function __construct(
        private GoogleTokenService $tokens,
        private GoogleAdminService $admins,
        private GoogleLocationService $locations,
        private GoogleAgencyService $agency,
    ) {}

    /**
     * Ask Google, without changing any records.
     */
    public function verify(GbpAccessRequest $request): VerificationResult
    {
        $account = $request->requested_account;

        if (blank($account)) {
            return new VerificationResult(VerificationState::Unverifiable, 'No Location Group is stored on this request.', GoogleErrorType::NotConfigured);
        }

        $results = $request->access_method === AccessMethod::Api && filled($request->location_resource_name)
            ? array_filter([$this->viaClientConnection($request, $account), $this->viaAgencyLocation($request, $account)])
            : array_filter([$this->viaAgencyTitleSearch($request, $account)]);

        foreach ([VerificationState::Connected, VerificationState::Pending, VerificationState::Missing] as $wanted) {
            foreach ($results as $result) {
                if ($result->state === $wanted) {
                    return $result;
                }
            }
        }

        return $results[array_key_last($results)]
            ?? new VerificationResult(VerificationState::Unverifiable, 'No valid Google authorization is available to check this request.', GoogleErrorType::TokenRevoked);
    }

    /**
     * Verify and apply the outcome to the request and the client.
     */
    public function refresh(GbpAccessRequest $request, ActorType $actor = ActorType::System): VerificationResult
    {
        $result = $this->verify($request);
        $client = $request->client;

        $request->forceFill(['last_checked_at' => now()])->save();

        switch ($result->state) {
            case VerificationState::Connected:
                $request->forceFill([
                    'status' => AccessRequestStatus::Connected,
                    'approved_at' => $request->approved_at ?? now(),
                    'error_code' => null,
                    'error_message' => null,
                ])->save();

                if ($result->locationName && ! $client->hasSelectedLocation()) {
                    $client->forceFill([
                        'location_resource_name' => $result->locationName,
                        'google_location_id' => Str::after($result->locationName, 'locations/'),
                    ])->save();
                }

                $client->tryTransitionTo(ClientStatus::PendingApproval);
                $client->tryTransitionTo(ClientStatus::Connected);
                $this->releaseClientAuthorization($request);
                break;

            case VerificationState::Pending:
                $request->forceFill([
                    'status' => AccessRequestStatus::PendingApproval,
                    'error_code' => null,
                    'error_message' => null,
                ])->save();
                $client->tryTransitionTo(ClientStatus::PendingApproval);
                break;

            case VerificationState::Missing:
                if ($request->status->isPending() && $request->submitted_at?->lt(now()->subMinutes(self::MISSING_GRACE_MINUTES))) {
                    $request->forceFill([
                        'status' => AccessRequestStatus::Rejected,
                        'error_code' => 'INVITATION_NOT_FOUND',
                        'error_message' => $result->detail,
                    ])->save();
                    $client->tryTransitionTo(ClientStatus::Rejected);
                }
                break;

            case VerificationState::Unverifiable:
                $request->forceFill([
                    'error_code' => $result->error?->value,
                    'error_message' => $result->detail,
                ])->save();
                break;
        }

        $client->logEvent('verification.'.strtolower($result->state->value), $actor, $result->detail, [], $request);

        return $result;
    }

    private function viaClientConnection(GbpAccessRequest $request, string $account): ?VerificationResult
    {
        $connection = $request->googleConnection;

        if ($connection === null || ! $connection->isActive()) {
            return null;
        }

        try {
            $token = $this->tokens->validAccessToken($connection);
            $admins = $this->admins->listLocationAdmins($token, $request->location_resource_name);
        } catch (GoogleApiException $e) {
            return new VerificationResult(VerificationState::Unverifiable, $e->adminMessage(), $e->type);
        }

        foreach ($admins as $admin) {
            $sameInvitation = filled($request->invitation_resource_name) && ($admin['name'] ?? null) === $request->invitation_resource_name;
            $sameAccount = $this->normalizeAccount((string) ($admin['account'] ?? '')) === $this->normalizeAccount($account);

            if (! $sameInvitation && ! $sameAccount) {
                continue;
            }

            $role = (string) ($admin['role'] ?? '');

            if (($admin['pendingInvitation'] ?? false) === true) {
                return new VerificationResult(VerificationState::Pending, 'Google shows the invitation as pending and waiting for the FTS Location Group.');
            }

            if (in_array($role, self::ACCEPTED_ROLES, true)) {
                return new VerificationResult(VerificationState::Connected, "Google lists the FTS Location Group as {$role} of this location.", locationName: $request->location_resource_name);
            }

            return new VerificationResult(VerificationState::Unverifiable, "The Location Group is listed with role {$role}, not Manager.");
        }

        return new VerificationResult(VerificationState::Missing, 'The location administrator list does not contain the FTS Location Group or the invitation.');
    }

    private function viaAgencyLocation(GbpAccessRequest $request, string $account): ?VerificationResult
    {
        $connection = $this->agency->connection();

        if ($connection === null) {
            return null;
        }

        try {
            $token = $this->tokens->validAccessToken($connection);

            if ($this->locations->accountHasLocation($token, $account, $request->location_resource_name)) {
                return new VerificationResult(VerificationState::Connected, 'The location is listed under the FTS Location Group.', locationName: $request->location_resource_name);
            }

            return $this->pendingInvitationResult($request->client->google_location_title)
                ?? new VerificationResult(VerificationState::Unverifiable, 'The location is not visible to the FTS Location Group yet.');
        } catch (GoogleApiException $e) {
            return new VerificationResult(VerificationState::Unverifiable, $e->adminMessage(), $e->type);
        }
    }

    /**
     * Manual requests have no location id, so the business is looked up by name inside the Location Group.
     */
    private function viaAgencyTitleSearch(GbpAccessRequest $request, string $account): ?VerificationResult
    {
        $connection = $this->agency->connection();

        if ($connection === null) {
            return new VerificationResult(
                VerificationState::Unverifiable,
                'Connect the FTS Google account in Settings to check access automatically, or confirm it manually in Business Profile Manager.',
                GoogleErrorType::TokenRevoked,
            );
        }

        $wanted = $this->normalizeTitle($request->client->business_name);

        try {
            $token = $this->tokens->validAccessToken($connection);

            foreach ($this->locations->listForAccount($token, $account) as $location) {
                if ($this->normalizeTitle($location['title']) === $wanted) {
                    return new VerificationResult(VerificationState::Connected, 'A location named "'.$location['title'].'" is listed under the FTS Location Group.', locationName: $location['name']);
                }
            }

            return $this->pendingInvitationResult($request->client->business_name)
                ?? new VerificationResult(VerificationState::Unverifiable, 'No business with this name is listed under the FTS Location Group yet.');
        } catch (GoogleApiException $e) {
            return new VerificationResult(VerificationState::Unverifiable, $e->adminMessage(), $e->type);
        }
    }

    private function pendingInvitationResult(?string $title): ?VerificationResult
    {
        if (blank($title)) {
            return null;
        }

        foreach ($this->agency->pendingInvitations() as $invitation) {
            if ($this->normalizeTitle($invitation['location']) === $this->normalizeTitle($title)) {
                return new VerificationResult(VerificationState::Pending, 'An invitation for this business is waiting for the FTS Location Group to approve it.');
            }
        }

        return null;
    }

    private function normalizeAccount(string $account): string
    {
        return $account === '' ? '' : (str_starts_with($account, 'accounts/') ? $account : 'accounts/'.$account);
    }

    public static function normalizeTitle(string $title): string
    {
        return Str::of($title)->lower()->replaceMatches('/[^\p{L}\p{N}]+/u', ' ')->squish()->toString();
    }

    /**
     * Once access is confirmed the client's Google token is no longer needed.
     */
    private function releaseClientAuthorization(GbpAccessRequest $request): void
    {
        $connection = $request->googleConnection;

        if ($connection instanceof GbpGoogleConnection && $connection->isActive()) {
            $this->tokens->disconnect($connection);
        }
    }
}
