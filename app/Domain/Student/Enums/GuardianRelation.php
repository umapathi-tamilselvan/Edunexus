<?php

namespace App\Domain\Student\Enums;

enum GuardianRelation: string
{
    case Father = 'father';
    case Mother = 'mother';
    case Guardian = 'guardian';
    case Grandparent = 'grandparent';
    case Sibling = 'sibling';
    case Other = 'other';
}
