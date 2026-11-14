<?php

namespace App\Domain\Fee\Actions;

use App\Domain\Fee\Enums\FeeEntryType;
use App\Domain\Fee\Models\FeeEntry;
use App\Domain\Fee\Models\FeeHead;
use App\Domain\Identity\Models\User;
use App\Domain\Student\Models\StudentEnrollment;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Posts the same due to many enrollments at once, e.g. Term 1 tuition of
 * ₹15,000 for every student of Class 5, due on 15 July: one insert however
 * many students.
 */
class PostFeeDues
{
    /**
     * @param  Builder<StudentEnrollment>  $enrollments  who is charged
     * @return int how many enrollments were charged
     */
    public function handle(FeeHead $head, int $amount, CarbonInterface $dueOn, ?string $description, Builder $enrollments, User $postedBy): int
    {
        $enrollmentIds = $enrollments->pluck('student_enrollments.id')->all();

        if ($enrollmentIds === []) {
            throw ValidationException::withMessages(['enrollments' => 'No enrolled students to charge.']);
        }

        $now = now();

        FeeEntry::query()->insert(array_map(fn (int $enrollmentId): array => [
            'enrollment_id' => $enrollmentId,
            'type' => FeeEntryType::Due->value,
            'fee_head_id' => $head->id,
            'amount' => $amount,
            'due_on' => $dueOn->toDateString(),
            'description' => $description,
            'receipt_id' => null,
            'reverses_id' => null,
            'recorded_by' => $postedBy->id,
            'created_at' => $now,
        ], $enrollmentIds));

        return count($enrollmentIds);
    }
}
