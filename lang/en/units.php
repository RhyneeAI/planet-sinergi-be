<?php

return [
    // Response messages
    'stored'   => 'Unit created successfully.',
    'updated'  => 'Unit updated successfully.',
    'deleted'  => 'Unit deleted successfully.',
    'list'     => 'Unit list retrieved successfully.',
    'detail'   => 'Unit detail retrieved successfully.',
    'not_found' => 'Unit not found.',
    'has_products' => 'Unit has products.',
    'unauthorized' => 'You are not authorized to access this unit.',
    
    // Validation messages
    'validation' => [
        'name_required' => 'The unit name is required.',
        'name_string'   => 'The unit name must be a string.',
        'name_max'      => 'The unit name may not be greater than 255 characters.',
        'name_unique'   => 'The unit name has already been taken.',
    ],
];