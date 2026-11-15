<?php

namespace App\Domain\Fee\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Cheque = 'cheque';
    case Upi = 'upi';
    case BankTransfer = 'bank_transfer';
    case Card = 'card';
    case Gateway = 'gateway';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Cheque => 'Cheque',
            self::Upi => 'UPI',
            self::BankTransfer => 'Bank transfer',
            self::Card => 'Card',
            self::Gateway => 'Online',
        };
    }
}
