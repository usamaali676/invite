<?php

use App\Enums\AccessRequestStatus;
use App\Enums\ClientStatus;
use App\Enums\ConnectionStatus;
use App\Enums\ConnectionType;
use App\Models\GbpClient;
use App\Models\GbpGoogleConnection;
use App\Models\GbpOnboardingLink;
use App\Models\User;
use App\Services\Google\GoogleInvitationService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeGoogle;

/**
 * Walks the browser through "Continue with Google" and Google's redirect back.
 *
 * @return array{0: string, 1: string} [onboarding token, oauth state]
 */
function startGoogle($test, GbpClient $client): array
{
    $token = GbpOnboardingLink::issue($client)->plainToken();
    $response = $test->post(route('onboarding.start', $token));
    $response->assertRedirectContains('https://accounts.google.com/o/oauth2/v2/auth');

    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

    return [$token, $query['state']];
}

function returnFromGoogle($test, string $state, array $params = ['code' => 'auth-code'])
{
    return $test->get('/google/callback?'.http_build_query($params + ['state' => $state]));
}

function connectedClient($test): array
{
    $client = GbpClient::factory()->withStatus(ClientStatus::LinkSent)->create(['business_name' => 'Test Cafe']);
    [$token, $state] = startGoogle($test, $client);
    returnFromGoogle($test, $state)->assertRedirect(route('onboarding.profiles', $token));

    return [$client, $token];
}

function invitationPosts(): int
{
    return Http::recorded(fn (Request $r) => $r->method() === 'POST' && str_contains($r->url(), '/admins'))->count();
}

beforeEach(function () {
    Queue::fake();
});

it('sends the client to Google with the minimum scope, offline access and a state value', function () {
    FakeGoogle::fake();
    $client = GbpClient::factory()->withStatus(ClientStatus::LinkSent)->create();

    [, $state] = startGoogle($this, $client);

    $url = $this->post(route('onboarding.start', GbpOnboardingLink::issue($client)->plainToken()))->headers->get('Location');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($query)->toMatchArray([
        'client_id' => 'test-client-id',
        'response_type' => 'code',
        'scope' => 'https://www.googleapis.com/auth/business.manage',
        'access_type' => 'offline',
        'redirect_uri' => route('google.callback'),
    ])->and(strlen($state))->toBeGreaterThanOrEqual(40)
        ->and($url)->not->toContain('test-secret');
});

it('refuses a callback whose state does not match', function () {
    FakeGoogle::fake();
    $client = GbpClient::factory()->withStatus(ClientStatus::LinkSent)->create();
    startGoogle($this, $client);

    returnFromGoogle($this, 'forged-state')->assertStatus(400);

    expect(GbpGoogleConnection::count())->toBe(0);
});

it('refuses a callback that arrives with no state at all', function () {
    FakeGoogle::fake();

    $this->get('/google/callback?code=abc')->assertStatus(400);
    expect(GbpGoogleConnection::count())->toBe(0);
});

it('only accepts a state value once', function () {
    FakeGoogle::fake();
    $client = GbpClient::factory()->withStatus(ClientStatus::LinkSent)->create();
    [, $state] = startGoogle($this, $client);

    returnFromGoogle($this, $state)->assertRedirect();
    returnFromGoogle($this, $state)->assertStatus(400);
});

it('handles a client who says no on the Google screen', function () {
    FakeGoogle::fake();
    $client = GbpClient::factory()->withStatus(ClientStatus::LinkSent)->create();
    [$token, $state] = startGoogle($this, $client);

    returnFromGoogle($this, $state, ['error' => 'access_denied'])->assertRedirect(route('onboarding.show', $token));

    $this->get(route('onboarding.show', $token))->assertSee('Nothing was shared with us');
    expect(GbpGoogleConnection::count())->toBe(0)
        ->and($client->fresh()->access_status)->not->toBe(ClientStatus::OauthConnected);
});

it('treats a missing Business Profile permission as a refusal', function () {
    FakeGoogle::fake(['POST oauth2.googleapis.com/token' => Http::response(FakeGoogle::tokens(['scope' => 'openid']))]);
    $client = GbpClient::factory()->withStatus(ClientStatus::LinkSent)->create();
    [$token, $state] = startGoogle($this, $client);

    returnFromGoogle($this, $state)->assertRedirect(route('onboarding.show', $token));
    expect(GbpGoogleConnection::count())->toBe(0);
});

it('stores Google tokens encrypted and tied to this client only', function () {
    FakeGoogle::fake();
    [$client] = connectedClient($this);

    $connection = GbpGoogleConnection::firstOrFail();
    $raw = DB::table('gbp_google_connections')->first();

    expect($connection->connection_type)->toBe(ConnectionType::Client)
        ->and($connection->gbp_client_id)->toBe($client->id)
        ->and($raw->access_token)->not->toContain('access-token-1')
        ->and($raw->refresh_token)->not->toContain('refresh-token-1')
        ->and($connection->refresh_token)->toBe('refresh-token-1')
        ->and($client->fresh()->access_status)->toBe(ClientStatus::OauthConnected);
});

it('shows a single business automatically and asks for confirmation', function () {
    FakeGoogle::fake();
    [, $token] = connectedClient($this);

    $this->get(route('onboarding.profiles', $token))->assertRedirect(route('onboarding.confirm', $token));

    $this->get(route('onboarding.confirm', $token))
        ->assertOk()
        ->assertSee('Test Cafe')
        ->assertSee('1 Main St, Austin TX 78701')
        ->assertSee('Manager')
        ->assertSee('I authorize Firm Tech Services to request Manager access', false)
        ->assertSee('Authorize Manager Access Request');
});

it('lets a client with several businesses pick the right one', function () {
    FakeGoogle::fake([
        'GET mybusinessbusinessinformation' => Http::response(['locations' => [
            FakeGoogle::location('111', 'Test Cafe'),
            FakeGoogle::location('222', 'Test Bakery', 'Dallas'),
        ]]),
    ]);
    [$client, $token] = connectedClient($this);

    $this->get(route('onboarding.profiles', $token))->assertOk()->assertSee('Test Cafe')->assertSee('Test Bakery')->assertSee('Dallas');

    $this->post(route('onboarding.select', $token), ['location' => 'locations/222'])->assertRedirect(route('onboarding.confirm', $token));

    expect($client->fresh())
        ->location_resource_name->toBe('locations/222')
        ->google_location_title->toBe('Test Bakery')
        ->access_status->toBe(ClientStatus::ProfileSelected);
});

it('rejects a location that does not belong to the signed-in Google account', function () {
    FakeGoogle::fake([
        'GET mybusinessbusinessinformation' => Http::response(['locations' => [
            FakeGoogle::location('111', 'Test Cafe'),
            FakeGoogle::location('222', 'Test Bakery'),
        ]]),
    ]);
    [$client, $token] = connectedClient($this);

    $this->post(route('onboarding.select', $token), ['location' => 'locations/999999'])->assertSessionHasErrors('location');

    expect($client->fresh()->location_resource_name)->toBeNull();
});

it('explains kindly when the Google account has no business listings', function () {
    FakeGoogle::fake(['GET mybusinessbusinessinformation' => Http::response(['locations' => []])]);
    [, $token] = connectedClient($this);

    $this->get(route('onboarding.profiles', $token))
        ->assertOk()
        ->assertSee("We couldn't find a business listing", false)
        ->assertSee('Contact FTS');
});

it('requires the authorization box before anything is sent to Google', function () {
    FakeGoogle::fake();
    [$client, $token] = connectedClient($this);
    $this->get(route('onboarding.profiles', $token));

    $this->post(route('onboarding.authorize', $token), [])->assertSessionHasErrors('authorize');

    expect(invitationPosts())->toBe(0)->and($client->accessRequests()->count())->toBe(0);
});

it('invites the FTS Location Group as Manager using the client\'s own token', function () {
    FakeGoogle::fake();
    [$client, $token] = connectedClient($this);
    $this->get(route('onboarding.profiles', $token));

    $this->post(route('onboarding.authorize', $token), ['authorize' => 1])->assertRedirect(route('onboarding.show', $token));

    Http::assertSent(fn (Request $r) => $r->method() === 'POST'
        && $r->url() === 'https://mybusinessaccountmanagement.googleapis.com/v1/locations/111/admins'
        && $r->data() === ['account' => 'accounts/999', 'role' => 'MANAGER']
        && $r->hasHeader('Authorization', 'Bearer access-token-1'));

    $request = $client->accessRequests()->firstOrFail();
    expect($request->status)->toBe(AccessRequestStatus::PendingApproval)
        ->and($request->invitation_resource_name)->toBe('locations/111/admins/77')
        ->and($request->requested_role)->toBe('MANAGER')
        ->and($client->fresh()->access_status)->toBe(ClientStatus::PendingApproval);
});

it('shows the success message right after submitting, then the pending message', function () {
    FakeGoogle::fake();
    [, $token] = connectedClient($this);
    $this->get(route('onboarding.profiles', $token));
    $this->post(route('onboarding.authorize', $token), ['authorize' => 1]);

    $this->get(route('onboarding.show', $token))
        ->assertSee('Your request has been submitted successfully. Firm Tech Services will now complete the required access confirmation.');

    $this->get(route('onboarding.show', $token))
        ->assertSee('Your Google access request is awaiting approval. There is nothing else you need to do right now.');
});

it('does not call the client connected just because Google accepted the POST', function () {
    FakeGoogle::fake([
        'POST /admins' => Http::response(FakeGoogle::admin(pending: false)),
        'GET /admins' => Http::response(['admins' => [FakeGoogle::admin(pending: true)]]),
    ]);
    [$client, $token] = connectedClient($this);
    $this->get(route('onboarding.profiles', $token));
    $this->post(route('onboarding.authorize', $token), ['authorize' => 1]);

    expect($client->fresh()->access_status)->toBe(ClientStatus::PendingApproval)
        ->and($client->accessRequests()->first()->status)->toBe(AccessRequestStatus::PendingApproval);
});

it('does not call the client connected when Google cannot confirm the access', function () {
    FakeGoogle::fake([
        'POST /admins' => Http::response(FakeGoogle::admin(pending: false)),
        'GET /admins' => FakeGoogle::error(403, 'PERMISSION_DENIED'),
    ]);
    [$client, $token] = connectedClient($this);
    $this->get(route('onboarding.profiles', $token));
    $this->post(route('onboarding.authorize', $token), ['authorize' => 1]);

    expect($client->fresh()->access_status)->not->toBe(ClientStatus::Connected);
});

it('never sends a second invitation for the same location', function () {
    FakeGoogle::fake();
    [$client, $token] = connectedClient($this);
    $this->get(route('onboarding.profiles', $token));

    $this->post(route('onboarding.authorize', $token), ['authorize' => 1]);
    $this->post(route('onboarding.authorize', $token), ['authorize' => 1]);

    expect(invitationPosts())->toBe(1)->and($client->accessRequests()->count())->toBe(1);
});

it('shows a friendly message and hides Google\'s technical error when permission is denied', function () {
    FakeGoogle::fake(['POST /admins' => FakeGoogle::error(403, 'PERMISSION_DENIED', 'The caller does not have permission')]);
    [$client, $token] = connectedClient($this);
    $this->get(route('onboarding.profiles', $token));
    $this->post(route('onboarding.authorize', $token), ['authorize' => 1]);

    $this->get(route('onboarding.show', $token))
        ->assertSee("We couldn't complete the connection. Your business profile has not been changed. Please contact our team for assistance.")
        ->assertDontSee('PERMISSION_DENIED')
        ->assertDontSee('The caller does not have permission');

    expect($client->accessRequests()->first())
        ->status->toBe(AccessRequestStatus::Failed)
        ->error_code->toBe('PERMISSION_DENIED')
        ->and($client->fresh()->access_status)->toBe(ClientStatus::Failed);
});

it('records when the Business Profile API is not approved or not enabled', function () {
    FakeGoogle::fake(['POST /admins' => FakeGoogle::error(403, 'PERMISSION_DENIED', 'API has not been used in project 1 before or it is disabled', [['reason' => 'SERVICE_DISABLED']])]);
    [$client, $token] = connectedClient($this);
    $this->get(route('onboarding.profiles', $token));
    $this->post(route('onboarding.authorize', $token), ['authorize' => 1]);

    expect($client->accessRequests()->first()->error_code)->toBe('API_NOT_ENABLED');
});

it('records a Google rate limit and offers a retry', function () {
    $calls = 0;
    FakeGoogle::fake(['POST /admins' => function () use (&$calls) {
        return ++$calls === 1 ? FakeGoogle::error(429, 'RESOURCE_EXHAUSTED', 'Quota exceeded') : Http::response(FakeGoogle::admin(true));
    }]);
    [$client, $token] = connectedClient($this);
    $this->get(route('onboarding.profiles', $token));
    $this->post(route('onboarding.authorize', $token), ['authorize' => 1]);

    expect($client->accessRequests()->first())->error_code->toBe('RATE_LIMITED')->status->toBe(AccessRequestStatus::Failed);

    // Google recovers; staff retry with the stored authorization.
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post(route('admin.clients.retry', $client))->assertSessionHas('status');

    expect($client->fresh()->access_status)->toBe(ClientStatus::PendingApproval)
        ->and($client->accessRequests()->count())->toBe(2);
});

it('treats an already pending invitation as pending instead of failing', function () {
    FakeGoogle::fake(['POST /admins' => FakeGoogle::error(409, 'ALREADY_EXISTS', 'Invitation is already pending')]);
    [$client, $token] = connectedClient($this);
    $this->get(route('onboarding.profiles', $token));
    $this->post(route('onboarding.authorize', $token), ['authorize' => 1]);

    expect($client->fresh()->access_status)->toBe(ClientStatus::PendingApproval);
});

it('handles a revoked Google token by marking the authorization unusable', function () {
    FakeGoogle::fake([
        'POST oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
    ]);
    $client = GbpClient::factory()->withLocation('111')->withStatus(ClientStatus::ProfileSelected)->create();
    $connection = GbpGoogleConnection::create([
        'gbp_client_id' => $client->id,
        'connection_type' => ConnectionType::Client,
        'access_token' => 'old',
        'refresh_token' => 'revoked-refresh-token',
        'expires_at' => now()->subHour(),
        'status' => ConnectionStatus::Active,
    ]);

    $request = app(GoogleInvitationService::class)->requestManagerAccess($client, $connection);

    expect($request->status)->toBe(AccessRequestStatus::Failed)
        ->and($request->error_code)->toBe('TOKEN_REVOKED')
        ->and($connection->fresh()->status)->toBe(ConnectionStatus::Expired);
});

it('refuses to use another client\'s Google authorization', function () {
    FakeGoogle::fake();
    $mine = GbpClient::factory()->withLocation('111')->withStatus(ClientStatus::ProfileSelected)->create();
    $other = GbpClient::factory()->create();
    $connection = GbpGoogleConnection::create([
        'gbp_client_id' => $other->id,
        'connection_type' => ConnectionType::Client,
        'access_token' => 'x', 'refresh_token' => 'y', 'expires_at' => now()->addHour(),
        'status' => ConnectionStatus::Active,
    ]);

    expect(fn () => app(GoogleInvitationService::class)->requestManagerAccess($mine, $connection))
        ->toThrow(InvalidArgumentException::class);
});

it('never uses the agency token for a client invitation', function () {
    FakeGoogle::fake();
    $client = GbpClient::factory()->withLocation('111')->withStatus(ClientStatus::ProfileSelected)->create();
    $agency = GbpGoogleConnection::create([
        'connection_type' => ConnectionType::Agency, 'access_token' => 'agency', 'refresh_token' => 'agency-r',
        'expires_at' => now()->addHour(), 'status' => ConnectionStatus::Active,
    ]);

    expect(fn () => app(GoogleInvitationService::class)->requestManagerAccess($client, $agency))
        ->toThrow(InvalidArgumentException::class);
});
