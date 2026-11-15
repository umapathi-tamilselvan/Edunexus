<?php

namespace App\Domain\Attendance\Jobs;

use App\Domain\Academic\Models\Period;
use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Communication\Contracts\GuardianNotifier;
use App\Domain\Student\Models\StudentEnrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;

/**
 * Tells the primary guardian of each student newly marked absent. Dispatched
 * after an attendance save commits, on the critical queue, with the job
 * settings of §4.3.
 */
class AbsenceAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [5, 30];

    /**
     * @param  list<int>  $enrollmentIds
     */
    public function __construct(public array $enrollmentIds, public string $date, public int $period)
    {
        $this->onQueue('critical');
    }

    public function handle(GuardianNotifier $notifier): void
    {
        $when = $this->period === AttendanceRecord::DAY ? 'the whole day' : $this->periodLabel();

        $enrollments = StudentEnrollment::query()
            ->whereKey($this->enrollmentIds)
            ->with('student.guardians')
            ->get();

        foreach ($enrollments as $enrollment) {
            $guardian = $enrollment->student->guardians->firstWhere('pivot.is_primary', true);

            if ($guardian === null) {
                continue;
            }

            $notifier->absent($guardian, $enrollment->student, Carbon::parse($this->date), $when);
        }
    }

    private function periodLabel(): string
    {
        $period = Period::query()->find($this->period);

        if ($period === null) {
            return 'the period';
        }

        return $period->session ? ucfirst($period->session).' session' : $period->name;
    }
}
