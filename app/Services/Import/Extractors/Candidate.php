<?php

namespace App\Services\Import\Extractors;

/** A possible profile update found in a conversation, before comparison with existing records. */
final class Candidate
{
    public function __construct(
        public string $entityType,
        public array $fields,
        public ?string $excerpt = null,
        public ?float $confidence = null,
        public ?string $rationale = null,
        public ?int $conversationId = null,
        public ?string $matchName = null,
    ) {}
}
