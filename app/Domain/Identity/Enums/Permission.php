<?php

namespace App\Domain\Identity\Enums;

/**
 * Permissions are fixed in code. Clients edit which roles hold them, never
 * the list itself.
 */
enum Permission: string
{
    case SettingsManage = 'settings.manage';
    case RoleManage = 'role.manage';
    case UserManage = 'user.manage';
    case AuditView = 'audit.view';

    case AcademicView = 'academic.view';
    case AcademicManage = 'academic.manage';

    case AdmissionManage = 'admission.manage';

    case StudentView = 'student.view';
    case StudentManage = 'student.manage';

    case StaffView = 'staff.view';
    case StaffManage = 'staff.manage';

    case AttendanceView = 'attendance.view';
    case AttendanceMark = 'attendance.mark';
    case AttendanceEditLate = 'attendance.edit_late';

    case ExamView = 'exam.view';
    case ExamEnterMarks = 'exam.enter_marks';
    case ExamPublish = 'exam.publish';
    case ExamUnlock = 'exam.unlock';

    case FeeView = 'fee.view';
    case FeeCollect = 'fee.collect';
    case FeeConcessionApprove = 'fee.concession_approve';
    case FeeReverse = 'fee.reverse';

    case CommunicationSend = 'communication.send';

    case ReportView = 'report.view';
}
