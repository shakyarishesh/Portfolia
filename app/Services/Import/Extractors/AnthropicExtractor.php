<?php

namespace App\Services\Import\Extractors;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Admin\ProfileForm;
use App\Admin\Resource;
use App\Admin\Resources;
use App\Models\ImportedConversation;
use Illuminate\Support\Facades\Log;
use Psr\Http\Client\ClientInterface;

/** Claude-backed extraction. Only constructed when AI_PROVIDER=anthropic and a key is set. */
class AnthropicExtractor implements LlmExtractor
{
    private ?Client $client = null;

    private ?array $fieldDefs = null;

    /** @param  ClientInterface|null  $transporter  only set in tests */
    public function __construct(private array $config, private ?ClientInterface $transporter = null) {}

    public function name(): string
    {
        return 'Claude ('.$this->config['model'].')';
    }

    public function extract(ImportedConversation $conversation, string $portfolioSnapshot): array
    {
        if (blank($this->config['api_key'] ?? null)) {
            throw new ExtractionException('ANTHROPIC_API_KEY is not set.');
        }

        $candidates = [];
        foreach ($this->chunks($conversation->messages()) as $chunk) {
            foreach ($this->call($chunk, $portfolioSnapshot) as $item) {
                if ($candidate = $this->toCandidate($item, $conversation->id)) {
                    $candidates[] = $candidate;
                }
            }
        }

        return $candidates;
    }

    /** Long conversations are split on message boundaries instead of being cut off. */
    private function chunks(array $messages): array
    {
        $max = (int) $this->config['max_input_chars'];
        $chunks = [];
        $current = '';
        foreach ($messages as $m) {
            $block = ($m['role'] === 'human' ? 'PORTFOLIO OWNER' : 'ASSISTANT').":\n".$m['text']."\n\n";
            if ($current !== '' && strlen($current) + strlen($block) > $max) {
                $chunks[] = $current;
                $current = '';
            }
            // A single message longer than the budget is split by size.
            foreach (str_split($block, $max) as $piece) {
                if (strlen($current) + strlen($piece) > $max && $current !== '') {
                    $chunks[] = $current;
                    $current = '';
                }
                $current .= $piece;
            }
        }
        if (trim($current) !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    private function call(string $transcript, string $snapshot): array
    {
        try {
            $message = $this->client()->beta->messages->create(
                maxTokens: 16000,
                messages: [['role' => 'user', 'content' => "<conversation>\n{$transcript}</conversation>"]],
                model: $this->config['model'],
                system: [['type' => 'text', 'text' => $this->systemPrompt($snapshot), 'cacheControl' => ['type' => 'ephemeral']]],
                outputConfig: ['effort' => 'medium', 'format' => ['type' => 'json_schema', 'schema' => $this->schema()]],
                betas: ['server-side-fallback-2026-07-01'],
                fallbacks: 'default',
            );
        } catch (AuthenticationException $e) {
            throw new ExtractionException('The Anthropic API key was rejected. Check ANTHROPIC_API_KEY.', previous: $e);
        } catch (RateLimitException $e) {
            throw new ExtractionException('Rate limited by the Anthropic API. Try again in a minute.', previous: $e);
        } catch (APIStatusException $e) {
            Log::warning('Anthropic extraction failed', ['status' => $e->getCode(), 'message' => $e->getMessage()]);
            throw new ExtractionException('The Anthropic API returned an error: '.$e->getMessage(), previous: $e);
        } catch (APIConnectionException $e) {
            throw new ExtractionException('Could not reach the Anthropic API.', previous: $e);
        }

        if ($message->stopReason === 'refusal') {
            throw new ExtractionException('The model declined to process this conversation.');
        }
        if ($message->stopReason === 'max_tokens') {
            throw new ExtractionException('The model response was cut off (max_tokens). Try a shorter conversation.');
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }
        $data = json_decode($text, true);
        if (! is_array($data) || ! isset($data['proposals']) || ! is_array($data['proposals'])) {
            throw new ExtractionException('The model returned output that was not the expected JSON.');
        }

        return $data['proposals'];
    }

    private function toCandidate(mixed $item, int $conversationId): ?Candidate
    {
        if (! is_array($item)) {
            return null;
        }
        $type = $item['entity_type'] ?? null;
        $fieldDefs = $this->fieldDefs()[$type] ?? null;
        if ($fieldDefs === null) {
            return null;
        }

        $fields = [];
        foreach ((array) ($item['fields'] ?? []) as $pair) {
            $name = $pair['name'] ?? null;
            $value = trim((string) ($pair['value'] ?? ''));
            if ($value === '' || ! is_string($name)) {
                continue;
            }
            if ($type === 'skill' && $name === 'category') {
                $fields['skill_category'] = $value;

                continue;
            }
            if (! isset($fieldDefs[$name])) {
                continue;
            }
            $fields[$name] = match ($fieldDefs[$name]['type']) {
                'lines' => Resource::splitLines($value),
                'tags' => Resource::splitTags($value),
                'checkbox' => in_array(strtolower($value), ['1', 'true', 'yes'], true),
                default => $value,
            };
        }
        if (! $fields) {
            return null;
        }

        return new Candidate(
            entityType: $type,
            fields: $fields,
            excerpt: mb_substr((string) ($item['excerpt'] ?? ''), 0, 2000) ?: null,
            confidence: is_numeric($item['confidence'] ?? null) ? max(0, min(1, (float) $item['confidence'])) : null,
            rationale: mb_substr((string) ($item['rationale'] ?? ''), 0, 1000) ?: null,
            conversationId: $conversationId,
            matchName: trim((string) ($item['match_name'] ?? '')) ?: null,
        );
    }

    /** entity_type => field definitions offered to the model. */
    private function fieldDefs(): array
    {
        if ($this->fieldDefs !== null) {
            return $this->fieldDefs;
        }
        $defs = ['profile' => ProfileForm::contentFields()];
        foreach (Resources::importable() as $resource) {
            $fields = $resource::contentFields();
            unset($fields['skill_category_id'], $fields['experience_id'], $fields['project_id']);
            if ($resource::KEY === 'skill') {
                $fields['category'] = ['type' => 'text', 'label' => 'Category name'];
            }
            $defs[$resource::KEY] = $fields;
        }

        return $this->fieldDefs = $defs;
    }

    private function systemPrompt(string $snapshot): string
    {
        $fieldDocs = collect($this->fieldDefs())->map(function ($fields, $type) {
            $list = collect($fields)->map(fn ($f, $name) => "{$name} ({$f['type']}".match ($f['type']) {
                'lines' => ', one item per line',
                'tags' => ', comma separated',
                'date' => ', YYYY-MM-DD',
                'select' => ', one of: '.implode('|', array_keys($f['options'] ?? [])),
                default => '',
            }.')')->implode('; ');

            return "- {$type}: {$list}";
        })->implode("\n");

        return <<<PROMPT
You maintain a developer's portfolio. You will read one conversation between the portfolio owner and an AI assistant, and propose updates to the portfolio.

Rules:
- Only use facts the PORTFOLIO OWNER states or clearly demonstrates about their own work (projects they built, employers, roles, dates, technologies they used, education, certifications, achievements). Assistant text is context only; never treat the assistant's suggestions or code as the owner's experience unless the owner confirms it.
- Never invent metrics, dates, employers, links or credentials. If a detail is uncertain, leave the field out or set a low confidence and explain in the rationale.
- Ignore secrets, credentials, customer data, other people's personal data, salaries and internal URLs.
- Prefer updating an existing record: set match_name to the existing record's name exactly as listed below; use an empty string for a new record.
- excerpt must be a short verbatim quote from the owner's message that supports the proposal.
- confidence is 0 to 1: how sure you are this is a correct, public-worthy fact about the owner.
- Return an empty proposals list when nothing qualifies. Most conversations are routine work and should produce few or no proposals.

Entity types and the field names you may use (values are always strings):
{$fieldDocs}

The portfolio currently contains:
{$snapshot}
PROMPT;
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['proposals'],
            'properties' => [
                'proposals' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['entity_type', 'match_name', 'fields', 'excerpt', 'confidence', 'rationale'],
                        'properties' => [
                            'entity_type' => ['type' => 'string', 'enum' => array_keys($this->fieldDefs())],
                            'match_name' => ['type' => 'string'],
                            'fields' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'additionalProperties' => false,
                                    'required' => ['name', 'value'],
                                    'properties' => ['name' => ['type' => 'string'], 'value' => ['type' => 'string']],
                                ],
                            ],
                            'excerpt' => ['type' => 'string'],
                            'confidence' => ['type' => 'number'],
                            'rationale' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function client(): Client
    {
        return $this->client ??= new Client(
            apiKey: $this->config['api_key'],
            baseUrl: $this->config['base_url'],
            requestOptions: array_filter(['timeout' => (float) $this->config['timeout'], 'transporter' => $this->transporter]),
        );
    }
}
