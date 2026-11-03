<?php

namespace App\Enums;

enum GoogleErrorType: string
{
    case ApiNotApproved = 'API_NOT_APPROVED';
    case ApiNotEnabled = 'API_NOT_ENABLED';
    case Unauthorized = 'UNAUTHORIZED';
    case PermissionDenied = 'PERMISSION_DENIED';
    case NotFound = 'NOT_FOUND';
    case Conflict = 'CONFLICT';
    case AlreadyPending = 'ALREADY_PENDING';
    case RateLimited = 'RATE_LIMITED';
    case Timeout = 'TIMEOUT';
    case TokenRevoked = 'TOKEN_REVOKED';
    case NotConfigured = 'NOT_CONFIGURED';
    case InvalidRequest = 'INVALID_REQUEST';
    case Unknown = 'UNKNOWN';

    /** Plain-language explanation for FTS staff. */
    public function adminMessage(): string
    {
        return match ($this) {
            self::ApiNotApproved => 'Google has not approved this Google Cloud project for the Business Profile APIs yet (quota is 0). Submit the Basic API Access request and wait for approval.',
            self::ApiNotEnabled => 'A required Business Profile API is not enabled in the Google Cloud project. Enable all Business Profile APIs listed in the README.',
            self::Unauthorized => 'Google rejected the access token. The Google account needs to authorize again.',
            self::PermissionDenied => 'This Google account does not have enough permission on the selected business profile. Only an Owner or Primary Owner can invite a new manager.',
            self::NotFound => 'Google could not find this business profile or resource. It may have been removed or the account has no access to it.',
            self::Conflict => 'Google reports a conflicting or duplicate request for this profile.',
            self::AlreadyPending => 'An invitation for this profile is already pending with Google.',
            self::RateLimited => 'Google rate limit reached. If this happens on the very first call, the project may still have 0 quota because API access is not approved yet. Otherwise wait a few minutes and retry.',
            self::Timeout => 'Google did not respond in time. Try again in a moment.',
            self::TokenRevoked => 'The Google authorization was revoked or has expired. The client needs to connect their Google account again.',
            self::NotConfigured => 'Google credentials or the FTS Location Group are not configured yet. Open Settings to finish setup.',
            self::InvalidRequest => 'Google rejected the request as invalid. Check the Location Group ID in Settings.',
            self::Unknown => 'Google returned an unexpected error.',
        };
    }

    /** Message that is always safe to show to a client. Never contains technical detail. */
    public function clientMessage(): string
    {
        return "We couldn't complete the connection. Your business profile has not been changed. Please contact our team for assistance.";
    }

    /** Retrying with the same authorization could succeed later. */
    public function isRetryable(): bool
    {
        return in_array($this, [self::RateLimited, self::Timeout, self::Unknown, self::ApiNotApproved, self::ApiNotEnabled], true);
    }

    /** The client must authorize Google again before anything else can work. */
    public function needsNewAuthorization(): bool
    {
        return in_array($this, [self::Unauthorized, self::TokenRevoked], true);
    }
}
