<?php

namespace App\Services\Google;

use App\Enums\GoogleErrorType;
use App\Exceptions\GoogleApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin server-side wrapper around the Business Profile REST APIs.
 * Translates every failure into a GoogleApiException and never logs tokens.
 */
class GoogleApiClient
{
    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws GoogleApiException
     */
    public function get(string $url, string $accessToken, array $query = []): array
    {
        return $this->send('GET', $url, $accessToken, $query);
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     *
     * @throws GoogleApiException
     */
    public function post(string $url, string $accessToken, array $json = []): array
    {
        return $this->send('POST', $url, $accessToken, [], $json);
    }

    public function accountManagementUrl(string $path): string
    {
        return rtrim(config('gbp.api.account_management'), '/').'/'.ltrim($path, '/');
    }

    public function businessInformationUrl(string $path): string
    {
        return rtrim(config('gbp.api.business_information'), '/').'/'.ltrim($path, '/');
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $json
     * @return array<string, mixed>
     */
    private function send(string $method, string $url, string $accessToken, array $query = [], ?array $json = null): array
    {
        try {
            $request = $this->pending($accessToken);

            // Only idempotent reads are retried; the invitation POST is never repeated automatically.
            if ($method === 'GET') {
                $request = $request->retry(2, 400, fn ($exception) => $exception instanceof ConnectionException, throw: false);
            }

            $response = $method === 'GET'
                ? $request->get($url, $query)
                : $request->withBody(json_encode($json ?: new \stdClass, JSON_THROW_ON_ERROR), 'application/json')->post($url);
        } catch (ConnectionException $e) {
            Log::warning('Google API connection problem', ['method' => $method, 'url' => $this->stripQuery($url)]);

            throw new GoogleApiException(GoogleErrorType::Timeout);
        }

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        throw $this->toException($response, $method, $url);
    }

    private function pending(string $accessToken): PendingRequest
    {
        return Http::withToken($accessToken)
            ->acceptJson()
            ->timeout((int) config('gbp.google.http_timeout'));
    }

    private function toException(Response $response, string $method, string $url): GoogleApiException
    {
        $status = $response->status();
        $error = (array) ($response->json('error') ?? []);
        $googleStatus = is_string($error['status'] ?? null) ? $error['status'] : null;
        $message = is_string($error['message'] ?? null) ? $error['message'] : '';
        $reasons = collect($error['details'] ?? [])
            ->map(fn ($detail) => is_array($detail) ? ($detail['reason'] ?? null) : null)
            ->filter()
            ->values()
            ->all();

        Log::warning('Google API request failed', [
            'method' => $method,
            'url' => $this->stripQuery($url),
            'http_status' => $status,
            'google_status' => $googleStatus,
            'reasons' => $reasons,
        ]);

        $type = $this->classify($status, $googleStatus, $message, $reasons);

        return new GoogleApiException($type, $status, $googleStatus);
    }

    /**
     * @param  array<int, string>  $reasons
     */
    private function classify(int $status, ?string $googleStatus, string $message, array $reasons): GoogleErrorType
    {
        $lower = strtolower($message);

        if (in_array('SERVICE_DISABLED', $reasons, true) || str_contains($lower, 'has not been used in project') || str_contains($lower, 'api is not enabled')) {
            return GoogleErrorType::ApiNotEnabled;
        }

        if (in_array('RATE_LIMIT_EXCEEDED', $reasons, true) || $status === 429 || $googleStatus === 'RESOURCE_EXHAUSTED') {
            return GoogleErrorType::RateLimited;
        }

        if ($googleStatus === 'ALREADY_EXISTS' || $status === 409) {
            return str_contains($lower, 'pending') ? GoogleErrorType::AlreadyPending : GoogleErrorType::Conflict;
        }

        if (str_contains($lower, 'pending invitation') || str_contains($lower, 'already invited')) {
            return GoogleErrorType::AlreadyPending;
        }

        return match (true) {
            $status === 401 || $googleStatus === 'UNAUTHENTICATED' => GoogleErrorType::Unauthorized,
            $status === 403 || $googleStatus === 'PERMISSION_DENIED' => GoogleErrorType::PermissionDenied,
            $status === 404 => GoogleErrorType::NotFound,
            $status === 400 => GoogleErrorType::InvalidRequest,
            default => GoogleErrorType::Unknown,
        };
    }

    private function stripQuery(string $url): string
    {
        return strtok($url, '?') ?: $url;
    }
}
