<?php

namespace App\Http\Controllers;

use App\Actions\AcceptInvitation;
use App\Enums\InvitationStatus;
use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $invitation = $this->openInvitation($token);

        return view('invitations.show', [
            'invitation' => $invitation,
            'addressMatches' => $this->addressMatches($request, $invitation),
        ]);
    }

    public function accept(Request $request, string $token, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        $invitation = $this->openInvitation($token);

        // A guest gets sent to sign in and brought straight back here,
        // rather than being asked to find the email again afterwards.
        if (! $request->user()) {
            $request->session()->put('url.intended', route('invitations.show', $token));

            return redirect()->route('login');
        }

        if (! $this->addressMatches($request, $invitation)) {
            return back();
        }

        $acceptInvitation($invitation, $request->user());

        return redirect()
            ->route('employer.dashboard', $invitation->company)
            ->with('success', __('You have joined :company.', ['company' => $invitation->company->name]));
    }

    /**
     * An invitation is only open while it is still pending and still in
     * date. Anything else -- revoked, already used, or past its week --
     * is gone, and says so with a 404 rather than hinting that a valid
     * token exists behind it.
     */
    private function openInvitation(string $token): Invitation
    {
        return Invitation::query()
            ->with('company')
            ->where('token', $token)
            ->where('status', InvitationStatus::Pending)
            ->where('expires_at', '>', now())
            ->firstOrFail();
    }

    /**
     * The invitation was addressed to one person, and forwarding the
     * email must not hand someone else a way in -- so the signed-in
     * account has to be that address. This is the rule every established
     * team product enforces; GitHub puts it plainly: an invitee can only
     * accept if the address matches one verified on their own account.
     */
    private function addressMatches(Request $request, Invitation $invitation): bool
    {
        $user = $request->user();

        return $user !== null
            && Str::lower($user->email) === Str::lower($invitation->email);
    }
}
