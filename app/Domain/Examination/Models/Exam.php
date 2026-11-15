<?php

namespace App\Domain\Examination\Models;

use App\Domain\Academic\Models\AcademicYear;
use Database\Factories\ExamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An exam of one academic year, e.g. "Quarterly". Every subject is out of
 * max_marks unless an ExamPaper sets a class's subject differently.
 *
 * @property int $id
 * @property int $academic_year_id
 * @property string $name
 * @property int $max_marks
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property Carbon|null $published_at
 */
#[Fillable(['name', 'max_marks', 'starts_on', 'ends_on'])]
#[UseFactory(ExamFactory::class)]
class Exam extends Model
{
    /** @use HasFactory<ExamFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_marks' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'published_at' => 'datetime',
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
     * @return HasMany<ExamPaper, $this>
     */
    public function papers(): HasMany
    {
        return $this->hasMany(ExamPaper::class);
    }

    /**
     * @return HasMany<ExamMark, $this>
     */
    public function marks(): HasMany
    {
        return $this->hasMany(ExamMark::class);
    }

    /**
     * The maximum marks of a class's subject in this exam.
     */
    public function maxMarksFor(int $classId, int $subjectId): int
    {
        return $this->papers()->where('class_id', $classId)->where('subject_id', $subjectId)->value('max_marks') ?? $this->max_marks;
    }

    /**
     * Published exams are locked: no marks and no maximum marks change until
     * someone with exam.unlock reopens them.
     */
    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
