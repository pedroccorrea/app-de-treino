<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

arch('controllers must delegate to services and never access DB directly')
    ->expect('App\Http\Controllers')
    ->not->toUse([DB::class])
    ->ignoring('App\Http\Controllers\Auth');

arch('services must not return HTTP or Inertia responses')
    ->expect('App\Services')
    ->not->toUse([Inertia::class, JsonResponse::class, RedirectResponse::class]);

arch('models must extend base eloquent model')
    ->expect('App\Models')
    ->toExtend(Model::class);

arch('enums must be backed enums')
    ->expect('App\Enums')
    ->toBeEnums();

test('vue components must use design tokens instead of literal hex colors', function () {
    // Architecture tests aren't bound to Tests\TestCase (see tests/Pest.php),
    // so there's no service container here — plain SPL iteration instead of
    // the File facade / resource_path() helper.
    $jsDir = realpath(__DIR__.'/../../resources/js');

    // resources/js/Pages/Welcome.vue is Breeze's stock scaffold splash page —
    // it uses Laravel's own brand red and was never part of the Setwave
    // design system (Phase 12), so it's excluded here rather than rebranded
    // as a side effect of this rule.
    $exceptions = [
        'resources/js/Pages/Welcome.vue',
    ];

    $violations = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($jsDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'vue') {
            continue;
        }

        $relativePath = 'resources/js/'.str_replace('\\', '/', substr($file->getPathname(), strlen($jsDir) + 1));

        if (in_array($relativePath, $exceptions, true)) {
            continue;
        }

        if (preg_match('/#[0-9a-fA-F]{6}/', file_get_contents($file->getPathname()))) {
            $violations[] = $relativePath;
        }
    }

    expect($violations)->toBe([]);
});
