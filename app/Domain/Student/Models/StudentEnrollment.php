<?php

namespace App\Domain\Student\Models;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\Section;
use App\Domain\Student\Enums\EnrollmentStatus;
use Database\Factories\StudentEnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
#[Fillable(['student_id', 'academic_year_id', 'section_id', 'roll_no', 'status'])]
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
}
