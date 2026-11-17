<?php

namespace App\Services\Import\Extractors;

use App\Models\ImportedConversation;

/** An AI provider that turns a conversation into candidate profile updates. */
interface LlmExtractor
{
    public function name(): string;

    /**
     * @param  string  $portfolioSnapshot  short text summary of what the portfolio already contains
     * @return array<int, Candidate>
     *
     * @throws ExtractionException
     */
    public function extract(ImportedConversation $conversation, string $portfolioSnapshot): array;
}
