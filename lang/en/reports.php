<?php

return [
    'validation' => [
        'marketingCommission' => [
            'date_from_required'      => 'Start date is required.',
            'date_to_required'        => 'End date is required.',
            'date_to_after'           => 'End date must be equal to or after start date.',
            'marketing_uuid_invalid'  => 'Invalid marketing UUID format.',
            'marketing_not_found'     => 'Marketing not found.',
        ],
        'salesRevenue' => [
            'date_from_required'      => 'Start date is required.',
            'date_to_required'        => 'End date is required.',
            'date_to_after'           => 'End date must be equal to or after start date.',
            'marketing_uuid_invalid'  => 'Invalid marketing UUID format.',
            'marketing_not_found'     => 'Marketing not found.',
        ],
    ],
];