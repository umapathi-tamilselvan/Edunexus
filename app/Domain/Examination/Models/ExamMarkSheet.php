<?php

namespace App\Domain\Examination\Models;

use App\Domain\Academic\Models\Section;
use App\Domain\Academic\Models\Subject;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The marks of one subject in one classroom for one exam have been
 * submitted: by whom they were last entered, and when they were first
 * submitted. Written by RecordExamMarks.
 *
 * @property int $id
 * @property int $exam_id
 * @property int $section_id
 * @property int $subject_id
 * @property int|null $entered_by
 * @property Carbon $submitted_at
 */
#[Fillable(['exam_id', 'section_id', 'subject_id', 'entered_by', 'submitted_at'])]
class ExamMarkSheet extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
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
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
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
    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}
