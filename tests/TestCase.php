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
        foreach ([
            'create_laravel_ab_events_table.php',
            'create_laravel_ab_experiments_table.php',
            'create_laravel_ab_goal_table.php',
            'create_laravel_ab_instance_table.php'] as $filepath) {
            $migration = include sprintf(__DIR__.'/../database/migrations/%s.stub', $filepath);
            $migration->up();
        }

    }
}
