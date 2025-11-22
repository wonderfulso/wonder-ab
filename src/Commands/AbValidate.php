<?php

namespace Wonderfulso\WonderAb\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AbValidate extends Command
{
    protected $signature = 'ab:validate';

    protected $description = 'Validate A/B testing configuration and database setup';

    public function handle(): int
    {
        $this->info('🔍 Validating A/B Testing Configuration...');
        $this->newLine();

        $errors = 0;

        // Check database tables
        $this->info('Checking database tables...');
        $tables = ['ab_events', 'ab_experiments', 'ab_goal', 'ab_instance'];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("  ✗ Missing table: {$table}");
                $errors++;
            } else {
                $this->info("  ✓ Table exists: {$table}");
            }
        }
        $this->newLine();

        // Check indexes
        $this->info('Checking database indexes...');
        $indexChecks = [
            'ab_instance' => ['ab_instance_instance_unique', 'ab_instance_identifier_index'],
            'ab_events' => ['ab_events_instance_id_index', 'ab_events_experiments_id_index'],
            'ab_goal' => ['ab_goal_instance_id_index', 'ab_goal_goal_index'],
        ];

        foreach ($indexChecks as $table => $indexes) {
            if (Schema::hasTable($table)) {
                try {
                    // SHOW INDEX only works on MySQL/MariaDB, skip for other databases
                    if (DB::connection()->getDriverName() === 'mysql') {
                        $tableIndexes = DB::select("SHOW INDEX FROM {$table}");
                        $indexNames = collect($tableIndexes)->pluck('Key_name')->unique()->toArray();

                        foreach ($indexes as $index) {
                            if (in_array($index, $indexNames)) {
                                $this->info("  ✓ Index exists: {$table}.{$index}");
                            } else {
                                $this->warn("  ⚠ Missing index: {$table}.{$index} (consider running migrations)");
                            }
                        }
                    } else {
                        $this->info('  ℹ Index checking skipped (not MySQL database)');
                    }
                } catch (\Exception $e) {
                    $this->warn("  ⚠ Unable to check indexes: {$e->getMessage()}");
                }
            }
        }
        $this->newLine();

        // Check configuration
        $this->info('Checking configuration...');

        if (empty(config('wonder-ab.cache_key'))) {
            $this->error('  ✗ Missing cache_key in config');
            $errors++;
        } else {
            $this->info('  ✓ Cache key configured');
        }

        // Check analytics configuration
        $driver = config('wonder-ab.analytics.driver', 'none');
        $this->info("  ✓ Analytics driver: {$driver}");

        if ($driver === 'pivotal') {
            if (empty(config('wonder-ab.analytics.pivotal.api_key'))) {
                $this->warn('  ⚠ Pivotal driver selected but API key not configured');
            } else {
                $this->info('  ✓ Pivotal API key configured');
            }
        } elseif ($driver === 'google') {
            if (empty(config('wonder-ab.analytics.google.measurement_id')) ||
                empty(config('wonder-ab.analytics.google.api_secret'))) {
                $this->warn('  ⚠ Google Analytics driver selected but credentials not fully configured');
            } else {
                $this->info('  ✓ Google Analytics credentials configured');
            }
        } elseif ($driver === 'plausible') {
            if (empty(config('wonder-ab.analytics.plausible.domain'))) {
                $this->warn('  ⚠ Plausible driver selected but domain not configured');
            } else {
                $this->info('  ✓ Plausible domain configured');
            }
        } elseif ($driver === 'webhook') {
            if (empty(config('wonder-ab.analytics.webhook_url'))) {
                $this->warn('  ⚠ Webhook driver selected but URL not configured');
            } else {
                $this->info('  ✓ Webhook URL configured');
            }
        }

        // Check authentication
        $authType = config('wonder-ab.report_auth', 'none');
        $this->info("  ✓ Report auth type: {$authType}");

        if ($authType === 'basic') {
            if (empty(config('wonder-ab.report_username')) ||
                empty(config('wonder-ab.report_password'))) {
                $this->warn('  ⚠ Basic auth selected but credentials not configured');
            } else {
                $this->info('  ✓ Basic auth credentials configured');
            }
        }

        // Check cache configuration
        if (config('wonder-ab.cache.enabled')) {
            $this->info('  ✓ Caching enabled');
            $cacheDriver = config('wonder-ab.cache.driver') ?? 'default';
            $this->info("  ✓ Cache driver: {$cacheDriver}");
        } else {
            $this->info('  ✓ Caching disabled');
        }

        $this->newLine();

        // Check data
        $this->info('Checking data...');
        $experimentCount = DB::table('ab_experiments')->count();
        $instanceCount = DB::table('ab_instance')->count();
        $eventCount = DB::table('ab_events')->count();
        $goalCount = DB::table('ab_goal')->count();

        $this->info("  Experiments: {$experimentCount}");
        $this->info("  Instances: {$instanceCount}");
        $this->info("  Events: {$eventCount}");
        $this->info("  Goals: {$goalCount}");

        $this->newLine();

        if ($errors > 0) {
            $this->error("❌ Validation failed with {$errors} error(s)");
            $this->info('Run: php artisan migrate');

            return self::FAILURE;
        }

        $this->info('✅ Validation complete! Everything looks good.');

        return self::SUCCESS;
    }
}
