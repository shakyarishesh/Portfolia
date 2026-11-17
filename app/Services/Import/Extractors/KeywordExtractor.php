<?php

namespace App\Services\Import\Extractors;

use App\Models\ImportedConversation;
use Illuminate\Support\Str;

/**
 * Deterministic scan of the human side of a conversation for technology names and
 * GitHub/LinkedIn profile links. It does not understand context: it only proposes
 * skills and links, and every proposal still needs review.
 */
class KeywordExtractor
{
    /** @return array<int, Candidate> */
    public function extract(ImportedConversation $conversation): array
    {
        $text = $conversation->humanText();
        if ($text === '') {
            return [];
        }

        $candidates = [];
        $caseSensitive = config('portfolio.keywords_case_sensitive', []);
        foreach (config('portfolio.keywords', []) as $category => $names) {
            foreach ($names as $name) {
                $flags = in_array($name, $caseSensitive, true) ? 'u' : 'iu';
                $pattern = '/(?<![\w.\/-])'.preg_quote($name, '/').'(?![\w-])/'.$flags;
                $count = preg_match_all($pattern, $text, $m, PREG_OFFSET_CAPTURE);
                if ($count > 0) {
                    $candidates[] = new Candidate(
                        entityType: 'skill',
                        fields: ['name' => $name, 'skill_category' => $category],
                        excerpt: $this->snippet($text, $m[0][0][1], strlen($m[0][0][0])),
                        rationale: "Keyword scan: \"{$name}\" appears {$count}× in your messages.",
                        conversationId: $conversation->id,
                    );
                }
            }
        }

        preg_match_all('~https?://(?:www\.|[a-z]{2}\.)?(github\.com/[A-Za-z0-9-]+|linkedin\.com/in/[A-Za-z0-9_%-]+)/?(?=[\s)>\]"\']|$)~i', $text, $links, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($links as $link) {
            $url = rtrim($link[0][0], '/');
            $isGithub = Str::contains(strtolower($url), 'github.com');
            $candidates[] = new Candidate(
                entityType: 'social_link',
                fields: ['platform' => $isGithub ? 'github' : 'linkedin', 'label' => $isGithub ? 'GitHub' : 'LinkedIn', 'url' => $url],
                excerpt: $this->snippet($text, $link[0][1], strlen($link[0][0])),
                rationale: 'Keyword scan: profile link found in your messages.',
                conversationId: $conversation->id,
            );
        }

        return $candidates;
    }

    private function snippet(string $text, int $offset, int $length): string
    {
        $start = max(0, $offset - 120);
        $snippet = substr($text, $start, $length + 240);

        return ($start > 0 ? '…' : '').trim(mb_convert_encoding($snippet, 'UTF-8', 'UTF-8')).'…';
    }
}
