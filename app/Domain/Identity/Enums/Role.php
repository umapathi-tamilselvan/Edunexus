<?php

namespace App\Domain\Identity\Enums;

/**
 * Roles seeded into every school at provisioning. Schools may change a
 * role's permissions afterwards; reseeding never overwrites those edits.
 *
 * Super Admin is the school's owner account, created with the school. It
 * always holds every permission, only a Super Admin can grant it, and a
 * school can never lose its last one.
 */
enum Role: string
{
    case SuperAdmin = 'Super Admin';
    case Admin = 'Admin';
    case Principal = 'Principal';
    case Teacher = 'Teacher';
    case Accountant = 'Accountant';
    case Guardian = 'Guardian';
    case Student = 'Student';

    /**
     * Roles whose permissions are reset to the full set on every sync.
     */
    public function alwaysHoldsEveryPermission(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Admin], true);
    }

    /**
     * Permissions the role starts with.
     *
     * @return list<Permission>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::SuperAdmin, self::Admin => Permission::cases(),
            self::Principal => array_values(array_filter(
                Permission::cases(),
                fn (Permission $permission): bool => ! in_array($permission, [Permission::RoleManage, Permission::SettingsManage], true),
            )),
            self::Teacher => [
                Permission::AcademicView,
                Permission::StudentViewAssigned,
                Permission::AttendanceView,
                Permission::AttendanceMark,
                Permission::ExamView,
                Permission::ExamEnterMarks,
            ],
            self::Accountant => [
                // To work through fees by class and section.
                Permission::AcademicView,
                Permission::StudentView,
                Permission::FeeView,
                Permission::FeeCollect,
                Permission::FeeReverse,
                Permission::ReportView,
            ],
            self::Guardian, self::Student => [],
        };
    }
}
