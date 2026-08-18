<?php

namespace App\Console\Commands;

use App\Enums\ChurchRole;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2 of the K-IDENTITY-001B staged migration. Converts legacy
 * users.church_id relationships into ChurchMembership rows.
 *
 * Deliberately an Artisan command, not a migration file — ownership
 * decisions (especially which user becomes Primary Administrator when a
 * church has more than one legacy user) must be visible and reviewable,
 * never buried inside an unattended migrate run. See K-IDENTITY-001A §S.
 *
 * Backfill role bundle: every backfilled membership receives the full
 * Administrator + Communications + Care bundle. This is legacy access
 * preservation, not a statement that any role implies Care — see
 * Blueprint v1.4.1 preflight decision #2. It should be reviewed before
 * K-AUTH-001 begins enforcing capability checks.
 */
class BackfillChurchMemberships extends Command
{
    protected $signature = 'identity:backfill-memberships
        {--dry-run : Report what would happen without writing anything}
        {--primary=* : Explicit Primary Administrator mapping for an ambiguous church, as church_id:user_id}';

    protected $description = 'Backfill ChurchMembership rows from legacy users.church_id (K-IDENTITY-001B Phase 2)';

    protected const BACKFILL_ROLES = [
        ChurchRole::ADMINISTRATOR,
        ChurchRole::COMMUNICATIONS,
        ChurchRole::CARE,
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $primaryOverrides = $this->parsePrimaryOverrides();

        if ($primaryOverrides === null) {
            return self::FAILURE;
        }

        $this->info($dryRun ? 'DRY RUN — no data will be written.' : 'Running backfill.');
        $this->newLine();

        $churches = Church::query()->orderBy('id')->get();
        $hadUnresolved = false;

        foreach ($churches as $church) {
            $result = $this->processChurch($church, $primaryOverrides, $dryRun);

            if ($result === 'ambiguous') {
                $hadUnresolved = true;
            }
        }

        $this->newLine();

        if ($hadUnresolved) {
            $this->warn('One or more churches were skipped — ambiguous Primary Administrator, no mapping provided. Re-run with --primary=church_id:user_id for each.');

            return self::FAILURE;
        }

        $this->info($dryRun ? 'Dry run complete.' : 'Backfill complete.');

        return self::SUCCESS;
    }

    /**
     * @return 'skipped-already-backfilled'|'no-legacy-users'|'ambiguous'|'backfilled'
     */
    protected function processChurch(Church $church, array $primaryOverrides, bool $dryRun): string
    {
        $label = "Church {$church->id} ({$church->name})";

        if ($church->memberships()->exists()) {
            $this->line("{$label}: already has membership records — skipped (idempotent).");

            return 'skipped-already-backfilled';
        }

        $legacyUsers = User::query()
            ->where('church_id', $church->id)
            ->orderBy('id')
            ->get();

        if ($legacyUsers->isEmpty()) {
            $this->line("{$label}: no legacy users — nothing to backfill.");

            return 'no-legacy-users';
        }

        $primaryUserId = $this->resolvePrimaryUserId($church, $legacyUsers, $primaryOverrides);

        if ($primaryUserId === null) {
            $ids = $legacyUsers->pluck('id')->implode(', ');
            $this->error("{$label}: AMBIGUOUS — {$legacyUsers->count()} legacy users [{$ids}]. No authoritative signal exists; provide --primary={$church->id}:<user_id>.");

            return 'ambiguous';
        }

        $ids = $legacyUsers->pluck('id')->implode(', ');
        $roleList = implode(', ', array_map(fn (ChurchRole $r) => $r->value, self::BACKFILL_ROLES));
        $this->info("{$label}: {$legacyUsers->count()} legacy user(s) [{$ids}] -> Primary = User {$primaryUserId}. Bundle: {$roleList}.");

        if ($dryRun) {
            return 'backfilled';
        }

        DB::transaction(function () use ($church, $legacyUsers, $primaryUserId) {
            foreach ($legacyUsers as $user) {
                if ($user->id === $primaryUserId) {
                    ChurchMembership::createPrimary($church, $user, self::BACKFILL_ROLES);

                    continue;
                }

                $membership = ChurchMembership::create([
                    'church_id' => $church->id,
                    'user_id' => $user->id,
                    'status' => \App\Enums\MembershipStatus::ACTIVE,
                    'is_primary' => false,
                    'joined_at' => now(),
                ]);

                $membership->assignRoles(self::BACKFILL_ROLES);
            }
        });

        return 'backfilled';
    }

    protected function resolvePrimaryUserId(Church $church, \Illuminate\Support\Collection $legacyUsers, array $primaryOverrides): ?int
    {
        if ($legacyUsers->count() === 1) {
            return $legacyUsers->first()->id;
        }

        // More than one legacy user — never infer. Only an explicit,
        // validated --primary mapping resolves this church.
        // See K-IDENTITY-001A §D/§10 and Blueprint v1.4.1 preflight
        // decision #1.
        if (! array_key_exists($church->id, $primaryOverrides)) {
            return null;
        }

        $mappedUserId = $primaryOverrides[$church->id];

        if (! $legacyUsers->contains('id', $mappedUserId)) {
            $this->error("Church {$church->id}: --primary={$church->id}:{$mappedUserId} does not reference a legacy user of this church.");

            return null;
        }

        return $mappedUserId;
    }

    /**
     * @return array<int, int>|null  church_id => user_id, or null on a parse error
     */
    protected function parsePrimaryOverrides(): ?array
    {
        $overrides = [];

        foreach ((array) $this->option('primary') as $mapping) {
            if (! str_contains($mapping, ':')) {
                $this->error("Invalid --primary mapping '{$mapping}'. Expected format: church_id:user_id.");

                return null;
            }

            [$churchId, $userId] = explode(':', $mapping, 2);

            if (! ctype_digit($churchId) || ! ctype_digit($userId)) {
                $this->error("Invalid --primary mapping '{$mapping}'. Both church_id and user_id must be integers.");

                return null;
            }

            $overrides[(int) $churchId] = (int) $userId;
        }

        return $overrides;
    }
}
