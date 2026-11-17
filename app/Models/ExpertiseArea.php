<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Model;

class ExpertiseArea extends Model
{
    use Auditable, Publishable;

    protected $guarded = ['id'];
}
