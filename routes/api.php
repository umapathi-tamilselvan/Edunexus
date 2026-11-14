<?php

use App\Http\Controllers\Api\V1\Academic\AcademicYearController;
use App\Http\Controllers\Api\V1\Academic\ClassroomController;
use App\Http\Controllers\Api\V1\Academic\HolidayController;
use App\Http\Controllers\Api\V1\Academic\PeriodController;
use App\Http\Controllers\Api\V1\Academic\SchoolClassController;
use App\Http\Controllers\Api\V1\Academic\SectionController;
use App\Http\Controllers\Api\V1\Academic\SectionSubjectTeacherController;
use App\Http\Controllers\Api\V1\Academic\SubjectController;
use App\Http\Controllers\Api\V1\Academic\TeacherController;
use App\Http\Controllers\Api\V1\Academic\TimetableController;
use App\Http\Controllers\Api\V1\Admission\AdmissionController;
use App\Http\Controllers\Api\V1\Attendance\AttendanceController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\Auth\TokenController;
use App\Http\Controllers\Api\V1\BrandingController;
use App\Http\Controllers\Api\V1\Examination\ExamController;
use App\Http\Controllers\Api\V1\Examination\ExamMarkController;
use App\Http\Controllers\Api\V1\Examination\SubjectScoreController;
use App\Http\Controllers\Api\V1\Fee\FeeController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\Settings\ClientSettingsController;
use App\Http\Controllers\Api\V1\Student\EnrollmentController;
use App\Http\Controllers\Api\V1\Student\StudentController;
use App\Http\Controllers\Api\V1\Student\StudentGuardianController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (/api/v1)
|--------------------------------------------------------------------------
|
| Consumed by the Vue SPA (Sanctum cookie session) and the Flutter app
| (Sanctum bearer tokens). This deployment serves exactly one client.
|
*/

Route::get('branding', BrandingController::class)->name('branding');

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('login', [SessionController::class, 'store'])->middleware('throttle:login')->name('login');
    Route::post('tokens', [TokenController::class, 'store'])->middleware('throttle:login')->name('tokens.store');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [SessionController::class, 'destroy'])->name('logout');
        Route::delete('tokens/current', [TokenController::class, 'destroy'])->name('tokens.destroy');
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', MeController::class)->name('me');

    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::apiResource('users', UserController::class)->only(['index', 'store']);
    Route::put('users/{user}/roles', [UserController::class, 'updateRoles'])->name('users.roles.update');
    Route::put('users/{user}/subjects', [UserController::class, 'updateSubjects'])->name('users.subjects.update');
});

/*
| Academic structure: years, classes, sections, subjects and who teaches
| each subject to each section.
*/
Route::middleware(['auth:sanctum', 'entitled:academic'])->group(function () {
    Route::middleware('can:academic.view')->group(function () {
        Route::get('academic-years', [AcademicYearController::class, 'index'])->name('academic-years.index');
        Route::get('academic-years/{academicYear}', [AcademicYearController::class, 'show'])->name('academic-years.show');
        Route::get('academic-years/{academicYear}/classes', [SchoolClassController::class, 'index'])->name('classes.index');
        Route::get('sections/{section}', [SectionController::class, 'show'])->name('sections.show');
        Route::get('subjects', [SubjectController::class, 'index'])->name('subjects.index');
        Route::get('academic-years/{academicYear}/setup-status', [AcademicYearController::class, 'setupStatus'])->name('academic-years.setup-status');
        Route::get('academic-years/{academicYear}/periods', [PeriodController::class, 'index'])->name('periods.index');
        Route::get('sections/{section}/timetable', [TimetableController::class, 'section'])->name('timetable.section');
        Route::get('teachers', [TeacherController::class, 'index'])->name('teachers.index');
        Route::get('teachers/workload', [TeacherController::class, 'workload'])->name('teachers.workload');
        Route::get('academic-years/{academicYear}/holidays', [HolidayController::class, 'index'])->name('holidays.index');
        Route::get('classes/{schoolClass}/teacher-grid', [ClassroomController::class, 'teacherGrid'])->name('classes.teacher-grid.show');
    });

    Route::get('me/timetable', [TimetableController::class, 'mine'])->name('timetable.mine');
    Route::get('me/teaching', [SectionSubjectTeacherController::class, 'mine'])->name('teaching.mine');

    Route::middleware('can:academic.manage')->group(function () {
        Route::post('academic-years/next', [AcademicYearController::class, 'next'])->name('academic-years.next');
        Route::post('academic-years/{academicYear}/make-current', [AcademicYearController::class, 'makeCurrent'])->name('academic-years.make-current');

        Route::post('academic-years/{academicYear}/classes', [SchoolClassController::class, 'store'])->name('classes.store');
        Route::patch('classes/{schoolClass}', [SchoolClassController::class, 'update'])->name('classes.update');
        Route::delete('classes/{schoolClass}', [SchoolClassController::class, 'destroy'])->name('classes.destroy');
        Route::put('classes/{schoolClass}/subjects', [SchoolClassController::class, 'syncSubjects'])->name('classes.subjects.sync');
        Route::put('classes/{schoolClass}/subjects/{subject}/teacher', [SectionSubjectTeacherController::class, 'updateClass'])->name('classes.subjects.teacher.update');
        Route::post('classes/{schoolClass}/classrooms', [ClassroomController::class, 'store'])->name('classes.classrooms.store');
        Route::put('classes/{schoolClass}/teacher-grid', [ClassroomController::class, 'saveTeacherGrid'])->name('classes.teacher-grid.update');

        Route::post('classes/{schoolClass}/sections', [SectionController::class, 'store'])->name('sections.store');
        Route::patch('sections/{section}', [SectionController::class, 'update'])->name('sections.update');
        Route::delete('sections/{section}', [SectionController::class, 'destroy'])->name('sections.destroy');
        Route::put('sections/{section}/subjects/{subject}/teacher', [SectionSubjectTeacherController::class, 'update'])->name('sections.subjects.teacher.update');

        Route::post('subjects', [SubjectController::class, 'store'])->name('subjects.store');
        Route::patch('subjects/{subject}', [SubjectController::class, 'update'])->name('subjects.update');
        Route::put('subjects/{subject}/teachers', [SubjectController::class, 'syncTeachers'])->name('subjects.teachers.sync');

        Route::post('academic-years/{academicYear}/holidays', [HolidayController::class, 'store'])->name('holidays.store');
        Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');

        Route::post('academic-years/{academicYear}/periods', [PeriodController::class, 'store'])->name('periods.store');
        Route::patch('periods/{period}', [PeriodController::class, 'update'])->name('periods.update');
        Route::delete('periods/{period}', [PeriodController::class, 'destroy'])->name('periods.destroy');

        Route::put('sections/{section}/timetable/{weekday}/{period}', [TimetableController::class, 'set'])->whereNumber('weekday')->where('weekday', '[1-7]')->name('timetable.set');
        Route::delete('sections/{section}/timetable/{weekday}/{period}', [TimetableController::class, 'clear'])->where('weekday', '[1-7]')->name('timetable.clear');
    });
});

/*
| Students, guardians and enrollments. Viewing is scoped per user by
| StudentPolicy; changes need student.manage.
*/
Route::middleware(['auth:sanctum', 'entitled:student'])->group(function () {
    Route::get('students', [StudentController::class, 'index'])->name('students.index');
    Route::get('students/{student}', [StudentController::class, 'show'])->name('students.show');

    Route::middleware('can:student.manage')->group(function () {
        Route::post('students', [StudentController::class, 'store'])->name('students.store');
        Route::patch('students/{student}', [StudentController::class, 'update'])->name('students.update');
        Route::post('classes/{schoolClass}/students/distribute', [ClassroomController::class, 'distribute'])->name('classes.students.distribute');

        Route::post('students/{student}/guardians', [StudentGuardianController::class, 'store'])->name('students.guardians.store');
        Route::delete('students/{student}/guardians/{guardian}', [StudentGuardianController::class, 'destroy'])->name('students.guardians.destroy');

        Route::post('students/{student}/enrollments', [EnrollmentController::class, 'store'])->name('students.enrollments.store');
        Route::patch('enrollments/{enrollment}', [EnrollmentController::class, 'update'])->name('enrollments.update');
    });
});

/*
| Attendance: a section's sheet for one day. Its teachers mark it within
| the edit window; attendance.edit_late changes any day (AttendancePolicy).
*/
Route::middleware(['auth:sanctum', 'entitled:attendance'])->group(function () {
    Route::middleware('can:attendance.view')->group(function () {
        Route::get('sections/{section}/attendance', [AttendanceController::class, 'show'])->name('attendance.show');
        Route::get('students/{student}/attendance', [AttendanceController::class, 'student'])->middleware('entitled:student')->name('students.attendance');
        Route::get('attendance/day-summary', [AttendanceController::class, 'daySummary'])->name('attendance.day-summary');
    });

    Route::put('sections/{section}/attendance', [AttendanceController::class, 'update'])->name('attendance.update');
});

/*
| Client settings: what the admins may change (settings.manage).
*/
Route::middleware(['auth:sanctum', 'can:settings.manage'])->group(function () {
    Route::get('settings', [ClientSettingsController::class, 'index'])->name('settings.index');
    Route::put('settings', [ClientSettingsController::class, 'update'])->name('settings.update');
});

/*
| Fees: an append-only ledger per enrollment, in integer paise. Dues and
| payments are reversed, never changed; receipts are numbered without gaps.
*/
Route::middleware(['auth:sanctum', 'entitled:fee'])->group(function () {
    Route::middleware('can:fee.view')->group(function () {
        Route::get('fee-heads', [FeeController::class, 'heads'])->name('fee-heads.index');
        Route::get('students/{student}/fees', [FeeController::class, 'student'])->middleware('entitled:student')->name('students.fees');
        Route::get('sections/{section}/fees', [FeeController::class, 'section'])->name('sections.fees');
        Route::get('receipts/{receipt}', [FeeController::class, 'receipt'])->name('receipts.show');
    });

    Route::middleware('can:fee.collect')->group(function () {
        Route::post('fee-heads', [FeeController::class, 'storeHead'])->name('fee-heads.store');
        Route::post('fee-dues', [FeeController::class, 'postDues'])->name('fee-dues.store');
        Route::post('enrollments/{enrollment}/payments', [FeeController::class, 'collect'])->name('payments.store');
    });

    Route::post('fee-entries/{feeEntry}/reverse', [FeeController::class, 'reverse'])->middleware('can:fee.reverse')->name('fee-entries.reverse');
});

/*
| Admissions: enquiry → approved → admitted (or rejected / withdrawn).
| Applications hold children's and guardians' personal data, so every
| route needs admission.manage.
*/
Route::middleware(['auth:sanctum', 'entitled:admission', 'can:admission.manage'])->group(function () {
    Route::get('admissions', [AdmissionController::class, 'index'])->name('admissions.index');
    Route::post('admissions', [AdmissionController::class, 'store'])->name('admissions.store');
    Route::get('admissions/{admission}', [AdmissionController::class, 'show'])->name('admissions.show');
    Route::patch('admissions/{admission}', [AdmissionController::class, 'update'])->name('admissions.update');
    Route::post('admissions/{admission}/decision', [AdmissionController::class, 'decide'])->name('admissions.decide');
    Route::post('admissions/{admission}/admit', [AdmissionController::class, 'admit'])->name('admissions.admit');
});

/*
| Exams and marks. Marks are entered by anyone with exam.manage, or with
| exam.enter_marks by the section's subject teacher (ExamPolicy).
*/
Route::middleware(['auth:sanctum', 'entitled:examination'])->group(function () {
    Route::middleware('can:exam.view')->group(function () {
        Route::get('academic-years/{academicYear}/exams', [ExamController::class, 'index'])->name('exams.index');
        Route::get('exams/{exam}', [ExamController::class, 'show'])->name('exams.show');
        Route::get('exams/{exam}/marks', [ExamMarkController::class, 'index'])->name('exams.marks.index');
        Route::get('students/{student}/scores', SubjectScoreController::class)->middleware('entitled:student')->name('students.scores');
    });

    Route::put('exams/{exam}/marks', [ExamMarkController::class, 'update'])->name('exams.marks.update');

    Route::middleware('can:exam.manage')->group(function () {
        Route::post('academic-years/{academicYear}/exams', [ExamController::class, 'store'])->name('exams.store');
        Route::patch('exams/{exam}', [ExamController::class, 'update'])->name('exams.update');
        Route::delete('exams/{exam}', [ExamController::class, 'destroy'])->name('exams.destroy');
        Route::put('exams/{exam}/classes/{schoolClass}/subjects/{subject}/max-marks', [ExamController::class, 'setMaxMarks'])->name('exams.max-marks.update');
    });
});
