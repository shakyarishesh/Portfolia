<?php

namespace App\Jobs;

use App\Models\ConversationImport;
use App\Services\Import\Extractors\ExtractionException;
use App\Services\Import\Extractors\LlmExtractor;
use App\Services\Import\ImportService;
use App\Services\Import\ProposalBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ExtractWithAi implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    /** @param array<int, int> $conversationIds */
    public function __construct(public int $importId, public array $conversationIds) {}

    public function handle(LlmExtractor $extractor, ProposalBuilder $builder): void
    {
        $import = ConversationImport::findOrFail($this->importId);
        $import->update(['status' => 'ai_running']);
        $snapshot = ImportService::snapshot();
        $errors = [];

        $conversations = $import->conversations()->whereKey($this->conversationIds)->get();
        foreach ($conversations as $conversation) {
            try {
                foreach ($extractor->extract($conversation, $snapshot) as $candidate) {
                    $builder->build($candidate, 'llm', $import);
                }
            } catch (ExtractionException $e) {
                Log::warning('AI extraction failed', ['import' => $import->id, 'conversation' => $conversation->id, 'error' => $e->getMessage()]);
                $errors[] = "“{$conversation->title}”: {$e->getMessage()}";
            }
        }

        $import->update([
            'status' => $errors && count($errors) === $conversations->count() ? 'ai_failed' : 'extracted',
            'error' => $errors ? implode("\n", $errors) : null,
            'extractors' => array_values(array_unique([...($import->extractors ?? []), 'llm'])),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        ConversationImport::whereKey($this->importId)->update(['status' => 'ai_failed', 'error' => 'AI extraction crashed: '.$e->getMessage()]);
    }
}
