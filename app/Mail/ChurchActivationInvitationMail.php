<?php

namespace App\Mail;

use App\InvitationDelivery\InvitationDeliveryMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class ChurchActivationInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly InvitationDeliveryMessage $messageData) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Become Primary Administrator for '.$this->messageData->churchName);
    }

    public function content(): Content
    {
        return new Content(html: 'mail.church-activation-invitation', text: 'mail.text.church-activation-invitation');
    }
}
