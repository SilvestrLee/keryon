<?php

namespace App\Http\Controllers;

use App\ChurchStaff\AcceptChurchStaffInvitation;
use App\ChurchStaff\ChurchStaffInvitationTokenService;
use App\Enums\ChurchStaffInvitationStatus;
use App\Models\ChurchStaffInvitation;
use App\Onboarding\SelectActiveChurch;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChurchStaffInvitationController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $invitation = $this->valid($token);
        abort_unless(strtolower($request->user()->email) === $invitation->email_normalized, 403);
        abort_if(blank(config('staff.terms_version')) || blank(config('staff.privacy_version')), 503);

        return view('staff.accept-invitation', ['invitation' => $invitation, 'token' => $token, 'termsVersion' => config('staff.terms_version'), 'privacyVersion' => config('staff.privacy_version')]);
    }

    public function accept(Request $request, string $token, AcceptChurchStaffInvitation $accept): RedirectResponse
    {
        $data = $request->validate([
            'acceptance_idempotency_key' => ['required', 'uuid'], 'legal_acceptance' => ['accepted'],
        ]);
        $termsVersion = (string) config('staff.terms_version');
        $privacyVersion = (string) config('staff.privacy_version');
        abort_if(blank($termsVersion) || blank($privacyVersion), 503);
        try {
            $result = $accept->execute($token, $request->user(), $termsVersion, $privacyVersion, $data['acceptance_idempotency_key']);
        } catch (DomainException $exception) {
            return back()->withErrors(['invitation' => $exception->getMessage()]);
        }

        return redirect()->route('church-staff-invitations.accepted', ['invitation' => $result->invitation->uuid]);
    }

    public function accepted(Request $request, string $invitation): View
    {
        $record = ChurchStaffInvitation::query()->where('uuid', $invitation)->where('status', ChurchStaffInvitationStatus::ACCEPTED)->with('church')->firstOrFail();
        abort_unless($record->legal_accepted_by_user_id === $request->user()->id, 403);

        return view('staff.invitation-accepted', ['invitation' => $record]);
    }

    public function enter(Request $request, string $invitation, SelectActiveChurch $select): RedirectResponse
    {
        $record = ChurchStaffInvitation::query()->where('uuid', $invitation)->where('status', ChurchStaffInvitationStatus::ACCEPTED)->firstOrFail();
        abort_unless($record->legal_accepted_by_user_id === $request->user()->id, 403);
        $select->execute($request->user(), $record->church_id);

        return redirect('/admin');
    }

    private function valid(string $token): ChurchStaffInvitation
    {
        $invitation = ChurchStaffInvitation::query()->where('token_hash', ChurchStaffInvitationTokenService::hash($token))->with(['church', 'roles'])->firstOrFail();
        abort_unless($invitation->status === ChurchStaffInvitationStatus::PENDING && $invitation->token_expires_at?->isFuture(), 410);

        return $invitation;
    }
}
