<?php

namespace App\Http\Controllers\Concerns;

use Closure;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * One-time form tokens: each form carries a random UUID. The first submission
 * claims it; a second submission of the same form (double-click, refresh,
 * back button) is detected and ignored, so stock is never changed twice.
 */
trait RunsOnce
{
    /**
     * @return mixed|null  The callback's result, or null if this form was already processed.
     */
    protected function runOnce(string $scope, string $token, Closure $callback): mixed
    {
        $key = "form-token:{$scope}:{$token}";

        if (! Cache::add($key, true, now()->addHours(12))) {
            return null;
        }

        try {
            return $callback();
        } catch (Throwable $e) {
            Cache::forget($key); // nothing was saved: allow the user to fix the form and resubmit
            throw $e;
        }
    }
}
