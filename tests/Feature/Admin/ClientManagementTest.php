<?php

use App\Enums\ClientStatus;
use App\Mail\OnboardingLinkMail;
use App\Models\GbpClient;
use App\Models\GbpOnboardingLink;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('keeps the dashboard private', function () {
    $this->get('/admin')->assertRedirect('/login');

    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
});

it('lets staff sign in, and refuses accounts that are not admins', function () {
    $user = User::factory()->admin()->create(['password' => 'a-long-password']);
    $this->post('/login', ['email' => $user->email, 'password' => 'a-long-password'])->assertRedirect('/admin');

    $this->post('/logout');
    $plain = User::factory()->create(['password' => 'a-long-password']);
    $this->post('/login', ['email' => $plain->email, 'password' => 'a-long-password'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('creates a client with only a business name and generates a link at once', function () {
    $response = $this->actingAs($this->admin)->post('/admin/clients', [
        'business_name' => 'Maple Street Dental',
        'access_method' => 'API',
    ]);

    $client = GbpClient::firstOrFail();
    $response->assertRedirect(route('admin.clients.show', $client));
    expect($client->access_status)->toBe(ClientStatus::LinkSent)
        ->and($client->created_by)->toBe($this->admin->id)
        ->and($client->onboardingLinks)->toHaveCount(1);
});

it('validates the add client form', function () {
    $this->actingAs($this->admin)->post('/admin/clients', ['business_name' => '', 'client_email' => 'nope', 'google_maps_url' => 'javascript:alert(1)'])
        ->assertSessionHasErrors(['business_name', 'client_email', 'google_maps_url']);
});

it('never stores the plain token and shows a working link to staff', function () {
    $client = GbpClient::factory()->create();
    $link = GbpOnboardingLink::issue($client, $this->admin);
    $token = $link->plainToken();

    expect($link->token_hash)->toBe(hash('sha256', $token))
        ->and($link->token_hash)->not->toBe($token)
        ->and($link->token_encrypted)->not->toContain($token);

    $this->actingAs($this->admin)->get(route('admin.clients.show', $client))->assertOk()->assertSee($token);
});

it('replaces the old link when a new one is generated', function () {
    $client = GbpClient::factory()->create();
    $old = GbpOnboardingLink::issue($client, $this->admin);
    $oldToken = $old->plainToken();

    $this->actingAs($this->admin)->post(route('admin.clients.link.store', $client))->assertRedirect();

    expect($old->fresh()->isRevoked())->toBeTrue();
    $this->get(route('onboarding.show', $oldToken))->assertNotFound();
});

it('revokes a link', function () {
    $client = GbpClient::factory()->withStatus(ClientStatus::LinkSent)->create();
    $link = GbpOnboardingLink::issue($client, $this->admin);

    $this->actingAs($this->admin)->delete(route('admin.clients.link.destroy', $client))->assertRedirect();

    expect($link->fresh()->isRevoked())->toBeTrue()
        ->and($client->fresh()->access_status)->toBe(ClientStatus::Revoked);
});

it('edits client details', function () {
    $client = GbpClient::factory()->create();

    $this->actingAs($this->admin)->put(route('admin.clients.update', $client), ['business_name' => 'New Name', 'client_email' => 'a@b.co'])
        ->assertRedirect(route('admin.clients.show', $client));

    expect($client->fresh()->business_name)->toBe('New Name');
});

it('queues the onboarding email', function () {
    Mail::fake();
    $client = GbpClient::factory()->create(['client_email' => 'dana@example.com']);
    GbpOnboardingLink::issue($client, $this->admin);

    $this->actingAs($this->admin)->post(route('admin.clients.link.email', $client))->assertSessionHas('status');

    Mail::assertQueued(OnboardingLinkMail::class, fn ($mail) => $mail->hasTo('dana@example.com'));
});

it('lists dashboard statistics', function () {
    GbpClient::factory()->withStatus(ClientStatus::Connected)->create();
    GbpClient::factory()->withStatus(ClientStatus::PendingApproval)->create();
    GbpClient::factory()->withStatus(ClientStatus::Failed)->create();

    $this->actingAs($this->admin)->get('/admin')->assertOk()
        ->assertViewHas('stats', ['total' => 3, 'connected' => 1, 'pending' => 1, 'failed' => 1]);
});

it('expires links through the scheduled command', function () {
    Queue::fake();
    $client = GbpClient::factory()->withStatus(ClientStatus::LinkSent)->create();
    $link = GbpOnboardingLink::issue($client);
    $link->update(['expires_at' => now()->subMinute()]);

    $this->artisan('gbp:expire-links')->assertSuccessful();

    expect($client->fresh()->access_status)->toBe(ClientStatus::Expired);
});
