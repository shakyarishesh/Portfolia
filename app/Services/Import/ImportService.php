<?php

namespace App\Services\Import;

use App\Admin\Resources;
use App\Jobs\ExtractWithAi;
use App\Models\ConversationImport;
use App\Models\Profile;
use App\Models\User;
use App\Services\Import\Extractors\KeywordExtractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ImportService
{
    public function __construct(
        private ConversationParser $parser,
        private KeywordExtractor $keywords,
        private ProposalBuilder $builder,
    ) {}

    public static function aiConfigured(): bool
    {
        return config('portfolio.ai.provider') === 'anthropic' && filled(config('portfolio.ai.anthropic.api_key'));
    }

    /** Parse, store and run the keyword scan. AI extraction is a separate, explicit step. */
    public function import(?UploadedFile $file, ?string $text, ?string $title, User $user): ConversationImport
    {
        [$source, $conversations] = $file
            ? $this->parser->parseFile($file->getRealPath(), $file->getClientOriginalName())
            : $this->parser->parseText((string) $text, $title ?: 'Pasted conversation');

        if (! $conversations) {
            throw new ImportException('No conversations with messages were found in this file.');
        }
        $max = config('portfolio.import.max_conversations');
        if (count($conversations) > $max) {
            throw new ImportException('This export has '.count($conversations)." conversations; the limit is {$max}. Raise PORTFOLIO_IMPORT_MAX_CONVERSATIONS or import a smaller export.");
        }

        return DB::transaction(function () use ($file, $source, $conversations, $user) {
            $import = ConversationImport::create([
                'user_id' => $user->id,
                'source' => $source,
                'original_name' => $file?->getClientOriginalName(),
                'status' => 'parsed',
                'conversation_count' => count($conversations),
                'message_count' => array_sum(array_map(fn ($c) => count($c['messages']), $conversations)),
                'extractors' => [],
            ]);

            $maxChars = config('portfolio.import.max_chars_per_conversation');
            foreach ($conversations as $c) {
                [$messages, $truncated] = $this->limit($c['messages'], $maxChars);
                $import->conversations()->create([
                    'external_id' => $c['external_id'],
                    'title' => mb_substr($c['title'], 0, 250),
                    'started_at' => $c['started_at'] ? \Illuminate\Support\Carbon::parse($c['started_at']) : null,
                    'message_count' => count($c['messages']),
                    'transcript' => ['messages' => $messages, 'truncated' => $truncated],
                ]);
            }

            $this->runKeywordScan($import);

            return $import;
        });
    }

    public function runKeywordScan(ConversationImport $import): int
    {
        $created = 0;
        foreach ($import->conversations()->get() as $conversation) {
            foreach ($this->keywords->extract($conversation) as $candidate) {
                $created += (int) (bool) $this->builder->build($candidate, 'keyword', $import);
            }
        }
        $import->update(['extractors' => array_values(array_unique([...($import->extractors ?? []), 'keyword'])), 'status' => 'extracted']);

        return $created;
    }

    /** @param array<int, int> $conversationIds */
    public function queueAi(ConversationImport $import, array $conversationIds): void
    {
        $import->update(['status' => 'ai_queued', 'error' => null]);
        ExtractWithAi::dispatch($import->id, $conversationIds);
    }

    /** Compact description of current records, so the model can match instead of duplicating. */
    public static function snapshot(): string
    {
        $profile = Profile::query()->first();
        $lines = $profile ? ["profile: {$profile->full_name} — {$profile->headline}"] : [];
        foreach (Resources::importable() as $resource) {
            $names = $resource::query()->limit(200)->get()->map(fn ($r) => $resource::title($r))->implode('; ');
            $lines[] = $resource::KEY.': '.($names ?: '(none)');
        }

        return implode("\n", $lines);
    }

    /** Keep whole messages up to the character budget; flag when something was dropped. */
    private function limit(array $messages, int $maxChars): array
    {
        $kept = [];
        $total = 0;
        foreach ($messages as $m) {
            $len = strlen($m['text']);
            if ($total + $len > $maxChars) {
                if (! $kept) {
                    $kept[] = ['role' => $m['role'], 'text' => mb_strcut($m['text'], 0, $maxChars)];
                }

                return [$kept, true];
            }
            $kept[] = $m;
            $total += $len;
        }

        return [$kept, false];
    }
}
