<?php

namespace App\Domain\Student\Enums;

/**
 * How a guardian's consent was obtained.
 */
enum ConsentMethod: string
{
    case InPerson = 'in_person';
    case SignedForm = 'signed_form';
    case Digital = 'digital';
}
