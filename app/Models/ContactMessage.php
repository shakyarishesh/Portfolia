<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    use \App\Models\Concerns\BelongsToPortfolio;

    protected $guarded = ['id'];

    protected $casts = ['read_at' => 'datetime', 'replied_at' => 'datetime', 'archived_at' => 'datetime'];
}
