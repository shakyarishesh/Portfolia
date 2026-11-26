<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposedChange extends Model
{
    use \App\Models\Concerns\BelongsToPortfolio;

    public const CLASSIFICATIONS = ['new' => 'New', 'changed' => 'Adds detail', 'duplicate' => 'Duplicate', 'contradiction' => 'Contradiction'];

    protected $guarded = ['id'];

    protected $casts = [
        'proposed' => 'array',
        'current' => 'array',
        'confidence' => 'float',
        'reviewed_at' => 'datetime',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(ConversationImport::class, 'conversation_import_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ImportedConversation::class, 'imported_conversation_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
