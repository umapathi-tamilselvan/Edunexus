<?php

namespace App\Domain\Examination\Models;

use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Subject;
use Database\Factories\ExamPaperFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A class's subject in an exam whose maximum marks differ from the exam's,
 * e.g. Quarterly, Class 1 Computer Science: out of 50.
 *
 * @property int $id
 * @property int $exam_id
 * @property int $class_id
 * @property int $subject_id
 * @property int $max_marks
 */
#[Fillable(['exam_id', 'class_id', 'subject_id', 'max_marks'])]
#[UseFactory(ExamPaperFactory::class)]
class ExamPaper extends Model
{
    /** @use HasFactory<ExamPaperFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_marks' => 'integer',
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
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
