<?php

namespace App\Domain\Attendance\Models;

use App\Domain\Academic\Models\Section;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Who submitted a section's attendance sheet for a day and period, and when
 * (D41). Written by RecordAttendance on the first save of the sheet.
 *
 * @property int $id
 * @property int $section_id
 * @property Carbon $date
 * @property int $period
 * @property int|null $submitted_by
 * @property Carbon $submitted_at
 */
#[Fillable(['section_id', 'date', 'period', 'submitted_by', 'submitted_at'])]
class AttendanceSheet extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'period' => 'integer',
            'submitted_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
