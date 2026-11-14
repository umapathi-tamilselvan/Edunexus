<?php

namespace App\Http\Controllers\Api\V1\Admission;

use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Section;
use App\Domain\Admission\Actions\AdmitApplicant;
use App\Domain\Admission\Actions\CreateApplication;
use App\Domain\Admission\Actions\DecideApplication;
use App\Domain\Admission\Enums\ApplicationStatus;
use App\Domain\Admission\Models\AdmissionApplication;
use App\Domain\Student\Models\Guardian;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admission\AdmitApplicantRequest;
use App\Http\Requests\Api\V1\Admission\DecideApplicationRequest;
use App\Http\Requests\Api\V1\Admission\StoreApplicationRequest;
use App\Http\Resources\Api\V1\AdmissionApplicationResource;
use App\Support\AcademicYearContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdmissionController extends Controller
{
    /**
     * Applications of a year (default: the request's selected year), newest
     * first. Filters: status, class_id, search (applicant name, application
     * no. or exact guardian phone).
     */
    public function index(Request $request, AcademicYearContext $academicYear): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'academic_year_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(ApplicationStatus::class)],
            'class_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $yearId = $filters['academic_year_id'] ?? $academicYear->id();

        $applications = AdmissionApplication::query()
            ->with(['academicYear', 'schoolClass'])
            ->where('academic_year_id', $yearId)
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['class_id'] ?? null, fn (Builder $query, int $classId) => $query->where('class_id', $classId))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('student_name', 'like', "%{$search}%")
                ->orWhere('application_no', 'like', "%{$search}%")
                ->orWhere('guardian_phone_hash', Guardian::hashPhone($search))))
            ->latest('id')
            ->paginate(50);

        return AdmissionApplicationResource::collection($applications);
    }

    public function store(StoreApplicationRequest $request, CreateApplication $createApplication): JsonResponse
    {
        $application = $createApplication->handle(
            SchoolClass::query()->with('academicYear')->findOrFail($request->validated('class_id')),
            $request->safe()->except('class_id'),
        );

        return (new AdmissionApplicationResource($application))->response()->setStatusCode(201);
    }

    public function show(AdmissionApplication $admission): AdmissionApplicationResource
    {
        return new AdmissionApplicationResource($admission->load(['academicYear', 'schoolClass', 'decidedBy', 'student']));
    }

    /**
     * Corrects an open application. A different class must be in the same
     * academic year.
     */
    public function update(StoreApplicationRequest $request, AdmissionApplication $admission): AdmissionApplicationResource
    {
        if (! $admission->status->isOpen()) {
            throw ValidationException::withMessages(['status' => "A {$admission->status->value} application can no longer be changed."]);
        }

        if ($request->has('class_id') && SchoolClass::query()->whereKey($request->validated('class_id'))->value('academic_year_id') !== $admission->academic_year_id) {
            throw ValidationException::withMessages(['class_id' => 'Choose a class in the same academic year as the application.']);
        }

        $admission->update($request->validated());

        return new AdmissionApplicationResource($admission->load(['academicYear', 'schoolClass']));
    }

    public function decide(DecideApplicationRequest $request, AdmissionApplication $admission, DecideApplication $decideApplication): AdmissionApplicationResource
    {
        return new AdmissionApplicationResource($decideApplication->handle(
            $admission,
            ApplicationStatus::from($request->validated('decision')),
            $request->user(),
            $request->validated('reason'),
        ));
    }

    public function admit(AdmitApplicantRequest $request, AdmissionApplication $admission, AdmitApplicant $admitApplicant): AdmissionApplicationResource
    {
        return new AdmissionApplicationResource($admitApplicant->handle(
            application: $admission,
            section: $request->validated('section_id') === null ? null : Section::query()->findOrFail($request->validated('section_id')),
            consentMethod: $request->validated('consent_method'),
            actor: $request->user(),
            admissionNo: $request->validated('admission_no'),
            rollNo: $request->validated('roll_no'),
            ipAddress: $request->ip(),
        ));
    }
}
