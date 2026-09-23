<?php

namespace App\Http\Controllers;

use App\Enums\ChurchActivationStatus;
use App\Filament\Pages\GuidedChurchSetup;
use App\Models\ChurchActivation;
use App\Onboarding\AcceptChurchPrimaryActivation;
use App\Onboarding\ChurchActivationTokenService;
use App\Onboarding\SelectActiveChurch;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChurchActivationController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $activation = $this->validActivation($token);
        abort_unless(strtolower($request->user()->email) === strtolower($activation->prospective_primary_email), 403);
        abort_if(blank(config('onboarding.legal.terms_version')) || blank(config('onboarding.legal.privacy_version')), 503);

        return view('onboarding.church-activation', ['activation' => $activation, 'token' => $token]);
    }

    public function accept(Request $request, string $token, AcceptChurchPrimaryActivation $accept, SelectActiveChurch $select): RedirectResponse
    {
        $validated = $request->validate([
            'acceptance_idempotency_key' => ['required', 'uuid'],
            'legal_acceptance' => ['accepted'],
        ]);
        $termsVersion = (string) config('onboarding.legal.terms_version');
        $privacyVersion = (string) config('onboarding.legal.privacy_version');
        abort_if(blank($termsVersion) || blank($privacyVersion), 503);
        try {
            $result = $accept->execute($token, $request->user(), $termsVersion, $privacyVersion, $validated['acceptance_idempotency_key']);
        } catch (DomainException $exception) {
            return back()->withErrors(['activation' => $exception->getMessage()]);
        }

        if ($request->user()->activeMemberships()->count() === 1) {
            $select->execute($request->user(), $result->church->id);

            return redirect(GuidedChurchSetup::getUrl());
        }

        return redirect('/admin');
    }

    private function validActivation(string $token): ChurchActivation
    {
        $activation = ChurchActivation::query()->with('church')->where('token_hash', ChurchActivationTokenService::hash($token))->firstOrFail();
        abort_unless($activation->status === ChurchActivationStatus::PENDING && $activation->token_expires_at?->isFuture(), 410);

        return $activation;
    }
}
