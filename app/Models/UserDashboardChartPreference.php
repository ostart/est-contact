<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDashboardChartPreference extends Model
{
    protected $fillable = [
        'user_id',
        'metrics',
        'filter_state',
        'y_scale',
        'y_min',
        'y_max',
        'y_logarithmic',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'metrics' => 'array',
            'filter_state' => 'array',
            'y_min' => 'integer',
            'y_max' => 'integer',
            'y_logarithmic' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
