<?php

namespace App\Domain\Attendance\Models;

use App\Domain\Attendance\Enums\AttendanceStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Student\Models\StudentEnrollment;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * A student's attendance on one day of their enrollment (period 0; a
 * period number later, if attendance is taken per period). Sheets are
 * saved in bulk by RecordAttendance, which audits edits itself.
 *
 * @property int $id
 * @property int $enrollment_id
 * @property Carbon $date
 * @property int $period
 * @property AttendanceStatus $status
 * @property string|null $remark
 * @property int|null $marked_by
 */
#[Fillable(['enrollment_id', 'date', 'period', 'status', 'remark', 'marked_by'])]
class AttendanceRecord extends Model implements AuditableContract
{
    use Auditable;

    /**
     * The whole day, rather than one period of it.
     */
    public const DAY = 0;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'period' => self::DAY,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'period' => 'integer',
            'status' => AttendanceStatus::class,
        ];
    }

    /**
     * @return BelongsTo<StudentEnrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
