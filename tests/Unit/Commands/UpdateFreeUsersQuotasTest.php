<?php

namespace Tests\Unit\Commands;

use App\Console\Commands\UpdateFreeUsersQuotas;
use App\Models\Credit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class UpdateFreeUsersQuotasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
    }

    public function test_updates_credit_for_user_with_expiring_credit(): void
    {
        $quotaLimit = config('billing.packages.free.quota_limits.monthly');

        $user = User::factory()->create();

        // Create an expiring credit (expires within a day)
        $expiringCredit = Credit::factory()->create([
            'user_id' => $user->id,
            'messages' => 30,
            'started_at' => now()->subDays(29),
            'ends_at' => now()->addHours(12), // Expires within a day
        ]);

        $this->artisan(UpdateFreeUsersQuotas::class);

        $user->refresh();

        $latestCredit = $user->credit;

        $this->assertNotNull($latestCredit);
        $this->assertNotEquals($expiringCredit->id, $latestCredit->id);
        $this->assertEquals($quotaLimit['messages_limit'], $latestCredit->messages);
        $this->assertTrue($latestCredit->ends_at->greaterThan(now()->addDays(29)));
        $this->assertTrue($latestCredit->ends_at->lessThanOrEqualTo(now()->addMonth()));
    }

    public function test_updates_credit_for_user_without_credit(): void
    {
        $quotaLimit = config('billing.packages.free.quota_limits.monthly');

        $user = User::factory()->create();

        $this->artisan(UpdateFreeUsersQuotas::class);

        $user->refresh();
        $latestCredit = $user->credit;

        $this->assertNotNull($latestCredit);

        $this->assertEquals($quotaLimit['messages_limit'], $latestCredit->messages);

        $this->assertTrue($latestCredit->ends_at->greaterThan(now()->addDays(29)));
        $this->assertTrue($latestCredit->ends_at->lessThanOrEqualTo(now()->addMonth()));
    }

    public function test_does_not_update_credit_for_user_with_valid_credit(): void
    {
        Event::fake();

        $user = User::factory()->create();

        $validCredit = Credit::factory()->create([
            'user_id' => $user->id,
            'messages' => 70,
            'started_at' => now()->subDays(10),
            'ends_at' => now()->addDays(20),
        ]);

        $this->artisan(UpdateFreeUsersQuotas::class);

        $user->refresh();
        $latestCredit = $user->credit;

        $this->assertEquals($validCredit->id, $latestCredit->id);
        $this->assertEquals(70, $latestCredit->messages);
    }
}
