<?php

use App\Enums\AbsPayrollStatus;
use App\Models\AbsAttendance;
use App\Models\AbsBonus;
use App\Models\AbsDeduction;
use App\Models\AbsEmployeeProfile;
use App\Models\AbsPayrollPeriod;
use App\Models\Position;
use App\Models\AbsShift;
use App\Models\Company;
use App\Models\SubCompany;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->admin = User::factory()->admin()->create([
        'company_id' => $this->company->id,
        'is_active' => true,
    ]);

    User::$skipSubCompanyAutoCreate = true;

    $this->mandor = User::factory()->mandor()->create([
        'company_id' => $this->company->id,
        'is_active' => true,
    ]);

    $this->subCompany = SubCompany::create([
        'name' => 'Cabang Test',
        'code' => 'TST-01',
        'address' => 'Jl. Test',
        'latitude' => -6.8266492915813215,
        'longitude' => 107.14791799479002,
        'radius_meter' => 500,
        'is_active' => true,
        'mandor_id' => $this->mandor->id,
        'company_id' => $this->company->id,
    ]);
    User::$skipSubCompanyAutoCreate = false;

    $this->position = Position::create([
        'name' => 'Operator',
        'daily_rate' => 120000,
        'company_id' => $this->company->id,
    ]);

    $this->shift = AbsShift::create([
        'name' => 'Shift Pagi',
        'start_time' => '08:00:00',
        'end_time' => '17:00:00',
        'company_id' => $this->company->id,
    ]);

    $this->employee = User::factory()->karyawan()->create([
        'company_id' => $this->company->id,
        'is_active' => true,
    ]);

    AbsEmployeeProfile::where('user_id', $this->employee->id)->update([
        'position_id' => $this->position->id,
        'sub_company_id' => $this->subCompany->id,
        'abs_shift_id' => $this->shift->id,
    ]);
});

it('generates payroll for the current period', function () {
    $now = Carbon::now();
    $month = (int) $now->month;
    $year = (int) $now->year;
    $daysInMonth = min((int) $now->day, 25);

    for ($day = 1; $day <= $daysInMonth; $day++) {
        AbsAttendance::factory()->create([
            'user_id' => $this->employee->id,
            'sub_company_id' => $this->subCompany->id,
            'abs_shift_id' => $this->shift->id,
            'company_id' => $this->company->id,
            'date' => Carbon::create($year, $month, $day)->toDateString(),
            'status' => 'hadir',
        ]);
    }

    $this->actingAs($this->admin)
        ->postJson('/api/v1/abs/payrolls/generate', [
            'month' => $month,
            'year' => $year,
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['generated_count']]);
});

it('lists payroll periods', function () {
    AbsPayrollPeriod::factory()->count(3)->create([
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/abs/payrolls')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data');
});

it('shows payroll detail with deductions and bonuses', function () {
    $period = AbsPayrollPeriod::factory()->create([
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);

    AbsDeduction::factory()->count(2)->create([
        'abs_payroll_period_id' => $period->id,
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);

    AbsBonus::factory()->create([
        'abs_payroll_period_id' => $period->id,
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/abs/payrolls/' . $period->ulid)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data.deductions')
        ->assertJsonCount(1, 'data.bonuses');
});

it('owner can view payrolls', function () {
    $owner = User::factory()->owner()->create(['company_id' => $this->company->id]);
    AbsPayrollPeriod::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($owner)
        ->getJson('/api/v1/abs/payrolls')
        ->assertOk();
});

it('karyawan cannot list payrolls', function () {
    $this->actingAs($this->employee)
        ->getJson('/api/v1/abs/payrolls')
        ->assertForbidden();
});

it('admin can add deduction to payroll', function () {
    $period = AbsPayrollPeriod::factory()->create([
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/abs/payrolls/' . $period->ulid . '/deductions', [
            'reason' => 'Terlambat 3 hari',
            'amount' => 50000,
        ])
        ->assertCreated()
        ->assertJsonPath('data.reason', 'Terlambat 3 hari')
        ->assertJsonPath('data.amount', 50000);

    expect(AbsDeduction::count())->toBe(1);
});

it('admin can update deduction', function () {
    $period = AbsPayrollPeriod::factory()->create([
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);
    $deduction = AbsDeduction::factory()->create([
        'abs_payroll_period_id' => $period->id,
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->admin)
        ->putJson('/api/v1/abs/payrolls/' . $period->ulid . '/deductions/' . $deduction->ulid, [
            'reason' => 'Terlambat Updated',
            'amount' => 75000,
        ])
        ->assertOk()
        ->assertJsonPath('data.reason', 'Terlambat Updated')
        ->assertJsonPath('data.amount', 75000);
});

it('admin can delete deduction', function () {
    $period = AbsPayrollPeriod::factory()->create([
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);
    $deduction = AbsDeduction::factory()->create([
        'abs_payroll_period_id' => $period->id,
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/abs/payrolls/' . $period->ulid . '/deductions/' . $deduction->ulid)
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(AbsDeduction::count())->toBe(0);
});

it('admin can add bonus to payroll', function () {
    $period = AbsPayrollPeriod::factory()->create([
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/abs/payrolls/' . $period->ulid . '/bonuses', [
            'reason' => 'Bonus Kinerja',
            'amount' => 200000,
        ])
        ->assertCreated()
        ->assertJsonPath('data.reason', 'Bonus Kinerja')
        ->assertJsonPath('data.amount', 200000);

    expect(AbsBonus::count())->toBe(1);
});

it('admin can update bonus', function () {
    $period = AbsPayrollPeriod::factory()->create([
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);
    $bonus = AbsBonus::factory()->create([
        'abs_payroll_period_id' => $period->id,
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->admin)
        ->putJson('/api/v1/abs/payrolls/' . $period->ulid . '/bonuses/' . $bonus->ulid, [
            'reason' => 'Bonus Updated',
            'amount' => 300000,
        ])
        ->assertOk()
        ->assertJsonPath('data.reason', 'Bonus Updated')
        ->assertJsonPath('data.amount', 300000);
});

it('admin can delete bonus', function () {
    $period = AbsPayrollPeriod::factory()->create([
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);
    $bonus = AbsBonus::factory()->create([
        'abs_payroll_period_id' => $period->id,
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/abs/payrolls/' . $period->ulid . '/bonuses/' . $bonus->ulid)
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(AbsBonus::count())->toBe(0);
});

it('admin can finalize payroll', function () {
    $period = AbsPayrollPeriod::factory()->create([
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->admin)
        ->putJson('/api/v1/abs/payrolls/' . $period->ulid . '/finalize')
        ->assertOk()
        ->assertJsonPath('data.status', AbsPayrollStatus::FINAL->value);
});

it('cannot modify deduction on finalized payroll', function () {
    $period = AbsPayrollPeriod::factory()->final()->create([
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/abs/payrolls/' . $period->ulid . '/deductions', [
            'reason' => 'Test',
            'amount' => 50000,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', __('absence.payroll.already_final'));
});

it('cannot modify bonus on finalized payroll', function () {
    $period = AbsPayrollPeriod::factory()->final()->create([
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/abs/payrolls/' . $period->ulid . '/bonuses', [
            'reason' => 'Test',
            'amount' => 50000,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', __('absence.payroll.already_final'));
});

it('admin can unlock finalized payroll', function () {
    $period = AbsPayrollPeriod::factory()->final()->create([
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($this->admin)
        ->putJson('/api/v1/abs/payrolls/' . $period->ulid . '/unlock')
        ->assertOk()
        ->assertJsonPath('data.status', AbsPayrollStatus::DRAFT->value);
});

it('recalculates net salary after adding deduction and bonus', function () {
    $now = Carbon::now();
    AbsAttendance::factory()->create([
        'user_id' => $this->employee->id,
        'sub_company_id' => $this->subCompany->id,
        'abs_shift_id' => $this->shift->id,
        'company_id' => $this->company->id,
        'date' => $now->toDateString(),
        'status' => 'hadir',
    ]);

    $period = AbsPayrollPeriod::factory()->create([
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
        'period_month' => (int) $now->month,
        'period_year' => (int) $now->year,
        'daily_rate' => 100000,
        'gross_salary' => 100000,
        'total_deduction' => 0,
        'total_bonus' => 0,
        'net_salary' => 100000,
    ]);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/abs/payrolls/' . $period->ulid . '/deductions', [
            'reason' => 'Potongan',
            'amount' => 100000,
        ])->assertCreated();

    $this->actingAs($this->admin)
        ->postJson('/api/v1/abs/payrolls/' . $period->ulid . '/bonuses', [
            'reason' => 'Bonus',
            'amount' => 50000,
        ])->assertCreated();

    $freshPeriod = $period->fresh();
    expect((float) $freshPeriod->total_deduction)->toBe(100000.0);
    expect((float) $freshPeriod->total_bonus)->toBe(50000.0);
    expect((float) $freshPeriod->net_salary)->toBe(50000.0);
});

it('owner cannot manage deductions or bonuses', function () {
    $owner = User::factory()->owner()->create(['company_id' => $this->company->id]);
    $period = AbsPayrollPeriod::factory()->create([
        'user_id' => $this->employee->id,
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($owner)
        ->postJson('/api/v1/abs/payrolls/' . $period->ulid . '/deductions', [
            'reason' => 'Test',
            'amount' => 50000,
        ])
        ->assertForbidden();
});
