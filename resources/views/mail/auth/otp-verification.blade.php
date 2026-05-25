@component('mail::message')
# Verify Your Account

Hi {{ $userName }},

Thank you for registering. Use the OTP below to verify your account.

@component('mail::panel')
# {{ $otpCode }}
@endcomponent

This code expires in **10 minutes**. Do not share it with anyone.

If you did not create an account, no further action is required.

Thanks,
{{ config('app.name') }}
@endcomponent
