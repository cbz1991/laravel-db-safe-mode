<?php

namespace Cabuz\SafeMode\Tests;

use Cabuz\SafeMode\Support\SafeMode;
use Cabuz\SafeMode\Tests\Fixtures\User;
use Illuminate\Support\Facades\DB;

/**
 * No SAFE_MODE set and no real data at boot: everyday local development must
 * not be blocked.
 */
class AutoModeTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('safe-mode.mode', null);
    }

    public function test_debug_mode_is_picked_without_real_data(): void
    {
        $guard = $this->app->make(SafeMode::class);

        $this->assertSame('debug', $guard->mode());
        $this->assertFalse($guard->isExplicitMode());
        $this->assertFalse($guard->blocksWrites());
    }

    public function test_local_development_can_write(): void
    {
        User::query()->create(['name' => 'Seeded Person', 'email' => 'seeded@example.com']);

        $this->assertSame(1, DB::table('users')->count());
    }

    public function test_seeded_data_is_not_masked(): void
    {
        $this->insertUsers('seeded@example.com');

        $this->assertSame('seeded@example.com', User::query()->value('email'));
    }
}
