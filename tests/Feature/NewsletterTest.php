<?php

namespace Tests\Feature;

use App\Models\FlashDropSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscribes_new_email_then_is_idempotent(): void
    {
        $this->postJson('/api/v1/newsletter/flash-drops', ['email' => 'Drop@Example.com'])
            ->assertCreated()->assertJson(['subscribed' => true]);

        $this->postJson('/api/v1/newsletter/flash-drops', ['email' => 'drop@example.com'])
            ->assertOk()->assertJson(['subscribed' => true]);

        $this->assertSame(1, FlashDropSubscriber::count());
        $this->assertDatabaseHas('flash_drop_subscribers', ['email' => 'drop@example.com']);
    }

    public function test_rejects_invalid_email(): void
    {
        $this->postJson('/api/v1/newsletter/flash-drops', ['email' => 'nope'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/v1/newsletter/flash-drops', [])->assertUnprocessable();
    }

    public function test_is_throttled_to_five_per_minute(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/newsletter/flash-drops', ['email' => "u{$i}@example.com"])->assertSuccessful();
        }

        $this->postJson('/api/v1/newsletter/flash-drops', ['email' => 'u9@example.com'])->assertStatus(429);
    }
}
