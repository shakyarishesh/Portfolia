<?php

namespace App\Services\Portfolio;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Illuminate\Support\Facades\Log;
use Psr\Http\Client\ClientInterface;

/**
 * Public Q&A about the portfolio owner, grounded only in published portfolio data.
 * Off unless PORTFOLIO_ASSISTANT=true and ANTHROPIC_API_KEY are set (it spends API credit per question).
 */
class Assistant
{
    public function __construct(
        private PortfolioData $data,
        private array $config,
        private ?ClientInterface $transporter = null, // tests only
    ) {}

    public function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false) && filled($this->config['api_key'] ?? null);
    }

    /**
     * @param  array<int, array{role: string, text: string}>  $history  earlier turns from this visitor's widget
     * @return string|null answer, or null when the model can't answer (caller shows a fallback)
     */
    public function ask(string $question, array $history = []): ?string
    {
        $messages = [];
        foreach (array_slice($history, -6) as $turn) {
            $messages[] = ['role' => $turn['role'] === 'assistant' ? 'assistant' : 'user', 'content' => mb_substr($turn['text'], 0, 1000)];
        }
        $messages[] = ['role' => 'user', 'content' => $question];
        // The API needs the first message to be from the user.
        while ($messages && $messages[0]['role'] !== 'user') {
            array_shift($messages);
        }

        try {
            $message = $this->client()->beta->messages->create(
                maxTokens: 1024,
                messages: $messages,
                model: $this->config['model'],
                system: [['type' => 'text', 'text' => $this->systemPrompt(), 'cacheControl' => ['type' => 'ephemeral']]],
                outputConfig: ['effort' => 'low'],
                betas: ['server-side-fallback-2026-07-01'],
                fallbacks: 'default',
            );
        } catch (APIStatusException|APIConnectionException $e) {
            Log::warning('Portfolio assistant failed', ['status' => $e->getCode(), 'message' => $e->getMessage()]);

            return null;
        }

        if ($message->stopReason === 'refusal') {
            return null;
        }
        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return trim($text) ?: null;
    }

    private function systemPrompt(): string
    {
        $facts = json_encode($this->data->toApi(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $name = $this->data->get()['profile']->full_name;

        return <<<PROMPT
You are the assistant on {$name}'s portfolio website. Visitors are mostly recruiters, engineering managers and other developers.

Answer questions about {$name}'s background, skills, experience, projects, technologies and how to get in touch, using only the facts in <portfolio> below. These facts are the published, owner-approved portfolio content.

- If the facts don't answer the question, say you don't have that information and suggest contacting {$name} directly (the contact details are in the facts). Never guess or invent employers, dates, numbers, qualifications, opinions or achievements.
- Speak about {$name} in the third person. You are not {$name}.
- Do not share details about employers' internal systems, customers or code beyond what the facts state.
- For questions unrelated to {$name} or their work, say briefly that you can only help with questions about this portfolio.
- Keep answers short: usually 2–4 sentences, plain text without Markdown headings. Mention a project's page URL from the facts when it helps.

<portfolio>
{$facts}
</portfolio>
PROMPT;
    }

    private function client(): Client
    {
        return new Client(
            apiKey: $this->config['api_key'],
            baseUrl: $this->config['base_url'],
            requestOptions: array_filter(['timeout' => 30.0, 'transporter' => $this->transporter]),
        );
    }
}
