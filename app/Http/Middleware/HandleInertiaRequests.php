<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'activeWorkoutSession' => fn () => $this->formatActiveWorkoutSession($request),
        ];
    }

    /**
     * The authenticated user's in-progress workout session (if any), shared
     * globally so any page can render the "treino em andamento" indicator
     * without an extra round trip.
     */
    private function formatActiveWorkoutSession(Request $request): ?array
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $session = $user->workoutSessions()
            ->whereNull('completed_at')
            ->with('workout')
            ->latest('started_at')
            ->first();

        if (! $session) {
            return null;
        }

        return [
            'id' => $session->id,
            'workout_name' => $session->workout->name,
            'started_at' => $session->started_at,
        ];
    }
}
