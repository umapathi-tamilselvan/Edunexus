<?php

namespace App\Domain\Fee\Enums;

/**
 * Kinds of ledger entry. A due adds to what the student owes; a payment
 * takes it off; a reversal cancels one due or payment with the opposite
 * amount.
 */
enum FeeEntryType: string
{
    case Due = 'due';
    case Payment = 'payment';
    case Reversal = 'reversal';
}
