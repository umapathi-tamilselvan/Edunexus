<?php

namespace App\Domain\Admission\Models;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Admission\Enums\ApplicationStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Student\Enums\Gender;
use App\Domain\Student\Enums\GuardianRelation;
use App\Domain\Student\Models\Guardian;
use App\Domain\Student\Models\Student;
use Database\Factories\AdmissionApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * An enquiry or application for admission into a class of a year. When
 * admitted it points at the student it created.
 *
 * @property int $id
 * @property string $application_no
 * @property int $academic_year_id
 * @property int $class_id
 * @property ApplicationStatus $status
 * @property string $student_name
 * @property Carbon $date_of_birth
 * @property Gender $gender
 * @property string|null $previous_school
 * @property string $guardian_name
 * @property string $guardian_phone
 * @property string|null $guardian_email
 * @property GuardianRelation $guardian_relation
 * @property string|null $notes
 * @property string|null $decision_reason
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property int|null $student_id
 */
#[Fillable(['class_id', 'student_name', 'date_of_birth', 'gender', 'previous_school', 'guardian_name', 'guardian_phone', 'guardian_email', 'guardian_relation', 'notes'])]
#[Hidden(['guardian_phone_hash'])]
#[UseFactory(AdmissionApplicationFactory::class)]
class AdmissionApplication extends Model implements AuditableContract
{
    /** @use HasFactory<AdmissionApplicationFactory> */
    use Auditable, HasFactory;

    /**
     * @var list<string>
     */
    protected $auditExclude = ['guardian_phone', 'guardian_phone_hash'];

    protected static function booted(): void
    {
        static::saving(function (self $application): void {
            $application->guardian_phone = Guardian::normalisePhone($application->guardian_phone);
            $application->guardian_phone_hash = Guardian::hashPhone($application->guardian_phone);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'date_of_birth' => 'date',
            'gender' => Gender::class,
            'guardian_phone' => 'encrypted',
            'guardian_relation' => GuardianRelation::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
