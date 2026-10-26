<?php

namespace App\Domain\Identity\Models;

use App\Domain\Academic\Models\SectionSubjectTeacher;
use App\Domain\Academic\Models\Subject;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\Permission\Traits\HasRoles;

/**
 * A login in this client's database: staff, guardian or student.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property int|null $subject_id One of the subjects the user teaches. Kept in
 *                                step with subjects() only so the previous
 *                                release still works; dropped in v3.3.
 */
#[Fillable(['name', 'email', 'password', 'subject_id'])]
#[Hidden(['password', 'remember_token'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable implements AuditableContract
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Roles and permissions are registered under "web". Pinning it keeps
     * checks working for token requests too, where the active guard is
     * "sanctum".
     */
    protected string $guard_name = 'web';

    /**
     * @var list<string>
     */
    protected $auditExclude = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The subjects this user teaches; a teacher may teach several.
     *
     * @return BelongsToMany<Subject, $this>
     */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_teacher')->orderBy('name');
    }

    /**
     * The sections and subjects this user teaches.
     *
     * @return HasMany<SectionSubjectTeacher, $this>
     */
    public function subjectTeaching(): HasMany
    {
        return $this->hasMany(SectionSubjectTeacher::class, 'teacher_id');
    }
}
