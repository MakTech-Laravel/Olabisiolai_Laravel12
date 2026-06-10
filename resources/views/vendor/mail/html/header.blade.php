@props(['url'])
@php
    $logoUrl = config('mail.logo_url') ?: asset('images/branding/gidira-logo.svg');
    $brandName = config('app.name', 'Gidira');
@endphp
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ $logoUrl }}" class="logo" alt="{{ $brandName }}">
</a>
</td>
</tr>
