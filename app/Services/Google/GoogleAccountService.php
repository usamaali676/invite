<?php

namespace App\Services\Google;

use App\Exceptions\GoogleApiException;

class GoogleAccountService
{
    private const MAX_PAGES = 10;

    public function __construct(private GoogleApiClient $api) {}

    /**
     * Accounts the authorized user can reach (their personal account first,
     * then location groups, user groups and organizations).
     *
     * @return array<int, array{name: string, id: string, accountName: string, type: string, role: ?string}>
     *
     * @throws GoogleApiException
     */
    public function listAccounts(string $accessToken): array
    {
        $accounts = [];
        $pageToken = null;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $response = $this->api->get(
                $this->api->accountManagementUrl('accounts'),
                $accessToken,
                array_filter(['pageSize' => 20, 'pageToken' => $pageToken]),
            );

            foreach ($response['accounts'] ?? [] as $account) {
                $name = (string) ($account['name'] ?? '');

                if ($name === '') {
                    continue;
                }

                $accounts[] = [
                    'name' => $name,
                    'id' => str_replace('accounts/', '', $name),
                    'accountName' => (string) ($account['accountName'] ?? $name),
                    'type' => (string) ($account['type'] ?? 'ACCOUNT_TYPE_UNSPECIFIED'),
                    'role' => $account['role'] ?? null,
                ];
            }

            $pageToken = $response['nextPageToken'] ?? null;

            if (! $pageToken) {
                break;
            }
        }

        return $accounts;
    }

    /**
     * @param  array<int, array{type: string}>  $accounts
     * @return array{name: string, id: string, accountName: string, type: string, role: ?string}|null
     */
    public function personalAccount(array $accounts): ?array
    {
        foreach ($accounts as $account) {
            if ($account['type'] === 'PERSONAL') {
                return $account;
            }
        }

        return $accounts[0] ?? null;
    }
}
