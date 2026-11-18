<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Model;

class SocialLink extends Model
{
    use \App\Models\Concerns\BelongsToPortfolio;

    use Auditable, Publishable;

    public const PLATFORMS = ['github' => 'GitHub', 'linkedin' => 'LinkedIn', 'website' => 'Website', 'x' => 'X / Twitter', 'other' => 'Other'];

    protected $guarded = ['id'];
}
