<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function createNotification(array $data = [], bool $read = false): DatabaseNotification
    {
        $n = DatabaseNotification::create([
            'id'              => \Illuminate\Support\Str::uuid(),
            'type'            => 'App\\Notifications\\LowBalanceFundNotification',
            'notifiable_type' => User::class,
            'notifiable_id'   => $this->user->id,
            'data'            => array_merge(['type' => 'low_balance', 'fund_name' => 'Test Fund'], $data),
            'read_at'         => $read ? now() : null,
        ]);
        return $n;
    }

    public function test_index_returns_notifications_and_unread_count(): void
    {
        $this->createNotification();
        $this->createNotification([], true); // read

        $response = $this->getJson('/notifications');

        $response->assertOk()
            ->assertJsonStructure(['notifications', 'unread_count'])
            ->assertJsonPath('unread_count', 1);
    }

    public function test_mark_read_sets_read_at(): void
    {
        $n = $this->createNotification();
        $this->assertNull($n->read_at);

        $this->patchJson("/notifications/{$n->id}/read")
            ->assertOk()
            ->assertJson(['status' => true]);

        $this->assertNotNull($n->fresh()->read_at);
    }

    public function test_mark_all_read_clears_all_unread(): void
    {
        $this->createNotification();
        $this->createNotification();

        $this->patchJson('/notifications/read-all')
            ->assertOk()
            ->assertJson(['status' => true]);

        $this->assertEquals(0, $this->user->unreadNotifications()->count());
    }
}
