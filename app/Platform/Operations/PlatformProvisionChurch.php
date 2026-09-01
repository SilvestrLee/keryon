<?php

namespace App\Platform\Operations;

use App\Enums\PlatformAuditEventType;
use App\Enums\PlatformAuditReasonCategory;
use App\Enums\PlatformAuditTargetType;
use App\Enums\PlatformCapability;
use App\Onboarding\ProvisionChurch;
use App\Onboarding\ProvisionChurchData;
use App\Platform\PlatformAudit;
use Illuminate\Support\Facades\DB;

final class PlatformProvisionChurch extends PlatformOperation
{
    public function execute(ProvisionChurchData $data, PlatformAuditReasonCategory $reason, string $note): PlatformOperationResult
    {
        $actor = $this->actor(PlatformCapability::PlatformChurchProvision);
        $this->requireReason($note);
        $this->requireCorrelation($data->idempotencyKey);

        return DB::transaction(function () use ($data, $reason, $note, $actor): PlatformOperationResult {
            $result = app(ProvisionChurch::class)->execute($data);
            if (! $this->prior(PlatformAuditEventType::CHURCH_PROVISIONED->value, PlatformAuditTargetType::CHURCH->value, $result->church->id, $data->idempotencyKey)) {
                PlatformAudit::record(
                    PlatformAuditEventType::CHURCH_PROVISIONED,
                    PlatformAuditTargetType::CHURCH,
                    $result->church->id,
                    $actor,
                    null,
                    ['church_id' => $result->church->id, 'activation_id' => $result->activation->id, 'activation_status' => $result->activation->status->value, 'created' => $result->created, 'actor_role' => $actor->role->value, 'capability' => PlatformCapability::PlatformChurchProvision->value, 'result' => $result->created ? 'created' : 'existing'],
                    $reason,
                    $note,
                    $data->idempotencyKey,
                );
            }

            return new PlatformOperationResult(
                $result->created ? 'Church provisioned and activation created.' : 'The existing provisioned Church was returned.',
                'church_activation',
                $result->activation->id,
                ['status' => $result->activation->status->value],
                $result->created,
            );
        }, 3);
    }
}
