<?php

use App\Actions\InviteTeamMember;
use App\Enums\InvitationStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\Membership;
use App\Support\SubmissionLimits;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::employer')] #[Title('Team')] class extends Component {
    public Company $company;

    public bool $showInviteModal = false;

    public string $inviteEmail = '';

    public string $inviteRole = 'member';

    public function mount(Company $company): void
    {
        $this->authorize('viewAny', [Membership::class, $company]);

        $this->company = $company;
    }

    #[Computed]
    public function members()
    {
        return $this->company->memberships()
            ->with('user')
            ->orderByRaw("CASE role WHEN 'owner' THEN 1 WHEN 'manager' THEN 2 ELSE 3 END")
            ->get();
    }

    #[Computed]
    public function pendingInvitations()
    {
        return $this->company->invitations()
            ->where('status', InvitationStatus::Pending)
            ->where('expires_at', '>', now())
            ->latest()
            ->latest('id')
            ->get();
    }

    public function invite(InviteTeamMember $inviteTeamMember): void
    {
        $this->authorize('create', [Invitation::class, $this->company]);

        $limitKey = SubmissionLimits::invitationKey($this->company);

        if (RateLimiter::tooManyAttempts($limitKey, SubmissionLimits::INVITATIONS_PER_DAY)) {
            Flux::toast(
                variant: 'warning',
                duration: 10000,
                heading: __("You've reached today's invitation limit"),
                text: __(':company can send up to :limit invitations a day. You can send more in :hours hours.', [
                    'company' => $this->company->name,
                    'limit' => SubmissionLimits::INVITATIONS_PER_DAY,
                    'hours' => SubmissionLimits::hoursUntilAvailable($limitKey),
                ]),
            );

            return;
        }

        $this->validate([
            'inviteEmail' => [
                'required',
                'email',
                // Someone already on the roster does not need inviting, and a
                // second open invitation to the same address would just make
                // two links that both work.
                // A lapsed one does not count, even in the minute before
                // invitations:expire marks it: it is no longer on the list.
                Rule::unique('invitations', 'email')
                    ->where('company_id', $this->company->id)
                    ->where('status', InvitationStatus::Pending->value)
                    ->where(fn ($query) => $query->where('expires_at', '>', now())),
            ],
            'inviteRole' => ['required', Rule::enum(MembershipRole::class)],
        ], [
            'inviteEmail.unique' => __('That address already has an invitation waiting.'),
        ]);

        $inviteTeamMember($this->company, auth()->user(), $this->inviteEmail, $this->inviteRole);

        RateLimiter::hit($limitKey, 86400);

        $this->reset('inviteEmail', 'inviteRole', 'showInviteModal');
        unset($this->pendingInvitations);

        Flux::toast(variant: 'success', text: __('Invitation sent.'));
    }

    public function revokeInvitation(int $invitationId): void
    {
        $invitation = $this->company->invitations()->findOrFail($invitationId);

        $this->authorize('revoke', $invitation);

        $invitation->update(['status' => InvitationStatus::Revoked]);

        unset($this->pendingInvitations);

        Flux::toast(variant: 'success', text: __('Invitation revoked.'));
    }

    public function changeRole(int $membershipId, string $role): void
    {
        $membership = $this->company->memberships()->findOrFail($membershipId);

        $this->authorize('update', $membership);

        $membership->update(['role' => $role]);

        unset($this->members);

        Flux::toast(variant: 'success', text: __('Role updated.'));
    }

    public function removeMember(int $membershipId): void
    {
        $membership = $this->company->memberships()->findOrFail($membershipId);

        $this->authorize('deactivate', $membership);

        // A status change, never a deleted row: who worked here and when is
        // what keeps their past postings and decisions attributable.
        $membership->update(['status' => MembershipStatus::Inactive]);

        unset($this->members);

        Flux::toast(variant: 'success', text: __('Member removed from the team.'));
    }
}; ?>

<div class="mx-auto flex max-w-4xl flex-col gap-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" class="font-display">{{ __('Team') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Who can post jobs and review applicants for :company.', ['company' => $this->company->name]) }}</flux:text>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="$set('showInviteModal', true)">
            {{ __('Invite someone') }}
        </flux:button>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <table class="w-full text-sm">
            <caption class="sr-only">{{ __('Team members') }}</caption>
            <thead class="border-b border-zinc-200 bg-zinc-50 text-start dark:border-zinc-800 dark:bg-zinc-800/50">
                <tr>
                    <th scope="col" class="px-5 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Member') }}</th>
                    <th scope="col" class="px-5 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Role') }}</th>
                    <th scope="col" class="px-5 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @foreach ($this->members as $membership)
                    <tr wire:key="membership-{{ $membership->id }}" @class(['opacity-60' => $membership->status === \App\Enums\MembershipStatus::Inactive])>
                        <td class="px-5 py-4">
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $membership->user->name }}</div>
                            <div class="text-zinc-500 dark:text-zinc-500">{{ $membership->user->email }}</div>
                        </td>
                        <td class="px-5 py-4">
                            @if ($membership->status === \App\Enums\MembershipStatus::Inactive)
                                <flux:badge color="zinc">{{ __('No longer on the team') }}</flux:badge>
                            @else
                                <flux:badge :color="$membership->role === \App\Enums\MembershipRole::Owner ? 'green' : 'zinc'">
                                    {{ $membership->role->label() }}
                                </flux:badge>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-end">
                            @can('update', $membership)
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Actions for :name', ['name' => $membership->user->name])" />

                                    <flux:menu>
                                        @foreach (\App\Enums\MembershipRole::cases() as $role)
                                            @if ($role !== $membership->role)
                                                <flux:menu.item wire:click="changeRole({{ $membership->id }}, '{{ $role->value }}')">
                                                    {{ __('Make :role', ['role' => \Illuminate\Support\Str::lower($role->label())]) }}
                                                </flux:menu.item>
                                            @endif
                                        @endforeach

                                        @if ($membership->status === \App\Enums\MembershipStatus::Active)
                                            <flux:menu.separator />
                                            <flux:menu.item
                                                variant="danger"
                                                wire:click="removeMember({{ $membership->id }})"
                                                wire:confirm="{{ __('Remove :name from the team? They lose access immediately.', ['name' => $membership->user->name]) }}"
                                            >
                                                {{ __('Remove from team') }}
                                            </flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            @else
                                <flux:text size="sm" class="text-zinc-400">&mdash;</flux:text>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($this->pendingInvitations->isNotEmpty())
        <div>
            <flux:heading size="lg">{{ __('Waiting to accept') }}</flux:heading>

            <div class="mt-4 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <ul class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach ($this->pendingInvitations as $invitation)
                        <li wire:key="invitation-{{ $invitation->id }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                            <div>
                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $invitation->email }}</div>
                                <div class="text-sm text-zinc-500 dark:text-zinc-500">
                                    {{ __('Invited as :role, expires :date', [
                                        'role' => \Illuminate\Support\Str::lower($invitation->role->label()),
                                        'date' => $invitation->expires_at->toFormattedDateString(),
                                    ]) }}
                                </div>
                            </div>

                            <flux:button
                                size="sm"
                                variant="subtle"
                                wire:click="revokeInvitation({{ $invitation->id }})"
                                wire:loading.attr="disabled"
                                wire:target="revokeInvitation({{ $invitation->id }})"
                            >
                                {{ __('Revoke') }}
                            </flux:button>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <flux:modal wire:model="showInviteModal" class="md:w-96">
        <form wire:submit="invite" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ __('Invite someone') }}</flux:heading>
                <flux:text class="mt-2">{{ __('They will get an email with a link to join.') }}</flux:text>
            </div>

            <flux:input wire:model="inviteEmail" type="email" :label="__('Email address')" required />

            <flux:select wire:model="inviteRole" :label="__('Role')">
                <flux:select.option value="member">{{ __('Member — review applicants') }}</flux:select.option>
                <flux:select.option value="manager">{{ __('Manager — also post jobs and manage the team') }}</flux:select.option>
                <flux:select.option value="owner">{{ __('Owner — full control of the company') }}</flux:select.option>
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="invite">
                    <span wire:loading.remove wire:target="invite">{{ __('Send invitation') }}</span>
                    <span wire:loading wire:target="invite">{{ __('Sending...') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
