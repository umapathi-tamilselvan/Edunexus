<?php

namespace App\Http\Controllers\Api\V1\Fee;

use App\Domain\Academic\Models\Section;
use App\Domain\Fee\Actions\CollectPayment;
use App\Domain\Fee\Actions\FeeAccount;
use App\Domain\Fee\Actions\PostFeeDues;
use App\Domain\Fee\Actions\ReverseFeeEntry;
use App\Domain\Fee\Enums\PaymentMethod;
use App\Domain\Fee\Models\FeeEntry;
use App\Domain\Fee\Models\FeeHead;
use App\Domain\Fee\Models\FeeReceipt;
use App\Domain\Student\Enums\EnrollmentStatus;
use App\Domain\Student\Models\Student;
use App\Domain\Student\Models\StudentEnrollment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Fee\CollectPaymentRequest;
use App\Http\Requests\Api\V1\Fee\PostFeeDuesRequest;
use App\Support\AcademicYearContext;
use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Fee heads, dues, payments, receipts and reversals: an append-only ledger
 * per enrollment. Every amount is integer paise.
 */
class FeeController extends Controller
{
    public function __construct(private OrganizationContext $organization) {}

    public function heads(): JsonResponse
    {
        return response()->json(['data' => FeeHead::query()->orderBy('name')->get(['id', 'name'])]);
    }

    public function storeHead(Request $request): JsonResponse
    {
        $head = FeeHead::query()->create($request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('fee_heads', 'name')],
        ]));

        return response()->json(['data' => ['id' => $head->id, 'name' => $head->name]], 201);
    }

    /**
     * Charges one enrollment, a section's active students or a class's.
     */
    public function postDues(PostFeeDuesRequest $request, PostFeeDues $postFeeDues): JsonResponse
    {
        $enrollments = StudentEnrollment::query()
            ->when($request->validated('enrollment_id'), fn (Builder $query, int $id) => $query->whereKey($id))
            ->when($request->validated('section_id'), fn (Builder $query, int $id) => $query->where('section_id', $id)->where('status', EnrollmentStatus::Active))
            ->when($request->validated('class_id'), fn (Builder $query, int $id) => $query
                ->whereHas('section', fn (Builder $sections) => $sections->where('class_id', $id))
                ->where('status', EnrollmentStatus::Active));

        $charged = $postFeeDues->handle(
            FeeHead::query()->findOrFail($request->validated('fee_head_id')),
            $request->integer('amount'),
            Carbon::parse($request->validated('due_on')),
            $request->validated('description'),
            $enrollments,
            $request->user(),
        );

        return response()->json(['data' => ['charged' => $charged]], 201);
    }

    /**
     * A student's account in one year: the academic_year_id filter, else
     * the request's selected year.
     */
    public function student(Request $request, Student $student, AcademicYearContext $academicYear, FeeAccount $feeAccount): JsonResponse
    {
        Gate::authorize('view', $student);

        $yearId = $request->validate(['academic_year_id' => ['nullable', 'integer']])['academic_year_id'] ?? $academicYear->id();
        $enrollment = $student->enrollments()->with(['academicYear', 'section.schoolClass'])->where('academic_year_id', $yearId)->first();

        return response()->json([
            'data' => $enrollment === null ? null : [
                'enrollment_id' => $enrollment->id,
                'academic_year' => ['id' => $enrollment->academicYear->id, 'name' => $enrollment->academicYear->name],
                'section' => ['id' => $enrollment->section->id, 'name' => $enrollment->section->name, 'class' => $enrollment->section->schoolClass->name],
                ...$feeAccount->handle($enrollment),
            ],
        ]);
    }

    /**
     * Each of a section's students the user may see, in roll order, with
     * their balance. One query for the balances, whatever the section's size.
     */
    public function section(Request $request, Section $section): JsonResponse
    {
        $section->load('schoolClass');

        $enrollments = $section->enrollments()
            ->where('status', EnrollmentStatus::Active)
            ->whereHas('student', fn (Builder $students) => $students->visibleTo($request->user(), $section->schoolClass->academic_year_id))
            ->with('student:id,name,admission_no')
            ->withSum('feeEntries as balance', 'amount')
            ->orderBy('roll_no')
            ->get();

        return response()->json([
            'data' => [
                'section' => ['id' => $section->id, 'name' => $section->name, 'class' => $section->schoolClass->name],
                'total_balance' => (int) $enrollments->sum('balance'),
                'students' => $enrollments->map(fn (StudentEnrollment $enrollment): array => [
                    'enrollment_id' => $enrollment->id,
                    'roll_no' => $enrollment->roll_no,
                    'student' => ['id' => $enrollment->student->id, 'name' => $enrollment->student->name, 'admission_no' => $enrollment->student->admission_no],
                    'balance' => (int) $enrollment->balance,
                ]),
            ],
        ]);
    }

    public function collect(CollectPaymentRequest $request, StudentEnrollment $enrollment, CollectPayment $collectPayment): JsonResponse
    {
        Gate::authorize('view', $enrollment->student);

        $receipt = $collectPayment->handle(
            enrollment: $enrollment,
            amount: $request->integer('amount'),
            method: PaymentMethod::from($request->validated('method')),
            reference: $request->validated('reference'),
            paidOn: Carbon::parse($request->validated('paid_on')),
            receivedBy: $request->user(),
            idempotencyKey: $request->validated('idempotency_key'),
        );

        return response()->json(['data' => $this->presentReceipt($receipt)], $receipt->wasRecentlyCreated ? 201 : 200);
    }

    public function reverse(Request $request, FeeEntry $feeEntry, ReverseFeeEntry $reverseFeeEntry): JsonResponse
    {
        Gate::authorize('view', $feeEntry->enrollment->student);

        $reversal = $reverseFeeEntry->handle($feeEntry, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return response()->json(['data' => ['id' => $reversal->id, 'reverses_id' => $reversal->reverses_id, 'amount' => $reversal->amount]], 201);
    }

    /**
     * A receipt with what is printed on it.
     */
    public function receipt(FeeReceipt $receipt): JsonResponse
    {
        Gate::authorize('view', $receipt->enrollment->student);

        return response()->json(['data' => $this->presentReceipt($receipt)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentReceipt(FeeReceipt $receipt): array
    {
        $receipt->loadMissing(['enrollment.student', 'enrollment.academicYear', 'enrollment.section.schoolClass', 'receivedBy:id,name', 'payment.reversal']);

        return [
            'id' => $receipt->id,
            'receipt_no' => $receipt->receipt_no,
            'financial_year' => $receipt->financial_year,
            'institution' => $this->organization->name(),
            'amount' => $receipt->amount,
            'method' => $receipt->method->value,
            'method_label' => $receipt->method->label(),
            'reference' => $receipt->reference,
            'paid_on' => $receipt->paid_on->toDateString(),
            'received_by' => $receipt->receivedBy?->name,
            'reversed' => $receipt->payment?->reversal === null ? null : ['reason' => $receipt->payment->reversal->description, 'at' => $receipt->payment->reversal->created_at->toIso8601String()],
            'student' => ['id' => $receipt->enrollment->student->id, 'name' => $receipt->enrollment->student->name, 'admission_no' => $receipt->enrollment->student->admission_no],
            'enrollment' => [
                'id' => $receipt->enrollment->id,
                'academic_year' => $receipt->enrollment->academicYear->name,
                'class' => $receipt->enrollment->section->schoolClass->name,
                'section' => $receipt->enrollment->section->name,
                'roll_no' => $receipt->enrollment->roll_no,
            ],
        ];
    }
}
