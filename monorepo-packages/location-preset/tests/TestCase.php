<?php

namespace LBHurtado\LocationPreset\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use LBHurtado\LocationPreset\Tests\Models\User;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'LBHurtado\\LocationPreset\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            \LBHurtado\LocationPreset\LocationPresetServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');

        $userMigration = include __DIR__.'/../database/migrations/test/0001_01_01_000000_create_users_table.php';
        $userMigration->up();

        $presetMigration = include __DIR__.'/../database/migrations/2025_03_08_000000_create_location_presets_table.php';
        $presetMigration->up();
    }
}
