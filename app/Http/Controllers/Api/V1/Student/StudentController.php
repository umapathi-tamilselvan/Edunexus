<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Section;
use App\Domain\Student\Actions\CreateStudent;
use App\Domain\Student\Enums\Gender;
use App\Domain\Student\Models\Student;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Student\StoreStudentRequest;
use App\Http\Requests\Api\V1\Student\UpdateStudentRequest;
use App\Http\Resources\Api\V1\StudentResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class StudentController extends Controller
{
    /**
     * Students the user may see, with their enrollment in the selected year.
     * The year is academic_year_id, else the filtered section's or class's
     * year, else the current year. Filters: academic_year_id, class_id,
     * section_id, search (name or admission number).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Student::class);

        $filters = $request->validate([
            'academic_year_id' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $yearId = $filters['academic_year_id']
            ?? (isset($filters['section_id']) ? Section::query()->join('classes', 'classes.id', '=', 'sections.class_id')->whereKey($filters['section_id'])->value('classes.academic_year_id') : null)
            ?? (isset($filters['class_id']) ? SchoolClass::query()->whereKey($filters['class_id'])->value('academic_year_id') : null)
            ?? AcademicYear::query()->current()->value('id');

        $students = Student::query()
            ->visibleTo($request->user())
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query->where('name', 'like', "%{$search}%")->orWhere('admission_no', 'like', "{$search}%"),
            ))
            ->when($filters['section_id'] ?? $filters['class_id'] ?? null, fn (Builder $query) => $query->whereHas('enrollments', fn (Builder $enrollments) => $enrollments
                ->where('academic_year_id', $yearId)
                ->when($filters['section_id'] ?? null, fn (Builder $query, int $sectionId) => $query->where('section_id', $sectionId))
                ->when($filters['class_id'] ?? null, fn (Builder $query, int $classId) => $query->whereHas('section', fn (Builder $section) => $section->where('class_id', $classId)))))
            ->with(['enrollments' => fn ($enrollments) => $enrollments->where('academic_year_id', $yearId)->with(['academicYear', 'section.schoolClass'])])
            ->orderBy('name')
            ->paginate(50);

        return StudentResource::collection($students);
    }

    public function store(StoreStudentRequest $request, CreateStudent $createStudent): JsonResponse
    {
        $student = $createStudent->handle(
            admissionNo: $request->validated('admission_no'),
            name: $request->validated('name'),
            dateOfBirth: Carbon::parse($request->validated('date_of_birth')),
            gender: Gender::from($request->validated('gender')),
            guardians: $request->validated('guardians'),
            enrollment: $request->validated('enrollment'),
        );

        return (new StudentResource($student))->response()->setStatusCode(201);
    }

    public function show(Student $student): StudentResource
    {
        Gate::authorize('view', $student);

        return new StudentResource($student->load([
            'guardians',
            'enrollments' => fn ($enrollments) => $enrollments->with(['academicYear', 'section.schoolClass'])->latest('id'),
        ]));
    }

    public function update(UpdateStudentRequest $request, Student $student): StudentResource
    {
        $student->update($request->validated());

        return new StudentResource($student);
    }
}
