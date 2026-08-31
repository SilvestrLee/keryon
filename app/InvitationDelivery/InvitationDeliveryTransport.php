<?php

namespace App\InvitationDelivery;

use App\Enums\InvitationDeliverySubjectType;

interface InvitationDeliveryTransport
{
    public function send(InvitationDeliverySubjectType $type, InvitationDeliveryMessage $message): DeliveryTransportReceipt;
}
