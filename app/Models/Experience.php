<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTechnologies;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Experience extends Model
{
    use Auditable, HasTechnologies, Publishable;

    public const TYPES = ['full_time' => 'Full-time', 'part_time' => 'Part-time', 'internship' => 'Internship', 'contract' => 'Contract', 'freelance' => 'Freelance'];

    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
        'highlights' => 'array',
    ];

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function period(): string
    {
        $end = $this->is_current ? 'Present' : ($this->end_date?->format('M Y') ?? '');

        return trim($this->start_date?->format('M Y').' – '.$end, ' –');
    }
}
