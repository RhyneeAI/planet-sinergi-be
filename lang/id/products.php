<?php

return [
    // ========== RESPONSE MESSAGES ==========
    'stored' => 'Produk berhasil dibuat.',
    'updated' => 'Produk berhasil diperbarui.',
    'deleted' => 'Produk berhasil dihapus.',
    'list' => 'Daftar produk berhasil diambil.',
    'detail' => 'Detail produk berhasil diambil.',
    'not_found' => 'Produk tidak ditemukan.',
    'has_relations' => 'Produk tidak dapat dihapus karena masih digunakan dalam transaksi.',
    'unauthorized' => 'Anda tidak memiliki izin mengakses produk ini.',

    // ========== VALIDATION MESSAGES ==========
    'validation' => [
        'name_required' => 'Nama produk wajib diisi.',
        'name_string' => 'Nama produk harus berupa teks.',
        'name_max' => 'Nama produk tidak boleh lebih dari :max karakter.',
        'code_unique' => 'Kode produk sudah digunakan.',
        'base_price_numeric' => 'Harga modal harus berupa angka.',
        'sales_price_required' => 'Harga jual wajib diisi.',
        'sales_price_numeric' => 'Harga jual harus berupa angka.',
        'marketing_price_numeric' => 'Harga marketing harus berupa angka.',
        'marketing_price_min'     => 'Harga marketing tidak boleh negatif.',
        'stock_integer' => 'Stok harus berupa bilangan bulat.',
        'min_stock_integer' => 'Stok minimal harus berupa bilangan bulat.',
        'category_uuid_exists' => 'Kategori yang dipilih tidak valid.',
        'unit_uuid_exists' => 'Satuan yang dipilih tidak valid.',
    ],
];