<?php

namespace App\Services\Google;

use App\Exceptions\GoogleApiException;

/**
 * Location administrators: accounts/locations that can manage a Business Profile location.
 * Docs: mybusinessaccountmanagement v1, locations.admins.{list,create}.
 */
class GoogleAdminService
{
    public function __construct(private GoogleApiClient $api) {}

    /**
     * Admins of a location, as the authorized user is allowed to see them.
     * Each entry holds name, admin (email or display name), account, role, pendingInvitation.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws GoogleApiException
     */
    public function listLocationAdmins(string $accessToken, string $locationName): array
    {
        $response = $this->api->get(
            $this->api->accountManagementUrl($locationName.'/admins'),
            $accessToken,
        );

        return array_values($response['admins'] ?? []);
    }

    /**
     * Invite an account (e.g. an agency Location Group) as admin of a location.
     * When `account` is set Google ignores `admin`, so no email is sent in the body.
     *
     * @return array<string, mixed> the created Admin resource
     *
     * @throws GoogleApiException
     */
    public function inviteAccount(string $accessToken, string $locationName, string $accountName, string $role): array
    {
        return $this->api->post(
            $this->api->accountManagementUrl($locationName.'/admins'),
            $accessToken,
            ['account' => $accountName, 'role' => $role],
        );
    }
}
