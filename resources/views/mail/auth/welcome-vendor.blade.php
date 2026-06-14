@component('mail::message')
# Welcome to {{ config('app.name') }}

Hi {{ $user->first_name ?: $user->name }},

Your Gidira Vendor Account has been successfully created. The next step is to complete your business profile so customers can find and contact you on Gidira.

@component('mail::button', ['url' => rtrim((string) config('app.frontend_url', config('app.url')), '/').'/user/profile'])
Set up your business profile
@endcomponent

You can return anytime to complete your business listings, upload photos and start your verification process.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
