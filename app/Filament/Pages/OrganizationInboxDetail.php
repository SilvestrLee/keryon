<?php

namespace App\Filament\Pages;

use App\Communications\OrganizationInbox\ChurchOrganizationCommunicationQuery;
use App\Communications\OrganizationInbox\Exceptions\OrganizationCommunicationResponseException;
use App\Communications\OrganizationInbox\OrganizationCommunicationChurchResponseService;
use App\Enums\OrganizationCommunicationAdaptationPolicy;
use App\Enums\OrganizationCommunicationDeclineReasonCode;
use App\Models\OrganizationCommunicationAsset;
use App\Models\OrganizationCommunicationDelivery;
use App\Models\OrganizationCommunicationMaterial;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Throwable;

/**
 * K-ORG-COMMS-001D §16-§32 — the single Organization Inbox item detail
 * surface: Overview, Resources, Assets, Guidance & dates, Response.
 * "ACCEPT != IMPORT" — the Accept/Decline actions below only ever call
 * `OrganizationCommunicationChurchResponseService`, which never creates a
 * ContentItem, Campaign, MediaAsset, Website, or FaithFlow record.
 */
class OrganizationInboxDetail extends Page
{
    protected string $view = 'filament.pages.organization-inbox-detail';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'organization-inbox/{delivery}';

    protected static ?string $title = 'Shared communication';

    public string $delivery;

    private ?OrganizationCommunicationDelivery $deliveryCache = null;

    public function mount(): void
    {
        // Fails closed (404/403) for a cross-Church or unknown uuid via
        // ChurchOrganizationCommunicationQuery's own scoped lookup.
        $this->deliveryRecord();
    }

    /**
     * Memoized per-request — several accessors below independently need
     * the delivery, and its own lookup is not cheap enough to repeat.
     */
    public function deliveryRecord(): OrganizationCommunicationDelivery
    {
        return $this->deliveryCache ??= app(ChurchOrganizationCommunicationQuery::class)->findByUuid($this->delivery);
    }

    public function getTitle(): string
    {
        return $this->deliveryRecord()->revision?->title ?? 'Shared communication';
    }

    public function responseState(): string
    {
        $state = $this->deliveryRecord()->derivedResponseState();

        return is_string($state) ? $state : $state->value;
    }

    public function responseStateLabel(): string
    {
        return app(ChurchOrganizationCommunicationQuery::class)->stateLabel($this->responseState());
    }

    public function isReferenceOnly(): bool
    {
        return $this->deliveryRecord()->revision?->adaptation_policy === OrganizationCommunicationAdaptationPolicy::REFERENCE_ONLY;
    }

    public function materialBodyHtml(OrganizationCommunicationMaterial $material): string
    {
        return Str::markdown((string) $material->body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public function assetUrl(OrganizationCommunicationAsset $asset): string
    {
        return route('organization-communications.church-inbox.assets.private', [
            'delivery' => $this->deliveryRecord()->uuid,
            'asset' => $asset->uuid,
        ]);
    }

    public function backUrl(): string
    {
        return OrganizationInbox::getUrl();
    }

    public function acceptDeliveryAction(): Action
    {
        return Action::make('acceptDelivery')
            ->label('Accept')
            ->color('success')
            ->visible(fn (): bool => $this->responseState() === ChurchOrganizationCommunicationQuery::STATE_AVAILABLE)
            ->authorize(fn (): bool => auth()->user()?->can('respond', $this->deliveryRecord()) ?? false)
            ->requiresConfirmation()
            ->modalHeading('Accept this Organization communication?')
            ->modalDescription('Accepting confirms that your Church intends to use or adapt this communication. It does not create local content or publish anything.')
            ->modalSubmitActionLabel('Accept')
            ->action(function (): void {
                $this->respond(fn () => app(OrganizationCommunicationChurchResponseService::class)->accept($this->deliveryRecord()), 'Accepted.');
            });
    }

    public function declineDeliveryAction(): Action
    {
        return Action::make('declineDelivery')
            ->label('Decline')
            ->color('gray')
            ->visible(fn (): bool => $this->responseState() === ChurchOrganizationCommunicationQuery::STATE_AVAILABLE)
            ->authorize(fn (): bool => auth()->user()?->can('respond', $this->deliveryRecord()) ?? false)
            ->requiresConfirmation()
            ->modalHeading('Decline this Organization communication?')
            ->modalDescription('Declining tells the Organization that your Church will not use this shared communication. It does not affect any local Church content.')
            ->modalSubmitActionLabel('Decline')
            ->schema([
                Select::make('decline_reason_code')
                    ->label('Reason (optional)')
                    ->placeholder('No reason given')
                    ->options(collect(OrganizationCommunicationDeclineReasonCode::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all())
                    ->native(false),
            ])
            ->action(function (array $data): void {
                $reason = filled($data['decline_reason_code'] ?? null)
                    ? OrganizationCommunicationDeclineReasonCode::from($data['decline_reason_code'])
                    : null;

                $this->respond(fn () => app(OrganizationCommunicationChurchResponseService::class)->decline($this->deliveryRecord(), $reason), 'Declined.');
            });
    }

    private function respond(callable $call, string $successMessage): void
    {
        try {
            $call();
        } catch (OrganizationCommunicationResponseException $e) {
            Notification::make()->danger()->title('This response could not be recorded')->body($e->getMessage())->send();

            return;
        } catch (Throwable $e) {
            report($e);
            Notification::make()->danger()->title('This response could not be recorded')->send();

            return;
        }

        $this->deliveryCache = null;
        Notification::make()->success()->title($successMessage)->send();
    }
}
