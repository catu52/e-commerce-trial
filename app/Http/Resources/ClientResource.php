<?php

namespace App\Http\Resources;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Client */
class ClientResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'emailVerifiedAt' => $this->email_verified_at?->toIso8601String(),
            'ordersCount' => $this->whenCounted('orders'),
            'sessionsCount' => $this->whenCounted('tokens'),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
