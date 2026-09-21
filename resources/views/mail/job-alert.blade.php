<x-mail::message>
# {{ trans_choice('{1} :count new job matches ":name"|[2,*] :count new jobs match ":name"', $total, ['name' => $jobAlert->name]) }}

{{ $searchedFor }}

@foreach ($jobPostings as $jobPosting)
**{{ $jobPosting->title }}**<br>
{{ collect([$jobPosting->company->name, $jobPosting->location_city])->filter()->join(' · ') }}<br>
[{{ __('View job') }}]({{ route('jobs.show', $jobPosting) }})

@endforeach
@if ($total > $jobPostings->count())
<x-mail::button :url="$allUrl">
{{ __('See all :count', ['count' => $total]) }}
</x-mail::button>
@endif

<x-mail::subcopy>
{{ __('You get this email because you set up this job alert.') }}
[{{ __('Manage your alerts') }}]({{ $manageUrl }}) · [{{ __('Unsubscribe from this alert') }}]({{ $unsubscribeUrl }})
</x-mail::subcopy>
</x-mail::message>
