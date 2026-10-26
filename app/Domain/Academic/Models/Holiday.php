<?php

namespace App\Domain\Academic\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A day of an academic year without classes, e.g. "Diwali". No
 * attendance is taken on it, so it never counts against a student.
 *
 * @property int $id
 * @property int $academic_year_id
 * @property Carbon $date
 * @property string $name
 */
#[Fillable(['date', 'name'])]
class Holiday extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
