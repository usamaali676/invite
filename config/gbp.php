<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Agency identity (shown to clients)
    |--------------------------------------------------------------------------
    */
    'agency_name' => env('GBP_AGENCY_NAME', 'Firm Tech Services'),
    'agency_short_name' => env('GBP_AGENCY_SHORT_NAME', 'FTS'),
    'support_email' => env('GBP_SUPPORT_EMAIL', 'support@firmtechservices.com'),
    'support_phone' => env('GBP_SUPPORT_PHONE'),

    /*
    |--------------------------------------------------------------------------
    | Onboarding links
    |--------------------------------------------------------------------------
    */
    'link_ttl_days' => (int) env('GBP_LINK_TTL_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Google OAuth 2.0 (server-side authorization-code flow)
    |--------------------------------------------------------------------------
    | The redirect URI must be registered exactly in Google Cloud Console.
    | Leave GOOGLE_REDIRECT_URI empty to use APP_URL + /google/callback.
    */
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
        'scope' => 'https://www.googleapis.com/auth/business.manage',
        'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_url' => 'https://oauth2.googleapis.com/token',
        'revoke_url' => 'https://oauth2.googleapis.com/revoke',
        'state_ttl_minutes' => 10,
        'http_timeout' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Business Profile API endpoints
    |--------------------------------------------------------------------------
    */
    'api' => [
        'account_management' => 'https://mybusinessaccountmanagement.googleapis.com/v1',
        'business_information' => 'https://mybusinessbusinessinformation.googleapis.com/v1',
    ],

    /*
    | Where FTS staff submit an agency access request by hand
    | (Business Profile Manager > Manage invites > Request access).
    */
    'business_profile_manager_url' => 'https://www.google.com/business/',

    /*
    | Opened for the client after they finish, so they can see the request
    | under People and access in their own Business Profile.
    */
    'client_profile_url' => 'https://business.google.com/locations',

    'verification' => [
        'schedule_every_minutes' => 30,
        'first_check_delay_seconds' => 90,
        'stop_checking_after_days' => 30,
    ],
];
