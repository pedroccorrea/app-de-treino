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
