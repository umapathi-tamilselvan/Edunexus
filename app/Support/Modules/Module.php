<?php

namespace App\Support\Modules;

/**
 * Feature modules. Which are enabled for a client comes from its
 * institution type and the overrides in its .env.
 */
enum Module: string
{
    case Academic = 'academic';
    case Admission = 'admission';
    case Student = 'student';
    case Staff = 'staff';
    case Attendance = 'attendance';
    case Examination = 'examination';
    case Fee = 'fee';
    case Communication = 'communication';
    case Report = 'report';
    case Transport = 'transport';
    case Library = 'library';
    case Payroll = 'payroll';
    case Inventory = 'inventory';
    case Hostel = 'hostel';
}
