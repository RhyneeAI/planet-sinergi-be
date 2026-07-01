# Gudang Planet — Ringkasan Eksekutif (v2)

> **v1 sudah berakhir.** Pengembangan sekarang di **v2** — deploy ke hosting berbeda dari v1.

## 1. Arsitektur Inti
- **Backend:** Laravel 12 (PHP 8.3) | **Auth:** Sanctum | **Testing:** Pest
- **Multi-modul:** POS (v2), Operasional, Absensi — semua dalam satu backend.
- **Multi-tenancy:** 
  - `Company` = Pusat (Global Scope otomatis).
  - `SubCompany` = Cabang (hanya untuk **Operasional & Absensi**).
  - **POS langsung ke Company, BUKAN SubCompany.**

## 2. Hierarki Role (Kunci!)
- **Role:** SUPERADMIN, OWNER, ADMIN, HRD, GUDANG, KEPALA_GUDANG, MARKETING_LEAD, MARKETING, MARKETING_TETAP, KASIR, KEPALA_MANDOR, MANDOR, KARYAWAN.
- **Aturan Emas:**
  - **SUPERADMIN:** Bisa semua + Telescope.
  - **OWNER:** **READ-ONLY** di semua modul (rekapitulasi), TIDAK bisa Telescope.
  - **MARKETING & MARKETING_LEAD:** Tidak bisa login — marker komisi saja.
  - **MARKETING_TETAP:** Bisa login (sama seperti KARYAWAN).
  - **KASIR:** Satu-satunya role (selain SUPERADMIN) yang bisa transaksi POS.

## 3. Modul POS (v2)
- **Pricing 4 Level:** `base_price` (modal) → `leader_price` → `marketing_price` → `sell_price`.
- **Profit Flow:** Company, KEPALA_GUDANG, dan Marketing dapat profit split sesuai role.
- **Transaksi:** Wajib pilih `marketing_id`. Customer nullable.
- **Piutang:** Hanya DP + Pelunasan (installment lama masih aktif; piutang v2 masih upcoming).
- **Retur:** KASIR input → KEPALA_GUDANG/GUDANG proses (stok balik).
- **Laporan:** Kartu stok & Komisi Marketing (scope MARKETING_LEAD + MARKETING).

## 4. Modul Operasional
- **Admin:** Pemasukan/Pengeluaran pusat (`INTERNAL`) + Transfer ke Mandor (`MANDOR`).
- **Mandor:** Kelola dompet digital cabang. Transfer admin harus di-*confirm* dulu.
- **Batas Waktu:** Pemasukan max H-3 / Edit H+3; Pengeluaran max H-1 / Edit H+1 (bisa diatur di `ops_configurations`).
- **Upload Bukti:** Wajib, max 3 gambar (jpg/jpeg/png/webp, 10MB/file).
- **SubCompany:** Tabel terpisah. Satu mandor bisa pegang banyak cabang (max default 10).

## 5. Modul Absensi (v1.7)
- **Entitas Baru:** `abs_overtimes` (Lembur) & `abs_loans` (Kasbon) — HRD input & approve.
- **Geofencing:** Ketat (di luar radius langsung block).
- **Payroll:** Manual per bulan oleh Admin (tidak auto-generate).
- **Konfigurasi Global:** Pakai `custom_configurations` (key: `overtime_hourly_rate` = 25000).

## 6. Konvensi Kode (WAJIB)
- **Prefix:** Tabel (`pos_`, `ops_`, `abs_`), Model (`Pos`, `Ops`, `Abs`).
- **Routing:** `/api/v1/pos/*`, `/api/v1/operational/*`, `/api/v1/abs/*`.
- **Identifier:** Master data = `UUID`; Transaksi = `ULID`.
- **Response:** `{ success: bool, message: string, data: mixed }`.
- **i18n:** Pakai `__('module.key')` (file di `lang/en` & `lang/id`).
- **Testing:** Happy path + authorization test di `tests/Feature/Api/[Modul]/`.

## 7. Laravel Specific
- **Global Scope:** `CompanyScope` otomatis filter by `company_id`.
- **Soft Deletes:** Dipakai di semua tabel bisnis.
- **Module Config:** Disabled module return 410 (via `config/modules.php` + middleware).
- **Rate Limit:** Auth 120/min, Guest write 30/min, Guest read 80/min.

## 8. Keputusan Produk (Non-Negotiable)
- **OWNER hanya Read-Only.**
- **POS tidak kenal SubCompany.**
- **Transfer Admin → Mandor:** Admin input EXPENSE → Mandor CONFIRM.
- **Pricing:** 4 harga default, TIDAK bisa diedit per transaksi.
- **Absensi:** Jam masuk WAJIB, jam keluar TOLERIR jika lupa.
- **Potongan/Bonus:** Manual Admin saat payroll.

## 9. File Penting (Referensi Cepat)
- Role: `app/Enums/Role.php`
- Module Config: `config/modules.php`
- Company Scope: `app/Models/Scopes/CompanyScope.php`
- Employees: `app/Http/Controllers/Api/EmployeeController.php`
- Ops Config: `app/Services/Operational/OpsOperationalConfigService.php`
- SubCompany: `app/Services/SubCompanyService.php`