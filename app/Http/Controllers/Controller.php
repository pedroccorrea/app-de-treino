<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Several screens can open the same create/edit/delete flow and pass
     * back a `return_to` path/URL so the user lands where they came from
     * instead of always on a fixed page. Only same-app targets (relative
     * paths or URLs under the app's own domain) are honored to avoid an
     * open redirect.
     */
    protected function safeRedirectTarget(?string $returnTo, string $fallback): string
    {
        if (! $returnTo) {
            return $fallback;
        }

        if (str_starts_with($returnTo, '/') && ! str_starts_with($returnTo, '//')) {
            return $returnTo;
        }

        if (str_starts_with($returnTo, config('app.url'))) {
            return $returnTo;
        }

        return $fallback;
    }
}
