<?php

namespace Wonderfulso\WonderAb\Commands;

use Illuminate\Console\Command;
use Wonderfulso\WonderAb\Jobs\GetLists;
use Wonderfulso\WonderAb\Jobs\GetReport;

class AbReport extends Command
{
    protected $signature = 'ab:report
    {experiment? : Name of the experiment to report on}
    {--list : list experiments in database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'provides statistic on experiments';

    /**
     * Execute the console command
     */
    public function handle(): int
    {
        $experiment = $this->argument('experiment');
        $list = $this->option('list');

        if ($list) {
            $this->prettyPrint(dispatch_sync(new GetLists));

            return self::SUCCESS;
        }

        if (! empty($experiment)) {
            $this->prettyPrint(dispatch_sync(new GetReport($experiment)));
        } else {
            $reports = dispatch_sync(new GetLists);
            $info = [];
            foreach ($reports as $report) {
                $info[$report->experiment] = dispatch_sync(new GetReport($report->experiment));
            }
            $this->prettyPrint($info);
        }

        return self::SUCCESS;
    }

    /**
     * Pretty print data as JSON
     */
    protected function prettyPrint(mixed $info): void
    {
        $this->info(json_encode($info, JSON_PRETTY_PRINT));
    }
}
