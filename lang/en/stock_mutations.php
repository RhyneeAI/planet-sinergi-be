<?php

return [
    // Response messages
    'list' => 'Stock mutation list retrieved successfully.',
    'product_list' => 'Product list retrieved successfully.',
    'stored' => 'Stock mutation created successfully.',
    'detail' => 'Stock mutation detail retrieved successfully.',
    'unauthorized' => 'You are not authorized to access this stock mutation.',

    // Validation messages
    'validation' => [
        'type_required' => 'Mutation type is required.',
        'type_in' => 'Invalid mutation type.',
        'quantity_required' => 'Quantity is required.',
        'quantity_integer' => 'Quantity must be an integer.',
        'quantity_min' => 'Quantity must be at least 1.',
        'product_uuid_required' => 'Product is required.',
        'product_uuid_exists' => 'Selected product does not exist.',
        'stock_before_integer' => 'Stock before must be an integer.',
        'stock_after_integer' => 'Stock after must be an integer.',
    ],
];