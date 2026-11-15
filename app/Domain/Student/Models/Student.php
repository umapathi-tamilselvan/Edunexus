<?php

namespace App\Domain\Student\Models;

use App\Domain\Academic\Models\Section;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Student\Enums\Gender;
use App\Support\AcademicYearContext;
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
     * students enrolled, in the academic year (default: the request's
     * selected year), in sections the user is class teacher of, subject
     * teacher of, or teaches in the timetable.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user, ?int $academicYearId = null): void
    {
        if ($user->can(Permission::StudentView->value) || $user->can(Permission::StudentManage->value)) {
            return;
        }

        if (! $user->can(Permission::StudentViewAssigned->value)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $academicYearId ??= app(AcademicYearContext::class)->id();

        $sectionIds = Section::query()->taughtBy($user, $academicYearId)->select('sections.id');

        $query->whereHas('enrollments', fn (Builder $enrollments) => $enrollments
            ->where('academic_year_id', $academicYearId)
            ->whereIn('section_id', $sectionIds));
    }
}
