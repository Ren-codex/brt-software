<?php

namespace Tests\Feature\Drafts;

use App\Models\FormDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A set-aside form is kept server-side so the floating Resume bar survives a
 * refresh on any device. A draft belongs to one user and no one else can see,
 * replace or discard it.
 */
class FormDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_saves_and_reads_back_their_draft(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/drafts/sales-order', [
                'summary' => 'Dela Cruz · 3 items',
                'payload' => ['form' => ['items' => [1, 2, 3]]],
                'return_path' => '/sales-orders-external',
            ])
            ->assertOk();

        $this->assertDatabaseHas('form_drafts', [
            'user_id' => $user->id,
            'kind' => 'sales-order',
            'summary' => 'Dela Cruz · 3 items',
            'return_path' => '/sales-orders-external',
        ]);

        $response = $this->actingAs($user)->getJson('/drafts')->assertOk();
        $draft = $response->json('data.sales-order');

        $this->assertSame('Dela Cruz · 3 items', $draft['summary']);
        $this->assertSame([1, 2, 3], $draft['payload']['form']['items']);
        $this->assertSame('/sales-orders-external', $draft['return_path']);
    }

    public function test_saving_again_replaces_the_one_draft_of_that_kind(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/drafts/sales-order', ['summary' => 'first', 'payload' => ['a' => 1]])->assertOk();
        $this->actingAs($user)->putJson('/drafts/sales-order', ['summary' => 'second', 'payload' => ['a' => 2]])->assertOk();

        $this->assertSame(1, FormDraft::where('user_id', $user->id)->where('kind', 'sales-order')->count());
        $this->assertSame('second', FormDraft::where('user_id', $user->id)->where('kind', 'sales-order')->first()->summary);
    }

    public function test_a_draft_is_private_to_its_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($owner)->putJson('/drafts/sales-order', ['summary' => 'mine', 'payload' => ['x' => 1]])->assertOk();

        $this->assertNull($this->actingAs($other)->getJson('/drafts')->json('data.sales-order'));

        // Another user discarding the same kind touches only their own (none).
        $this->actingAs($other)->deleteJson('/drafts/sales-order')->assertOk();
        $this->assertDatabaseHas('form_drafts', ['user_id' => $owner->id, 'kind' => 'sales-order']);
    }

    public function test_discarding_removes_the_draft(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/drafts/sales-order', ['summary' => 'x', 'payload' => ['x' => 1]])->assertOk();
        $this->actingAs($user)->deleteJson('/drafts/sales-order')->assertOk();

        $this->assertDatabaseMissing('form_drafts', ['user_id' => $user->id, 'kind' => 'sales-order']);
    }

    public function test_an_unknown_kind_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/drafts/not-a-form', ['summary' => 'x'])->assertNotFound();
    }
}
