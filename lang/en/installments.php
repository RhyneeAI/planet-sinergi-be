<?php

return [
    'list'              => 'Installment list retrieved successfully.',
    'detail'            => 'Installment detail retrieved successfully.',
    'payment_recorded'  => 'Installment payment recorded successfully.',
    'completed'         => 'Installment fully paid.',
    'already_completed' => 'This installment has already been completed.',
    'must_pay_full'     => 'Tenor has ended. Must pay the remaining balance of Rp :remaining.',
    'overpaid'          => 'Payment amount exceeds remaining balance of Rp :remaining.',
    'validation'        => [
        'paid_amount_required' => 'Payment amount is required.',
        'paid_amount_numeric'  => 'Payment amount must be a number.',
        'paid_amount_min'      => 'Payment amount must be at least 1.',
    ],
];