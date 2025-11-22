<?php

namespace Wonderfulso\WonderAb\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Wonderfulso\WonderAb\WonderAbServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Wonderfulso\\WonderAb\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

    }

    protected function getPackageProviders($app)
    {
        return [
            WonderAbServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'sqlite');
        $dbPath = __DIR__.'/db.sqlite';
        if (file_exists($dbPath)) {
            unlink($dbPath);
        }
        touch($dbPath);
        config()->set('database.connections.sqlite.database', $dbPath);
        // Order matters: create parent tables before child tables with foreign keys
        foreach ([
            'create_wonder_ab_experiments_table.php',  // No dependencies
            'create_wonder_ab_instances_table.php',    // No dependencies
            'create_wonder_ab_events_table.php',       // References experiments & instances
            'create_wonder_ab_goals_table.php',        // References instances
        ] as $filepath) {
            $migration = include sprintf(__DIR__.'/../database/migrations/%s.stub', $filepath);
            $migration->up();
        }

    }
}
