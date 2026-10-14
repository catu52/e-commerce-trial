<?php

namespace App\Services;

use App\Models\FlashDropSubscriber;

class NewsletterService
{
    /**
     * Idempotently subscribe an email; returns whether a new row was created.
     */
    public function subscribeToFlashDrops(string $email): bool
    {
        $subscriber = FlashDropSubscriber::firstOrCreate(
            ['email' => $email],
            ['subscribed_at' => now()],
        );

        return $subscriber->wasRecentlyCreated;
    }
}
