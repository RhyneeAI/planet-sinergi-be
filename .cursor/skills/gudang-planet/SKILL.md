---
name: gudang-planet
description: Backend Laravel multi-modul (POS, Operasional, Absensi) untuk Gudang Planet. Gunakan skill ini saat mengembangkan fitur, refactor, atau debug di project ini — termasuk role/permission, pricing marketing MLM, modul operasional cabang, dan absensi dengan geofencing + payroll.
---

# Gudang Planet — Project Skill (v1)

## Ringkasan Project

**Gudang Planet** adalah backend API Laravel 12 (PHP 8.3) untuk manajemen bisnis multi-modul dalam satu monorepo backend. Satu aplikasi backend melayani **3 modul aplikasi** yang dibedakan oleh prefix (tabel, model, controller, enum, views) dan folder terpisah untuk kode non-database.


| Modul           | Status                  | Route prefix                       | Table prefix       | Namespace                                          |
| --------------- | ----------------------- | ---------------------------------- | ------------------ | -------------------------------------------------- |
| **POS**         | Aktif (sedang direvisi) | `/api/v1/`*                        | *(tanpa prefix)*   | `App\Http\Controllers\Api\`                        |
| **Operasional** | Sudah diimplementasi    | `/api/v1/operational/`*            | `ops_`             | `App\Http\Controllers\Api\Operational\`            |
| **Absensi**     | Belum ada (greenfield)  | `/api/v1/attendance/`* *(rencana)* | `att_` *(rencana)* | `App\Http\Controllers\Api\Attendance\` *(rencana)* |


**Stack:** Laravel Sanctum (auth), Pest (testing), DomPDF + Maatwebsite Excel (laporan), Laravel Telescope (monitoring dev).

---

## Hierarki Role & Akses

```php
enum Role: string
{
    case SUPERADMIN = 'SUPERADMIN';
    case OWNER = 'OWNER';
    case ADMIN = 'ADMIN';
    case MARKETING_LEADER = 'MARKETING_LEADER';
    case MARKETING = 'MARKETING';
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
| MARKETING_LEADER | Diinput admin, dapat komisi MLM           | —                                            | Absen (jika pegawai)       |
| MARKETING        | Diinput admin, dapat komisi MLM           | —                                            | Absen (jika pegawai)       |
| KASIR            | **Satu-satunya role transaksi penjualan** | —                                            | Absen (jika pegawai)       |
| MANDOR           | —                                         | Pemasukan/pengeluaran cabang, dompet digital | Absen (jika pegawai)       |
| KARYAWAN         | —                                         | —                                            | Absen                      |


**Absensi:** Berlaku untuk semua role **kecuali** OWNER dan SUPERADMIN.

---

## Arsitektur Multi-Tenancy

- Setiap user terikat ke `company_id` (kantor pusat).
- Global scope `CompanyScope` otomatis filter query berdasarkan `auth()->user()->company_id`.
- `**Company`** = kantor pusat (milik OWNER).
- `**SubCompany**` = cabang (**tabel terpisah**, FK `company_id` + `mandor_id`) — dipakai **Operasional & Absensi saja**.
- Satu mandor bisa kelola banyak sub-company (max 5 via config DB).

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

- Semua entitas POS (`products`, `sales_transactions`, `customers`, dll.) hanya punya `company_id`.
- Tidak ada relasi ke sub-company / cabang.
- User POS (KASIR, ADMIN, MARKETING, dll.) terikat ke `company_id` pusat.

### Status Saat Ini

Modul POS sudah berjalan dengan entitas: products, categories, units, suppliers, customers, stock mutations, purchase/sales transactions, installments, marketing products, dan laporan komisi.

**File kunci:**

- Routes: `routes/api.php`
- Controllers: `app/Http/Controllers/Api/`
- Models: `app/Models/` (Product, SalesTransaction, MarketingProduct, dll.)
- Laporan: `app/Http/Controllers/Api/ReportController.php`

### Perubahan Direncanakan (v1)

#### 1. Pricing Marketing — 4 Level (Model MLM)

**v1 (legacy):** Product punya 3 harga + fitur **edit harga per transaksi** (`sell_price` bebas di cart).

**v2 (target):** Hapus edit harga per transaksi. Hanya **4 harga default** per product:


| Level                  | Field             | Deskripsi                                               |
| ---------------------- | ----------------- | ------------------------------------------------------- |
| Harga Modal Perusahaan | `base_price`      | Biaya dasar perusahaan                                  |
| Harga Marketing Leader | `leader_price`    | Floor price leader                                      |
| Harga Marketing        | `marketing_price` | Floor price marketing bawah                             |
| Harga Jual             | `sell_price`      | Harga jual default product *(bukan edit per line item)* |


> Field `sales_price` lama digantikan/direstruktur menjadi `leader_price` + `sell_price` sesuai migrasi v2.

**Alur keuntungan (MLM) — sudah dikonfirmasi:**


| Penerima             | Rumus margin                     | Kapan dapat                                                                   |
| -------------------- | -------------------------------- | ----------------------------------------------------------------------------- |
| **Perusahaan**       | `leader_price − base_price`      | **Selalu** (termasuk saat leader yang terkait transaksi)                      |
| **Marketing Leader** | `leader_price − marketing_price` | Saat leader terkait transaksi **atau** marketing bawah yang terkait transaksi |
| **Marketing**        | `sell_price − marketing_price`   | Hanya saat **marketing bawah** (role MARKETING) yang terkait transaksi        |


**Saat MARKETING_LEADER terkait transaksi:**

- Perusahaan tetap dapat margin `leader_price − base_price`.
- Leader dapat margin `leader_price − marketing_price`.
- Marketing bawah **tidak** dapat bagian.

#### 1b. Hierarki Marketing (tetap)

Setiap user role **MARKETING** wajib punya `**leader_id`** (FK → user role MARKETING_LEADER). Hierarki fixed, bukan pilih leader per transaksi.

```
users
├── role = MARKETING_LEADER  (leader_id = null)
└── role = MARKETING         (leader_id → MARKETING_LEADER)
```

Saat transaksi penjualan, kasir **wajib pilih marketing** (MARKETING atau MARKETING_LEADER). Sistem resolve komisi dari role + `leader_id` terkait user tersebut.

#### 2. Perubahan Actor Transaksi


| Sebelum                                      | Sesudah (v2)                                                                |
| -------------------------------------------- | --------------------------------------------------------------------------- |
| Role MARKETING melakukan transaksi penjualan | Role **KASIR** (+ SUPERADMIN) melakukan transaksi                           |
| Admin ikut transaksi                         | **ADMIN tidak bisa transaksi** (POS maupun operasional transaksi penjualan) |
| Marketing self-service                       | Marketing & Marketing Leader **hanya diinput oleh Admin** (CRUD user)       |


**Siapa boleh transaksi POS:**


| Role                         | Transaksi                                         |
| ---------------------------- | ------------------------------------------------- |
| SUPERADMIN                   | ✅ Full                                            |
| OWNER                        | ❌ Read-only (rekapitulasi saja)                   |
| ADMIN                        | ❌ **Tidak bisa transaksi**                        |
| KASIR                        | ✅ Penjualan (dan transaksi POS lain sesuai route) |
| MARKETING / MARKETING_LEADER | ❌ Tidak transaksi                                 |


**Alur penjualan v2:**

1. Kasir login → buat transaksi penjualan.
2. **Wajib pilih marketing** (user MARKETING atau MARKETING_LEADER).
3. Harga diambil dari 4 default product — **tidak ada edit harga per line item**.
4. Sistem hitung komisi dari hierarki `leader_id` + role marketing terpilih.
5. Alur cart, payment, installment **tetap sama** — perubahan signifikan di actor (KASIR), pricing 4 level, dan hierarki MLM.

#### 3. Perubahan Laporan

Laporan perlu disesuaikan dengan model MLM 4 level:

- `ReportController::marketingCommission` — perlu breakdown per level (perusahaan, leader, marketing).
- `ReportController::salesRevenue` — mungkin perlu dimensi baru per marketing leader vs marketing.
- View PDF: `resources/views/reports/`

#### 4. Route & Middleware (rencana)

```
role:SUPERADMIN,OWNER,ADMIN     → master data CRUD, marketings CRUD (OWNER: read-only mutasi)
role:SUPERADMIN,KASIR           → transaksi penjualan & pembelian (ADMIN excluded)
role:SUPERADMIN,OWNER           → rekapitulasi/laporan (OWNER: GET/read-only saja)
```

**OWNER read-only:** Tidak ada endpoint mutasi (POST/PATCH/DELETE) untuk OWNER — hanya GET/rekapitulasi. Implement via middleware atau route group terpisah.

> **Catatan implementasi:** Role `KASIR`, `MARKETING_LEADER` sudah ada di enum tapi **belum dipakai di route**. Perlu update middleware di `routes/api.php`.

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
| OpsIncome               | ops_incomes                | Pemasukan (admin → pusat / admin → mandor) |
| OpsExpense              | ops_expenses               | Pengeluaran (pusat / cabang mandor)        |
| OpsWallet               | ops_wallets                | Dompet digital mandor                      |
| OpsWalletTransaction    | ops_wallet_transactions    | Ledger dompet                              |
| OpsTransferConfirmation | ops_transfer_confirmations | Konfirmasi transfer admin → mandor         |
| OpsNotification         | ops_notifications          | Notifikasi in-app                          |
| OpsEditLog              | ops_edit_logs              | Audit trail edit                           |


### Role & Alur

**Role:** ADMIN dan MANDOR (keduanya bisa input). ADMIN punya otoritas audit mandor.

#### Admin — Pemasukan

- Hanya pencatatan operasional **kantor pusat** (bukan ke mandor — transfer ke mandor via pengeluaran).

#### Admin — Pengeluaran

- Pengeluaran **pusat** saja.
- Pengeluaran **ke mandor** → trigger alur transfer (lihat bawah).

#### Admin → Mandor: Transfer Dana

Alur **sudah selaras** dengan implementasi `OpsTransferConfirmation` yang ada:

```
1. Admin input manual pengeluaran (expense) ke mandor tertentu
2. Sistem buat pending transfer + notifikasi ke mandor
3. Mandor review & APPROVE pemasukan dari admin
4. Setelah approve → tercatat sebagai pemasukan mandor + update dompet digital
5. Mandor bisa REJECT jika tidak sesuai
```

> Mandor **tidak** request dari sisi mereka — admin yang initiate pengeluaran; mandor yang **approve/reject** pemasukan masuk.

#### Mandor — Pemasukan

- Pemasukan dari admin: **wajib approve** dulu via `OpsTransferConfirmation` sebelum masuk ke buku mandor/dompet.
- Pemasukan lain (non-admin) → input langsung oleh mandor.

#### Mandor — Pengeluaran

- Hanya pencatatan operasional dari **cabang yang mandor kelola**.

### Aturan Input

- CRUD pemasukan & pengeluaran.
- Input tanggal: **backdate max 3 hari** — berlaku untuk **semua role** (ADMIN & MANDOR) dan **semua jenis** (pemasukan & pengeluaran).
- Upload bukti/invoice.
- Field: keterangan, judul, tanggal, bukti.

### Sub-Company (rencana perlu ditambahkan)

Saat ini belum ada model `SubCompany`. Perlu ditambahkan karena operasional = manage cabang:

- `Company` = kantor pusat (milik OWNER).
- `**SubCompany`** = cabang (**tabel terpisah**, bukan `parent_id` di `companies`).
- Satu **MANDOR bisa kelola banyak cabang** (many sub-companies).
- **Limit cabang per mandor:** tabel konfigurasi di DB (default maks **5**), **tanpa UI** — cukup seed/config via database.
- Validasi saat create sub-company: cek jumlah sub-company mandor ≤ limit dari config.

---

## Modul 3: Absensi (Greenfield)

Modul absensi **belum ada** di codebase. Ikuti pola operasional sebagai referensi implementasi.

### Konvensi Rencana


| Aspek                | Konvensi                                                       |
| -------------------- | -------------------------------------------------------------- |
| Route prefix         | `/api/v1/attendance/`*                                         |
| Table prefix         | `att_`                                                         |
| Model prefix         | `Att*`                                                         |
| Namespace controller | `App\Http\Controllers\Api\Attendance\`                         |
| Config               | `config/attendance.php`                                        |
| Routes file          | `routes/attendance-api.php` (daftarkan di `bootstrap/app.php`) |


### Entitas Rencana


| Model (rencana) | Fungsi                                                                                                                 |
| --------------- | ---------------------------------------------------------------------------------------------------------------------- |
| AttSubCompany   | Cabang — **tabel terpisah** (mirip Company + lat, long, radius, toleransi keterlambatan). FK `mandor_id`, `company_id` |
| AttPosition     | Jabatan (salary/hari, jadwal hari kerja, jam kerja from-to)                                                            |
| AttWorkSchedule | Parent data jadwal harian (auto-generate by system)                                                                    |
| AttAttendance   | Record absensi (foto, lokasi, jam masuk/keluar, status)                                                                |
| AttPayroll      | Riwayat penggajian bulanan                                                                                             |
| AttPayrollItem  | Detail slip gaji (bonus, potongan manual)                                                                              |


### Perluas Company

Tambahkan ke `companies` (kantor pusat):

- `latitude`, `longitude`, `radius` (meter)
- `late_tolerance` (menit keterlambatan)

Sub-company punya field yang sama.

### Alur Absensi

```
1. User login (role selain OWNER/SUPERADMIN)
2. Sistem cek: pegawai kantor pusat (Company) atau cabang (SubCompany)?
3. User pilih kategori: Izin | Sakit | Hadir (default, tidak perlu pilihan di BE)
4. Validasi geofencing (status Hadir):
   - Ambil lat/long + radius dari Company atau SubCompany user
   - Cek apakah lokasi user dalam radius
   - Di luar radius → **BLOCK** (tidak bisa absen, tanpa approval manual)
5. Jika valid → absen dengan foto + keterangan (opsional)
6. Simpan: lokasi user, jam masuk, jam keluar
7. Jam masuk: **WAJIB**
8. Jam keluar: **boleh kosong/ditolerir** jika lupa (khusus status Hadir)
9. Cek jam kerja dari jabatan → jika belum memenuhi: PERINGATAN saja (tidak block), catat kekurangan jam
10. Status approval: field ada, default APPROVED
```

### Konfigurasi Awal (Admin)

1. Set lat, long, radius, toleransi keterlambatan di Company/SubCompany.
2. Buat master **Jabatan** (AttPosition):
  - Salary per hari
  - Jadwal hari kerja (Senin–Jumat, dll.)
  - Jam kerja (from–to, total jam)
  - System auto-generate parent schedule per hari
3. Saat create user: pilih **role** + **jabatan** (strict **1 user = 1 jabatan**) + assign ke Company atau SubCompany.
4. **Seeder wajib:** buat `AttPositionSeeder` dengan jabatan default **"Karyawan"** (strict, dipakai sebagai fallback jabatan standar).

### Sistem Penggajian

**Trigger: manual oleh Admin** — payroll diproses **per bulan per karyawan**, hanya saat Admin meminta/menjalankan proses penggajian (tidak auto-generate draft).


| Komponen             | Keterangan                                                                                                   |
| -------------------- | ------------------------------------------------------------------------------------------------------------ |
| Gaji harian          | Hadir × salary/hari dari jabatan; kurang jam tetap dihitung                                                  |
| Total keterlambatan  | Kumulatif dari setiap hari                                                                                   |
| Total kekurangan jam | Kumulatif dari setiap hari                                                                                   |
| Rekap kehadiran      | Jumlah hadir, sakit, izin                                                                                    |
| Bonus & potongan     | **Seluruhnya input manual oleh Admin** saat proses penggajian — tidak ada potongan otomatis untuk izin/sakit |
| Slip gaji            | Cetak PDF (DomPDF, ikuti pola `resources/views/reports/`)                                                    |
| Riwayat              | Simpan snapshot bulanan per karyawan                                                                         |


> **Izin/Sakit:** Tidak ada aturan potong gaji otomatis. Admin menentukan potongan (dan bonus) manual per payroll. Rekap jumlah izin/sakit tetap ditampilkan sebagai informasi.

---

## Konvensi Kode (Wajib Diikuti)

### Struktur Folder per Modul Baru

```
app/
├── Enums/Att*.php                    # Enum modul absensi
├── Http/
│   ├── Controllers/Api/Attendance/   # Att*Controller
│   ├── Requests/Attendance/          # Form requests
│   └── Resources/Attendance/         # API resources
├── Models/Att*.php                   # Model dengan prefix Att
├── Services/Attendance/              # Business logic
config/attendance.php
routes/attendance-api.php
database/migrations/                  # att_* tables
lang/en|id/attendance.php
resources/views/reports/attendance/   # PDF templates
tests/Feature/Api/Attendance/         # Pest tests
```

### Pola yang Sudah Ada (Operasional = Referensi)

- Service layer untuk logic kompleks (`OpsWalletService` pattern).
- Audit log untuk edit (`OpsEditLog` pattern).
- Notifikasi in-app (`OpsNotification` pattern).
- File upload via dedicated service (`OpsFileService` pattern).
- Enum backed string dengan `values()` dan `label()`.
- Form Request untuk validasi.
- API Resource untuk response transformation.
- Factory + Seeder untuk testing data.

### Database

- snake_case plural untuk tabel.
- FK: `{entity}_id`.
- Soft deletes (`deleted_at`) pada tabel bisnis.
- `created_by` FK ke users.
- Morph map didefinisikan di `AppServiceProvider`.

### Testing

- Framework: Pest.
- Lokasi: `tests/Feature/Api/`.
- POS sudah punya 22 feature tests; Operasional **belum ada test** — tambahkan saat develop.

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
| SubCompany            | Tabel terpisah; untuk **Operasional & Absensi**; mandor max 5 cabang via config DB            |
| Backdate operasional  | Max 3 hari — berlaku **semua role & semua jenis** (pemasukan & pengeluaran)                   |
| Transfer admin→mandor | Admin input **pengeluaran** → mandor **approve/reject pemasukan** (`OpsTransferConfirmation`) |
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
| Auth middleware       | `app/Http/Middleware/CheckRole.php`                            |
| Route registration    | `bootstrap/app.php`                                            |
| POS routes            | `routes/api.php`                                               |
| Operational routes    | `routes/operational-api.php`                                   |
| Company + scope       | `app/Models/Company.php`, `app/Models/Scopes/CompanyScope.php` |
| Marketing commission  | `app/Http/Controllers/Api/ReportController.php`                |
| Ops wallet logic      | `app/Services/Operational/OpsWalletService.php`                |
| Ops config            | `config/operational.php`                                       |
| Flowchart operasional | `public/flowchart_operasional.pdf`                             |


