<?php

return [
    // Response messages
    'list' => 'Daftar mutasi stok berhasil diambil.',
    'product_list' => 'Daftar produk berhasil diambil.',
    'detail' => 'Detail mutasi stok berhasil diambil.',
    'stored' => 'Mutasi stok berhasil dibuat.',
    'unauthorized' => 'Anda tidak memiliki izin mengakses mutasi stok ini.',

    // Validation messages
    'validation' => [
        'type_required' => 'Tipe mutasi wajib diisi.',
        'type_in' => 'Tipe mutasi tidak valid.',
        'quantity_required' => 'Jumlah wajib diisi.',
        'quantity_integer' => 'Jumlah harus berupa bilangan bulat.',
        'quantity_min' => 'Jumlah minimal 1.',
        'product_uuid_required' => 'Produk wajib diisi.',
        'product_uuid_exists' => 'Produk yang dipilih tidak valid.',
        'stock_before_integer' => 'Stok awal harus berupa bilangan bulat.',
        'stock_after_integer' => 'Stok akhir harus berupa bilangan bulat.',
    ],
];