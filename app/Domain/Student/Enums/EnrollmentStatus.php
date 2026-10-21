<?php

namespace App\Domain\Student\Enums;

/**
 * Where an enrollment ended up. Promotion and leaving create or close
 * enrollments; they never edit a past year's enrollment.
 */
enum EnrollmentStatus: string
{
    case Active = 'active';
    case Promoted = 'promoted';
    case Detained = 'detained';
    case Left = 'left';
}
