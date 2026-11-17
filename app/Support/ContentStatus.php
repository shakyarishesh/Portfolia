<?php

namespace App\Support;

// Review status shared by every content type; only "published" is public.
final class ContentStatus
{
    public const LABELS = [
        'draft' => 'Draft',
        'needs_review' => 'Needs review',
        'published' => 'Published',
    ];
}
