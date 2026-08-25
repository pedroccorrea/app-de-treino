<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Support\Carbon;

class DashboardAnalyticsService
{
    /**
     * Counts the number of consecutive days (ending today or yesterday) in
     * which the user completed at least one workout session.
     */
    public function calculateStreak(User $user): int
    {
        $completedDates = $user->workoutSessions()
            ->whereNotNull('completed_at')
            ->get()
            ->map(fn (WorkoutSession $session) => $session->completed_at->toDateString())
            ->unique()
            ->flip();

        if ($completedDates->isEmpty()) {
            return 0;
        }

        $cursor = Carbon::today();

        // Allow the streak to still count if the user simply hasn't trained
        // yet today, as long as yesterday is covered.
        if (! $completedDates->has($cursor->toDateString())) {
            $cursor = $cursor->copy()->subDay();
        }

        $streak = 0;

        while ($completedDates->has($cursor->toDateString())) {
            $streak++;
            $cursor = $cursor->copy()->subDay();
        }

        return $streak;
    }
}
