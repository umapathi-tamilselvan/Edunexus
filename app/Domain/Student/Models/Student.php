<?php

namespace App\Domain\Student\Models;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Student\Enums\Gender;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * A student's identity. Class and section live on the yearly enrollment,
 * never on the student.
 *
 * @property int $id
 * @property string $admission_no
 * @property string $name
 * @property Carbon $date_of_birth
 * @property Gender $gender
 * @property string|null $photo_path
 */
#[Fillable(['admission_no', 'name', 'date_of_birth', 'gender'])]
#[UseFactory(StudentFactory::class)]
class Student extends Model implements AuditableContract
{
    /** @use HasFactory<StudentFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'gender' => Gender::class,
        ];
    }

    /**
     * @return BelongsToMany<Guardian, $this>
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class)
            ->withPivot(['relation', 'is_primary'])
            ->withTimestamps()
            ->orderByPivot('is_primary', 'desc');
    }

    /**
     * @return HasMany<GuardianConsent, $this>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(GuardianConsent::class);
    }

    /**
     * @return HasMany<StudentEnrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    /**
     * Students the user may see: everyone with student.view, otherwise only
     * students in current-year sections the user is class teacher of,
     * subject teacher of, or teaches in the timetable.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->can(Permission::StudentView->value) || $user->can(Permission::StudentManage->value)) {
            return;
        }

        if (! $user->can(Permission::StudentViewAssigned->value)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('enrollments', function (Builder $enrollments) use ($user): void {
            $enrollments
                ->whereHas('academicYear', fn (Builder $year) => $year->where('is_current', true))
                ->whereHas('section', fn (Builder $section) => $section->where(fn (Builder $taught) => $taught
                    ->where('class_teacher_id', $user->id)
                    ->orWhereHas('subjectTeachers', fn (Builder $teachers) => $teachers->where('teacher_id', $user->id))
                    ->orWhereHas('timetableEntries', fn (Builder $entries) => $entries->where('teacher_id', $user->id))));
        });
    }
}
