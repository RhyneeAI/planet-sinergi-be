<?php

return [
    'incomes' => [
        'list' => 'Daftar pemasukan operasional berhasil diambil.',
        'detail' => 'Detail pemasukan operasional berhasil diambil.',
        'stored' => 'Pencairan dana berhasil dicatat. Menunggu konfirmasi mandor.',
    ],
    'expenses' => [
        'list' => 'Daftar pengeluaran operasional berhasil diambil.',
        'detail' => 'Detail pengeluaran operasional berhasil diambil.',
        'stored' => 'Pengeluaran berhasil dicatat.',
        'updated' => 'Pengeluaran berhasil diperbarui.',
        'deleted' => 'Pengeluaran berhasil dihapus.',
        'blocked_insufficient_balance' => 'Saldo virtual tidak mencukupi. Pengeluaran diblokir.',
        'edit_window_expired' => 'Pengeluaran hanya dapat diedit dalam :days hari terakhir.',
        'edit_limit_reached' => 'Batas edit untuk pengeluaran ini sudah tercapai.',
    ],
    'confirmations' => [
        'list' => 'Daftar konfirmasi transfer berhasil diambil.',
        'detail' => 'Detail konfirmasi transfer berhasil diambil.',
        'confirmed' => 'Penerimaan dana berhasil dikonfirmasi. Saldo virtual aktif.',
        'rejected' => 'Konfirmasi transfer ditolak.',
        'amount_mismatch' => 'Nominal tidak sesuai. Silakan hubungi admin.',
        'already_processed' => 'Konfirmasi transfer sudah diproses sebelumnya.',
    ],
    'wallet' => [
        'detail' => 'Saldo virtual berhasil diambil.',
        'transactions' => 'Riwayat mutasi saldo berhasil diambil.',
        'insufficient_balance' => 'Saldo virtual tidak mencukupi.',
        'adjustment_debit' => 'Penyesuaian saldo akibat edit pengeluaran (debit).',
        'adjustment_credit' => 'Penyesuaian saldo akibat edit pengeluaran (kredit).',
    ],
    'notifications' => [
        'list' => 'Daftar notifikasi berhasil diambil.',
        'read' => 'Notifikasi ditandai sudah dibaca.',
        'read_all' => 'Semua notifikasi ditandai sudah dibaca.',
        'income_pending' => [
            'title' => 'Dana Masuk Baru',
            'message' => 'Ada dana masuk baru. Silakan konfirmasi penerimaan.',
        ],
        'insufficient_balance' => [
            'title' => 'Pengeluaran Diblokir',
            'message' => ':mandor mencoba mencatat pengeluaran sebesar Rp :amount namun saldo virtual tidak mencukupi.',
        ],
        'expense_created' => [
            'title' => 'Pengeluaran Baru',
            'message' => ':mandor mencatat pengeluaran ":name" sebesar Rp :amount.',
        ],
    ],
    'edit_logs' => [
        'list' => 'Log audit edit berhasil diambil.',
    ],
    'mandors' => [
        'list' => 'Daftar mandor berhasil diambil.',
    ],
    'validation' => [
        'mandor_uuid_required' => 'Mandor wajib dipilih.',
        'mandor_uuid_string' => 'Mandor tidak valid.',
        'mandor_uuid_invalid' => 'Mandor tidak valid.',
        'mandor_uuid_not_found' => 'Mandor tidak ditemukan.',
        'name_required' => 'Nama wajib diisi.',
        'name_string' => 'Nama tidak valid.',
        'name_max' => 'Nama tidak boleh lebih dari 255 karakter.',
        'amount_required' => 'Nominal wajib diisi.',
        'amount_numeric' => 'Nominal harus berupa angka.',
        'amount_min' => 'Nominal minimal 0.',
        'date_required' => 'Tanggal wajib diisi.',
        'date_invalid' => 'Tanggal tidak valid.',
        'proof_file_required' => 'Bukti transfer wajib diupload.',
        'proof_file_file' => 'Bukti transfer harus berupa file.',
        'proof_file_invalid' => 'Format bukti transfer tidak valid.',
        'proof_file_max' => 'Bukti transfer tidak boleh lebih dari 2MB.',
        'reason_required' => 'Alasan edit wajib diisi.',
        'reason_string' => 'Alasan edit harus berupa string.',
        'note_invalid' => 'Catatan tidak valid.',
        'note_max' => 'Catatan tidak boleh lebih dari 255 karakter.',

        'confirmed_amount_required' => 'Nominal konfirmasi wajib diisi.',
        'mandor_proof_required' => 'Bukti rekening wajib diupload.',
    ],
];
