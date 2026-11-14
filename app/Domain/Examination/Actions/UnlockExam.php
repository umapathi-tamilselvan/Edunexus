<?php

namespace App\Domain\Examination\Actions;

use App\Domain\Examination\Models\Exam;

/**
 * Reopens a published exam, so its marks and maximum marks can change again.
 * Unlocking an exam that is not published changes nothing.
 */
class UnlockExam
{
    public function handle(Exam $exam): Exam
    {
        if ($exam->isPublished()) {
            $exam->forceFill(['published_at' => null])->save();
        }

        return $exam;
    }
}
