<?php

namespace App\Domain\Admission\Enums;

/**
 * enquiry → approved → admitted, or → rejected / withdrawn at any open
 * step. Small institutions may admit straight from an enquiry.
 */
enum ApplicationStatus: string
{
    case Enquiry = 'enquiry';
    case Approved = 'approved';
    case Admitted = 'admitted';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function isOpen(): bool
    {
        return in_array($this, [self::Enquiry, self::Approved], true);
    }

    public function canBecome(self $next): bool
    {
        return match ($this) {
            self::Enquiry => in_array($next, [self::Approved, self::Admitted, self::Rejected, self::Withdrawn], true),
            self::Approved => in_array($next, [self::Admitted, self::Rejected, self::Withdrawn], true),
            self::Admitted, self::Rejected, self::Withdrawn => false,
        };
    }
}
