<?php

namespace App\Services\Google;

use App\Enums\AccessMethod;
use App\Enums\AccessRequestStatus;
use App\Enums\ActorType;
use App\Enums\ClientStatus;
use App\Enums\ConnectionType;
use App\Enums\GoogleErrorType;
use App\Enums\VerificationState;
use App\Exceptions\GoogleApiException;
use App\Jobs\VerifyAccessRequestJob;
use App\Models\GbpAccessRequest;
use App\Models\GbpAgencySetting;
use App\Models\GbpClient;
use App\Models\GbpGoogleConnection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Submits the Manager invitation for the FTS Location Group using the
 * client's own Google authorization (locations.admins.create).
 */
class GoogleInvitationService
{
    public function __construct(
        private GoogleTokenService $tokens,
        private GoogleAdminService $admins,
        private GoogleAccessVerificationService $verification,
    ) {}

    /**
     * Returns the resulting request. A failure is recorded on the request (status FAILED)
     * instead of being thrown, so callers can show a friendly message.
     * Calling this twice for the same location never sends a second invitation.
     */
    public function requestManagerAccess(GbpClient $client, GbpGoogleConnection $connection, ActorType $actor = ActorType::Client): GbpAccessRequest
    {
        $this->assertConnectionBelongsToClient($client, $connection);

        $settings = GbpAgencySetting::current();
        $account = $settings->locationGroupResourceName();

        if ($account === null || ! $client->hasSelectedLocation()) {
            throw new InvalidArgumentException('The Location Group or the business profile is not configured.');
        }

        [$request, $created] = DB::transaction(function () use ($client, $connection, $settings, $account) {
            $locked = GbpClient::query()->lockForUpdate()->findOrFail($client->id);

            $existing = $locked->accessRequests()
                ->where('location_resource_name', $locked->location_resource_name)
                ->whereIn('status', [
                    AccessRequestStatus::Submitted->value,
                    AccessRequestStatus::PendingApproval->value,
                    AccessRequestStatus::Connected->value,
                ])
                ->latest('id')
                ->first();

            if ($existing !== null) {
                return [$existing, false];
            }

            return [$locked->accessRequests()->create([
                'access_method' => AccessMethod::Api,
                'google_connection_id' => $connection->id,
                'location_resource_name' => $locked->location_resource_name,
                'requested_account' => $account,
                'requested_role' => $settings->requestedRole(),
                'status' => AccessRequestStatus::Submitted,
            ]), true];
        });

        if (! $created) {
            $client->logEvent('request.duplicate_prevented', $actor, 'A request for this location already exists, so no new invitation was sent.', [], $request);

            return $request;
        }

        $client->tryTransitionTo(ClientStatus::RequestSubmitted);

        try {
            $token = $this->tokens->validAccessToken($connection);
            $admin = $this->admins->inviteAccount($token, $request->location_resource_name, $account, $request->requested_role);
        } catch (GoogleApiException $e) {
            return $this->handleFailure($client, $connection, $request, $e, $actor);
        }

        $request->forceFill([
            'invitation_resource_name' => $admin['name'] ?? null,
            'submitted_at' => now(),
            'status' => ($admin['pendingInvitation'] ?? true) ? AccessRequestStatus::PendingApproval : AccessRequestStatus::Submitted,
        ])->save();

        $client->logEvent('request.submitted', $actor, 'Manager invitation sent to Google for the FTS Location Group.', [
            'location' => $request->location_resource_name,
            'invitation' => $request->invitation_resource_name,
        ], $request);

        $client->onboardingLinks()->whereNull('revoked_at')->whereNull('used_at')->update(['used_at' => now()]);

        // Google's answer to the POST is not proof of access: check what Google actually shows.
        $this->verification->refresh($request, $actor);

        VerifyAccessRequestJob::dispatch($request->id)
            ->delay(now()->addSeconds((int) config('gbp.verification.first_check_delay_seconds')));

        return $request->refresh();
    }

    /**
     * Send the invitation again for a request that previously failed.
     * Uses the stored client authorization; fails cleanly when it is gone.
     */
    public function retry(GbpAccessRequest $failed, ActorType $actor = ActorType::Admin): GbpAccessRequest
    {
        $client = $failed->client;
        $connection = $client->activeClientConnection();

        if ($connection === null) {
            throw new GoogleApiException(GoogleErrorType::TokenRevoked);
        }

        $client->tryTransitionTo(ClientStatus::ProfileSelected);
        $client->logEvent('request.retry', $actor, 'Retrying the failed Google invitation.', [], $failed);

        return $this->requestManagerAccess($client, $connection, $actor);
    }

    private function handleFailure(GbpClient $client, GbpGoogleConnection $connection, GbpAccessRequest $request, GoogleApiException $e, ActorType $actor): GbpAccessRequest
    {
        if ($e->type->needsNewAuthorization()) {
            $this->tokens->markExpired($connection);
        }

        // Google says the invitation already exists: confirm what Google shows instead of failing.
        if (in_array($e->type, [GoogleErrorType::AlreadyPending, GoogleErrorType::Conflict], true)) {
            $request->forceFill(['submitted_at' => now(), 'status' => AccessRequestStatus::PendingApproval])->save();
            $result = $this->verification->refresh($request, $actor);

            if (in_array($result->state, [VerificationState::Connected, VerificationState::Pending], true)) {
                return $request->refresh();
            }
        }

        $request->forceFill([
            'status' => AccessRequestStatus::Failed,
            'error_code' => $e->type->value,
            'error_message' => $e->adminMessage(),
        ])->save();

        $client->tryTransitionTo(ClientStatus::Failed);
        $client->logEvent('request.failed', $actor, $e->adminMessage(), ['error' => $e->type->value, 'http_status' => $e->httpStatus], $request);

        return $request->refresh();
    }

    private function assertConnectionBelongsToClient(GbpClient $client, GbpGoogleConnection $connection): void
    {
        if ($connection->connection_type !== ConnectionType::Client || $connection->gbp_client_id !== $client->id) {
            throw new InvalidArgumentException('The Google authorization does not belong to this client.');
        }
    }
}
