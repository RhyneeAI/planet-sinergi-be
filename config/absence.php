<?php

return [
    'timezone' => env('ABS_TIMEZONE', 'Asia/Jakarta'),
    'default_radius_meter' => (int) env('ABS_DEFAULT_RADIUS_METER', 50),
    'photo_disk' => 'public',
    'photo_directory' => 'abs/photos',
    'attended_statuses' => [
        'hadir',
        'terlambat',
        'pulang_awal',
        'terlambat_pulang_awal',
    ],
    'overtime_hourly_rate' => (int) env('ABS_OVERTIME_HOURLY_RATE', 25000),
];
