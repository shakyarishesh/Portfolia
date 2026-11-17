<?php

namespace App\Services\Import;

use ZipArchive;

/**
 * Normalises supported Claude exports into:
 * [source, [ ['external_id', 'title', 'started_at', 'messages' => [['role' => 'human'|'assistant', 'text' => ...]]] ]].
 *
 * Supported: claude.ai data export (.zip with conversations.json, or the .json itself),
 * Claude Code session transcripts (.jsonl), and plain text / markdown.
 */
class ConversationParser
{
    public function parseFile(string $path, string $originalName): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        return match ($ext) {
            'zip' => $this->parseZip($path),
            'json' => $this->parseClaudeExport($this->read($path)),
            'jsonl' => $this->parseClaudeCode($this->read($path), pathinfo($originalName, PATHINFO_FILENAME)),
            'txt', 'md' => $this->parseText($this->read($path), pathinfo($originalName, PATHINFO_FILENAME)),
            default => throw new ImportException("Unsupported file type .{$ext}"),
        };
    }

    public function parseText(string $text, string $title = 'Pasted conversation'): array
    {
        $text = trim($text);
        if ($text === '') {
            throw new ImportException('The pasted text is empty.');
        }

        // "Human:" / "User:" / "Assistant:" / "Claude:" prefixes split turns when present.
        $parts = preg_split('/^(Human|User|Me|Assistant|Claude)\s*:\s*/mi', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $messages = [];
        if (count($parts) > 1) {
            if (! preg_match('/^(Human|User|Me|Assistant|Claude)$/i', $parts[0])) {
                array_shift($parts); // preamble before the first speaker
            }
            for ($i = 0; $i + 1 < count($parts); $i += 2) {
                $role = in_array(strtolower($parts[$i]), ['assistant', 'claude'], true) ? 'assistant' : 'human';
                $messages[] = ['role' => $role, 'text' => trim($parts[$i + 1])];
            }
        }
        if (! $messages) {
            $messages = [['role' => 'human', 'text' => $text]];
        }

        return ['text', [['external_id' => null, 'title' => $title, 'started_at' => null, 'messages' => $messages]]];
    }

    public function parseClaudeExport(string $json): array
    {
        $data = json_decode($json, true);
        if (! is_array($data)) {
            throw new ImportException('This is not valid JSON.');
        }
        if (isset($data['chat_messages'])) {
            $data = [$data]; // a single conversation object
        }
        if (! array_is_list($data)) {
            throw new ImportException('Expected a list of conversations (conversations.json from a claude.ai data export).');
        }

        $conversations = [];
        foreach ($data as $conv) {
            if (! is_array($conv) || ! isset($conv['chat_messages']) || ! is_array($conv['chat_messages'])) {
                throw new ImportException('Unrecognised conversation format: each item needs "chat_messages".');
            }
            $messages = [];
            foreach ($conv['chat_messages'] as $msg) {
                $text = $this->exportMessageText($msg);
                if ($text !== '') {
                    $messages[] = ['role' => ($msg['sender'] ?? '') === 'assistant' ? 'assistant' : 'human', 'text' => $text];
                }
            }
            if ($messages) {
                $conversations[] = [
                    'external_id' => $conv['uuid'] ?? null,
                    'title' => trim((string) ($conv['name'] ?? '')) ?: 'Untitled conversation',
                    'started_at' => $conv['created_at'] ?? null,
                    'messages' => $messages,
                ];
            }
        }

        return ['claude_export', $conversations];
    }

    public function parseClaudeCode(string $jsonl, string $title): array
    {
        $messages = [];
        $startedAt = null;
        $sessionId = null;
        foreach (preg_split('/\R/', $jsonl) as $n => $line) {
            if (trim($line) === '') {
                continue;
            }
            $row = json_decode($line, true);
            if (! is_array($row)) {
                throw new ImportException('Line '.($n + 1).' is not valid JSON — is this a Claude Code .jsonl transcript?');
            }
            $type = $row['type'] ?? null;
            if (! in_array($type, ['user', 'assistant'], true) || ! empty($row['isMeta']) || ! empty($row['isSidechain'])) {
                continue;
            }
            $text = $this->blocksText($row['message']['content'] ?? '', skipToolResults: true);
            // Skip harness boilerplate (command wrappers, reminders) that the person did not type.
            if ($text === '' || preg_match('/^\s*<(command-|local-command|system-reminder|task-notification)/', $text)) {
                continue;
            }
            $startedAt ??= $row['timestamp'] ?? null;
            $sessionId ??= $row['sessionId'] ?? null;
            $messages[] = ['role' => $type === 'assistant' ? 'assistant' : 'human', 'text' => $text];
        }
        if (! $messages) {
            throw new ImportException('No user or assistant messages found in this transcript.');
        }

        return ['claude_code', [['external_id' => $sessionId, 'title' => $title, 'started_at' => $startedAt, 'messages' => $messages]]];
    }

    private function parseZip(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new ImportException('Could not open the zip file.');
        }
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (basename($name) === 'conversations.json') {
                    $stat = $zip->statIndex($i);
                    if ($stat['size'] > config('portfolio.import.max_kb') * 1024 * 4) {
                        throw new ImportException('conversations.json inside the zip is too large.');
                    }

                    return $this->parseClaudeExport((string) $zip->getFromIndex($i));
                }
            }
        } finally {
            $zip->close();
        }
        throw new ImportException('No conversations.json found in the zip. Upload the claude.ai data export.');
    }

    private function exportMessageText(array $msg): string
    {
        if (isset($msg['content']) && is_array($msg['content'])) {
            $text = $this->blocksText($msg['content'], skipToolResults: true);
            if ($text !== '') {
                return $text;
            }
        }

        return trim((string) ($msg['text'] ?? ''));
    }

    private function blocksText(mixed $content, bool $skipToolResults): string
    {
        if (is_string($content)) {
            return trim($content);
        }
        if (! is_array($content)) {
            return '';
        }
        $texts = [];
        foreach ($content as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'text' && isset($block['text'])) {
                $texts[] = $block['text'];
            }
        }

        return trim(implode("\n\n", $texts));
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new ImportException('Could not read the uploaded file.');
        }

        return $contents;
    }
}
