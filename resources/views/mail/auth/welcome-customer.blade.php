@component('mail::message')
# Welcome to {{ config('app.name') }}

Hi {{ $user->first_name ?: $user->name }},

Your Gidira account has been successfully created. You can discover trusted businesses, save favourites, send messages, and leave reviews.

@component('mail::button', ['url' => rtrim((string) config('app.frontend_url', config('app.url')), '/').'/user/dashboard'])
Go to your dashboard
@endcomponent

If you did not create this account, please contact our support team.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
