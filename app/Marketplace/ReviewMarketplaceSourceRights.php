<?php

namespace App\Marketplace;

use App\Enums\MarketplaceRightsStatus;
use App\Models\MarketplaceSourceVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewMarketplaceSourceRights
{
    public function __construct(private readonly MarketplaceRightsGate $gate) {}

    /** @param array<string, mixed> $evidence */
    public function verify(MarketplaceSourceVersion $source, array $evidence, string $operatorReference): MarketplaceSourceVersion
    {
        $this->assertOperatorReference($operatorReference);
        $target = $source;

        $source = DB::transaction(function () use ($source, $evidence, $operatorReference): MarketplaceSourceVersion {
            $locked = MarketplaceSourceVersion::query()->lockForUpdate()->findOrFail($source->id);
            $now = now();

            DB::table('marketplace_source_versions')->where('id', $locked->id)->update([
                'creator_name' => $evidence['creator_name'] ?? null,
                'rightsholder_name' => $evidence['rightsholder_name'] ?? null,
                'license_reference' => $evidence['license_reference'] ?? null,
                'licensing_metadata' => $this->json($evidence['licensing_metadata'] ?? null),
                'font_metadata' => $this->json($evidence['font_metadata'] ?? null),
                'source_provenance' => $evidence['source_provenance'] ?? null,
                'rights_evidence_reference' => $evidence['rights_evidence_reference'] ?? null,
                'rights_status' => MarketplaceRightsStatus::VERIFIED->value,
                'rights_verified_by_type' => 'platform_operator',
                'rights_verified_by_reference' => $operatorReference,
                'rights_verified_at' => $now,
                'rights_decided_by_type' => 'platform_operator',
                'rights_decided_by_reference' => $operatorReference,
                'rights_decided_at' => $now,
                'rights_decision_reason' => null,
                'updated_at' => $now,
            ]);

            if (! $this->gate->permits($locked->fresh())) {
                throw ValidationException::withMessages([
                    'rights' => 'Marketplace rights verification requires complete publication and redistribution evidence.',
                ]);
            }

            return $locked->fresh();
        });

        return $target->refresh();
    }

    public function reject(MarketplaceSourceVersion $source, string $operatorReference, string $reason): MarketplaceSourceVersion
    {
        return $this->decide($source, MarketplaceRightsStatus::REJECTED, $operatorReference, $reason);
    }

    public function revoke(MarketplaceSourceVersion $source, string $operatorReference, string $reason): MarketplaceSourceVersion
    {
        if ($source->rights_status !== MarketplaceRightsStatus::VERIFIED) {
            throw ValidationException::withMessages(['rights' => 'Only verified Marketplace rights may be revoked.']);
        }

        return $this->decide($source, MarketplaceRightsStatus::REVOKED, $operatorReference, $reason);
    }

    private function decide(MarketplaceSourceVersion $source, MarketplaceRightsStatus $status, string $operatorReference, string $reason): MarketplaceSourceVersion
    {
        $this->assertOperatorReference($operatorReference);

        if (blank($reason) || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['rights' => 'A bounded rights decision reason is required.']);
        }

        DB::table('marketplace_source_versions')->where('id', $source->id)->update([
            'rights_status' => $status->value,
            'rights_decided_by_type' => 'platform_operator',
            'rights_decided_by_reference' => $operatorReference,
            'rights_decided_at' => now(),
            'rights_decision_reason' => $reason,
            'updated_at' => now(),
        ]);

        $source->item->unpublish();

        return $source->fresh();
    }

    private function assertOperatorReference(string $reference): void
    {
        if (! preg_match('/^[a-z0-9][a-z0-9._:@-]{2,127}$/', $reference)) {
            throw ValidationException::withMessages(['rights' => 'A controlled platform operator reference is required.']);
        }
    }

    private function json(mixed $value): ?string
    {
        return $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR);
    }
}
