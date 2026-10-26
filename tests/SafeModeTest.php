<?php

namespace Cabuz\SafeMode\Tests;

use Cabuz\SafeMode\Exceptions\WriteBlockedException;
use Cabuz\SafeMode\Support\SafeMode;
use Cabuz\SafeMode\Tests\Fixtures\User;
use Illuminate\Support\Facades\DB;

class SafeModeTest extends TestCase
{
    protected function seedUser(): User
    {
        /** @var SafeMode $guard */
        $guard = $this->app->make(SafeMode::class);

        // Seeding is an intentional write.
        return $guard->allowWrites(function () {
            return User::query()->create([
                'name' => 'Real Person',
                'email' => 'real.person@gmail.com',
                'is_premium' => false,
            ]);
        });
    }

    public function test_guard_is_engaged_in_local(): void
    {
        $guard = $this->app->make(SafeMode::class);

        $this->assertTrue($guard->isActive());
    }

    /** Use case 1 & 3: a stray job flipping a flag on a real user is blocked. */
    public function test_a_stray_model_update_is_blocked(): void
    {
        $user = $this->seedUser();

        $this->expectException(WriteBlockedException::class);

        // This is exactly the forgotten-job scenario: change a flag.
        $user->is_premium = true;
        $user->save();
    }

    public function test_raw_dml_is_blocked_but_select_is_allowed(): void
    {
        $this->seedUser();

        // Reads always work.
        $this->assertSame(1, DB::table('users')->count());

        $this->expectException(WriteBlockedException::class);
        DB::table('users')->update(['is_premium' => true]);
    }

    public function test_intentional_writes_pass_through_allow_writes(): void
    {
        $user = $this->seedUser();

        $guard = $this->app->make(SafeMode::class);

        $guard->allowWrites(function () use ($user) {
            $user->is_premium = true;
            $user->save();
        });

        $this->assertTrue(
            $guard->allowWrites(fn () => User::query()->find($user->id)->is_premium) == true
        );
    }

    public function test_infrastructure_tables_stay_writable(): void
    {
        // Writing to a framework table (e.g. the queue) must not be blocked,
        // otherwise migrations and the queue worker would break.
        DB::table('migrations')->insert(['migration' => 'x', 'batch' => 99]);

        $this->assertSame(
            'x',
            DB::table('migrations')->where('batch', 99)->value('migration')
        );
    }

    public function test_outbound_http_is_blocked(): void
    {
        $this->expectException(\Cabuz\SafeMode\Exceptions\OutboundBlockedException::class);

        \Illuminate\Support\Facades\Http::get('https://api.stripe.com/v1/charges');
    }

    /** Masking: PII is hidden on read, original is never persisted. */
    public function test_email_is_masked_on_read(): void
    {
        $user = $this->seedUser();

        $fresh = User::query()->find($user->id);

        $this->assertSame("user_{$user->id}@example.com", $fresh->email);
    }

    public function test_masking_does_not_persist_the_fake_value(): void
    {
        $user = $this->seedUser();
        $guard = $this->app->make(SafeMode::class);

        // Read (masks email), then make an unrelated allowed change and save.
        $fresh = $guard->allowWrites(function () use ($user) {
            $u = User::query()->find($user->id);
            $u->name = 'Changed Name';
            $u->save();

            return $u;
        });

        // The real email must still be in the database, untouched.
        $realEmail = DB::table('users')->where('id', $user->id)->value('email');
        $this->assertSame('real.person@gmail.com', $realEmail);
        $this->assertSame('Changed Name', DB::table('users')->where('id', $user->id)->value('name'));
    }
}
