<?php

namespace App\InvitationDelivery;

use App\Enums\InvitationDeliverySubjectType;
use App\Mail\ChurchActivationInvitationMail;
use App\Mail\ChurchStaffInvitationMail;
use Illuminate\Support\Facades\Mail;

final class LocalInvitationDeliveryTransport implements InvitationDeliveryTransport
{
    public function send(InvitationDeliverySubjectType $type, InvitationDeliveryMessage $message): DeliveryTransportReceipt
    {
        $mailable = match ($type) {
            InvitationDeliverySubjectType::CHURCH_ACTIVATION => new ChurchActivationInvitationMail($message),
            InvitationDeliverySubjectType::CHURCH_STAFF_INVITATION => new ChurchStaffInvitationMail($message),
        };
        Mail::mailer(config('invitation-delivery.mailer'))->to($message->recipient)->send($mailable);

        return new DeliveryTransportReceipt;
    }
}
