<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportedConversation extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['started_at' => 'datetime', 'transcript' => 'array'];

    /** @return array<int, array{role: string, text: string}> */
    public function messages(): array
    {
        return $this->transcript['messages'] ?? [];
    }

    public function humanText(): string
    {
        return collect($this->messages())->where('role', 'human')->pluck('text')->implode("\n\n");
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(ConversationImport::class, 'conversation_import_id');
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(ProposedChange::class);
    }
}
