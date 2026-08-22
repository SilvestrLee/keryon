<?php

namespace App\Enums;

enum CommunicationChannel: string
{
    case GENERAL = 'general';
    case WEBSITE = 'website';
    case INSTAGRAM = 'instagram';
    case FACEBOOK = 'facebook';
    case WHATSAPP = 'whatsapp';
    case YOUTUBE = 'youtube';
    case EMAIL = 'email';
    case SMS = 'sms';

    public function label(): string
    {
        return match ($this) {
            self::GENERAL => 'General',
            self::WEBSITE => 'Website',
            self::INSTAGRAM => 'Instagram',
            self::FACEBOOK => 'Facebook',
            self::WHATSAPP => 'WhatsApp',
            self::YOUTUBE => 'YouTube',
            self::EMAIL => 'Email',
            self::SMS => 'SMS',
        };
    }
}
