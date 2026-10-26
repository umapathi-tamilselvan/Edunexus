<?php

namespace App\Domain\Examination\Models;

use App\Domain\Academic\Models\Subject;
use App\Domain\Identity\Models\User;
use App\Domain\Student\Models\StudentEnrollment;
use Database\Factories\ExamMarkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * A student's marks in one subject of one exam, kept on their enrollment.
 * An absent student has no marks.
 *
 * @property int $id
 * @property int $exam_id
 * @property int $enrollment_id
 * @property int $subject_id
 * @property float|null $marks
 * @property bool $is_absent
 * @property int|null $recorded_by
 */
#[Fillable(['exam_id', 'enrollment_id', 'subject_id', 'marks', 'is_absent', 'recorded_by'])]
#[UseFactory(ExamMarkFactory::class)]
class ExamMark extends Model implements AuditableContract
{
    /** @use HasFactory<ExamMarkFactory> */
    use Auditable, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_absent' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'marks' => 'float',
            'is_absent' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Exam, $this>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * @return BelongsTo<StudentEnrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
