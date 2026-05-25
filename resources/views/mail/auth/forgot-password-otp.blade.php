@component('mail::message')
# Reset Your Password

Hi {{ $userName }},

We received a request to reset the password for your account. Use the OTP below together with your reset token to complete the process.

@component('mail::panel')
# {{ $otpCode }}
@endcomponent

This code expires in **10 minutes**. Do not share it with anyone.

If you did not request a password reset, no further action is required.

Thanks,
{{ config('app.name') }}
@endcomponent
