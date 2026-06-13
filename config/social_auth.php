<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Social login providers
    |--------------------------------------------------------------------------
    |
    | Add new providers here and implement SocialAuthProviderContract.
    | Only enabled providers are exposed through the API.
    |
    */

    'providers' => [
        'google' => [
            'enabled' => env('SOCIAL_AUTH_GOOGLE_ENABLED', true),
            'label' => 'Google',
            'driver' => App\Services\SocialAuth\Providers\GoogleSocialAuthProvider::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Frontend callback (optional — web SPA redirect flow only)
    |--------------------------------------------------------------------------
    |
    | Leave unset for API/mobile: GET /auth/social/{provider}/callback returns JSON
    | with the authorization code. Set only when a browser SPA handles the callback.
    |
    */

    'frontend_callback_url' => env('SOCIAL_AUTH_FRONTEND_CALLBACK_URL'),

];
