<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class SensorData extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'sensor_data';

    protected $fillable = [
        'device_uuid',
        'timestamp_unix',
        'sample_count',
        'window_seconds',
        'steps',
        'rumination_events',
        'temperatura_animal',
        'samples',
    ];

    protected $casts = [
        'samples' => 'array',
        'temperatura_animal' => 'float',
    ];
}
