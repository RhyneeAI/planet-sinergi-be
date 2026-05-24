<?php

return [
    // ========== RESPONSE MESSAGES ==========
    'stored' => 'Product created successfully.',
    'updated' => 'Product updated successfully.',
    'deleted' => 'Product deleted successfully.',
    'list' => 'Product list retrieved successfully.',
    'detail' => 'Product detail retrieved successfully.',
    'not_found' => 'Product not found.',
    'has_relations' => 'Product cannot be deleted because it is still used in transactions.',
    'unauthorized' => 'You are not authorized to access this product.',

    // ========== VALIDATION MESSAGES ==========
    'validation' => [
        'name_required' => 'The product name is required.',
        'name_string' => 'The product name must be a string.',
        'name_max' => 'The product name may not be greater than :max characters.',
        'code_unique' => 'The product code has already been taken.',
        'base_price_numeric' => 'The base price must be a number.',
        'sales_price_required' => 'The sales price is required.',
        'sales_price_numeric' => 'The sales price must be a number.',
        'marketing_price_numeric' => 'Marketing price must be a number.',
        'marketing_price_min'     => 'Marketing price cannot be negative.',
        'stock_integer' => 'The stock must be an integer.',
        'min_stock_integer' => 'The minimum stock must be an integer.',
        'category_uuid_exists' => 'Selected category does not exist.',
        'unit_uuid_exists' => 'Selected unit does not exist.',
    ],
];