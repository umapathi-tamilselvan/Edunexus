<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\Section;
use App\Domain\Admission\Enums\ApplicationStatus;
use App\Domain\Admission\Models\AdmissionApplication;
use App\Domain\Identity\Models\User;

/**
 * The office home (D43), for Admin and Super Admin: how far Year Setup has
 * got, the classrooms without a class teacher, and the admissions waiting.
 * Each block is shown only to a user who may see its data. Fee collection
 * joins once Finance exists.
 */
class OfficeHome
{
    public function __construct(private YearSetupStatus $yearSetupStatus) {}

    /**
     * @return array{academic_year: array{id: int, name: string}|null, year_setup: array<string, mixed>|null, classrooms_without_class_teacher: list<array{id: int, label: string}>, admissions: array<string, mixed>|null}
     */
    public function handle(User $user, ?AcademicYear $year): array
    {
        if ($year === null) {
            return ['academic_year' => null, 'year_setup' => null, 'classrooms_without_class_teacher' => [], 'admissions' => null];
        }

        $steps = $this->yearSetupStatus->handle($year);

        return [
            'academic_year' => ['id' => $year->id, 'name' => $year->name],
            'year_setup' => [
                'done' => collect($steps)->where('done', true)->count(),
                'total' => count($steps),
                'open_steps' => collect($steps)
                    ->where('done', false)
                    ->map(fn (array $step): array => ['key' => $step['key'], 'label' => $step['label'], 'detail' => $step['detail'], 'optional' => $step['optional'] ?? false])
                    ->values()
                    ->all(),
            ],
            'classrooms_without_class_teacher' => $user->can('academic.view') ? $this->classroomsWithoutTeacher($year) : [],
            'admissions' => $user->can('admission.manage') ? $this->admissions($year) : null,
        ];
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function classroomsWithoutTeacher(AcademicYear $year): array
    {
        return Section::query()
            ->whereHas('schoolClass', fn ($classes) => $classes->where('academic_year_id', $year->id))
            ->whereNull('class_teacher_id')
            ->with('schoolClass:id,name,sort_order')
            ->get()
            ->sortBy(fn (Section $section): string => sprintf('%05d%s%s', $section->schoolClass->sort_order, $section->schoolClass->name, $section->name))
            ->map(fn (Section $section): array => ['id' => $section->id, 'label' => "{$section->schoolClass->name} {$section->name}"])
            ->values()
            ->all();
    }

    /**
     * @return array{open: bool, pending: int, recent: list<array<string, mixed>>}
     */
    private function admissions(AcademicYear $year): array
    {
        $pending = AdmissionApplication::query()
            ->where('academic_year_id', $year->id)
            ->whereIn('status', [ApplicationStatus::Enquiry, ApplicationStatus::Approved]);

        return [
            'open' => $year->admissions_open,
            'pending' => (clone $pending)->count(),
            'recent' => (clone $pending)
                ->with('schoolClass:id,name')
                ->latest('id')
                ->limit(5)
                ->get(['id', 'application_no', 'student_name', 'class_id', 'status'])
                ->map(fn (AdmissionApplication $application): array => [
                    'id' => $application->id,
                    'application_no' => $application->application_no,
                    'student_name' => $application->student_name,
                    'class' => $application->schoolClass->name,
                    'status' => $application->status->value,
                ])
                ->values()
                ->all(),
        ];
    }
}
