@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ rtrim((string) config('platform.public_url'), '/') }}/images/vetor-smart-logo-reduced.png" width="44" height="32" alt="" style="height: 32px; width: auto; vertical-align: middle; border: 0;">
<span style="vertical-align: middle; margin-left: 8px;">{!! $slot !!}</span>
</a>
</td>
</tr>
