# Release v1.7 — POS Module Refactor

Tanggal: 2026-06-22

## Breaking Changes
**Wajib `migrate:fresh --seed`** karena rename tabel POS.

## Perubahan

### Database — Prefix `pos_` pada semua tabel POS
16 tabel di-rename konsisten dengan modul `abs_` dan `ops_`:

| Sebelum | Sesudah |
|---|---|
| `categories` | `pos_categories` |
| `customers` | `pos_customers` |
| `customer_types` | `pos_customer_types` |
| `marketing_products` | `pos_marketing_products` |
| `products` | `pos_products` |
| `purchase_details` | `pos_purchase_details` |
| `purchase_installment_payments` | `pos_purchase_installment_payments` |
| `purchase_installment_plans` | `pos_purchase_installment_plans` |
| `purchase_transactions` | `pos_purchase_transactions` |
| `sales_details` | `pos_sales_details` |
| `sales_installment_payments` | `pos_sales_installment_payments` |
| `sales_installment_plans` | `pos_sales_installment_plans` |
| `sales_transactions` | `pos_sales_transactions` |
| `stock_mutations` | `pos_stock_mutations` |
| `suppliers` | `pos_suppliers` |
| `units` | `pos_units` |

### File Structure — Konsistensi Modul
- **Controllers**: Semua POS controller di-prefix `Pos` (e.g., `ProductController` → `PosProductController`)
- **Requests/Resources**: Semua POS request & resource di-prefix `Pos`
- **Factories**: Dipindah ke `database/factories/{Abs,Ops,Pos}/`
- **Tests**: Dipindah ke `tests/Feature/Api/{Abs,Ops,Pos}/`
- **Lang**: File lang POS digabung dari 14 file → 1 file `pos.php` (nested array)

### Model
- Menambahkan `protected static $factory` pada 25 model module agar dapat menemukan factory di subfolder
- Menambahkan `protected $model` pada 14 factory Abs & Ops

### lainnya
- Fix duplicate key di `lang/en/operational.php`

## Catatan Deploy
```bash
php artisan migrate:fresh --seed
```
