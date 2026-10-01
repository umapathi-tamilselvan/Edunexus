<?php

namespace App\Domain\Student\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A guardian's consent to the institution processing a child's personal
 * data (DPDP Act), tied to the privacy notice version shown.
 *
 * @property int $id
 * @property int $guardian_id
 * @property int $student_id
 * @property string $notice_version
 * @property string $method
 * @property Carbon $consented_at
 * @property int|null $recorded_by
 * @property string|null $ip_address
 * @property Carbon|null $withdrawn_at
 */
#[Fillable(['guardian_id', 'student_id', 'notice_version', 'method', 'consented_at', 'recorded_by', 'ip_address'])]
class GuardianConsent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Guardian, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
