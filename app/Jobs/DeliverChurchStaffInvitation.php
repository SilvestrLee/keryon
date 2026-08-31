<?php

namespace App\Jobs;

use App\Enums\InvitationDeliverySubjectType;
use App\InvitationDelivery\DeliverInvitationAttempt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class DeliverChurchStaffInvitation implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(public readonly string $attemptUuid) {}

    public function handle(DeliverInvitationAttempt $delivery): void
    {
        $delivery->execute($this->attemptUuid, InvitationDeliverySubjectType::CHURCH_STAFF_INVITATION);
    }
}
