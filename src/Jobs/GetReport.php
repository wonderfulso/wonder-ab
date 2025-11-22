<?php

namespace Wonderfulso\WonderAb\Jobs;

use Illuminate\Support\Facades\DB;

class GetReport
{
    public string $experiment;

    public function __construct(string $experiment)
    {
        $this->experiment = $experiment;
    }

    /**
     * Execute the job and return report data
     *
     * @return list<array{condition: string, hits: int, goals: int, conversion: float}>
     */
    public function handle(): array
    {
        return $this->printReport($this->experiment);
    }

    /**
     * Generate report data for an experiment
     *
     * @return list<array{condition: string, hits: int, goals: int, conversion: float}>
     */
    protected function printReport(string $experiment): array
    {
        $info = [];

        // Get total hits per variant
        $full_count = DB::table('ab_events')
            ->select(DB::raw('ab_events.value, count(*) as hits'))
            ->where('ab_events.name', '=', $experiment)
            ->groupBy('ab_events.value')
            ->get();

        foreach ($full_count as $record) {
            $info[$record->value] = [
                'condition' => $record->value,
                'hits' => (int) $record->hits,
                'goals' => 0,
                'conversion' => 0.0,
            ];
        }

        // Get goal conversions per variant
        $goal_count = DB::table('ab_events')
            ->select(DB::raw('ab_events.value, count(ab_events.value) as goals'))
            ->join('ab_goal', 'ab_goal.instance_id', '=', 'ab_events.instance_id')
            ->where('ab_events.name', '=', $experiment)
            ->groupBy('ab_events.value')
            ->get();

        foreach ($goal_count as $record) {
            if (isset($info[$record->value])) {
                $info[$record->value]['goals'] = (int) $record->goals;
                $info[$record->value]['conversion'] = round(
                    ($record->goals / $info[$record->value]['hits']) * 100,
                    2
                );
            }
        }

        // Sort by conversion rate (descending)
        usort($info, fn ($a, $b) => $b['conversion'] <=> $a['conversion']);

        return $info;
    }
}
