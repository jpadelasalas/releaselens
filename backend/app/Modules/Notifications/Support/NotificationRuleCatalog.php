<?php

namespace App\Modules\Notifications\Support;

/**
 * Rule catalog: which notification types exist and how long a duplicate
 * for the same subject is suppressed (dedup window). A window of 0 means
 * no deduplication (every occurrence notifies).
 */
class NotificationRuleCatalog
{
    /**
     * @var array<string, int>
     */
    private const DEDUP_WINDOW_MINUTES = [
        'release.approval_required' => 60,
        'release.released' => 0,
        'deployment.failed' => 30,
        'deployment.rolled_back' => 30,
    ];

    public static function isKnown(string $type): bool
    {
        return array_key_exists($type, self::DEDUP_WINDOW_MINUTES);
    }

    public static function dedupWindowMinutes(string $type): int
    {
        return self::DEDUP_WINDOW_MINUTES[$type] ?? 0;
    }

    /**
     * @return array<int, string>
     */
    public static function knownTypes(): array
    {
        return array_keys(self::DEDUP_WINDOW_MINUTES);
    }
}
