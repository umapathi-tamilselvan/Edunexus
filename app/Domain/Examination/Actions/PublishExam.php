<?php

namespace App\Domain\Examination\Actions;

use App\Domain\Examination\Models\Exam;

/**
 * Publishes an exam's marks: from then on they cannot be entered or changed,
 * nor the exam's maximum marks, until someone with exam.unlock reopens it.
 * Publishing an exam that is already published changes nothing.
 */
class PublishExam
{
    public function handle(Exam $exam): Exam
    {
        if (! $exam->isPublished()) {
            $exam->forceFill(['published_at' => now()])->save();
        }

        return $exam;
    }
}
