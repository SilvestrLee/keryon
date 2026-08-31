<?php

namespace App\Mail;

use App\InvitationDelivery\InvitationDeliveryMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class ChurchStaffInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly InvitationDeliveryMessage $messageData) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'You are invited to join '.$this->messageData->churchName.' on Keryon');
    }

    public function content(): Content
    {
        return new Content(html: 'mail.church-staff-invitation', text: 'mail.text.church-staff-invitation');
    }
}
