<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactStatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'contact_id',
        'user_id',
        'old_status',
        'new_status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

