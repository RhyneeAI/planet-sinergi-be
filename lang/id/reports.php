<?php

return [
    'validation' => [
        'marketingCommission' => [
            'date_from_required'      => 'Tanggal awal wajib diisi.',
            'date_to_required'        => 'Tanggal akhir wajib diisi.',
            'date_to_after'           => 'Tanggal akhir harus sama dengan atau setelah tanggal awal.',
            'marketing_uuid_invalid'  => 'Format UUID marketing tidak valid.',
            'marketing_not_found'     => 'Marketing tidak ditemukan.',
        ]
    ],
];