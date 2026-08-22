# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

"Meu App de Treino" (IA-LIFT) — a mobile-first Laravel + Inertia + Vue workout-logging app. Full product vision and roadmap live in `PROJECT_CONTEXT.md` (Portuguese); read it for the "why" behind features. Current phase is the MVP: workout catalog, workout (ficha) management, and a live workout-session tracker. Planned but not yet built: AI vision scanning of paper workout sheets and AI-driven progressive-overload recommendations.

## Commands

```bash
# Start full dev environment (Laravel server + queue listener + Vite HMR concurrently)
composer dev

# Frontend only
npm run dev
npm run build

# Backend tests (Pest, in-memory SQLite, RefreshDatabase applied to all Feature tests)
composer test
php artisan test
php artisan test --filter=WorkoutTest
php artisan test tests/Feature/WorkoutSessionTest.php

# Lint/format PHP (Pint)
vendor/bin/pint
vendor/bin/pint --dirty

# DB
php artisan migrate
php artisan migrate:fresh --seed   # seeds the global exercise catalog via ExerciseSeeder
```

There is no JS linter/formatter configured (no ESLint/Prettier in package.json).

## Architecture

**Stack:** Laravel 13 (PHP 8.3+), Inertia.js v2, Vue 3 (`<script setup>` only), Tailwind CSS 3, SQLite, Pest for tests. Auth scaffolding is Laravel Breeze (standard, unmodified — `app/Http/Controllers/Auth/*`).

**Layered backend pattern — follow this for any new feature:**
- Controllers (`app/Http/Controllers/`) stay thin: resolve the authenticated user, call a Service, return `Inertia::render(...)` or a redirect. They also own request→prop shaping (private `formatX()` helpers) since that's presentation, not business logic.
- Business logic and multi-step writes live in `app/Services/` (`WorkoutService`, `WorkoutSessionService`), not in controllers or models. Wrap multi-row writes in `DB::transaction()`.
- Validation lives in dedicated `app/Http/Requests/` classes (e.g. `StoreWorkoutRequest`, `LogSetRequest`), not inline in controllers.
- Fixed-domain values are native PHP backed enums in `app/Enums/` (e.g. `MuscleGroup`), each with a `label()` method returning the Portuguese display string. Enums are cast on models via `casts()` (see `Exercise::casts()`, including `AsEnumCollection::of(MuscleGroup::class)` for the `secondary_muscle_groups` array column).
- Models use the `#[Fillable([...])]` attribute (not a `$fillable` property) to declare mass-assignable fields.

**Domain model:** `User` → has many `Workout` (a "ficha"/workout template) → `belongsToMany Exercise` through the `workout_exercises` pivot (carries `order`, `target_sets`, `target_reps`, `rest_seconds`, `notes`; ordered via `orderByPivot('order')`). Starting a workout creates a `WorkoutSession` (`WorkoutSessionService::startSession`), and each set performed during that session is a `SetLog` (`updateOrCreate`d by `exercise_id` + `set_number`, so re-logging a set updates it in place). `Exercise` rows are either global (`user_id` null, seeded catalog) or user-created; use the `scopeGlobal` / `scopeForUser` query scopes rather than raw `whereNull`/`where` checks.

**Authorization:** no policies yet — ownership checks are done inline in controllers via `abort_if($model->user_id !== $request->user()->id, 403)`. Follow this pattern for new authenticated resource routes.

**Routing:** all authenticated app routes are declared directly in `routes/web.php` inside a single `Route::middleware('auth')` group (resource-style manual routes, not `Route::resource`). Breeze auth routes are required in from `routes/auth.php`. Ziggy exposes these routes to the frontend as the `route()` helper.

**Frontend:** Inertia pages live in `resources/js/Pages/`, mirroring controller/route names (e.g. `WorkoutController@show` renders `Pages/Workouts/Show.vue`). Shared/reusable UI goes in `resources/js/Components/` (Breeze-provided primitives at the top level, feature-specific components namespaced by folder, e.g. `Components/Workouts/ExerciseSelector.vue`). Two layouts: `Layouts/AuthenticatedLayout.vue` and `Layouts/GuestLayout.vue`. Forms should use Inertia's `useForm`; navigation should rely on Inertia visits, not full page reloads. Tailwind theme accent color is violet `#8B5CF6`; design mobile-first with dark mode support.

- **Modularização de Componentes:** Evite criar arquivos de páginas (.vue) gigantescos e monolíticos. Extraia elementos independentes e lógicas isoladas em componentes dedicados dentro de `resources/js/Components/` (ex: `RestTimerModal.vue`, `SetRow.vue`, `ExerciseHeader.vue`).
- **Contratos Claros:** Componentes filhos devem usar estritamente `defineProps()` e `defineEmits()` para comunicação limpa com a página pai.
- **Princípio DRY (Don't Repeat Yourself):** Reutilize os componentes primitivos existentes (como `Modal.vue`, `TextInput.vue`, `PrimaryButton.vue`) em vez de recriar tags HTML com classes duplicadas do zero.

## Response style for this repo

Per `.cursorrules` / `PROJECT_CONTEXT.md`, the developer (transitioning into fullstack web dev) wants a didactic mentor, not just code output. After implementing or refactoring a feature, close the response with three sections:

1. **🗺️ O Fluxo Humano de Execução** — a short chronological walkthrough of the user action → Vue handler → route → Controller → Service → DB → Inertia response → updated component.
2. **📂 Roteiro de Leitura de Código** — a numbered reading order of the files touched (e.g. migration → route → service → controller → Vue component) so the developer can study the change without getting lost.
3. **🧠 Ponto Chave de Aprendizado** — the single most important Laravel concept and the single most important Vue 3 reactivity concept applied in the change, and why each matters.

Keep explanations of architectural decisions brief but present — don't skip the "why." Prefer small, incremental, versionable steps over large sweeping changes.
