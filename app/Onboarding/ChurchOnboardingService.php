<?php

namespace App\Onboarding;

use App\Enums\ChurchOnboardingStatus;
use App\Enums\ChurchOnboardingStep;
use App\Models\Church;
use App\Models\ChurchOnboardingState;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class ChurchOnboardingService
{
    public function start(Church $church, User $actor): ChurchOnboardingState
    {
        if (! $church->is_active) {
            throw new DomainException('Only an active Church can begin guided setup.');
        }

        return DB::transaction(function () use ($church, $actor): ChurchOnboardingState {
            Church::query()->lockForUpdate()->findOrFail($church->id);
            $state = ChurchOnboardingState::query()->lockForUpdate()->firstOrNew(['church_id' => $church->id]);
            if ($state->exists && $state->status === ChurchOnboardingStatus::COMPLETED) {
                return $state;
            }
            $state->forceFill([
                'status' => ChurchOnboardingStatus::IN_PROGRESS,
                'current_step' => $state->exists && $state->current_step !== ChurchOnboardingStep::WELCOME ? $state->current_step : ChurchOnboardingStep::IDENTITY,
                'started_at' => $state->started_at ?? now(),
                'dismissed_at' => null,
                'last_actor_user_id' => $actor->id,
            ])->save();

            return $state->fresh();
        }, 3);
    }

    public function advance(ChurchOnboardingState $state, ChurchOnboardingStep $next, User $actor): ChurchOnboardingState
    {
        return DB::transaction(function () use ($state, $next, $actor): ChurchOnboardingState {
            $locked = ChurchOnboardingState::query()->lockForUpdate()->findOrFail($state->id);
            if ($locked->status !== ChurchOnboardingStatus::IN_PROGRESS || $next !== $locked->current_step->next()) {
                throw new DomainException('The guided setup step transition is invalid.');
            }
            $locked->forceFill(['current_step' => $next, 'last_actor_user_id' => $actor->id])->save();

            return $locked->fresh();
        });
    }

    public function complete(ChurchOnboardingState $state, User $actor): ChurchOnboardingState
    {
        return DB::transaction(function () use ($state, $actor): ChurchOnboardingState {
            $locked = ChurchOnboardingState::query()->lockForUpdate()->findOrFail($state->id);
            if ($locked->status !== ChurchOnboardingStatus::IN_PROGRESS || $locked->current_step !== ChurchOnboardingStep::COMPLETE) {
                throw new DomainException('Guided setup can only finish from its final step.');
            }
            $locked->forceFill([
                'status' => ChurchOnboardingStatus::COMPLETED, 'completed_at' => now(),
                'dismissed_at' => null, 'last_actor_user_id' => $actor->id,
            ])->save();

            return $locked->fresh();
        });
    }

    public function dismiss(ChurchOnboardingState $state, User $actor): ChurchOnboardingState
    {
        return DB::transaction(function () use ($state, $actor): ChurchOnboardingState {
            $locked = ChurchOnboardingState::query()->lockForUpdate()->findOrFail($state->id);
            if ($locked->status === ChurchOnboardingStatus::COMPLETED) {
                throw new DomainException('Completed guided setup cannot be dismissed.');
            }
            $locked->forceFill([
                'status' => ChurchOnboardingStatus::DISMISSED, 'dismissed_at' => now(),
                'last_actor_user_id' => $actor->id,
            ])->save();

            return $locked->fresh();
        });
    }
}
