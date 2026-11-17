<?php

namespace Tests\Unit;

use App\Services\Import\ConversationParser;
use App\Services\Import\ImportException;
use Tests\TestCase;

class ConversationParserTest extends TestCase
{
    private ConversationParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ConversationParser;
    }

    public function test_parses_claude_ai_export(): void
    {
        $json = json_encode([[
            'uuid' => 'u1', 'name' => 'Chat', 'created_at' => '2026-01-01T00:00:00Z',
            'chat_messages' => [
                ['sender' => 'human', 'text' => 'old text', 'content' => [['type' => 'text', 'text' => 'I built X']]],
                ['sender' => 'assistant', 'text' => 'Nice', 'content' => []],
                ['sender' => 'human', 'text' => '', 'content' => []],
            ],
        ]]);

        [$source, $convs] = $this->parser->parseClaudeExport($json);

        $this->assertSame('claude_export', $source);
        $this->assertSame('u1', $convs[0]['external_id']);
        $this->assertSame([['role' => 'human', 'text' => 'I built X'], ['role' => 'assistant', 'text' => 'Nice']], $convs[0]['messages']);
    }

    public function test_parses_zip_export(): void
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'exp');
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::OVERWRITE);
        $zip->addFromString('data/conversations.json', json_encode([['name' => 'Z', 'chat_messages' => [['sender' => 'human', 'text' => 'hi']]]]));
        $zip->close();

        [$source, $convs] = $this->parser->parseFile($zipPath, 'export.zip');
        unlink($zipPath);

        $this->assertSame('claude_export', $source);
        $this->assertSame('Z', $convs[0]['title']);
    }

    public function test_parses_claude_code_transcript_and_skips_tool_output(): void
    {
        $lines = [
            ['type' => 'user', 'sessionId' => 's1', 'timestamp' => '2026-01-01T00:00:00Z', 'message' => ['content' => 'Add Redis caching']],
            ['type' => 'assistant', 'message' => ['content' => [['type' => 'text', 'text' => 'Done.'], ['type' => 'tool_use', 'name' => 'Bash']]]],
            ['type' => 'user', 'message' => ['content' => [['type' => 'tool_result', 'content' => 'secret output']]]],
            ['type' => 'user', 'isMeta' => true, 'message' => ['content' => 'meta']],
            ['type' => 'user', 'message' => ['content' => '<command-name>/clear</command-name>']],
            ['type' => 'summary', 'summary' => 'x'],
        ];
        $jsonl = implode("\n", array_map('json_encode', $lines));

        [$source, $convs] = $this->parser->parseClaudeCode($jsonl, 'session');

        $this->assertSame('claude_code', $source);
        $this->assertSame('s1', $convs[0]['external_id']);
        $this->assertSame([['role' => 'human', 'text' => 'Add Redis caching'], ['role' => 'assistant', 'text' => 'Done.']], $convs[0]['messages']);
    }

    public function test_parses_plain_text_with_speaker_prefixes(): void
    {
        [, $convs] = $this->parser->parseText("Human: I use Laravel\nAssistant: Great\nHuman: And MySQL", 'T');

        $this->assertSame(['human', 'assistant', 'human'], array_column($convs[0]['messages'], 'role'));
        $this->assertSame('And MySQL', $convs[0]['messages'][2]['text']);
    }

    public function test_rejects_invalid_input(): void
    {
        foreach ([
            fn () => $this->parser->parseClaudeExport('{not json'),
            fn () => $this->parser->parseClaudeExport('{"foo": 1}'),
            fn () => $this->parser->parseClaudeCode("not json\n", 'x'),
            fn () => $this->parser->parseText('   '),
            fn () => $this->parser->parseFile(__FILE__, 'script.php'),
        ] as $call) {
            try {
                $call();
                $this->fail('Expected ImportException');
            } catch (ImportException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
