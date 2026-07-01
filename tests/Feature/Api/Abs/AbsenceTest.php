<?php

use App\Models\AbsEmployeeProfile;
use App\Models\AbsShift;
use App\Models\Position;
use App\Models\Company;
use App\Models\SubCompany;
use App\Models\User;
use App\Services\SubCompanyService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

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
        'name' => 'Cabang Pusat',
        'code' => 'GP-01',
        'address' => 'Jl. Test',
        'latitude' => -6.8266492915813215,
        'longitude' => 107.14791799479002,
        'radius_meter' => 500,
        'is_active' => true,
        'mandor_id' => $this->mandor->id,
        'company_id' => $this->company->id,
    ]);
    User::$skipSubCompanyAutoCreate = false;

    $this->shift = AbsShift::create([
        'name' => 'Shift Pagi',
        'start_time' => '08:00:00',
        'end_time' => '17:00:00',
        'company_id' => $this->company->id,
    ]);

    $this->position = Position::create([
        'name' => 'Operator',
        'daily_rate' => 120000,
        'company_id' => $this->company->id,
    ]);

    $this->employee = User::factory()->karyawan()->create([
        'company_id' => $this->company->id,
        'is_active' => true,
    ]);

    AbsEmployeeProfile::where('user_id', $this->employee->id)->update([
        'abs_jabatan_id' => $this->position->id,
        'sub_company_id' => $this->subCompany->id,
        'abs_shift_id' => $this->shift->id,
    ]);
});

it('admin can create employee via employees api', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/employees', [
            'name' => 'Budi',
            'phone' => '081234567890',
            'password' => 'password123',
            'role' => 'KARYAWAN',
            'position_uuid' => $this->position->uuid,
            'sub_company_uuid' => $this->subCompany->uuid,
            'shift_uuid' => $this->shift->uuid,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.profile.jabatan.name', 'Operator')
        ->assertJsonPath('data.profile.jabatan.daily_rate', 120000);
});

it('employee can check in within sub company radius', function () {
    $photo = UploadedFile::fake()->create('selfie.jpg', 100);

    $this->actingAs($this->employee)
        ->postJson('/api/v1/abs/me/attendance/check-in', [
            'photo' => $photo,
            'latitude' => -6.826577731426878,
            'longitude' => 107.14796351338576,
            'late_reason' => 'test'
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true);
});

it('employee check in is blocked outside sub company radius', function () {
    $photo = UploadedFile::fake()->create('selfie.jpg', 100);

    $this->actingAs($this->employee)
        ->postJson('/api/v1/abs/me/attendance/check-in', [
            'photo' => $photo,
            'latitude' => -6.3,
            'longitude' => 106.9,
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('owner can view abs dashboard but cannot create employee', function () {
    $owner = User::factory()->owner()->create([
        'company_id' => $this->company->id,
    ]);

    $this->actingAs($owner)
        ->getJson('/api/v1/abs/dashboard')
        ->assertStatus(200);

    $this->actingAs($owner)
        ->postJson('/api/v1/employees', [
            'name' => 'Blocked',
            'phone' => '081111111111',
            'password' => 'password123',
            'role' => 'KARYAWAN',
        ])
        ->assertStatus(403);
});

it('creates employee profile for all seeded users', function () {
    $this->seed(\Database\Seeders\AbsEmployeeProfileSeeder::class);

    expect(User::where('company_id', $this->company->id)->whereDoesntHave('absEmployeeProfile')->count())->toBe(0);
});
