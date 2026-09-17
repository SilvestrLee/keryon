<?php

namespace App\Http\Controllers;

use App\ChurchStaff\AcceptChurchStaffInvitation;
use App\ChurchStaff\ChurchStaffInvitationTokenService;
use App\Enums\ChurchActivationStatus;
use App\Enums\ChurchStaffInvitationStatus;
use App\InvitationDelivery\InvitationContinuation;
use App\Models\ChurchActivation;
use App\Models\ChurchStaffInvitation;
use App\Models\User;
use App\Onboarding\AcceptChurchPrimaryActivation;
use App\Onboarding\ChurchActivationTokenService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class InvitationLandingController extends Controller
{
    public function land(Request $request, InvitationContinuation $continuations): RedirectResponse
    {
        $type = (string) $request->route('type');
        $this->throttle($request, $request->route('token'));
        $this->subject($type, $request->route('token'));
        $id = $continuations->create($type, $request->route('token'));

        return redirect()->route('invitations.continue', ['continuation' => $id]);
    }

    public function show(string $continuation, InvitationContinuation $continuations): View
    {
        [$data, $subject] = $this->resolve($continuation, $continuations);
        $email = $this->email($data['type'], $subject);

        return view('invitations.continue', ['continuation' => $continuation, 'subject' => $subject, 'type' => $data['type'], 'existing' => User::query()->whereRaw('lower(email) = ?', [strtolower($email)])->exists(), 'email' => $email]);
    }

    public function establish(Request $request, string $continuation, InvitationContinuation $continuations): RedirectResponse
    {
        [$data, $subject] = $this->resolve($continuation, $continuations);
        $email = strtolower($this->email($data['type'], $subject));
        $existing = User::query()->whereRaw('lower(email) = ?', [$email])->first();
        if ($existing) {
            $request->validate(['password' => ['required', 'string']]);
            if (! Hash::check($request->string('password')->toString(), $existing->password)) {
                return back()->withErrors(['identity' => 'Unable to continue with these credentials.']);
            }
            Auth::login($existing);
        } else {
            $validated = $request->validate(['name' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', 'min:12', 'confirmed']]);
            $existing = User::query()->create(['name' => $validated['name'], 'email' => $email, 'password' => $validated['password']]);
            $existing->forceFill(['email_verified_at' => now()])->save();
            Auth::login($existing);
        }
        $request->session()->regenerate();

        return redirect()->route('invitations.review', ['continuation' => $continuation]);
    }

    public function review(Request $request, string $continuation, InvitationContinuation $continuations): View
    {
        [$data, $subject] = $this->resolve($continuation, $continuations);
        abort_unless(strtolower($request->user()?->email ?? '') === strtolower($this->email($data['type'], $subject)), 403);

        return view('invitations.review', ['continuation' => $continuation, 'subject' => $subject, 'type' => $data['type']]);
    }

    public function accept(Request $request, string $continuation, InvitationContinuation $continuations, AcceptChurchPrimaryActivation $primary, AcceptChurchStaffInvitation $staff): RedirectResponse
    {
        [$data, $subject] = $this->resolve($continuation, $continuations);
        abort_unless(strtolower($request->user()?->email ?? '') === strtolower($this->email($data['type'], $subject)), 403);
        $validated = $request->validate(['legal_acceptance' => ['accepted']]);
        unset($validated);
        try {
            if ($data['type'] === 'activation') {
                $termsVersion = (string) config('onboarding.legal.terms_version');
                $privacyVersion = (string) config('onboarding.legal.privacy_version');
                abort_if(blank($termsVersion) || blank($privacyVersion), 503);
                $primary->execute($data['token'], $request->user(), $termsVersion, $privacyVersion, (string) Str::uuid());
            } else {
                $termsVersion = (string) config('staff.terms_version');
                $privacyVersion = (string) config('staff.privacy_version');
                abort_if(blank($termsVersion) || blank($privacyVersion), 503);
                $result = $staff->execute($data['token'], $request->user(), $termsVersion, $privacyVersion, (string) Str::uuid());
            }
        } catch (DomainException) {
            return back()->withErrors(['invitation' => 'This invitation cannot be accepted.']);
        }
        $continuations->forget($continuation);

        return $data['type'] === 'staff' ? redirect()->route('church-staff-invitations.accepted', ['invitation' => $result->invitation->uuid]) : redirect('/admin');
    }

    /** @return array{0:array{type:string,token:string},1:ChurchActivation|ChurchStaffInvitation} */
    private function resolve(string $id, InvitationContinuation $continuations): array
    {
        $data = $continuations->get($id);
        abort_unless($data, 410);

        return [$data, $this->subject($data['type'], $data['token'])];
    }

    private function subject(string $type, string $token): ChurchActivation|ChurchStaffInvitation
    {
        $subject = $type === 'activation'
            ? ChurchActivation::query()->with('church')->where('token_hash', ChurchActivationTokenService::hash($token))->first()
            : ChurchStaffInvitation::query()->with(['church', 'roles'])->where('token_hash', ChurchStaffInvitationTokenService::hash($token))->first();
        $pending = $type === 'activation' ? ChurchActivationStatus::PENDING : ChurchStaffInvitationStatus::PENDING;
        abort_unless($subject && $subject->status === $pending && $subject->token_expires_at?->isFuture(), 410);

        return $subject;
    }

    private function email(string $type, ChurchActivation|ChurchStaffInvitation $subject): string
    {
        return $type === 'activation' ? $subject->prospective_primary_email : $subject->email_normalized;
    }

    private function throttle(Request $request, string $token): void
    {
        $ip = 'invite-token:ip:'.$request->ip();
        $identity = 'invite-token:id:'.hash_hmac('sha256', $token, (string) config('app.key'));
        abort_if(RateLimiter::tooManyAttempts($ip, 10) || RateLimiter::tooManyAttempts($identity, 5), 429);
        RateLimiter::hit($ip, 60);
        RateLimiter::hit($identity, 60);
    }
}
