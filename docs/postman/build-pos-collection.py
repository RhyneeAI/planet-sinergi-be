#!/usr/bin/env python3
"""Generate docs/postman/pos-api.postman_collection.json from route definitions."""
import json
from pathlib import Path

BASE = "{{base_url}}"
HEADERS_JSON = [
    {"key": "Accept", "value": "application/json"},
    {"key": "Accept-Language", "value": "{{lang}}"},
]
HEADERS_JSON_BODY = HEADERS_JSON + [{"key": "Content-Type", "value": "application/json"}]
AUTH = {"type": "bearer", "bearer": [{"key": "token", "value": "{{token}}", "type": "string"}]}


def url(path_segments, query=None, *, pos=True):
    prefix = ["api", "v1", "pos"] if pos else ["api", "v1"]
    path = [*prefix, *path_segments]
    item = {
        "raw": f"{BASE}/{'/'.join(path)}",
        "host": [BASE],
        "path": path,
    }
    if query:
        item["query"] = query
        qs = "&".join(f"{q['key']}={q['value']}" for q in query if not q.get("disabled"))
        if qs:
            item["raw"] += "?" + qs
    return item


def req(method, path_segments, name, body=None, query=None, description=None, auth=None, test_script=None, pos=True):
    request = {
        "method": method,
        "header": HEADERS_JSON_BODY if body is not None else HEADERS_JSON,
        "url": url(path_segments, query, pos=pos),
    }
    if body is not None:
        request["body"] = {"mode": "raw", "raw": body}
    if description:
        request["description"] = description
    if auth is not None:
        request["auth"] = auth
    item = {"name": name, "request": request}
    if test_script:
        item["event"] = [{"listen": "test", "script": {"type": "text/javascript", "exec": test_script}}]
    return item


def folder(name, items, description=None):
    f = {"name": name, "item": items}
    if description:
        f["description"] = description
    return f


SAVE_ULID_FROM_DATA = [
    "const json = pm.response.json();",
    "if (json.data?.ulid) pm.collectionVariables.set('transaction_ulid', json.data.ulid);",
]

FORGOT_VERIFY_TEST = [
    "const json = pm.response.json();",
    "if (json.data?.reset_token) pm.collectionVariables.set('reset_token', json.data.reset_token);",
]

def save_first(field, var_name):
    return [
        "const json = pm.response.json();",
        f"const row = json.data?.data?.[0] || json.data?.[0];",
        f"if (row?.{field}) pm.collectionVariables.set('{var_name}', row.{field});",
    ]


LOGIN_TEST = [
    "const json = pm.response.json();",
    "if (json.data && json.data.token) {",
    "    pm.collectionVariables.set('token', json.data.token);",
    "}",
]

collection = {
    "info": {
        "name": "Gudang Planet — POS API (v2.7)",
        "description": (
            "Dokumentasi lengkap REST API modul POS Gudang Planet v2.7.\n\n"
            "**Base URL:** `{{base_url}}/api/v1/pos`\n\n"
            "**Auth:** `POST /api/v1/login` → set `{{token}}`\n\n"
            "**Credential demo:**\n"
            "| Role | Phone | Password |\n|------|-------|----------|\n"
            "| KASIR | 087777888888 | kasir_gp |\n"
            "| ADMIN | 081122223333 | admin_gp |\n"
            "| GUDANG | (lihat seeder) | — |\n\n"
            "**Header wajib:**\n"
            "- `Accept: application/json`\n"
            "- `Accept-Language: {{lang}}` (id/en)\n"
            "- `Authorization: Bearer {{token}}`\n\n"
            "**Modul sales:** `sales-transactions`, `sales-installments`, `sales-transaction-returns`\n\n"
            "**Marketing CRUD:** dilakukan di OPS (`/api/v1/operational/marketings`). POS hanya read picker.\n\n"
            "Folder **Legacy** = endpoint lama yang masih ada di backend tetapi tidak dipakai di POS v2."
        ),
        "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json",
    },
    "variable": [
        {"key": "base_url", "value": "http://localhost:8000", "type": "string"},
        {"key": "token", "value": "", "type": "string"},
        {"key": "lang", "value": "id", "type": "string"},
        {"key": "category_uuid", "value": "", "type": "string"},
        {"key": "unit_uuid", "value": "", "type": "string"},
        {"key": "product_uuid", "value": "", "type": "string"},
        {"key": "marketing_uuid", "value": "", "type": "string"},
        {"key": "marketing_lead_uuid", "value": "", "type": "string"},
        {"key": "transaction_ulid", "value": "", "type": "string"},
        {"key": "sales_detail_uuid", "value": "", "type": "string"},
        {"key": "installment_ulid", "value": "", "type": "string"},
        {"key": "return_ulid", "value": "", "type": "string"},
        {"key": "supplier_uuid", "value": "", "type": "string"},
        {"key": "customer_type_uuid", "value": "", "type": "string"},
        {"key": "customer_uuid", "value": "", "type": "string"},
        {"key": "marketing_product_uuid", "value": "", "type": "string"},
        {"key": "purchase_transaction_ulid", "value": "", "type": "string"},
        {"key": "purchase_installment_ulid", "value": "", "type": "string"},
        {"key": "reset_token", "value": "", "type": "string"},
    ],
    "auth": AUTH,
    "item": [
        folder("Auth", [
            req("POST", ["login"], "Login", '{\n  "phone": "087777888888",\n  "password": "kasir_gp"\n}', auth={"type": "noauth"}, test_script=LOGIN_TEST, pos=False),
            req("POST", ["logout"], "Logout", pos=False),
            req("POST", ["reset-password"], "Reset Password (authenticated)", '{\n  "password": "newpassword123",\n  "password_confirmation": "newpassword123"\n}', pos=False),
            req("POST", ["forgot-password", "verify"], "Forgot Password — Verify Phone", '{\n  "phone": "087777888888"\n}', auth={"type": "noauth"}, test_script=FORGOT_VERIFY_TEST, pos=False),
            req("POST", ["forgot-password", "reset"], "Forgot Password — Reset", '{\n  "password": "newpassword123",\n  "password_confirmation": "newpassword123"\n}', auth={"type": "bearer", "bearer": [{"key": "token", "value": "{{reset_token}}", "type": "string"}]}, description="Gunakan `reset_token` dari langkah Verify (bukan token login).", pos=False),
        ], "Autentikasi Sanctum token."),
        folder("Profile & Home", [
            req("GET", ["profile"], "Get Profile", pos=False),
            req("PATCH", ["profile"], "Update Profile", '{\n  "name": "Kasir GP",\n  "email": "kasir@example.com"\n}', pos=False),
            req("GET", ["home"], "Dashboard Home — Day", query=[{"key": "period", "value": "day"}]),
            req("GET", ["home"], "Dashboard Home — Month", query=[{"key": "period", "value": "month"}]),
            req("GET", ["home"], "Dashboard Home — Year", query=[{"key": "period", "value": "year"}]),
        ]),
        folder("Categories", [
            req("GET", ["categories"], "List Categories", test_script=save_first("uuid", "category_uuid")),
            req("GET", ["categories", "{{category_uuid}}"], "Show Category"),
            req("POST", ["categories"], "Create Category", '{\n  "name": "Kategori Contoh"\n}'),
            req("PUT", ["categories", "{{category_uuid}}"], "Update Category", '{\n  "name": "Kategori Updated"\n}'),
            req("DELETE", ["categories", "{{category_uuid}}"], "Delete Category"),
        ], "Role write: SUPERADMIN, ADMIN, GUDANG, KEPALA_GUDANG, KEPALA_MANDOR"),
        folder("Units", [
            req("GET", ["units"], "List Units", test_script=save_first("uuid", "unit_uuid")),
            req("GET", ["units", "{{unit_uuid}}"], "Show Unit"),
            req("POST", ["units"], "Create Unit", '{\n  "name": "Pcs"\n}'),
            req("PUT", ["units", "{{unit_uuid}}"], "Update Unit", '{\n  "name": "Pieces"\n}'),
            req("DELETE", ["units", "{{unit_uuid}}"], "Delete Unit"),
        ]),
        folder("Products (v2.7 Pricing)", [
            req("GET", ["products", "generate-code"], "Generate Product Code"),
            req("GET", ["products"], "List Products", query=[{"key": "per_page", "value": "15"}], test_script=save_first("uuid", "product_uuid")),
            req("GET", ["products", "{{product_uuid}}"], "Show Product"),
            req("POST", ["products"], "Create Product", (
                '{\n  "name": "Produk Contoh",\n  "code": "PRD-001",\n'
                '  "base_price": 20000,\n  "leader_price": 25000,\n'
                '  "marketing_price": 26000,\n  "sell_price": 30000,\n'
                '  "category_uuid": "{{category_uuid}}",\n  "unit_uuid": "{{unit_uuid}}",\n'
                '  "stock": 100,\n  "min_stock": 10,\n  "description": "Produk contoh"\n}'
            )),
            req("PUT", ["products", "{{product_uuid}}"], "Update Product", (
                '{\n  "name": "Produk Updated",\n  "leader_price": 24000,\n'
                '  "marketing_price": 25500,\n  "sell_price": 29000,\n  "stock": 90\n}'
            )),
            req("DELETE", ["products", "{{product_uuid}}"], "Delete Product"),
        ], "Pricing: base_price, leader_price, marketing_price, sell_price"),
        folder("Stock", [
            req("GET", ["stock-mutations", "products"], "List Stock Mutations (by products)"),
            req("GET", ["stock-mutations", "products", "{{product_uuid}}"], "Stock Mutations by Product"),
            req("POST", ["stock-mutations"], "Create Stock Mutation", (
                '{\n  "product_uuid": "{{product_uuid}}",\n'
                '  "type": "ADJUST_IN",\n  "quantity": 5,\n  "notes": "Stok masuk manual"\n}'
            ), description="type: ADJUST_IN | ADJUST_OUT | OPNAME"),
        ]),
        folder("Marketing (v2.7 — Picker & Reports)", [
            req("GET", ["marketings"], "List Marketings", query=[
                {"key": "per_page", "value": "15"},
                {"key": "role", "value": "", "disabled": True, "description": "MARKETING_LEAD|MARKETING|MARKETING_TETAP"},
            ], test_script=[
                "const json = pm.response.json();",
                "const rows = json.data?.data || json.data || [];",
                "if (rows[0]?.uuid) pm.collectionVariables.set('marketing_uuid', rows[0].uuid);",
                "const lead = rows.find((r) => r.role === 'MARKETING_LEAD');",
                "if (lead) pm.collectionVariables.set('marketing_lead_uuid', lead.uuid);",
            ]),
            req("GET", ["marketings"], "List — MARKETING", query=[{"key": "role", "value": "MARKETING"}]),
            req("GET", ["marketings"], "List — MARKETING_LEAD", query=[{"key": "role", "value": "MARKETING_LEAD"}]),
            req("GET", ["marketings"], "List — MARKETING_TETAP", query=[{"key": "role", "value": "MARKETING_TETAP"}]),
            req("GET", ["marketings", "{{marketing_uuid}}"], "Show Marketing"),
            req("GET", ["reports", "marketing-commission"], "Report Marketing Commission", query=[
                {"key": "date_from", "value": "2026-06-01"},
                {"key": "date_to", "value": "2026-06-30"},
                {"key": "marketing_uuid", "value": "{{marketing_uuid}}", "disabled": True},
            ]),
            req("GET", ["reports", "sales-revenue"], "Report Sales Revenue", query=[
                {"key": "date_from", "value": "2026-06-01"},
                {"key": "date_to", "value": "2026-06-30"},
                {"key": "marketing_uuid", "value": "{{marketing_uuid}}", "disabled": True},
            ]),
        ], "CRUD marketing di OPS. POS read-only + laporan komisi."),
        folder("Sales Transactions", [
            req("GET", ["sales-transactions"], "List Sales Transactions", query=[
                {"key": "per_page", "value": "15"},
                {"key": "search", "value": "", "disabled": True},
                {"key": "date_from", "value": "", "disabled": True},
                {"key": "date_to", "value": "", "disabled": True},
                {"key": "status", "value": "", "disabled": True, "description": "COMPLETED|CANCELLED|..."},
                {"key": "order_by_key", "value": "transaction_date", "disabled": True},
                {"key": "order_by_value", "value": "DESC", "disabled": True},
            ], test_script=save_first("ulid", "transaction_ulid")),
            req("POST", ["sales-transactions"], "Create — CASH", (
                '{\n  "transaction_date": "2026-06-25 10:00:00",\n'
                '  "marketing_uuid": "{{marketing_uuid}}",\n'
                '  "payment_type": "CASH",\n  "discount": 0,\n'
                '  "total": 60000,\n  "paid": 60000,\n'
                '  "items": [{\n    "product_uuid": "{{product_uuid}}",\n'
                '    "quantity": 2,\n    "sell_price": 30000,\n'
                '    "marketing_price": 26000,\n    "discount": 0\n  }]\n}'
            ), test_script=SAVE_ULID_FROM_DATA),
            req("POST", ["sales-transactions"], "Create — CICIL (DP)", (
                '{\n  "transaction_date": "2026-06-25 10:00:00",\n'
                '  "marketing_uuid": "{{marketing_uuid}}",\n'
                '  "payment_type": "CICIL",\n  "down_payment": 10000,\n'
                '  "discount": 0,\n  "total": 60000,\n'
                '  "items": [{\n    "product_uuid": "{{product_uuid}}",\n'
                '    "quantity": 2,\n    "sell_price": 30000,\n'
                '    "marketing_price": 26000,\n    "discount": 0\n  }]\n}'
            )),
            req("GET", ["sales-transactions", "{{transaction_ulid}}"], "Show Sales Transaction"),
            req("PATCH", ["sales-transactions", "{{transaction_ulid}}", "cancel"], "Cancel Sales Transaction"),
        ], "Role: SUPERADMIN, OWNER, ADMIN, KEPALA_GUDANG, KEPALA_MANDOR, GUDANG, KASIR"),
        folder("Sales Installments", [
            req("GET", ["sales-installments"], "List Installment Plans", test_script=save_first("ulid", "installment_ulid")),
            req("GET", ["sales-installments", "{{installment_ulid}}"], "Show Installment Plan"),
            req("POST", ["sales-installments", "{{installment_ulid}}", "pay"], "Pay Installment", (
                '{\n  "paid_amount": 25000,\n  "notes": "Cicilan ke-1"\n}'
            )),
        ]),
        folder("Sales Transaction Returns", [
            req("GET", ["sales-transaction-returns"], "List Returns", test_script=save_first("ulid", "return_ulid")),
            req("POST", ["sales-transaction-returns"], "Create Return", (
                '{\n  "sales_transaction_uuid": "{{transaction_ulid}}",\n'
                '  "sales_detail_uuid": "{{sales_detail_uuid}}",\n'
                '  "product_uuid": "{{product_uuid}}",\n'
                '  "qty": 1,\n  "reason": "Produk cacat",\n'
                '  "refund_amount": 30000\n}'
            ), description=(
                "Retur item dari transaksi penjualan. Stok dikembalikan otomatis.\n\n"
                "**sales_detail_uuid:** ambil dari kolom `ulid` tabel `pos_sales_details` "
                "(belum diekspos di response Show Transaction). Set variabel `{{sales_detail_uuid}}` manual."
            )),
        ], "Modul retur penjualan (sebelumnya `/returns`)."),
        folder("Legacy — Master & Purchase", [
            folder("Suppliers", [
                req("GET", ["suppliers"], "List Suppliers", test_script=save_first("uuid", "supplier_uuid")),
                req("GET", ["suppliers", "{{supplier_uuid}}"], "Show Supplier"),
                req("POST", ["suppliers"], "Create Supplier", '{\n  "name": "Supplier Contoh",\n  "phone": "081200000001"\n}'),
                req("PUT", ["suppliers", "{{supplier_uuid}}"], "Update Supplier", '{\n  "name": "Supplier Updated"\n}'),
                req("DELETE", ["suppliers", "{{supplier_uuid}}"], "Delete Supplier"),
            ]),
            folder("Customer Types", [
                req("GET", ["customer-types"], "List Customer Types", test_script=save_first("uuid", "customer_type_uuid")),
                req("GET", ["customer-types", "{{customer_type_uuid}}"], "Show Customer Type"),
                req("POST", ["customer-types"], "Create Customer Type", '{\n  "type": "Retail",\n  "discount": 0\n}'),
                req("PUT", ["customer-types", "{{customer_type_uuid}}"], "Update Customer Type", '{\n  "type": "Grosir",\n  "discount": 5\n}'),
                req("DELETE", ["customer-types", "{{customer_type_uuid}}"], "Delete Customer Type"),
            ]),
            folder("Customers", [
                req("GET", ["customers"], "List Customers", test_script=save_first("uuid", "customer_uuid")),
                req("GET", ["customers", "{{customer_uuid}}"], "Show Customer"),
                req("POST", ["customers"], "Create Customer", (
                    '{\n  "name": "Pelanggan Contoh",\n  "phone": "081300000001",\n'
                    '  "customer_type_uuid": "{{customer_type_uuid}}"\n}'
                )),
                req("PUT", ["customers", "{{customer_uuid}}"], "Update Customer", '{\n  "name": "Pelanggan Updated"\n}'),
                req("DELETE", ["customers", "{{customer_uuid}}"], "Delete Customer"),
            ]),
            folder("Marketing Products", [
                req("GET", ["marketing-products"], "List Marketing Products", test_script=save_first("uuid", "marketing_product_uuid")),
                req("GET", ["marketing-products", "{{marketing_product_uuid}}"], "Show Marketing Product"),
                req("POST", ["marketing-products"], "Create Marketing Product", (
                    '{\n  "product_uuid": "{{product_uuid}}",\n'
                    '  "marketing_uuid": "{{marketing_uuid}}",\n'
                    '  "marketing_price": 26500\n}'
                )),
                req("PUT", ["marketing-products", "{{marketing_product_uuid}}"], "Update Marketing Product", '{\n  "marketing_price": 27000\n}'),
                req("DELETE", ["marketing-products", "{{marketing_product_uuid}}"], "Delete Marketing Product"),
            ]),
            folder("Purchase Transactions", [
                req("GET", ["purchase-transactions"], "List Purchase Transactions", test_script=save_first("ulid", "purchase_transaction_ulid")),
                req("POST", ["purchase-transactions"], "Create Purchase Transaction", (
                    '{\n  "supplier_uuid": "{{supplier_uuid}}",\n'
                    '  "transaction_date": "2026-06-25 10:00:00",\n'
                    '  "discount": 0,\n  "total": 40000,\n  "paid": 40000,\n'
                    '  "payment_type": "CASH",\n'
                    '  "items": [{\n    "product_uuid": "{{product_uuid}}",\n'
                    '    "quantity": 2,\n    "buy_price": 20000\n  }]\n}'
                )),
                req("GET", ["purchase-transactions", "{{purchase_transaction_ulid}}"], "Show Purchase Transaction"),
                req("PATCH", ["purchase-transactions", "{{purchase_transaction_ulid}}", "cancel"], "Cancel Purchase Transaction"),
            ]),
            folder("Purchase Installments", [
                req("GET", ["purchase-installments"], "List Purchase Installment Plans", test_script=save_first("ulid", "purchase_installment_ulid")),
                req("GET", ["purchase-installments", "{{purchase_installment_ulid}}"], "Show Purchase Installment Plan"),
                req("POST", ["purchase-installments", "{{purchase_installment_ulid}}", "pay"], "Pay Purchase Installment", '{\n  "paid_amount": 50000\n}'),
            ]),
        ], "Endpoint legacy — tidak dipakai di alur POS v2 utama."),
    ],
}

out = Path(__file__).resolve().parent / "pos-api.postman_collection.json"
out.write_text(json.dumps(collection, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
print(f"Wrote {out}")
