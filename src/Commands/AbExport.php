<?php

namespace Wonderfulso\WonderAb\Commands;

use Illuminate\Console\Command;
use Wonderfulso\WonderAb\Models\Events;
use Wonderfulso\WonderAb\Models\Experiments;
use Wonderfulso\WonderAb\Models\Goal;
use Wonderfulso\WonderAb\Models\Instance;

class AbExport extends Command
{
    protected $signature = 'ab:export';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'provides a way to send experiment data to pivotal.so';

    /**
     * Execute the console command
     */
    public function handle(): int
    {
        $this->info('Exporting A/B test data...');

        $experiments = Experiments::orderBy('created_at')->get()->map(function (Experiments $experiment) {
            return [
                'experiment' => $experiment->experiment,
                'goal' => $experiment->goal,
                'created_at' => $experiment->created_at->toDateTimeString(),
            ];
        });

        $goals = Goal::with('instance')->orderBy('created_at')->get()->map(function (Goal $goal) {
            /** @var Instance|null $instanceModel */
            $instanceModel = $goal->instance;

            return [
                'goal' => $goal->goal,
                'value' => $goal->value,
                'instance' => $instanceModel?->instance,
                'created_at' => $goal->created_at->toDateTimeString(),
            ];
        });

        $instances = Instance::orderBy('created_at')->get()->map(function (Instance $instance) {
            return [
                'instance' => $instance->instance,
                'identifier' => $instance->identifier,
                'metadata' => $instance->metadata,
                'created_at' => $instance->created_at->toDateTimeString(),
            ];
        });

        $events = Events::with('instance', 'experiment')->orderBy('created_at')->get()->map(function (Events $event) {
            /** @var Instance|null $instanceModel */
            $instanceModel = $event->instance;
            /** @var Experiments|null $experimentModel */
            $experimentModel = $event->experiment;

            return [
                'experiment' => $experimentModel?->experiment,
                'name' => $event->name,
                'value' => $event->value,
                'instance' => $instanceModel?->instance,
                'created_at' => $event->created_at->toDateTimeString(),
            ];
        });

        $data = [
            'exported_at' => now()->toIso8601String(),
            'experiments' => $experiments,
            'goals' => $goals,
            'instances' => $instances,
            'events' => $events,
        ];

        $filename = sprintf('ab_export_%s.json', date('Y-m-d_H-i-s'));

        file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));

        $this->info("✓ Exported to {$filename}");
        $this->info("  Experiments: {$experiments->count()}");
        $this->info("  Goals: {$goals->count()}");
        $this->info("  Instances: {$instances->count()}");
        $this->info("  Events: {$events->count()}");

        return self::SUCCESS;
    }
}
