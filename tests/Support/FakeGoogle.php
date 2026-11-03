<?php

namespace Tests\Support;

use App\Models\GbpAgencySetting;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Mocked Google responses. Keys of $overrides are "METHOD url-fragment"; the first match wins.
 */
class FakeGoogle
{
    public const LOCATION_GROUP = 'accounts/999';

    /** Credentials plus the FTS Location Group, i.e. everything the automatic flow needs. */
    public static function configure(): void
    {
        config([
            'gbp.google.client_id' => 'test-client-id',
            'gbp.google.client_secret' => 'test-secret',
            'gbp.google.redirect_uri' => null,
        ]);

        GbpAgencySetting::current()->update([
            'location_group_account_id' => '999',
            'organization_name' => 'FTS',
            'api_approval_status' => 'APPROVED',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function fake(array $overrides = []): void
    {
        static::configure();

        Http::fake(function (Request $request) use ($overrides) {
            foreach ($overrides as $key => $response) {
                [$method, $needle] = explode(' ', $key, 2);

                if ($request->method() === $method && str_contains($request->url(), $needle)) {
                    return is_callable($response) ? $response($request) : $response;
                }
            }

            return static::defaults($request);
        });
    }

    public static function tokens(array $extra = []): array
    {
        return $extra + [
            'access_token' => 'access-token-1',
            'refresh_token' => 'refresh-token-1',
            'expires_in' => 3600,
            'scope' => 'https://www.googleapis.com/auth/business.manage',
        ];
    }

    public static function admin(bool $pending = true, string $role = 'MANAGER'): array
    {
        return [
            'name' => 'locations/111/admins/77',
            'account' => self::LOCATION_GROUP,
            'role' => $role,
            'pendingInvitation' => $pending,
        ];
    }

    public static function location(string $id, string $title, string $city = 'Austin'): array
    {
        return [
            'name' => 'locations/'.$id,
            'title' => $title,
            'storefrontAddress' => ['addressLines' => ['1 Main St'], 'locality' => $city, 'administrativeArea' => 'TX', 'postalCode' => '78701'],
        ];
    }

    public static function error(int $status, string $googleStatus, string $message = 'x', array $details = []): mixed
    {
        return Http::response(['error' => ['code' => $status, 'status' => $googleStatus, 'message' => $message, 'details' => $details]], $status);
    }

    private static function defaults(Request $request): mixed
    {
        $url = $request->url();
        $method = $request->method();

        return match (true) {
            str_contains($url, 'oauth2.googleapis.com/token') => Http::response(self::tokens()),
            str_contains($url, 'oauth2.googleapis.com/revoke') => Http::response([], 200),
            $method === 'POST' && str_contains($url, '/admins') => Http::response(self::admin(true)),
            $method === 'GET' && str_contains($url, '/admins') => Http::response(['admins' => [self::admin(true)]]),
            str_contains($url, '/invitations') => Http::response(['invitations' => []]),
            str_contains($url, 'mybusinessbusinessinformation') => Http::response(['locations' => [self::location('111', 'Test Cafe')]]),
            str_contains($url, 'mybusinessaccountmanagement.googleapis.com/v1/accounts') => Http::response(['accounts' => [
                ['name' => 'accounts/100', 'accountName' => 'Dana W', 'type' => 'PERSONAL', 'role' => 'PRIMARY_OWNER'],
            ]]),
            default => Http::response([], 404),
        };
    }
}
