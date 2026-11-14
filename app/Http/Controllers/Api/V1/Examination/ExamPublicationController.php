<?php

namespace App\Http\Controllers\Api\V1\Examination;

use App\Domain\Examination\Actions\PublishExam;
use App\Domain\Examination\Actions\UnlockExam;
use App\Domain\Examination\Models\Exam;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ExamResource;

/**
 * Publishing an exam locks its marks; unlocking reopens them.
 */
class ExamPublicationController extends Controller
{
    public function publish(Exam $exam, PublishExam $publishExam): ExamResource
    {
        return new ExamResource($publishExam->handle($exam));
    }

    public function unlock(Exam $exam, UnlockExam $unlockExam): ExamResource
    {
        return new ExamResource($unlockExam->handle($exam));
    }
}
