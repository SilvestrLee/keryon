<?php

namespace App\Organizations\Communications\Tracking;

use App\Enums\OrganizationCommunicationDeliveryState;
use Illuminate\Support\Carbon;

/**
 * K-ORG-COMMS-001F §5-§10 — the single canonical Organization-side
 * delivery-outcome precedence. Deliberately distinct from
 * `OrganizationCommunicationDelivery::derivedResponseState()` (the
 * Church-side resolver): Organization tracking must surface Imported as
 * the current bounded outcome once an import event exists, ranked above
 * Accepted — a Church-facing view has no reason to make that
 * distinction, since Accept and Import are two separate, Church-only
 * actions from that side.
 *
 * Never mutates `delivery.state`. Exposed two ways so the same
 * precedence can never drift between the paginated recipient list (PHP,
 * §5) and the bulk summary counts (SQL, §54 — "no ->get()->groupBy()
 * over thousands of rows"):
 *
 * - `resolve()` — scalar precedence over already-loaded values, for one
 *   bounded row at a time (the recipient table).
 * - `sqlCaseExpression()` — the identical precedence expressed as one
 *   SQL `CASE` fragment, for `GROUP BY`/aggregate counting entirely in
 *   the database.
 */
final class OrganizationCommunicationDeliveryOutcomeResolver
{
    public const IMPORTED = 'imported';

    public const DECLINED = 'declined';

    public const ACCEPTED = 'accepted';

    public const WITHDRAWN = 'withdrawn';

    public const EXPIRED = 'expired';

    public const AVAILABLE = 'available';

    /** @return list<string> in most-progressed-first order */
    public static function precedence(): array
    {
        return [self::IMPORTED, self::DECLINED, self::ACCEPTED, self::WITHDRAWN, self::EXPIRED, self::AVAILABLE];
    }

    public function resolve(
        bool $imported,
        ?Carbon $declinedAt,
        ?Carbon $acceptedAt,
        OrganizationCommunicationDeliveryState $state,
        ?Carbon $availableUntil,
    ): string {
        if ($imported) {
            return self::IMPORTED;
        }

        if ($declinedAt !== null) {
            return self::DECLINED;
        }

        if ($acceptedAt !== null) {
            return self::ACCEPTED;
        }

        if ($state === OrganizationCommunicationDeliveryState::WITHDRAWN) {
            return self::WITHDRAWN;
        }

        if ($availableUntil !== null && $availableUntil->isPast()) {
            return self::EXPIRED;
        }

        return self::AVAILABLE;
    }

    /**
     * A raw SQL `CASE` expression computing the same precedence, given
     * the table aliases actually used by `OrganizationCommunicationTrackingQuery`.
     * `$importedColumn` is expected to be a boolean/expression that is
     * true exactly when a matching `organization_communication_imports`
     * row exists (a `LEFT JOIN ... IS NOT NULL`), never a subquery per
     * row. The "now" comparison is inlined as a literal (server-
     * generated, never user input) rather than a `?` binding, so this
     * fragment composes safely inside a larger `selectRaw()` alongside
     * other expressions without binding-order fragility.
     */
    public static function sqlCaseExpression(
        string $importedColumn = 'imports.id',
        string $declinedAtColumn = 'deliveries.declined_at',
        string $acceptedAtColumn = 'deliveries.accepted_at',
        string $stateColumn = 'deliveries.state',
        string $availableUntilColumn = 'deliveries.available_until',
    ): string {
        $withdrawn = OrganizationCommunicationDeliveryState::WITHDRAWN->value;
        $now = now()->toDateTimeString();

        return <<<SQL
            case
                when {$importedColumn} is not null then 'imported'
                when {$declinedAtColumn} is not null then 'declined'
                when {$acceptedAtColumn} is not null then 'accepted'
                when {$stateColumn} = '{$withdrawn}' then 'withdrawn'
                when {$availableUntilColumn} is not null and {$availableUntilColumn} < '{$now}' then 'expired'
                else 'available'
            end
        SQL;
    }

    /**
     * K-ORG-COMMS-001F §21 — the numeric form of `precedence()`, for
     * ranking a Church's several deliveries of the *same* revision down
     * to the single most-progressed one (via `ROW_NUMBER() OVER (...
     * ORDER BY outcome_rank)`), without hand-writing the ordering twice.
     */
    public static function rankCaseExpression(string $outcomeColumn): string
    {
        $cases = collect(self::precedence())
            ->map(fn (string $outcome, int $rank): string => "when {$outcomeColumn} = '{$outcome}' then ".($rank + 1))
            ->implode(' ');

        return "case {$cases} else 99 end";
    }

    public function label(string $outcome): string
    {
        return match ($outcome) {
            self::IMPORTED => 'Imported',
            self::DECLINED => 'Declined',
            self::ACCEPTED => 'Accepted',
            self::WITHDRAWN => 'Withdrawn',
            self::EXPIRED => 'Expired',
            self::AVAILABLE => 'Available',
            default => str($outcome)->headline()->toString(),
        };
    }
}
