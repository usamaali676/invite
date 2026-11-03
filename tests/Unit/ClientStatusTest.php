<?php

use App\Enums\ClientStatus;
use App\Exceptions\InvalidStatusTransition;
use App\Models\GbpClient;
use Tests\TestCase;

uses(TestCase::class);

it('allows the normal path from new to connected', function () {
    $path = [
        ClientStatus::New, ClientStatus::LinkSent, ClientStatus::LinkOpened, ClientStatus::OauthConnected,
        ClientStatus::ProfileSelected, ClientStatus::RequestSubmitted, ClientStatus::PendingApproval, ClientStatus::Connected,
    ];

    foreach (array_slice($path, 0, -1) as $index => $from) {
        expect($from->canTransitionTo($path[$index + 1]))->toBeTrue("{$from->value} should lead to {$path[$index + 1]->value}");
    }
});

it('never jumps straight to connected before a request exists', function () {
    foreach ([ClientStatus::New, ClientStatus::LinkSent, ClientStatus::LinkOpened, ClientStatus::OauthConnected, ClientStatus::ProfileSelected, ClientStatus::Expired, ClientStatus::Revoked] as $from) {
        expect($from->canTransitionTo(ClientStatus::Connected))->toBeFalse($from->value);
    }
});

it('throws on a forbidden transition and leaves the status untouched', function () {
    $client = new GbpClient(['business_name' => 'X', 'access_status' => ClientStatus::New]);

    expect(fn () => $client->transitionTo(ClientStatus::Connected))->toThrow(InvalidStatusTransition::class);
    expect($client->access_status)->toBe(ClientStatus::New);
});
