<?php

return [
    // Response messages
    'stored'   => 'Satuan berhasil dibuat.',
    'updated'  => 'Satuan berhasil diperbarui.',
    'deleted'  => 'Satuan berhasil dihapus.',
    'list'     => 'Daftar satuan berhasil diambil.',
    'detail'   => 'Detail satuan berhasil diambil.',
    'not_found' => 'Satuan tidak ditemukan.',
    'has_products' => 'Satuan ini masih digunakan oleh produk.',
    'unauthorized' => 'Anda tidak memiliki izin mengakses satuan ini.',
    
    // Validation messages
    'validation' => [
        'name_required' => 'Nama satuan wajib diisi.',
        'name_string'   => 'Nama satuan harus berupa teks.',
        'name_max'      => 'Nama satuan tidak boleh lebih dari 255 karakter.',
        'name_unique'   => 'Nama satuan sudah digunakan.',
    ],
];