<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardMetricSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'snapshot_date',
        'metric_key',
        'value',
        'created_at',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'value' => 'integer',
        'created_at' => 'datetime',
    ];
}
