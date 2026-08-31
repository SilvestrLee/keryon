<?php

namespace App\InvitationDelivery;

final readonly class DeliveryTransportReceipt
{
    public function __construct(public ?string $messageReference = null, public ?string $accountKey = null) {}
}
