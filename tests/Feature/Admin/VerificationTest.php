<?php

use App\Enums\AccessRequestStatus;
use App\Enums\ClientStatus;
use App\Enums\ConnectionStatus;
use App\Enums\ConnectionType;
use App\Jobs\VerifyAccessRequestJob;
use App\Models\GbpAccessRequest;
use App\Models\GbpClient;
use App\Models\GbpGoogleConnection;
use App\Models\User;
use App\Services\Google\GoogleAccessVerificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeGoogle;

/**
 * A client whose invitation was already sent and is being tracked.
 *
 * @return array{0: GbpClient, 1: GbpAccessRequest, 2: GbpGoogleConnection}
 */
function pendingRequest(?string $status = null): array
{
    $client = GbpClient::factory()->withLocation('111')->withStatus(ClientStatus::PendingApproval)->create();
    $connection = GbpGoogleConnection::create([
        'gbp_client_id' => $client->id,
        'connection_type' => ConnectionType::Client,
        'access_token' => 'access-token-1', 'refresh_token' => 'refresh-token-1',
        'expires_at' => now()->addHour(), 'status' => ConnectionStatus::Active,
    ]);
    $request = GbpAccessRequest::factory()->for($client, 'client')->create([
        'google_connection_id' => $connection->id,
        'location_resource_name' => 'locations/111',
        'requested_account' => FakeGoogle::LOCATION_GROUP,
        'invitation_resource_name' => 'locations/111/admins/77',
        'status' => $status ?? AccessRequestStatus::PendingApproval,
        'submitted_at' => now()->subHour(),
    ]);

    return [$client, $request, $connection];
}

it('marks a client connected only when Google lists the Location Group as Manager', function () {
    FakeGoogle::fake(['GET /admins' => Http::response(['admins' => [FakeGoogle::admin(pending: false)]])]);
    [$client, $request] = pendingRequest();

    $result = app(GoogleAccessVerificationService::class)->refresh($request);

    expect($result->isConnected())->toBeTrue()
        ->and($client->fresh()->access_status)->toBe(ClientStatus::Connected)
        ->and($request->fresh())->status->toBe(AccessRequestStatus::Connected)
        ->and($request->fresh()->approved_at)->not->toBeNull();
});

it('revokes the client\'s Google token once access is confirmed', function () {
    FakeGoogle::fake(['GET /admins' => Http::response(['admins' => [FakeGoogle::admin(pending: false)]])]);
    [, $request, $connection] = pendingRequest();

    app(GoogleAccessVerificationService::class)->refresh($request);

    expect($connection->fresh())->status->toBe(ConnectionStatus::Revoked)->access_token->toBeNull()->refresh_token->toBeNull();
    Http::assertSent(fn ($r) => str_contains($r->url(), 'oauth2.googleapis.com/revoke'));
});

it('keeps showing pending while the invitation is still waiting', function () {
    FakeGoogle::fake(['GET /admins' => Http::response(['admins' => [FakeGoogle::admin(pending: true)]])]);
    [$client, $request] = pendingRequest();

    app(GoogleAccessVerificationService::class)->refresh($request);

    expect($client->fresh()->access_status)->toBe(ClientStatus::PendingApproval)
        ->and($request->fresh()->status)->toBe(AccessRequestStatus::PendingApproval);
});

it('does not accept a read-only or lower role as Manager access', function () {
    FakeGoogle::fake(['GET /admins' => Http::response(['admins' => [FakeGoogle::admin(pending: false, role: 'SITE_MANAGER')]])]);
    [$client, $request] = pendingRequest();

    app(GoogleAccessVerificationService::class)->refresh($request);

    expect($client->fresh()->access_status)->not->toBe(ClientStatus::Connected);
});

it('matches the invitation by name even if Google omits the account field', function () {
    $admin = FakeGoogle::admin(pending: false);
    unset($admin['account']);
    FakeGoogle::fake(['GET /admins' => Http::response(['admins' => [$admin]])]);
    [$client, $request] = pendingRequest();

    app(GoogleAccessVerificationService::class)->refresh($request);

    expect($client->fresh()->access_status)->toBe(ClientStatus::Connected);
});

it('marks the request rejected when the invitation disappears after the grace period', function () {
    FakeGoogle::fake(['GET /admins' => Http::response(['admins' => []])]);
    [$client, $request] = pendingRequest();

    app(GoogleAccessVerificationService::class)->refresh($request);

    expect($client->fresh()->access_status)->toBe(ClientStatus::Rejected)
        ->and($request->fresh()->status)->toBe(AccessRequestStatus::Rejected);
});

it('waits instead of rejecting a request that was only just submitted', function () {
    FakeGoogle::fake(['GET /admins' => Http::response(['admins' => []])]);
    [$client, $request] = pendingRequest();
    $request->update(['submitted_at' => now()]);

    app(GoogleAccessVerificationService::class)->refresh($request);

    expect($client->fresh()->access_status)->toBe(ClientStatus::PendingApproval);
});

it('leaves everything unchanged when the check itself fails', function () {
    FakeGoogle::fake(['GET /admins' => FakeGoogle::error(429, 'RESOURCE_EXHAUSTED')]);
    [$client, $request] = pendingRequest();

    app(GoogleAccessVerificationService::class)->refresh($request);

    expect($client->fresh()->access_status)->toBe(ClientStatus::PendingApproval)
        ->and($request->fresh())->status->toBe(AccessRequestStatus::PendingApproval)->error_code->toBe('RATE_LIMITED');
});

it('verifies from the agency side when the client token is gone', function () {
    FakeGoogle::fake([
        'GET mybusinessbusinessinformation' => Http::response(['locations' => [FakeGoogle::location('111', 'Test Cafe')]]),
    ]);
    [$client, $request, $connection] = pendingRequest();
    $connection->update(['status' => ConnectionStatus::Revoked]);
    GbpGoogleConnection::create([
        'connection_type' => ConnectionType::Agency, 'access_token' => 'agency-token', 'refresh_token' => 'agency-refresh',
        'expires_at' => now()->addHour(), 'status' => ConnectionStatus::Active,
    ]);

    app(GoogleAccessVerificationService::class)->refresh($request);

    expect($client->fresh()->access_status)->toBe(ClientStatus::Connected);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'accounts/999/locations') && $r->hasHeader('Authorization', 'Bearer agency-token'));
});

it('lets staff refresh the status from the dashboard', function () {
    FakeGoogle::fake(['GET /admins' => Http::response(['admins' => [FakeGoogle::admin(pending: false)]])]);
    [$client] = pendingRequest();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.clients.refresh', $client))
        ->assertSessionHas('status');

    expect($client->fresh()->access_status)->toBe(ClientStatus::Connected);
});

it('queues a check for every pending request but not for finished ones', function () {
    Queue::fake();
    [, $pending] = pendingRequest();
    [, $connected] = pendingRequest(AccessRequestStatus::Connected->value);

    $this->artisan('gbp:verify-pending')->assertSuccessful();

    Queue::assertPushed(VerifyAccessRequestJob::class, 1);
    Queue::assertPushed(VerifyAccessRequestJob::class, fn ($job) => $job->accessRequestId === $pending->id);
});

it('skips the background check when the request is no longer pending', function () {
    FakeGoogle::fake();
    [, $request] = pendingRequest(AccessRequestStatus::Connected->value);

    (new VerifyAccessRequestJob($request->id))->handle(app(GoogleAccessVerificationService::class));

    Http::assertNothingSent();
});

it('approves a pending invitation for the Location Group from Settings', function () {
    FakeGoogle::fake([
        'GET /invitations' => Http::response(['invitations' => [[
            'name' => 'accounts/999/invitations/555',
            'role' => 'MANAGER',
            'targetLocation' => ['locationName' => 'Test Cafe', 'address' => '1 Main St'],
        ]]]),
    ]);
    GbpGoogleConnection::create([
        'connection_type' => ConnectionType::Agency, 'access_token' => 'agency-token', 'refresh_token' => 'agency-refresh',
        'expires_at' => now()->addHour(), 'status' => ConnectionStatus::Active,
    ]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.settings.edit'))->assertOk()->assertSee('Test Cafe')->assertSee('Approve');

    $this->actingAs($admin)->post(route('admin.settings.invitations.accept'), ['invitation' => 'accounts/999/invitations/555'])
        ->assertSessionHas('status');

    Http::assertSent(fn ($r) => $r->method() === 'POST'
        && $r->url() === 'https://mybusinessaccountmanagement.googleapis.com/v1/accounts/999/invitations/555:accept'
        && $r->hasHeader('Authorization', 'Bearer agency-token'));
});

it('rejects a malformed invitation name', function () {
    FakeGoogle::fake();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.settings.invitations.accept'), ['invitation' => '../../evil'])
        ->assertSessionHasErrors('invitation');
});
