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
    | Frontend callback (optional web redirect flow)
    |--------------------------------------------------------------------------
    */

    'frontend_callback_url' => env(
        'SOCIAL_AUTH_FRONTEND_CALLBACK_URL',
        rtrim((string) env('FRONTEND_URL', ''), '/').'/auth/social/callback',
    ),

];
