<?php

return [
    'income_store_backdate_days' => (int) env('OPS_INCOME_STORE_BACKDATE_DAYS', 3),
    'income_edit_days_after_create' => (int) env('OPS_INCOME_EDIT_DAYS_AFTER_CREATE', 3),
    'expense_store_backdate_days' => (int) env('OPS_EXPENSE_STORE_BACKDATE_DAYS', 1),
    'expense_edit_days_after_create' => (int) env('OPS_EXPENSE_EDIT_DAYS_AFTER_CREATE', 1),
    'expense_max_edit_count'   => (int) env('OPS_EXPENSE_MAX_EDIT_COUNT', 1),
    'max_sub_companies_per_mandor' => (int) env('OPS_MAX_SUB_COMPANIES_PER_MANDOR', 10),
    'proof_disk'               => 'public',
    'proof_directories'        => [
        'admin' => [
            'income'   => 'operational/proofs/admin/incomes',
            'expense'  => 'operational/proofs/admin/expenses',
            'transfer' => 'operational/proofs/admin/transfers',
        ],
        'mandor' => [
            'income'   => 'operational/proofs/mandor/incomes',
            'expense'  => 'operational/proofs/mandor/expenses',
            'transfer' => 'operational/proofs/mandor/transfers',
        ],
    ],
];
