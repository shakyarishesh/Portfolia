<?php

namespace App\Support;

// Tells audit logs where a change came from while a block of code runs.
class AuditContext
{
    private static string $source = 'admin';

    private static ?int $proposedChangeId = null;

    public static function source(): string
    {
        return app()->runningInConsole() && self::$source === 'admin' && ! app()->runningUnitTests()
            ? 'console'
            : self::$source;
    }

    public static function proposedChangeId(): ?int
    {
        return self::$proposedChangeId;
    }

    public static function run(string $source, callable $callback, ?int $proposedChangeId = null): mixed
    {
        [$prevSource, $prevId] = [self::$source, self::$proposedChangeId];
        [self::$source, self::$proposedChangeId] = [$source, $proposedChangeId];

        try {
            return $callback();
        } finally {
            [self::$source, self::$proposedChangeId] = [$prevSource, $prevId];
        }
    }
}
