<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['venue_id', 'name', 'type', 'phone', 'email', 'instagram', 'next_step', 'notes'])]
class Contact extends Model
{
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
