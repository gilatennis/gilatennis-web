<?php

namespace App\Enums;

enum PaymentEventSource: string
{
    case Webhook = 'webhook';
    case Return = 'return';
    case Admin = 'admin';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Webhook => 'Duitku webhook',
            self::Return => 'Return URL',
            self::Admin => 'Admin override',
            self::System => 'System',
        };
    }
}
