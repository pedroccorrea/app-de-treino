<?php

namespace App\Http\Controllers;

use App\Enums\MuscleGroup;
use App\Exceptions\GeminiException;
use App\Models\User;
use App\Services\DashboardAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardAnalyticsService $dashboardAnalyticsService): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'streak' => $dashboardAnalyticsService->calculateStreak($user),
            'personalRecords' => $dashboardAnalyticsService->calculatePersonalRecords($user),
            'weeklyVolume' => $this->formatWeeklyVolume(
                $dashboardAnalyticsService->calculateWeeklyVolumeByMuscleGroup($user)
            ),
            'activityHeatmap' => $dashboardAnalyticsService->calculateActivityHeatmap($user),
            'muscleBalanceAlert' => $this->safeAnalyzeMuscleBalance($dashboardAnalyticsService, $user),
        ]);
    }

    /**
     * The muscle-balance alert is the only AI-backed part of the dashboard;
     * a Gemini failure shouldn't take down the whole page, so it degrades to
     * "no alert" instead of a fatal error.
     *
     * @return array<string, mixed>|null
     */
    private function safeAnalyzeMuscleBalance(DashboardAnalyticsService $dashboardAnalyticsService, User $user): ?array
    {
        try {
            return $dashboardAnalyticsService->analyzeMuscleBalance($user);
        } catch (GeminiException) {
            return null;
        }
    }

    /**
     * @param  array<string, int>  $weeklyVolume
     * @return array<int, array{muscle_group: string, label: string, sets: int}>
     */
    private function formatWeeklyVolume(array $weeklyVolume): array
    {
        return collect($weeklyVolume)
            ->map(fn (int $sets, string $muscleGroup) => [
                'muscle_group' => $muscleGroup,
                'label' => MuscleGroup::from($muscleGroup)->label(),
                'sets' => $sets,
            ])
            ->values()
            ->all();
    }
}
