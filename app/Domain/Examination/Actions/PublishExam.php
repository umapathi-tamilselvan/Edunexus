<?php

namespace App\Domain\Examination\Actions;

use App\Domain\Examination\Models\Exam;
use App\Domain\Identity\Models\User;

/**
 * Publishes an exam's marks, recording who did it (D46): from then on they cannot be entered or changed,
 * nor the exam's maximum marks, until someone with exam.unlock reopens it.
 * Publishing an exam that is already published changes nothing.
 */
class PublishExam
{
    public function handle(Exam $exam, User $publishedBy): Exam
    {
        if (! $exam->isPublished()) {
            $exam->forceFill(['published_at' => now(), 'published_by' => $publishedBy->id])->save();
        }

        return $exam;
    }
}
