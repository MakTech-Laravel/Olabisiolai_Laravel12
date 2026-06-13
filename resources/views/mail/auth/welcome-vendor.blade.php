@component('mail::message')
# Welcome to {{ config('app.name') }}

Hi {{ $user->first_name ?: $user->name }},

Your vendor account is verified. The next step is to complete your business profile so customers can find and contact you on Gidira.

@component('mail::button', ['url' => rtrim((string) config('app.frontend_url', config('app.url')), '/').'/vendor/plan-form'])
Complete vendor onboarding
@endcomponent

You can return anytime to finish listing details, upload photos, and choose your plan.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
