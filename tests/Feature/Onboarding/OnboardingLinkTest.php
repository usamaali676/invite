<?php

use App\Enums\ClientStatus;
use App\Models\GbpClient;
use App\Models\GbpOnboardingLink;
use Tests\Support\FakeGoogle;

it('opens the page without any FTS login and marks the link opened', function () {
    FakeGoogle::configure();
    $client = GbpClient::factory()->withStatus(ClientStatus::LinkSent)->create(['business_name' => 'Maple Street Dental']);
    $token = GbpOnboardingLink::issue($client)->plainToken();

    $this->get(route('onboarding.show', $token))
        ->assertOk()
        ->assertSee('Connect Your Google Business Profile')
        ->assertSee('Maple Street Dental')
        ->assertSee('Continue with Google')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    expect($client->fresh()->access_status)->toBe(ClientStatus::LinkOpened);
});

it('rejects an expired link and marks the client expired', function () {
    $client = GbpClient::factory()->withStatus(ClientStatus::LinkSent)->create();
    $link = GbpOnboardingLink::issue($client);
    $link->update(['expires_at' => now()->subSecond()]);

    $this->get(route('onboarding.show', $link->plainToken()))->assertNotFound()->assertSee("This link isn't available", false);

    expect($client->fresh()->access_status)->toBe(ClientStatus::Expired);
});

it('rejects a revoked link', function () {
    $client = GbpClient::factory()->create();
    $link = GbpOnboardingLink::issue($client);
    $link->update(['revoked_at' => now()]);

    $this->get(route('onboarding.show', $link->plainToken()))->assertNotFound();
});

it('answers unknown, expired and revoked tokens with the same page so tokens cannot be enumerated', function () {
    $client = GbpClient::factory()->create();
    $expired = GbpOnboardingLink::issue($client);
    $expired->update(['expires_at' => now()->subDay()]);

    $unknown = $this->get(route('onboarding.show', str_repeat('a', 43)));
    $known = $this->get(route('onboarding.show', $expired->plainToken()));

    expect($unknown->status())->toBe($known->status())
        ->and($unknown->getContent())->toBe($known->getContent());
});

it('keeps one client from reaching another client\'s onboarding record', function () {
    $alice = GbpClient::factory()->withLocation('111')->create(['business_name' => 'Alice Bakery']);
    $bob = GbpClient::factory()->withLocation('222')->create(['business_name' => 'Bob Plumbing']);
    $aliceToken = GbpOnboardingLink::issue($alice)->plainToken();
    GbpOnboardingLink::issue($bob);

    $this->get(route('onboarding.show', $aliceToken))->assertSee('Alice Bakery')->assertDontSee('Bob Plumbing');

    $this->get(route('onboarding.show', $alice->id))->assertNotFound();
    $this->get(route('onboarding.profiles', $alice->id))->assertNotFound();
    $this->get(route('onboarding.confirm', 'not-a-real-token-at-all-1234'))->assertNotFound();
});

it('does not let a link holder skip the Google step', function () {
    $client = GbpClient::factory()->withLocation('111')->withStatus(ClientStatus::LinkOpened)->create();
    $token = GbpOnboardingLink::issue($client)->plainToken();

    $this->get(route('onboarding.confirm', $token))->assertRedirect(route('onboarding.show', $token));
    $this->post(route('onboarding.authorize', $token), ['authorize' => 1])->assertRedirect(route('onboarding.show', $token));

    expect($client->accessRequests()->count())->toBe(0);
});

it('shows approval steps for manual agency requests instead of Google sign-in', function () {
    $client = GbpClient::factory()->manual()->withStatus(ClientStatus::PendingApproval)->create();
    $token = GbpOnboardingLink::issue($client)->plainToken();

    $this->get(route('onboarding.show', $token))
        ->assertOk()
        ->assertSee('Please approve our request')
        ->assertDontSee('Continue with Google');
});
