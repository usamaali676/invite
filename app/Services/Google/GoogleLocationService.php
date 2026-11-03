<?php

namespace App\Services\Google;

use App\Enums\GoogleErrorType;
use App\Exceptions\GoogleApiException;

class GoogleLocationService
{
    private const MAX_PAGES = 20;

    private const READ_MASK = 'name,title,storefrontAddress';

    public function __construct(private GoogleApiClient $api) {}

    /**
     * Locations under every account the user can see, de-duplicated by location.
     * An account that cannot be read is skipped so one bad account does not hide the rest.
     *
     * @param  array<int, array{name: string, type: string}>  $accounts
     * @return array<int, array{account: string, name: string, id: string, title: string, address: string}>
     *
     * @throws GoogleApiException when no account at all could be read
     */
    public function listForAccounts(string $accessToken, array $accounts): array
    {
        $locations = [];
        $firstError = null;
        $readable = 0;

        foreach ($accounts as $account) {
            if ($account['type'] === 'ORGANIZATION') {
                continue;
            }

            try {
                foreach ($this->listForAccount($accessToken, $account['name']) as $location) {
                    $locations[$location['name']] ??= $location;
                }
                $readable++;
            } catch (GoogleApiException $e) {
                if ($e->type->needsNewAuthorization()) {
                    throw $e;
                }

                $firstError ??= $e;
            }
        }

        if ($readable === 0 && $firstError !== null) {
            throw $firstError;
        }

        return array_values($locations);
    }

    /**
     * @return array<int, array{account: string, name: string, id: string, title: string, address: string}>
     *
     * @throws GoogleApiException
     */
    public function listForAccount(string $accessToken, string $accountName): array
    {
        $locations = [];
        $pageToken = null;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $response = $this->api->get(
                $this->api->businessInformationUrl($accountName.'/locations'),
                $accessToken,
                array_filter(['readMask' => self::READ_MASK, 'pageSize' => 100, 'pageToken' => $pageToken]),
            );

            foreach ($response['locations'] ?? [] as $location) {
                $name = (string) ($location['name'] ?? '');

                if ($name === '') {
                    continue;
                }

                $locations[] = [
                    'account' => $accountName,
                    'name' => $name,
                    'id' => str_replace('locations/', '', $name),
                    'title' => (string) ($location['title'] ?? 'Untitled business'),
                    'address' => $this->formatAddress((array) ($location['storefrontAddress'] ?? [])),
                ];
            }

            $pageToken = $response['nextPageToken'] ?? null;

            if (! $pageToken) {
                break;
            }
        }

        return $locations;
    }

    /**
     * Whether a location is listed under the given account (used from the agency side
     * to prove the Location Group really received access).
     *
     * @throws GoogleApiException
     */
    public function accountHasLocation(string $accessToken, string $accountName, string $locationName): bool
    {
        try {
            foreach ($this->listForAccount($accessToken, $accountName) as $location) {
                if ($location['name'] === $locationName) {
                    return true;
                }
            }
        } catch (GoogleApiException $e) {
            if ($e->type === GoogleErrorType::NotFound) {
                return false;
            }

            throw $e;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $address
     */
    public function formatAddress(array $address): string
    {
        $lines = array_filter((array) ($address['addressLines'] ?? []));
        $cityLine = trim(implode(' ', array_filter([
            $address['locality'] ?? null,
            $address['administrativeArea'] ?? null,
            $address['postalCode'] ?? null,
        ])));

        return implode(', ', array_filter([...$lines, $cityLine, $address['regionCode'] ?? null]));
    }
}
