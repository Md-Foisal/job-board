{{-- Whether our team has verified the company, as its own people see it:
     verified, asked for documents, or not yet looked at. The company page
     shows only the first, as a tick beside the name. --}}
@props(['company'])

@if ($company->verified_at)
    <flux:badge color="green" size="sm" {{ $attributes }}>{{ __('Verified') }}</flux:badge>
@elseif ($company->outstandingDocumentsRequest())
    <flux:badge color="amber" size="sm" {{ $attributes }}>{{ __('Documents requested') }}</flux:badge>
@else
    <flux:badge color="zinc" size="sm" {{ $attributes }}>{{ __('Pending verification') }}</flux:badge>
@endif
