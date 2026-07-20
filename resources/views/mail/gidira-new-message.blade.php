@component('mail::message')
# New message on Gidira

Hi {{ $recipient->first_name ?: $recipient->name }},

You have a new message on Gidira from **{{ $senderName }}**.

Open Gidira to read and reply — this email does not include the message content.

@component('mail::button', ['url' => $actionUrl])
Open Gidira
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
