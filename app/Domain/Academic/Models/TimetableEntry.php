<?php

namespace App\Domain\Academic\Models;

use App\Domain\Academic\Enums\Weekday;
use App\Domain\Identity\Models\User;
use Database\Factories\TimetableEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a section studies, and with whom, in one period of one weekday. The
 * teacher is the section's subject teacher for the subject, and follows
 * it when it changes, unless someone else was set by hand (is_override).
 *
 * @property int $id
 * @property int $section_id
 * @property Weekday $weekday
 * @property int $period_id
 * @property int $subject_id
 * @property int|null $teacher_id
 * @property bool $is_override
 */
#[Fillable(['section_id', 'weekday', 'period_id', 'subject_id', 'teacher_id', 'is_override'])]
#[UseFactory(TimetableEntryFactory::class)]
class TimetableEntry extends Model
{
    /** @use HasFactory<TimetableEntryFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_override' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => Weekday::class,
            'is_override' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return BelongsTo<Period, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
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
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
