<?php

namespace App\Domain\Academic\Models;

use Database\Factories\PeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One slot of a year's bell schedule, e.g. "Period 1, 08:45–09:30", or a
 * break that no timetable entry may use.
 *
 * @property int $id
 * @property int $academic_year_id
 * @property string $name
 * @property string $starts_at
 * @property string $ends_at
 * @property bool $is_break
 * @property int $sort_order
 */
#[Fillable(['name', 'starts_at', 'ends_at', 'is_break', 'sort_order'])]
#[UseFactory(PeriodFactory::class)]
class Period extends Model
{
    /** @use HasFactory<PeriodFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_break' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_break' => 'boolean',
            'sort_order' => 'integer',
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
     * @return HasMany<TimetableEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }
}
