<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/** Fills the plain-text details template from a PDF CV using Gemini (free tier key in GEMINI_API_KEY). */
class CvReader
{
    private const PROMPT = <<<'PROMPT'
You fill in a plain-text form from the attached CV (PDF).
Return the form exactly as given — same section headings (== … ==), same field labels, same order — with answers written after the colons.
Rules:
- Only use facts written in the CV. Never invent dates, employers, links, numbers or skills. Leave a field empty when the CV doesn't say.
- Follow the notes on the # lines (date formats, allowed options, comma-separated lists). You may drop the # lines.
- For sections with several entries, repeat the block of fields for each entry and put a line with only --- between entries.
- Leave Username empty and keep the Template line exactly as given.
- Output only the filled-in form: no explanations, no Markdown, no code fences.
PROMPT;

    public static function enabled(): bool
    {
        return filled(config('services.gemini.key'));
    }

    /** @throws CvReaderException */
    public function fill(string $pdfPath, string $template): string
    {
        if (! self::enabled()) {
            throw new CvReaderException('GEMINI_API_KEY is not set.');
        }

        $body = [
            'systemInstruction' => ['parts' => [['text' => self::PROMPT]]],
            'contents' => [['role' => 'user', 'parts' => [
                ['inline_data' => ['mime_type' => 'application/pdf', 'data' => base64_encode((string) file_get_contents($pdfPath))]],
                ['text' => $template],
            ]]],
            'generationConfig' => ['temperature' => 0],
        ];

        // The free tier often answers "busy" (503) or "quota" (429): retry, then try the lighter model.
        foreach (array_unique(array_filter([config('services.gemini.model'), config('services.gemini.fallback_model')])) as $model) {
            try {
                $res = Http::withHeaders(['x-goog-api-key' => config('services.gemini.key')])
                    ->timeout(90)
                    ->retry([2000, 5000], when: fn ($e) => $e instanceof RequestException && self::busy($e->response), throw: false)
                    ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", $body);
            } catch (ConnectionException $e) {
                throw new CvReaderException('Could not reach the Gemini API.', previous: $e);
            }
            if (! self::busy($res)) {
                break;
            }
        }

        if ($res->failed()) {
            throw new CvReaderException('Gemini API error '.$res->status().': '.mb_strimwidth($res->body(), 0, 500));
        }
        $text = collect($res->json('candidates.0.content.parts', []))->pluck('text')->implode('');
        if (trim($text) === '') {
            throw new CvReaderException('Gemini returned no text (finish reason: '.($res->json('candidates.0.finishReason') ?? $res->json('promptFeedback.blockReason') ?? 'unknown').').');
        }

        // Models sometimes wrap the answer in a code fence despite being told not to.
        return preg_replace('/^\s*```\w*\s*\n|\n\s*```\s*$/', '', $text);
    }

    private static function busy(Response $res): bool
    {
        return in_array($res->status(), [429, 503], true);
    }
}
