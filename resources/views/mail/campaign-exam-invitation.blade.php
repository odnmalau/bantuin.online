<x-mail::message>
# {{ __('Assessment invitation') }}

{{ __('You have been invited to complete the :campaign assessment for the :role role.', [
    'campaign' => $campaignTitle,
    'role' => $roleTitle,
]) }}

@if ($expiresAt !== null)
{{ __('This invitation expires on :date.', ['date' => $expiresAt->timezone(config('app.timezone'))->format('M j, Y g:i A T')]) }}
@endif

<x-mail::button :url="$inviteUrl">
{{ __('Start assessment') }}
</x-mail::button>

{{ __('If you did not expect this invitation, you can ignore this email.') }}
</x-mail::message>
