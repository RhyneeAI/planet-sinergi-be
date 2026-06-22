---
name: gudang-planet
description: Backend Laravel multi-modul (POS, Operasional, Absensi) untuk Gudang Planet. Gunakan skill ini saat mengembangkan fitur, refactor, atau debug di project ini — termasuk role/permission, pricing marketing MLM, modul operasional cabang (wallet, transfer, payment_method, batas waktu input/edit), dan absensi dengan geofencing + payroll.
---

# Gudang Planet — Project Skill (v1.7)

## Ringkasan Project

**Gudang Planet** adalah backend API Laravel 12 (PHP 8.3) untuk manajemen bisnis multi-modul dalam satu monorepo backend. Satu aplikasi backend melayani **3 modul aplikasi** yang dibedakan oleh prefix (tabel, model, controller, enum, views) dan folder terpisah untuk kode non-database.


| Modul           | Status                  | Route prefix                       | Table prefix       | Namespace                                          |
| --------------- | ----------------------- | ---------------------------------- | ------------------ | -------------------------------------------------- |
| **POS**         | Aktif (v2)              | `/api/v1/pos/`*                    | `pos_`             | `App\Http\Controllers\Api\Pos\`                    |
| **Operasional** | Aktif                   | `/api/v1/operational/`*            | `ops_`             | `App\Http\Controllers\Api\Operational\`            |
| **Absensi**     | Aktif (mulai v1.7)      | `/api/v1/abs/`*                    | `abs_`             | `App\Http\Controllers\Api\Absensi\`                |


**Stack:** Laravel Sanctum (auth), Pest (testing), DomPDF + Maatwebsite Excel (laporan), Laravel Telescope (monitoring dev).

---

## Hierarki Role & Akses

```php
enum Role: string
{
    case SUPERADMIN = 'SUPERADMIN';
    case OWNER = 'OWNER';
    case ADMIN = 'ADMIN';
    case HRD = 'HRD';
    case MANAJER_GUDANG = 'MANAJER_GUDANG';
    case MARKETING_LEAD = 'MARKETING_LEAD';
    case MARKETING = 'MARKETING';
    case MARKETING_TETAP = 'MARKETING_TETAP';
    case KASIR = 'KASIR';
    case MANDOR = 'MANDOR';
    case KARYAWAN = 'KARYAWAN';
}
```

### Garis bawahi: OWNER vs SUPERADMIN


| Aspek                          | SUPERADMIN      | OWNER                                   |
| ------------------------------ | --------------- | --------------------------------------- |
| Akses data semua modul         | ✅ Full          | ✅ Read-only rekapitulasi                |
| POS transaksi                  | ✅               | ❌ Read-only                             |
| Operasional transaksi          | ✅               | ❌ Read-only                             |
| Absensi transaksi              | ✅               | ❌ Read-only                             |
| **Telescope / monitoring dev** | ✅ **Eksklusif** | ❌                                       |
| **Monitoring data bisnis**     | ✅               | ✅ **Read-only** (tanpa akses Telescope) |


> **Implementasi saat ini:** Middleware `role` di `CheckRole.php`. OWNER dan SUPERADMIN sering digabung di route group yang sama — perlu dipisah akses transaksi vs rekapitulasi untuk OWNER sesuai brief.

### Pemetaan Role per Modul


| Role             | POS                                       | Operasional                                  | Absensi                    |
| ---------------- | ----------------------------------------- | -------------------------------------------- | -------------------------- |
| SUPERADMIN       | Full + Telescope                          | Full                                         | Full                       |
| OWNER            | **Read-only** rekapitulasi                | **Read-only** rekapitulasi                   | **Read-only** rekapitulasi |
| ADMIN            | Master data (no transaksi POS)            | Pemasukan/pengeluaran pusat, audit mandor    | Konfigurasi, penggajian    |
| HRD              | —                                         | Input karyawan, lihat lembur & kasbon        | **Full** (lembur, kasbon, payroll lihat) |
| MANAJER_GUDANG   | Produk, kategori, stok, laporan           | —                                            | —                          |
| MARKETING_LEAD   | Transaksi + laporan commission            | —                                            | —                          |
| MARKETING        | Transaksi + laporan commission (sendiri)  | —                                            | —                          |
| MARKETING_TETAP  | Transaksi (no commission report)          | —                                            | —                          |
| KASIR            | **Satu-satunya role transaksi penjualan** | —                                            | —                          |
| MANDOR           | —                                         | Pemasukan/pengeluaran cabang, dompet digital | Absen (jika pegawai)       |
| KARYAWAN         | —                                         | —                                            | Absen                      |


**Absensi:** Berlaku untuk semua role **kecuali** OWNER, SUPERADMIN, MARKETING, dan MARKETING_TETAP.

---

## Arsitektur Multi-Tenancy

- Setiap user terikat ke `company_id` (kantor pusat).
- Global scope `CompanyScope` otomatis filter query berdasarkan `auth()->user()->company_id`.
- `**Company`** = kantor pusat (milik OWNER).
- `**SubCompany**` = cabang (**tabel terpisah**, FK `company_id` + `mandor_id`) — dipakai **Operasional & Absensi saja**.
- Satu mandor bisa kelola banyak sub-company (max via `ops_configurations` key `max_sub_companies_per_mandor`, default **10**).

### Scope Modul vs Company / SubCompany


| Modul           | Nginduk ke                   | Catatan                                                                              |
| --------------- | ---------------------------- | ------------------------------------------------------------------------------------ |
| **POS**         | `**Company` saja**           | Tidak ada `sub_company_id`. Semua master data & transaksi POS scoped ke kantor pusat |
| **Operasional** | `Company` + `**SubCompany`** | Admin = pusat; Mandor = cabang (sub-company)                                         |
| **Absensi**     | `Company` + `**SubCompany`** | Pegawai pusat → Company; pegawai cabang → SubCompany                                 |


> **POS tidak pernah nginduk ke SubCompany.** Jangan tambahkan FK `sub_company_id` pada model/tabel POS.

### Identifikator Publik


| Tipe                              | Trait     | Route binding key |
| --------------------------------- | --------- | ----------------- |
| Master data (product, user, dll.) | `HasUuid` | `{model:uuid}`    |
| Transaksi (sales, purchase, dll.) | `HasUlid` | `{model:ulid}`    |


### Response API Standar

```json
{ "success": true|false, "message": "...", "data": { ... } }
```

Gunakan `__('module.key')` untuk i18n (`lang/en/`, `lang/id/`).

---

## Modul 1: POS (Point of Sale)

### Scope Tenancy

**POS nginduk langsung ke `Company` (kantor pusat) — bukan SubCompany.**

- Semua entitas POS (`products`, `sales_transactions`, dll.) hanya punya `company_id`.
- Tidak ada relasi ke sub-company / cabang.
- User POS (KASIR, MANAJER_GUDANG, MARKETING, dll.) terikat ke `company_id` pusat.

### Status Saat Ini (v2 — mulai v1.7)

**Perubahan v1.7:**

#### 1. Pricing — 4 Level

| Field             | Deskripsi                                               |
| ----------------- | ------------------------------------------------------- |
| `base_price`      | Modal perusahaan                                        |
| `leader_price`    | Harga jual perusahaan ke LEAD_MARKETING / MARKETING_TETAP |
| `marketing_price` | Harga untuk MARKETING lepas                             |
| `sell_price`      | Harga jual akhir (bisa diubah FE saat transaksi)        |

**Profit Flow (contoh: base=20k, leader=25k, marketing=26k, sell=30k):**

| Skenario                | Company   | LEAD_MARKETING | MARKETING |
| ----------------------- | --------- | -------------- | --------- |
| Dijual MARKETING        | 5k (25-20) | 1k (26-25)     | 4k (30-26) |
| Dijual MARKETING_LEAD   | 5k (25-20) | 5k (30-25)     | —         |
| Dijual MARKETING_TETAP  | 5k (25-20) | —              | —         |
| Dijual KASIR            | 5k (25-20) | —              | —         |

#### 2. Transaksi Penjualan

- **Customer:** nullable (tidak wajib pilih)
- **Marketing:** wajib pilih (`marketing_id`)
- **Profit fields baru** di `pos_sales_details`: `company_profit`, `lead_profit`, `marketing_profit`
- Discount, biaya opsional, custom sell_price tetap ada

#### 3. Piutang v2 (`pos_receivables`)

- No installment / tenor — hanya DP + pelunasan langsung
- Installment lama (`pos_installments`) tetap berjalan

#### 4. Retur Penjualan (`pos_returns`)

- KASIR input → MANAJER_GUDANG proses; stok otomatis dikembalikan

#### 5. Module Config (disabled modules)

`pos_customer`, `pos_purchase`, `pos_installment` → disabled via `config/modules.php` (endpoint return 410)

#### 6. Laporan

- **Marketing commission:** scope MARKETING_LEAD + MARKETING saja
- **Kartu stok:** `GET /api/v1/pos/stock-card/{productId}`
- **Sales report:** disesuaikan profit per role

#### 7. MARKETING tidak punya akses ke aplikasi POS

**File kunci:**
- Routes: `routes/pos-api.php`
- Controllers: `app/Http/Controllers/Api/Pos/`
- Models: `app/Models/Pos`* (PosProduct, PosSalesTransaction, dll.)
- Laporan: `app/Http/Controllers/Api/Pos/PosReportController.php`
- Services: `app/Services/Pos/`

### Role & Akses POS

| Role                         | Transaksi | Produk | Stok | Laporan Marketing |
| ---------------------------- | --------- | ------ | ---- | ----------------- |
| SUPERADMIN                   | ✅ Full    | ✅      | ✅    | ✅                 |
| MANAJER_GUDANG               | ❌         | ✅ CRUD | ✅    | ❌                 |
| KASIR                        | ✅         | ❌      | ❌    | ❌                 |
| MARKETING_LEAD               | ✅         | ❌      | ❌    | ✅                 |
| MARKETING                    | ✅         | ❌      | ❌    | ✅ (diri sendiri)  |
| MARKETING_TETAP              | ✅         | ❌      | ❌    | ❌ (bonus via absensi) |
| ADMIN                        | ❌         | ✅      | ❌    | ❌                 |
| OWNER                        | ❌         | ❌      | ❌    | Read-only          |

---

## Modul 2: Operasional

### Status: Sudah Diimplementasi

Modul operasional mengelola keuangan cabang dengan sistem dompet digital mandor.

**Konvensi kode:**

- Prefix tabel: `ops_`
- Prefix model: `Ops`* (OpsIncome, OpsExpense, OpsWallet, dll.)
- Folder: `app/Http/Controllers/Api/Operational/`, `app/Services/Operational/`, `app/Http/Requests/Operational/`
- Config: `config/operational.php`
- Routes: `routes/operational-api.php` → `/api/v1/operational/*`

### Entitas


| Model                   | Tabel                      | Fungsi                                     |
| ----------------------- | -------------------------- | ------------------------------------------ |
| SubCompany            | sub_companies              | Cabang operasional (FK mandor_id)          |
| OpsConfiguration        | ops_configurations         | Config per company (limit cabang, batas waktu input/edit) |
| OpsIncome               | ops_incomes                | Pemasukan pusat (admin) / cabang (mandor) / pending transfer (admin→mandor) |
| OpsExpense              | ops_expenses               | Pengeluaran pusat (admin INTERNAL) / transfer mandor (admin MANDOR) / cabang (mandor) |
| OpsWallet               | ops_wallets                | Dompet digital mandor                      |
| OpsWalletTransaction    | ops_wallet_transactions    | Ledger dompet                              |
| OpsTransferConfirmation | ops_transfer_confirmations | Konfirmasi transfer admin → mandor         |
| OpsNotification         | ops_notifications          | Notifikasi in-app                          |
| OpsEditLog              | ops_edit_logs              | Audit trail edit                           |


### Role & Alur (v2 — sudah diimplementasi)

**Role:** ADMIN dan MANDOR (keduanya bisa write income/expense dengan logic berbeda). ADMIN punya otoritas audit mandor.

Route tunggal per resource (`/incomes`, `/expenses`) — controller branch by role. Form Request: **`OpsIncomeRequest`** & **`OpsExpenseRequest`** (satu file per resource, store + update, kondisi role di dalamnya — pola sama `CategoryRequest`).

#### Admin — Pemasukan (`POST /incomes`)

- Pencatatan pemasukan **kantor pusat** (`source_type: INTERNAL`).
- `mandor_uuid` & `sub_company_uuid` **opsional** (hanya atribusi, bukan transfer).
- **Tidak** membuat `OpsTransferConfirmation`.

#### Admin — Pengeluaran (`POST /expenses`)

Dua cabang via `expense_type`:

| `expense_type` | Perilaku |
| -------------- | -------- |
| `INTERNAL` | Pengeluaran pusat saja (tanpa mandor/sub_company) |
| `MANDOR` | Transfer ke mandor → buat `OpsExpense` + linked `OpsIncome` (`source_type: MANDOR`) + `OpsTransferConfirmation` PENDING + notifikasi mandor |

Field wajib saat `MANDOR`: `mandor_uuid`, `sub_company_uuid`. Link expense→income: kolom `transfer_income_id` di `ops_expenses`.

#### Admin → Mandor: Transfer Dana

```
1. Admin POST expense expense_type=MANDOR ke mandor + sub_company
2. Sistem buat income pending + transfer confirmation + notifikasi mandor
3. Mandor confirm/reject via /transfer-confirmations/{uuid}/confirm|reject
4. Confirm → kredit dompet digital mandor (mandor dapat sesuaikan `confirmed_amount` saat confirm)
```

> Transfer **bukan** lewat admin income — hanya lewat admin expense `MANDOR`.

#### Mandor — Pemasukan (`POST /incomes`)

- **Cabang sendiri:** `sub_company_uuid` wajib → `INTERNAL` + langsung kredit wallet.
- **Dari admin:** `source_type: MANDOR` — read-only untuk mandor; dikonfirmasi via transfer confirmation.

#### Mandor — Pengeluaran (`POST /expenses`)

- Hanya pengeluaran **internal cabang** (`expense_type: INTERNAL`, auto-set).
- Field wajib: `sub_company_uuid`, `name`, `amount`, `date`, `payment_method`, bukti (`proof_files[]` atau legacy `proof_file`).
- Debit wallet; block jika saldo tidak cukup + notifikasi admin.

#### Field transaksi income & expense (store + update)

| Field | Keterangan |
| ----- | ---------- |
| `name`, `amount`, `date` | Wajib |
| `payment_method` | Wajib — enum `TRANSFER` \| `CASH` (`App\Enums\OpsPaymentMethod`) |
| `proof_files[]` | 1–3 gambar (jpg/jpeg/png/webp, max 10MB/file). Legacy `proof_file` (1 file) masih didukung |
| `note` | Opsional |
| `reason` | Opsional — disarankan saat update (audit log) |

Response API: `proof_files` (array URL) + `proof_file` (URL pertama, backward compat) + `payment_method`.

### Batas Waktu Input & Edit

Dikelola via **`ops_configurations`** (per `company_id`) dengan fallback `config/operational.php`. Service: `OpsOperationalConfigService`.

| Key config | Default | Arti |
| ---------- | ------- | ---- |
| `income_store_backdate_days` | 3 | Input pemasukan max **H-3** |
| `income_edit_days_after_create` | 3 | Edit pemasukan sampai **H+3** setelah `created_at` |
| `expense_store_backdate_days` | 1 | Input pengeluaran max **H-1** |
| `expense_edit_days_after_create` | 1 | Edit pengeluaran sampai **H+1** setelah `created_at` |
| `max_sub_companies_per_mandor` | 10 | Max cabang per mandor |

Validasi di controller via trait `UsesOperationalTransactionWindow`:

- **Store** — tanggal transaksi tidak boleh lebih lama dari batas backdate.
- **Update** — cek batas edit (berdasarkan `created_at`) **dan** batas backdate tanggal baru.

Env override (opsional): `OPS_INCOME_STORE_BACKDATE_DAYS`, `OPS_INCOME_EDIT_DAYS_AFTER_CREATE`, `OPS_EXPENSE_STORE_BACKDATE_DAYS`, `OPS_EXPENSE_EDIT_DAYS_AFTER_CREATE`, `OPS_MAX_SUB_COMPANIES_PER_MANDOR`.

### Aturan Upload Bukti

- Format: **jpg, jpeg, png, webp** (bukan PDF).
- Max **10MB** per file.
- **1–3 gambar** per transaksi (`proof_files[]`); legacy single `proof_file` tetap diterima.
- Helper: trait `ValidatesOperationalProofFiles` (request), `HandlesOperationalProofFiles` + `MapsOperationalProofFiles` (controller/resource).
- Kolom DB: `proof_files` (JSON array path).

### Akses Mandor & Wallet

- Trait `ScopesOperationalBySubCompany`: mandor dapat akses record jika `mandor_id` record = user **atau** mandor saat ini dari cabang terkait (penting setelah reassignment cabang).
- `OpsWalletService::getOrCreateWallet()`: reuse wallet existing by `(sub_company_id, mandor_id)` — hindari duplikat wallet setelah cabang dipindah.

### API Behavior

- **GET show** (income, expense, transfer-confirmation, sub-company): UUID tidak ditemukan → `data: []` (bukan 404).
- **Admin dashboard** (`GET /dashboard/admin`): response termasuk `sub_companies[]` (ringkasan income/expense per cabang). Drill-down read-only: `GET /incomes?sub_company_uuid=...` & `GET /expenses?sub_company_uuid=...`.
- **Telescope login** (`/telescope-admin/login`): credential pakai **phone** + password (bukan username). Hanya SUPERADMIN.

### Sub-Company (sudah diimplementasi)

Model `SubCompany` (`sub_companies`) sudah ada dan dipakai modul operasional:

- `Company` = kantor pusat (milik OWNER).
- **`SubCompany`** = cabang (tabel terpisah, FK `company_id` + `mandor_id`).
- Satu **MANDOR bisa kelola banyak cabang** (many sub-companies).
- **Limit cabang per mandor:** `ops_configurations` key `max_sub_companies_per_mandor` (fallback `config/operational.php`, default **10**).
- Validasi saat create/assign cabang: cek jumlah sub-company mandor ≤ limit.

**Alur create cabang + mandor (satu endpoint):**

`POST /api/v1/sub-companies`

```json
{
  "mandor": {
    "name": "...",
    "phone": "...",
    "email": "...",
    "address": "..."
  },
  "sub_company": {
    "name": "...",
    "address": "..."
  }
}
```

- `sub_company.code` auto-generate (`{company_code}-{seq}`).
- Response create: `message` berisi phone + password; `data.credentials` juga tersedia.
- Role write: SUPERADMIN, OWNER, ADMIN. Role read: + MANDOR (milik sendiri).

**Endpoint cabang:**

| Route | Role | Keterangan |
| ----- | ---- | ---------- |
| `POST /api/v1/sub-companies` | ADMIN, OWNER, SUPERADMIN | Buat cabang + mandor |
| `GET /api/v1/sub-companies` | ADMIN, OWNER, SUPERADMIN, MANDOR | List cabang (+ mandor lihat milik sendiri) |
| `GET /api/v1/sub-companies/{uuid}` | ADMIN, OWNER, SUPERADMIN, MANDOR | Detail `{ sub_company, mandor }`; not found → `data: []` |
| `PATCH /api/v1/sub-companies/{uuid}` | ADMIN, OWNER, SUPERADMIN | Update unified mandor + sub_company |
| `DELETE /api/v1/sub-companies/{uuid}` | ADMIN, OWNER, SUPERADMIN | Soft delete cascade (income, expense, mandor jika tidak dipakai); block jika transfer pending |

**Wallet mandor (`GET /operational/wallet`):**

- 1 cabang → infer otomatis tanpa query param.
- Multi cabang → wajib `?sub_company_uuid=...` (422 field `errors.sub_company_uuid`).
- Belum punya cabang → 422 field `errors.sub_company_uuid`.

**Login mandor:** response `user.sub_companies: [{ uuid, name, code }]`.

**Username:** kolom DB tidak ada; login pakai **phone**. Field `username` di response mandor = slug dari nama (display only). Password awal auto-generate: `{namatanpasasi}{3digit}`.

### Operasional — Perubahan v1.7

#### Employee Management

| Endpoint | Method | Auth | Notes |
|---|---|---|---|
| `/api/v1/operational/employees` | GET | ADMIN, HRD, SUPERADMIN | Semua user |
| `/api/v1/operational/employees` | POST | ADMIN, HRD, SUPERADMIN | Create user (tidak bisa create Superadmin/Owner) |
| `/api/v1/operational/employees/{uuid}` | GET | ADMIN, HRD, SUPERADMIN | Detail + riwayat kasbon + lembur + gaji |
| `/api/v1/operational/employees/{uuid}/toggle-active` | PUT | ADMIN, SUPERADMIN | Aktif/nonaktifkan user |

#### Lembur & Kasbon (Read-only)

| Endpoint | Method | Auth |
|---|---|---|
| `/api/v1/operational/overtimes` | GET | ADMIN, HRD, SUPERADMIN |
| `/api/v1/operational/loans` | GET | ADMIN, HRD, SUPERADMIN |

#### Edit Log Jabatan

Saat salary `AbsJabatan` berubah → insert ke `ops_edit_logs` (`loggable_type: 'abs_jabatans'`).

---

## Modul 3: Absensi (Aktif mulai v1.7)

### Prefix & Konvensi

| Aspek                | Konvensi                                                       |
| -------------------- | -------------------------------------------------------------- |
| Route prefix         | `/api/v1/abs/`*                                                |
| Table prefix         | `abs_`                                                         |
| Model prefix         | `Abs`*                                                         |
| Namespace controller | `App\Http\Controllers\Api\Absensi\`                            |
| Config               | `config/absensi.php`                                           |
| Routes file          | `routes/absensi-api.php` (daftarkan di `bootstrap/app.php`)    |

### Entitas (Existing)

| Model              | Tabel                  | Fungsi                                     |
| ------------------ | ---------------------- | ------------------------------------------ |
| AbsEmployeeProfile | abs_employee_profiles  | Profil karyawan (jabatan, shift, cabang)   |
| AbsJabatan         | abs_jabatans           | Jabatan + salary                           |
| AbsShift           | abs_shifts             | Shift kerja                                |
| AbsAttendance      | abs_attendances        | Record absensi harian                      |
| AbsEmployeePayroll | abs_employee_payrolls  | Penggajian bulanan                         |

### Entitas Baru (v1.7)

#### `abs_overtimes` — Lembur

| Field | Type | Notes |
|---|---|---|
| user_id | FK → users | |
| date | date | |
| start_time | time | |
| end_time | time | |
| reason | text | |
| status | enum | `pending`, `approved`, `rejected` |
| approved_by | FK → users, nullable | HRD yg ACC |

**Alur:** Karyawan/HRD input batch → HRD ACC → data approved dipakai payroll.
**API:** CRUD + approve/reject — hanya HRD & Admin.

#### `abs_loans` — Kasbon

| Field | Type | Notes |
|---|---|---|
| user_id | FK → users | |
| amount | decimal | |
| reason | text | |
| tenor_months | int | 1–4 |
| monthly_installment | decimal | amount / tenor |
| remaining_balance | decimal | |
| status | enum | `pending`, `approved`, `rejected`, `paid` |
| approved_by | FK → users, nullable | |

**Alur:** HRD input → ACC → potong gaji bulanan via payroll.

#### `custom_configurations` — Config Global

| Field | Type |
|---|---|
| key | string unique |
| value | text |
| company_id | FK |

**Seed:** `overtime_hourly_rate` = 25000

### Employee List + Detail (HRD)

| Endpoint | Notes |
|---|---|
| `GET /abs/employees` | Semua user (Owner, Superadmin, dll) |
| `GET /abs/employees/{uuid}` | Profil + riwayat kasbon + lembur + gaji |

### Rule Akses

- **HRD** bisa lihat payroll history (read-only), tidak bisa create/update
- **MARKETING** dan **MARKETING_TETAP** tidak absensi & payroll (bukan karyawan tetap)

### Alur Absensi (Existing — tidak berubah)

```
1. User login (role selain OWNER/SUPERADMIN/MARKETING/MARKETING_TETAP)
2. Sistem cek: pegawai pusat (Company) atau cabang (SubCompany)
3. Validasi geofencing → block jika luar radius
4. Absen dengan foto; jam masuk wajib, jam keluar boleh kosong
5. Approval default APPROVED
```

### Sistem Penggajian (Existing — tidak berubah)

- Manual oleh Admin per bulan
- Gaji harian × hadir, keterlambatan, kekurangan jam, bonus & potongan manual

---

## Konvensi Kode (Wajib Diikuti)

### Struktur Folder per Modul Baru

```
app/
├── Enums/Abs*.php                    # Enum modul absensi
├── Http/
│   ├── Controllers/Api/Absensi/      # Abs*Controller
│   ├── Requests/Absensi/             # Form requests
│   └── Resources/Absensi/            # API resources
├── Models/Abs*.php                   # Model dengan prefix Abs
├── Services/Absensi/                 # Business logic
config/absensi.php
routes/absensi-api.php
database/migrations/                  # abs_* tables
lang/en|id/absensi.php
resources/views/reports/absensi/      # PDF templates
tests/Feature/Api/Absensi/            # Pest tests
```

### Module Config & Versioning

File `config/modules.php`:

```php
return [
    'absensi' => ['enabled' => true, 'version' => 'v1'],
    'operational' => ['enabled' => true, 'version' => 'v1'],
    'pos' => ['enabled' => true, 'version' => 'v2'],
    'pos_customer' => ['enabled' => false],
    'pos_purchase' => ['enabled' => false],
    'pos_installment' => ['enabled' => false],
];
```

Middleware `CheckModule` digunakan di route group untuk return 410 jika module disabled. Registrasi alias `module` di `bootstrap/app.php`.

### Pola yang Sudah Ada (Operasional = Referensi)

- Service layer untuk logic kompleks (`OpsWalletService`, `OpsOperationalConfigService`, `SubCompanyService`).
- Audit log untuk edit (`OpsEditLog` pattern) — payload auditable termasuk `payment_method`.
- Notifikasi in-app (`OpsNotification` pattern).
- File upload via dedicated service (`OpsFileService` pattern) + multi-file trait.
- Enum backed string dengan `values()` (`OpsPaymentMethod`, `OpsExpenseType`, dll.).
- Form Request untuk validasi — satu request per resource dengan branch by role (`OpsIncomeRequest`, `OpsExpenseRequest`).
- API Resource untuk response transformation.
- Factory + Seeder untuk testing data (`OpsConfigurationSeeder` untuk default config company).

### Database

- snake_case plural untuk tabel.
- FK: `{entity}_id`.
- Soft deletes (`deleted_at`) pada tabel bisnis.
- `created_by` FK ke users.
- Morph map didefinisikan di `AppServiceProvider`.

### Testing

- Framework: Pest.
- Lokasi: `tests/Feature/Api/`.
- Operasional: `OperationalIncomeTest.php`, `OperationalExpenseTest.php`, `OperationalTransferConfirmationTest.php`, `SubCompanyTest.php`.
- Rate limit API (non-testing): auth 120/min, guest write 30/min, guest read 80/min (`AppServiceProvider`).

---

## Checklist Saat Mengembangkan Fitur

1. **Tentukan modul** — POS / Operasional / Absensi.
2. **Cek role** — siapa boleh akses? OWNER hanya rekapitulasi?
3. **Ikuti prefix** — tabel, model, enum, views sesuai modul.
4. **CompanyScope** — pastikan model bisnis pakai global scope.
5. **Route binding** — uuid untuk master, ulid untuk transaksi.
6. **i18n** — tambah key di `lang/en/` dan `lang/id/`.
7. **Test** — minimal happy path + authorization test.
8. **Laporan** — jika perlu PDF/Excel, ikuti `ReportController` pattern.

---

## Keputusan Product (Sudah Dikonfirmasi)


| Topik                 | Keputusan                                                                                     |
| --------------------- | --------------------------------------------------------------------------------------------- |
| Hierarki marketing    | Setiap MARKETING wajib `leader_id` → MARKETING_LEADER (fixed hierarchy)                       |
| Margin perusahaan     | `leader_price − base_price` — selalu, termasuk saat leader terkait transaksi                  |
| Margin leader         | `leader_price − marketing_price`                                                              |
| Margin marketing      | `sell_price − marketing_price` — hanya saat role MARKETING terkait transaksi                  |
| Pricing v2            | 4 harga default — **tanpa edit harga per transaksi**                                          |
| Transaksi POS         | **ADMIN tidak bisa transaksi**; KASIR (+ SUPERADMIN) yang transaksi                           |
| OWNER akses           | **Read-only** di semua modul (GET/rekapitulasi saja)                                          |
| POS tenancy           | **Langsung ke `Company` saja** — tidak ke SubCompany                                          |
| SubCompany            | Tabel terpisah; untuk **Operasional & Absensi**; mandor max cabang via `ops_configurations` |
| Backdate operasional  | **Pemasukan H-3 / edit H+3**; **Pengeluaran H-1 / edit H+1** — configurable per company |
| Payment method        | Wajib `TRANSFER` \| `CASH` di semua create/update income & expense |
| Bukti transaksi       | Max **3 gambar** per transaksi (jpg/jpeg/png/webp) |
| Transfer admin→mandor | Admin input **pengeluaran** → mandor **approve/reject**; `confirmed_amount` dapat disesuaikan mandor |
| Payroll               | **Manual per bulan** saat Admin memproses — tidak auto-generate draft                         |
| Geofencing absensi    | Di luar radius → **block** langsung                                                           |
| Jam absensi (Hadir)   | Masuk **wajib**; keluar **boleh ditolerir** jika lupa                                         |
| Potongan izin/sakit   | Tidak otomatis; bonus & potongan **manual Admin** saat payroll                                |
| Jabatan               | Strict **1 user = 1 jabatan**; seeder default **"Karyawan"**                                  |


---

## Referensi File Penting


| Area                  | Path                                                           |
| --------------------- | -------------------------------------------------------------- |
| Role enum             | `app/Enums/Role.php`                                           |
| Payment method enum   | `app/Enums/OpsPaymentMethod.php`                               |
| Auth middleware       | `app/Http/Middleware/CheckRole.php`                            |
| Module config         | `config/modules.php`                                           |
| CheckModule middleware| `app/Http/Middleware/CheckModule.php`                          |
| Route registration    | `bootstrap/app.php`                                            |
| POS routes            | `routes/pos-api.php`                                           |
| POS controllers       | `app/Http/Controllers/Api/Pos/`                                |
| POS models            | `app/Models/Pos`* (PosProduct, PosSalesTransaction, dll.)      |
| POS services          | `app/Services/Pos/`                                            |
| POS laporan           | `app/Http/Controllers/Api/Pos/PosReportController.php`         |
| Operational routes    | `routes/operational-api.php`                                   |
| Company + scope       | `app/Models/Company.php`, `app/Models/Scopes/CompanyScope.php` |
| Marketing commission  | `app/Http/Controllers/Api/ReportController.php`                |
| SubCompany            | `app/Models/SubCompany.php`, `app/Services/SubCompanyService.php` |
| SubCompany controller | `app/Http/Controllers/Api/SubCompanyController.php`            |
| Ops config service    | `app/Services/Operational/OpsOperationalConfigService.php`     |
| Ops config model      | `app/Models/OpsConfiguration.php`                              |
| Ops mandor + cabang   | `app/Http/Controllers/Api/Operational/OpsMandorController.php` |
| Ops Form Requests     | `app/Http/Requests/Operational/OpsIncomeRequest.php`, `OpsExpenseRequest.php` |
| Ops proof helpers     | `ValidatesOperationalProofFiles`, `HandlesOperationalProofFiles`, `MapsOperationalProofFiles` |
| Ops date window trait | `app/Http/Controllers/Api/Operational/UsesOperationalTransactionWindow.php` |
| Ops mandor scope      | `app/Http/Controllers/Api/Operational/ScopesOperationalBySubCompany.php` |
| Ops config            | `config/operational.php`                                       |
| Ops config seeder     | `database/seeders/OpsConfigurationSeeder.php`                  |
| Ops production seeder | `database/seeders/OpsProductionSeeder.php`                     |
| Absensi routes        | `routes/absensi-api.php`                                       |
| Absensi controllers   | `app/Http/Controllers/Api/Absensi/`                            |
| Absensi models        | `app/Models/Abs`* (AbsAttendance, AbsEmployeePayroll, dll.)     |
| Custom config model   | `app/Models/CustomConfiguration.php`                           |
| Absensi config        | `config/absensi.php`                                           |
| Report scope fix      | `app/Http/Controllers/Api/Operational/OpsReportController.php` |
| SubCompany request    | `app/Http/Requests/Operational/SubCompanyRequest.php`          |
| Postman collection    | `docs/postman/operational-api.postman_collection.json`         |
| Flowchart operasional | `public/flowchart_operasional.pdf`                             |
| Release notes v1.6    | `RELEASE-v1.6.md`                                              |
| Deliverables v1.7     | `docs/release/v1.7-ABS.md`, `v1.7-OPS.md`, `v1.7-POS.md`      |


