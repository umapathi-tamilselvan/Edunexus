<?php

namespace App\Domain\Academic\Models;

use App\Domain\Identity\Models\User;
use Database\Factories\SectionSubjectTeacherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Who teaches one subject to one section, e.g. Class 1 A Mathematics:
 * Satheesh. The teacher's own subject is always this subject.
 *
 * @property int $id
 * @property int $section_id
 * @property int $subject_id
 * @property int $teacher_id
 */
#[Fillable(['section_id', 'subject_id', 'teacher_id'])]
#[UseFactory(SectionSubjectTeacherFactory::class)]
class SectionSubjectTeacher extends Model
{
    /** @use HasFactory<SectionSubjectTeacherFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
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
