<?php

namespace App\Support;

use Illuminate\Support\Str;

final class Markdown
{
    /**
     * Article Markdown → safe HTML plus a table of contents.
     * Raw HTML is escaped and javascript: links are dropped, so authors can't inject scripts.
     *
     * @return array{html: string, toc: array<int, array{id: string, text: string, level: int}>}
     */
    public static function article(?string $markdown): array
    {
        $html = Str::markdown((string) $markdown, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
        $toc = [];
        $used = [];

        $html = preg_replace_callback('#<h([23])>(.*?)</h\1>#s', function ($m) use (&$toc, &$used) {
            $text = html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5);
            $id = $base = Str::slug($text) ?: 'section';
            for ($i = 2; isset($used[$id]); $i++) {
                $id = "{$base}-{$i}";
            }
            $used[$id] = true;
            $toc[] = ['id' => $id, 'text' => $text, 'level' => (int) $m[1]];

            return "<h{$m[1]} id=\"{$id}\">{$m[2]}</h{$m[1]}>";
        }, $html);

        // Images in articles load lazily.
        $html = str_replace('<img ', '<img loading="lazy" ', $html);

        return ['html' => $html, 'toc' => $toc];
    }

    /** Paragraphs separated by blank lines, each rendered as inline Markdown (escaped). */
    public static function paragraphs(?string $text): array
    {
        return array_map(
            fn ($p) => Str::inlineMarkdown($p, ['html_input' => 'escape', 'allow_unsafe_links' => false]),
            array_values(array_filter(array_map('trim', preg_split('/\R{2,}/', (string) $text))))
        );
    }
}
