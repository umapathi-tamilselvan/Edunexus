<?php

namespace Tests\Feature\Fee;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Section;
use App\Domain\Fee\Enums\FeeEntryType;
use App\Domain\Fee\Models\FeeEntry;
use App\Domain\Fee\Models\FeeHead;
use App\Domain\Fee\Models\FeeReceipt;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Student\Enums\EnrollmentStatus;
use App\Domain\Student\Models\Student;
use App\Domain\Student\Models\StudentEnrollment;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use LogicException;
use Tests\TestCase;

/**
 * The fee ledger: heads, dues, payments with gapless receipts, reversals
 * and balances. Every amount is integer paise.
 */
class FeeTest extends TestCase
{
    private User $accountant;

    private string $accountantToken;

    private string $adminToken;

    private AcademicYear $year;

    private Section $section;

    private FeeHead $tuition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountant = $this->createUser(Role::Accountant);
        $this->accountantToken = $this->issueToken($this->accountant->email);
        $this->adminToken = $this->issueToken($this->createUser(Role::Admin)->email);
        $this->year = AcademicYear::factory()->current()->create([
            'starts_on' => today()->subMonths(3)->startOfMonth(),
            'ends_on' => today()->addMonths(3)->endOfMonth(),
        ]);
        $this->section = Section::factory()->for(SchoolClass::factory()->for($this->year)->create(['name' => 'Class 5']), 'schoolClass')->create(['name' => 'A']);
        $this->tuition = FeeHead::query()->create(['name' => 'Tuition']);
    }

    private function enroll(string $name, int $rollNo, EnrollmentStatus $status = EnrollmentStatus::Active): StudentEnrollment
    {
        return StudentEnrollment::factory()
            ->for(Student::factory()->state(['name' => $name]))
            ->create(['section_id' => $this->section->id, 'roll_no' => $rollNo, 'status' => $status]);
    }

    /**
     * @param  array<string, mixed>  $target
     */
    private function postDues(array $target, int $amount, string $dueOn = '2026-01-10'): TestResponse
    {
        return $this->withToken($this->accountantToken)->postJson('/api/v1/fee-dues', [
            'fee_head_id' => $this->tuition->id,
            'amount' => $amount,
            'due_on' => $dueOn,
            ...$target,
        ]);
    }

    /**
     * @return array{receipt_no: string, id: int}
     */
    private function collect(StudentEnrollment $enrollment, int $amount, array $overrides = []): array
    {
        return $this->withToken($this->accountantToken)->postJson("/api/v1/enrollments/{$enrollment->id}/payments", [
            'amount' => $amount,
            'method' => 'cash',
            'paid_on' => today()->toDateString(),
            ...$overrides,
        ])->assertCreated()->json('data');
    }

    private function balance(StudentEnrollment $enrollment): int
    {
        return (int) FeeEntry::query()->where('enrollment_id', $enrollment->id)->sum('amount');
    }

    public function test_fee_heads_are_listed_and_names_are_unique(): void
    {
        $this->withToken($this->accountantToken)->getJson('/api/v1/fee-heads')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Tuition');

        $this->withToken($this->adminToken)->postJson('/api/v1/fee-heads', ['name' => 'Transport'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Transport');

        $this->withToken($this->adminToken)->postJson('/api/v1/fee-heads', ['name' => 'Tuition'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_a_teacher_cannot_see_or_collect_fees(): void
    {
        $anu = $this->enroll('Anu', rollNo: 1);
        $teacher = $this->issueToken($this->createUser(Role::Teacher)->email);

        $this->withToken($teacher)->getJson('/api/v1/fee-heads')->assertForbidden();
        $this->withToken($teacher)->postJson("/api/v1/enrollments/{$anu->id}/payments", [
            'amount' => 100, 'method' => 'cash', 'paid_on' => today()->toDateString(),
        ])->assertForbidden();
    }

    public function test_dues_post_to_the_active_students_of_a_section_only(): void
    {
        $this->enroll('Anu', rollNo: 1);
        $this->enroll('Bala', rollNo: 2);
        $this->enroll('Chitra', rollNo: 3);
        $this->enroll('Dev', rollNo: 4, status: EnrollmentStatus::Left);

        $this->postDues(['section_id' => $this->section->id], 1500000)
            ->assertCreated()
            ->assertJsonPath('data.charged', 3);

        $this->assertSame(3, FeeEntry::query()->where('type', FeeEntryType::Due)->count());
        $this->assertSame(0, FeeEntry::query()->whereHas('enrollment', fn ($e) => $e->where('status', EnrollmentStatus::Left))->count());
    }

    public function test_dues_post_to_a_whole_class_and_to_one_enrollment(): void
    {
        $anu = $this->enroll('Anu', rollNo: 1);
        $this->enroll('Bala', rollNo: 2);

        $this->postDues(['class_id' => $this->section->schoolClass->id], 500000)
            ->assertCreated()
            ->assertJsonPath('data.charged', 2);

        $this->postDues(['enrollment_id' => $anu->id], 20000)
            ->assertCreated()
            ->assertJsonPath('data.charged', 1);

        $this->assertSame(520000, $this->balance($anu));
    }

    public function test_dues_need_exactly_one_target_and_someone_to_charge(): void
    {
        $this->postDues([], 100)->assertUnprocessable()->assertJsonValidationErrors(['enrollment_id', 'section_id', 'class_id']);

        $this->postDues(['section_id' => $this->section->id], 100)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['enrollments' => 'No enrolled students to charge.']);
    }

    public function test_a_students_account_shows_dues_payments_and_what_is_overdue(): void
    {
        $anu = $this->enroll('Anu', rollNo: 1);
        $this->postDues(['enrollment_id' => $anu->id], 1500000, '2026-01-10');
        $this->collect($anu, 500000);

        $this->withToken($this->accountantToken)->getJson("/api/v1/students/{$anu->student_id}/fees")
            ->assertOk()
            ->assertJsonPath('data.balance', 1000000)
            ->assertJsonPath('data.total_due', 1500000)
            ->assertJsonPath('data.total_paid', 500000)
            ->assertJsonPath('data.advance', 0)
            ->assertJsonPath('data.overdue', 1000000)
            ->assertJsonPath('data.dues.0.fee_head', 'Tuition')
            ->assertJsonPath('data.dues.0.outstanding', 1000000)
            ->assertJsonPath('data.dues.0.is_overdue', true);
    }

    public function test_a_payment_goes_to_the_oldest_due_first(): void
    {
        $anu = $this->enroll('Anu', rollNo: 1);
        $this->postDues(['enrollment_id' => $anu->id], 500000, '2026-03-10');
        $this->postDues(['enrollment_id' => $anu->id], 1000000, '2026-01-10');

        $this->collect($anu, 600000);

        $account = $this->withToken($this->accountantToken)->getJson("/api/v1/students/{$anu->student_id}/fees")->json('data');
        $this->assertSame([[1000000, 400000], [500000, 500000]], array_map(fn (array $due): array => [$due['amount'], $due['outstanding']], $account['dues']));
        $this->assertSame(900000, $account['balance']);
    }

    public function test_a_payment_above_the_dues_is_kept_as_advance(): void
    {
        $anu = $this->enroll('Anu', rollNo: 1);
        $this->postDues(['enrollment_id' => $anu->id], 100000);

        $this->collect($anu, 150000);

        $this->withToken($this->accountantToken)->getJson("/api/v1/students/{$anu->student_id}/fees")
            ->assertJsonPath('data.balance', -50000)
            ->assertJsonPath('data.advance', 50000);
    }

    public function test_receipts_are_numbered_without_gaps_per_financial_year(): void
    {
        $anu = $this->enroll('Anu', rollNo: 1);
        $this->postDues(['enrollment_id' => $anu->id], 300000);
        $financialYear = FeeReceipt::financialYearOf(today());
        $prefix = config('edunexus.receipt_prefix');

        $first = $this->collect($anu, 100000);
        $second = $this->collect($anu, 100000);

        $this->assertSame("{$prefix}/{$financialYear}/00001", $first['receipt_no']);
        $this->assertSame("{$prefix}/{$financialYear}/00002", $second['receipt_no']);

        $this->withToken($this->accountantToken)->getJson("/api/v1/receipts/{$first['id']}")
            ->assertOk()
            ->assertJsonPath('data.amount', 100000)
            ->assertJsonPath('data.method', 'cash')
            ->assertJsonPath('data.student.name', 'Anu')
            ->assertJsonPath('data.reversed', null);
    }

    public function test_a_future_payment_is_refused(): void
    {
        $anu = $this->enroll('Anu', rollNo: 1);

        $this->withToken($this->accountantToken)->postJson("/api/v1/enrollments/{$anu->id}/payments", [
            'amount' => 100, 'method' => 'cash', 'paid_on' => today()->addDay()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['paid_on' => 'A payment cannot be dated in the future.']);
    }

    public function test_a_cheque_or_upi_payment_needs_its_reference(): void
    {
        $anu = $this->enroll('Anu', rollNo: 1);

        $this->withToken($this->accountantToken)->postJson("/api/v1/enrollments/{$anu->id}/payments", [
            'amount' => 100, 'method' => 'cheque', 'paid_on' => today()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['reference' => 'Enter the cheque number or transaction reference.']);

        $this->collect($anu, 100, ['method' => 'cheque', 'reference' => '000123']);
    }

    public function test_the_same_idempotency_key_takes_the_money_once(): void
    {
        $anu = $this->enroll('Anu', rollNo: 1);
        $this->postDues(['enrollment_id' => $anu->id], 300000);
        $key = Str::uuid()->toString();

        $first = $this->collect($anu, 100000, ['idempotency_key' => $key]);

        $this->withToken($this->accountantToken)->postJson("/api/v1/enrollments/{$anu->id}/payments", [
            'amount' => 100000, 'method' => 'cash', 'paid_on' => today()->toDateString(), 'idempotency_key' => $key,
        ])->assertOk()->assertJsonPath('data.id', $first['id']);

        $this->assertSame(200000, $this->balance($anu));
        $this->assertSame(1, FeeReceipt::query()->count());
    }

    public function test_reusing_an_idempotency_key_for_a_different_payment_is_refused(): void
    {
        $anu = $this->enroll('Anu', rollNo: 1);
        $this->postDues(['enrollment_id' => $anu->id], 300000);
        $key = Str::uuid()->toString();
        $this->collect($anu, 100000, ['idempotency_key' => $key]);

        $this->withToken($this->accountantToken)->postJson("/api/v1/enrollments/{$anu->id}/payments", [
            'amount' => 200000, 'method' => 'cash', 'paid_on' => today()->toDateString(), 'idempotency_key' => $key,
        ])->assertUnprocessable()->assertJsonValidationErrors(['idempotency_key']);
    }

    public function test_a_payment_can_be_reversed_once_and_keeps_its_receipt(): void
    {
        $anu = $this->enroll('Anu', rollNo: 1);
        $this->postDues(['enrollment_id' => $anu->id], 300000);
        $payment = $this->collect($anu, 100000);
        $paymentEntry = FeeEntry::query()->where('receipt_id', $payment['id'])->sole();

        $this->withToken($this->accountantToken)->postJson("/api/v1/fee-entries/{$paymentEntry->id}/reverse", ['reason' => 'Cheque bounced'])
            ->assertCreated()
            ->assertJsonPath('data.reverses_id', $paymentEntry->id)
            ->assertJsonPath('data.amount', 100000);

        $this->assertSame(300000, $this->balance($anu));

        $this->withToken($this->accountantToken)->getJson("/api/v1/receipts/{$payment['id']}")
            ->assertOk()
            ->assertJsonPath('data.reversed.reason', 'Cheque bounced');

        $this->withToken($this->accountantToken)->postJson("/api/v1/fee-entries/{$paymentEntry->id}/reverse", ['reason' => 'Again'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['entry' => 'This entry has already been reversed.']);
    }

    public function test_a_reversal_cannot_itself_be_reversed(): void
    {
        $anu = $this->enroll('Anu', rollNo: 1);
        $this->postDues(['enrollment_id' => $anu->id], 300000);
        $due = FeeEntry::query()->where('enrollment_id', $anu->id)->sole();

        $reversal = $this->withToken($this->accountantToken)->postJson("/api/v1/fee-entries/{$due->id}/reverse", ['reason' => 'Posted by mistake'])
            ->assertCreated()->json('data');

        $this->assertSame(0, $this->balance($anu));

        $this->withToken($this->accountantToken)->postJson("/api/v1/fee-entries/{$reversal['id']}/reverse", ['reason' => 'Undo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['entry' => 'A reversal cannot be reversed. Post the due or payment again instead.']);
    }

    public function test_a_reversal_needs_a_reason(): void
    {
        $anu = $this->enroll('Anu', rollNo: 1);
        $this->postDues(['enrollment_id' => $anu->id], 300000);
        $due = FeeEntry::query()->where('enrollment_id', $anu->id)->sole();

        $this->withToken($this->accountantToken)->postJson("/api/v1/fee-entries/{$due->id}/reverse", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_a_section_shows_each_active_students_balance_in_roll_order(): void
    {
        $bala = $this->enroll('Bala', rollNo: 2);
        $anu = $this->enroll('Anu', rollNo: 1);
        $this->enroll('Dev', rollNo: 3, status: EnrollmentStatus::Left);
        $this->postDues(['enrollment_id' => $bala->id], 200000);
        $this->postDues(['enrollment_id' => $anu->id], 100000);
        $this->collect($bala, 50000);

        $this->withToken($this->accountantToken)->getJson("/api/v1/sections/{$this->section->id}/fees")
            ->assertOk()
            ->assertJsonPath('data.total_balance', 250000)
            ->assertJsonCount(2, 'data.students')
            ->assertJsonPath('data.students.0.student.name', 'Anu')
            ->assertJsonPath('data.students.0.balance', 100000)
            ->assertJsonPath('data.students.1.student.name', 'Bala')
            ->assertJsonPath('data.students.1.balance', 150000);
    }

    public function test_the_ledger_cannot_be_changed_or_deleted(): void
    {
        $anu = $this->enroll('Anu', rollNo: 1);
        $this->postDues(['enrollment_id' => $anu->id], 300000);
        $due = FeeEntry::query()->where('enrollment_id', $anu->id)->sole();

        $this->expectException(LogicException::class);
        $due->update(['amount' => 1]);
    }
}
