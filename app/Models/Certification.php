<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Model;

class Certification extends Model
{
    use \App\Models\Concerns\BelongsToPortfolio;

    use Auditable, Publishable;

    protected $guarded = ['id'];

    protected $casts = ['issued_on' => 'date', 'expires_on' => 'date'];
}
