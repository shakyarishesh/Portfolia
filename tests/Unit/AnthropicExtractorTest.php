<?php

namespace Tests\Unit;

use App\Models\ImportedConversation;
use App\Services\Import\Extractors\AnthropicExtractor;
use App\Services\Import\Extractors\ExtractionException;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnthropicExtractorTest extends TestCase
{
    use RefreshDatabase;

    private array $history = [];

    private function extractor(array $responses): AnthropicExtractor
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new AnthropicExtractor([
            'api_key' => 'test-key', 'model' => 'claude-opus-5-5', 'base_url' => 'https://api.anthropic.com',
            'max_input_chars' => 120000, 'timeout' => 30,
        ], new Guzzle(['handler' => $stack]));
    }

    private function message(array $proposals, string $stopReason = 'end_turn'): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
            'content' => [['type' => 'text', 'text' => json_encode(['proposals' => $proposals])]],
            'stop_reason' => $stopReason, 'stop_sequence' => null,
            'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
        ]));
    }

    private function conversation(): ImportedConversation
    {
        $c = new ImportedConversation(['title' => 'T', 'transcript' => ['messages' => [['role' => 'human', 'text' => 'I built Hajir payroll with Laravel and MySQL.']]]]);
        $c->id = 7;

        return $c;
    }

    public function test_maps_structured_output_to_candidates(): void
    {
        $extractor = $this->extractor([$this->message([
            ['entity_type' => 'project', 'match_name' => 'Hajir', 'fields' => [
                ['name' => 'features', 'value' => "Payroll engine\nTax engine"],
                ['name' => 'technologies', 'value' => 'Laravel, MySQL'],
                ['name' => 'not_a_field', 'value' => 'ignored'],
            ], 'excerpt' => 'I built Hajir payroll', 'confidence' => 1.4, 'rationale' => 'Stated by owner'],
            ['entity_type' => 'skill', 'match_name' => '', 'fields' => [['name' => 'name', 'value' => 'MySQL'], ['name' => 'category', 'value' => 'Databases']],
                'excerpt' => 'MySQL', 'confidence' => 0.8, 'rationale' => ''],
            ['entity_type' => 'spaceship', 'match_name' => '', 'fields' => [['name' => 'name', 'value' => 'x']], 'excerpt' => '', 'confidence' => 1, 'rationale' => ''],
        ])]);

        $candidates = $extractor->extract($this->conversation(), 'snapshot');

        $this->assertCount(2, $candidates);
        $this->assertSame(['Payroll engine', 'Tax engine'], $candidates[0]->fields['features']);
        $this->assertSame(['Laravel', 'MySQL'], $candidates[0]->fields['technologies']);
        $this->assertArrayNotHasKey('not_a_field', $candidates[0]->fields);
        $this->assertSame('Hajir', $candidates[0]->matchName);
        $this->assertSame(1.0, $candidates[0]->confidence);
        $this->assertSame(7, $candidates[0]->conversationId);
        $this->assertSame(['name' => 'MySQL', 'skill_category' => 'Databases'], $candidates[1]->fields);

        $body = json_decode((string) $this->history[0]['request']->getBody(), true);
        $this->assertSame('claude-opus-5-5', $body['model']);
        $this->assertSame('json_schema', $body['output_config']['format']['type']);
        $this->assertSame('default', $body['fallbacks']);
        $this->assertSame('test-key', $this->history[0]['request']->getHeaderLine('x-api-key'));
    }

    public function test_refusal_and_bad_output_raise_extraction_errors(): void
    {
        foreach ([
            $this->message([], 'refusal'),
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 'm', 'type' => 'message', 'role' => 'assistant', 'model' => 'x',
                'content' => [['type' => 'text', 'text' => 'not json']], 'stop_reason' => 'end_turn', 'stop_sequence' => null, 'usage' => ['input_tokens' => 1, 'output_tokens' => 1]])),
            new Response(401, ['Content-Type' => 'application/json'], json_encode(['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key']])),
        ] as $response) {
            try {
                $this->extractor([$response])->extract($this->conversation(), '');
                $this->fail('Expected ExtractionException');
            } catch (ExtractionException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_long_conversations_are_chunked_not_truncated(): void
    {
        $long = new ImportedConversation(['title' => 'Long', 'transcript' => ['messages' => [
            ['role' => 'human', 'text' => str_repeat('a', 900)],
            ['role' => 'human', 'text' => str_repeat('b', 900)],
        ]]]);
        $long->id = 1;
        $stack = HandlerStack::create(new MockHandler([$this->message([]), $this->message([])]));
        $stack->push(Middleware::history($this->history));
        $extractor = new AnthropicExtractor(['api_key' => 'k', 'model' => 'claude-opus-5-5', 'base_url' => 'https://api.anthropic.com', 'max_input_chars' => 1000, 'timeout' => 5], new Guzzle(['handler' => $stack]));

        $extractor->extract($long, '');

        $this->assertCount(2, $this->history);
    }
}
