<?php

namespace App\Domain\Student\Models;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Section;
use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Examination\Models\ExamMark;
use App\Domain\Fee\Models\FeeEntry;
use App\Domain\Student\Enums\EnrollmentStatus;
use Database\Factories\StudentEnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * A student's place in one academic year: the anchor for attendance, marks
 * and fees. One per student per year.
 *
 * @property int $id
 * @property int $student_id
 * @property int $academic_year_id
 * @property int $section_id
 * @property int|null $roll_no
 * @property EnrollmentStatus $status
 */
#[Fillable(['student_id', 'academic_year_id', 'class_id', 'section_id', 'roll_no', 'status'])]
#[UseFactory(StudentEnrollmentFactory::class)]
class StudentEnrollment extends Model implements AuditableContract
{
    /** @use HasFactory<StudentEnrollmentFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'roll_no' => 'integer',
            'status' => EnrollmentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return HasMany<ExamMark, $this>
     */
    public function examMarks(): HasMany
    {
        return $this->hasMany(ExamMark::class, 'enrollment_id');
    }

    /**
     * @return HasMany<AttendanceRecord, $this>
     */
    public function attendance(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'enrollment_id');
    }

    /**
     * The enrollment's fee ledger; its balance is the sum of amounts.
     *
     * @return HasMany<FeeEntry, $this>
     */
    public function feeEntries(): HasMany
    {
        return $this->hasMany(FeeEntry::class, 'enrollment_id');
    }

    /**
     * The class the student is enrolled in. Set even before a section (D45).
     *
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}
