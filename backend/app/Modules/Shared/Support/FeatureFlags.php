<?php

namespace App\Modules\Shared\Support;

class FeatureFlags
{
    public function enabled(string $feature): bool
    {
        return (bool) config("releaselens.features.{$feature}", false);
    }
}
