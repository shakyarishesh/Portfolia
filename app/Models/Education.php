<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Model;

class Education extends Model
{
    use \App\Models\Concerns\BelongsToPortfolio;

    use Auditable, Publishable;

    protected $table = 'education';

    protected $guarded = ['id'];

    public function period(): string
    {
        return collect([$this->start_year, $this->end_year])->filter()->implode(' – ');
    }
}
