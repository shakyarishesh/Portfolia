<?php

namespace App\Jobs;

use App\Models\ConversationImport;
use App\Services\Import\Extractors\ExtractionException;
use App\Services\Import\Extractors\LlmExtractor;
use App\Services\Import\ImportService;
use App\Services\Import\ProposalBuilder;
use App\Support\CurrentPortfolio;
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
        // Queue workers have no request: work inside the import's own portfolio.
        $import = ConversationImport::withoutGlobalScope('portfolio')->findOrFail($this->importId);
        CurrentPortfolio::set($import->portfolio);
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
        ConversationImport::withoutGlobalScope('portfolio')->whereKey($this->importId)->update(['status' => 'ai_failed', 'error' => 'AI extraction crashed: '.$e->getMessage()]);
    }
}
