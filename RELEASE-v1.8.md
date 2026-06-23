# v1.8 — New Roles, Production Seeder & Security

## Changes

### Roles
- Add `HRD`, `MANAJER_GUDANG`, `MARKETING_TETAP` cases to `Role` enum
- Rename `MARKETING_LEADER` → `MARKETING_LEAD` for consistency
- Add factory states for all new roles (`hrd()`, `manajerGudang()`, `marketingLead()`, `marketingTetap()`, `kasir()`)

### Production Seeder
- Rewrite `Test/ProductionSeeder` with 2 companies:
  - **Company 1 (GP001 / Test):** Owner + Admin (UAT environment)
  - **Company 2 (GP002 / Go-Live):** Owner + Admin only (no extra inserts)
  - **Superadmin:** global user, no company binding
- Remove `OpsProductionSeeder` (merged into `Test/ProductionSeeder`)
- `DatabaseSeeder` now runs **only `ProductionSeeder`** in production environment; full seeder set for non-production

### Security
- **Hard-block destructive commands in production:**
  - `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe` throw `RuntimeException` when `APP_ENV=production`
  - Implemented via `CommandStarting` event listener in `AppServiceProvider`

### Application Name
- Default `APP_NAME` changed to `Planet Sinergi BE`
- Hardcoded "Gudang Planet" replaced with dynamic `config('app.name')` across blade views and seeders
- `.env`, `.env.production`, `.env.example` updated

### Bug Fixes (backported)
- Fix MANDOR expenses scope on cabang report
- Fix SubCompany phone validation (min length)
- Refactor SubCompany requests into single `SubCompanyRequest`
- Fix flaky `AbsAdminAttendance` unique constraint test

---

## Files Changed
| File | Change |
|------|--------|
| `app/Enums/Role.php` | New roles + rename MARKETING_LEADER → MARKETING_LEAD |
| `app/Providers/AppServiceProvider.php` | Hard-block destructive commands in production |
| `database/seeders/Test/ProductionSeeder.php` | Rewrite: 2 companies + superadmin |
| `database/seeders/OpsProductionSeeder.php` | Deleted (merged) |
| `database/seeders/DatabaseSeeder.php` | Conditional seeder: production vs non-production |
| `database/factories/UserFactory.php` | Factory states for new roles |
| `config/app.php` | Default APP_NAME → Planet Sinergi BE |
| `.env`, `.env.production`, `.env.example` | APP_NAME updated |
| `resources/views/reports/operational/income-expense-excel.blade.php` | Dynamic app name |

## Upgrade Notes
- Run `php artisan migrate:fresh --seed` for fresh install
- For production deployment: `APP_ENV=production php artisan migrate:fresh --force --seed`
