<?php

use App\Enums\ClientStatus;
use App\Models\GbpClient;
use App\Models\GbpOnboardingLink;
use App\Models\User;
use Tests\Support\FakeGoogle;

function welcomePage($test, array $attributes = [])
{
    $client = GbpClient::factory()->withStatus(ClientStatus::LinkSent)->create($attributes + ['business_name' => 'Maple Street Dental', 'client_name' => 'Dana Whitfield']);

    return [$client, $test->get(route('onboarding.show', GbpOnboardingLink::issue($client)->plainToken()))];
}

beforeEach(function () {
    FakeGoogle::configure();
    config([
        'gbp.support_email' => 'help@fts.test',
        'gbp.support_phone' => '+92 300 0000000',
        'gbp.company_address' => '1 Example Road, Lahore',
        'gbp.company_website' => 'https://fts.test',
    ]);
});

it('publishes the privacy policy and terms without any login', function () {
    $this->get(route('legal.privacy'))
        ->assertOk()
        ->assertSee('Privacy policy')
        ->assertSee('Google API Services User Data Policy')
        ->assertSee('Limited Use requirements')
        ->assertSee('https://www.googleapis.com/auth/business.manage')
        ->assertSee('help@fts.test');

    $this->get(route('legal.terms'))
        ->assertOk()
        ->assertSee('Terms of service')
        ->assertSee('You remain the owner of your listing');
});

it('greets the client by first name and keeps the required wording', function () {
    [, $response] = welcomePage($this);

    $response->assertOk()
        ->assertSee('Hi Dana,')
        ->assertSee('Connect Your Google Business Profile')
        ->assertSee('We are ready to start managing your Google Business Profile. To proceed, please connect your Google account and authorize Firm Tech Services to request Manager access to your business listing.')
        ->assertSee('Your Google password is never shared with FTS.')
        ->assertSee('Your business ownership remains yours.')
        ->assertSee('FTS is requesting Manager access to provide SEO services.')
        ->assertSee('Continue with Google');
});

it('shows who is behind the page and how to reach a person', function () {
    [, $response] = welcomePage($this);

    $response->assertSee('Firm Tech Services')
        ->assertSee('1 Example Road, Lahore')
        ->assertSee('help@fts.test')
        ->assertSee('+92 300 0000000')
        ->assertSee('fts.test')
        ->assertSee(route('legal.privacy'), false)
        ->assertSee(route('legal.terms'), false);
});

it('explains what Manager access allows and does not allow', function () {
    [, $response] = welcomePage($this);

    $response->assertSee('What Manager access means')
        ->assertSee('Reply to your reviews')
        ->assertSee('Delete your profile')
        ->assertSee('How do I remove your access later?');
});

it('warns about the unverified Google screen until the app is verified', function () {
    config(['gbp.google_app_verified' => false]);
    [, $response] = welcomePage($this);
    $response->assertSee('A note about a Google message')->assertSee('Ask us to do it directly');

    config(['gbp.google_app_verified' => true]);
    [, $response] = welcomePage($this);
    $response->assertDontSee('A note about a Google message');
});

it('gives staff a ready-made personal message to send with the link', function () {
    $admin = User::factory()->admin()->create(['name' => 'Sam Rivera']);
    $client = GbpClient::factory()->withStatus(ClientStatus::LinkSent)->create(['business_name' => 'Maple Street Dental', 'client_name' => 'Dana Whitfield']);
    $token = GbpOnboardingLink::issue($client)->plainToken();

    $this->actingAs($admin)->get(route('admin.clients.show', $client))
        ->assertOk()
        ->assertSee('Message to send with the link')
        ->assertSee('Hi Dana,')
        ->assertSee('This is Sam Rivera from Firm Tech Services.')
        ->assertSee(route('onboarding.show', $token), false)
        ->assertSee('we never see your password');
});

it('keeps the expired link page helpful', function () {
    $client = GbpClient::factory()->create();
    $link = GbpOnboardingLink::issue($client);
    $link->update(['revoked_at' => now()]);

    $this->get(route('onboarding.show', $link->plainToken()))
        ->assertNotFound()
        ->assertSee('Please ask Firm Tech Services to send you a fresh link.')
        ->assertSee('help@fts.test')
        ->assertSee('Call +92 300 0000000');
});
