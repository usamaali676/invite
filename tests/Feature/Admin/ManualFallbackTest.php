<?php

use App\Enums\AccessMethod;
use App\Enums\AccessRequestStatus;
use App\Enums\ClientStatus;
use App\Enums\ConnectionStatus;
use App\Enums\ConnectionType;
use App\Mail\OnboardingLinkMail;
use App\Models\GbpAgencySetting;
use App\Models\GbpClient;
use App\Models\GbpGoogleConnection;
use App\Models\GbpOnboardingLink;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FakeGoogle;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    GbpAgencySetting::current()->update(['location_group_account_id' => '999', 'organization_name' => 'FTS']);
    Http::preventStrayRequests();
});

it('shows the agency request panel with the business and the FTS Location Group', function () {
    $client = GbpClient::factory()->manual()->create(['business_name' => 'Maple Dental']);

    $this->actingAs($this->admin)->get(route('admin.clients.show', $client))
        ->assertOk()
        ->assertSee('Agency access request')
        ->assertSee('Maple Dental')
        ->assertSee('Location Group ID 999')
        ->assertSee('Open Business Profile Manager');
});

it('lets staff switch a client to the manual method before a request exists', function () {
    $client = GbpClient::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.clients.method', $client), ['access_method' => 'AGENCY_MANUAL'])->assertSessionHas('status');

    expect($client->fresh()->access_method)->toBe(AccessMethod::AgencyManual);
});

it('records the submitted agency request as awaiting approval and emails the instructions', function () {
    Mail::fake();
    $client = GbpClient::factory()->manual()->withStatus(ClientStatus::LinkSent)->create(['client_email' => 'dana@example.com']);
    GbpOnboardingLink::issue($client);

    $this->actingAs($this->admin)->post(route('admin.clients.manual.submitted', $client), ['email_client' => 1])->assertSessionHas('status');

    $request = $client->accessRequests()->firstOrFail();
    expect($client->fresh()->access_status)->toBe(ClientStatus::PendingApproval)
        ->and($request->access_method)->toBe(AccessMethod::AgencyManual)
        ->and($request->status)->toBe(AccessRequestStatus::PendingApproval)
        ->and($request->requested_account)->toBe('accounts/999');
    Mail::assertQueued(OnboardingLinkMail::class, fn ($mail) => $mail->manual && $mail->hasTo('dana@example.com'));
});

it('does not record the same agency request twice', function () {
    $client = GbpClient::factory()->manual()->withStatus(ClientStatus::LinkSent)->create();

    $this->actingAs($this->admin)->post(route('admin.clients.manual.submitted', $client));
    $this->actingAs($this->admin)->post(route('admin.clients.manual.submitted', $client))->assertSessionHas('error');

    expect($client->accessRequests()->count())->toBe(1);
});

it('needs the Location Group configured before an agency request can be recorded', function () {
    GbpAgencySetting::current()->update(['location_group_account_id' => null]);
    $client = GbpClient::factory()->manual()->withStatus(ClientStatus::LinkSent)->create();

    $this->actingAs($this->admin)->post(route('admin.clients.manual.submitted', $client))->assertSessionHas('error');

    expect($client->accessRequests()->count())->toBe(0);
});

it('will not mark a manual request connected without Google proof or staff confirmation', function () {
    $client = GbpClient::factory()->manual()->withStatus(ClientStatus::LinkSent)->create();
    $this->actingAs($this->admin)->post(route('admin.clients.manual.submitted', $client));

    $this->actingAs($this->admin)->post(route('admin.clients.manual.connected', $client))->assertSessionHas('error');

    expect($client->fresh()->access_status)->toBe(ClientStatus::PendingApproval);
});

it('marks a manual request connected once staff confirm they saw the access', function () {
    $client = GbpClient::factory()->manual()->withStatus(ClientStatus::LinkSent)->create();
    $this->actingAs($this->admin)->post(route('admin.clients.manual.submitted', $client));

    $this->actingAs($this->admin)->post(route('admin.clients.manual.connected', $client), ['confirmed' => 1])->assertSessionHas('status');

    expect($client->fresh()->access_status)->toBe(ClientStatus::Connected);
    expect($client->logs()->where('action', 'request.manually_confirmed')->exists())->toBeTrue();
});

it('marks a manual request connected on Google\'s word when the FTS account is connected', function () {
    FakeGoogle::fake([
        'GET mybusinessbusinessinformation' => Http::response(['locations' => [FakeGoogle::location('333', 'Maple Dental')]]),
    ]);
    GbpGoogleConnection::create([
        'connection_type' => ConnectionType::Agency, 'access_token' => 'agency-token', 'refresh_token' => 'agency-refresh',
        'expires_at' => now()->addHour(), 'status' => ConnectionStatus::Active,
    ]);
    $client = GbpClient::factory()->manual()->withStatus(ClientStatus::LinkSent)->create(['business_name' => 'Maple Dental']);
    $this->actingAs($this->admin)->post(route('admin.clients.manual.submitted', $client));

    $this->actingAs($this->admin)->post(route('admin.clients.manual.connected', $client))->assertSessionHas('status');

    expect($client->fresh())->access_status->toBe(ClientStatus::Connected)->location_resource_name->toBe('locations/333');
});

it('keeps the settings page usable while the Google API is not approved', function () {
    $this->actingAs($this->admin)->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertSee('Business Profile API access approved')
        ->assertSee('FTS Google account');
});

it('saves agency settings and fixes the role at Manager', function () {
    $this->actingAs($this->admin)->put(route('admin.settings.update'), [
        'organization_name' => 'Firm Tech Services',
        'organization_id' => 'organizations/123',
        'location_group_account_id' => 'accounts/456',
        'manager_email' => 'ops@fts.test',
        'api_project_number' => '123456789012',
        'api_approval_status' => 'APPROVED',
        'default_role' => 'OWNER',
    ])->assertRedirect(route('admin.settings.edit'));

    expect(GbpAgencySetting::current())
        ->organization_id->toBe('123')
        ->location_group_account_id->toBe('456')
        ->default_role->toBe('MANAGER')
        ->and(GbpAgencySetting::current()->locationGroupResourceName())->toBe('accounts/456');
});

it('rejects a Location Group ID that is not an ID', function () {
    $this->actingAs($this->admin)->put(route('admin.settings.update'), [
        'location_group_account_id' => 'drop table; --',
        'api_approval_status' => 'APPROVED',
    ])->assertSessionHasErrors('location_group_account_id');
});

it('tests the Google connection from Settings and reports a clear problem', function () {
    FakeGoogle::fake(['GET mybusinessaccountmanagement.googleapis.com/v1/accounts' => FakeGoogle::error(429, 'RESOURCE_EXHAUSTED')]);
    GbpGoogleConnection::create([
        'connection_type' => ConnectionType::Agency, 'access_token' => 'agency-token', 'refresh_token' => 'agency-refresh',
        'expires_at' => now()->addHour(), 'status' => ConnectionStatus::Active,
    ]);

    $this->actingAs($this->admin)->post(route('admin.settings.test'))
        ->assertSessionHas('error', fn ($message) => str_contains($message, 'rate limit'));
});

it('does not send clients to Google while the API is not marked as approved', function () {
    FakeGoogle::configure();
    GbpAgencySetting::current()->update(['api_approval_status' => 'REQUESTED']);
    $client = GbpClient::factory()->create(['access_status' => ClientStatus::LinkSent]);
    $token = GbpOnboardingLink::issue($client)->plainToken();

    $this->get(route('onboarding.show', $token))->assertDontSee('Continue with Google');
    $this->post(route('onboarding.start', $token))->assertRedirect(route('onboarding.show', $token));
});

it('does not offer Google sign-in to clients until credentials and the Location Group exist', function () {
    GbpAgencySetting::current()->update(['location_group_account_id' => null]);
    $client = GbpClient::factory()->create(['access_status' => ClientStatus::LinkSent]);
    $token = GbpOnboardingLink::issue($client)->plainToken();

    $this->get(route('onboarding.show', $token))
        ->assertOk()
        ->assertDontSee('Continue with Google')
        ->assertSee('The connection isn\'t open just yet', false);

    $this->post(route('onboarding.start', $token))->assertRedirect(route('onboarding.show', $token));
});
