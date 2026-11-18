<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConversationImport extends Model
{
    use \App\Models\Concerns\BelongsToPortfolio;

    public const SOURCES = ['claude_export' => 'Claude.ai export', 'claude_code' => 'Claude Code transcript', 'text' => 'Pasted / plain text'];

    protected $guarded = ['id'];

    protected $casts = ['extractors' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(ImportedConversation::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(ProposedChange::class);
    }
}
