<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Http\Requests\CMS\StoreClientRequest;
use App\Http\Resources\ClientResource;
use App\Http\Resources\OrderResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Staff management of storefront customers. Mirrors the Filament "Customers" screen. */
class ClientController extends Controller
{
    private function allow(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermission($permission), 403, 'This action is unauthorized.');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->allow($request, 'clients.view');

        $query = Client::query()->withCount(['orders', 'tokens'])->latest('id');

        if ($search = trim((string) $request->query('search', ''))) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(fn ($q) => $q->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('email', 'like', $like));
        }

        if ($request->has('has_orders')) {
            $request->boolean('has_orders') ? $query->whereHas('orders') : $query->whereDoesntHave('orders');
        }

        return ClientResource::collection($query->paginate(min(max((int) $request->query('per_page', 15), 1), 100)));
    }

    public function show(Request $request, Client $client): ClientResource
    {
        $this->allow($request, 'clients.view');

        return new ClientResource($client->loadCount(['orders', 'tokens']));
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = Client::create($request->safe()->only(['first_name', 'last_name', 'email', 'phone', 'password']));
        $this->syncVerification($client, $request);

        return (new ClientResource($client->loadCount(['orders', 'tokens'])))->response()->setStatusCode(201);
    }

    public function update(StoreClientRequest $request, Client $client): ClientResource
    {
        $client->update($request->safe()->only(['first_name', 'last_name', 'email', 'phone']));
        $this->syncVerification($client, $request);

        return new ClientResource($client->loadCount(['orders', 'tokens']));
    }

    public function destroy(Request $request, Client $client): JsonResponse
    {
        $this->allow($request, 'clients.manage');

        if ($client->orders()->exists()) {
            return response()->json(['message' => 'Customers with orders cannot be deleted.'], 422);
        }

        $client->tokens()->delete();
        $client->delete();

        return response()->json(['message' => 'Customer deleted.']);
    }

    public function setPassword(Request $request, Client $client): JsonResponse
    {
        $this->allow($request, 'clients.manage');
        $data = $request->validate(['password' => ['required', 'string', 'min:8']]);

        $client->update(['password' => $data['password']]);
        $client->tokens()->delete();

        return response()->json(['message' => 'Password updated and sessions revoked.']);
    }

    public function revokeSessions(Request $request, Client $client): JsonResponse
    {
        $this->allow($request, 'clients.manage');
        $revoked = $client->tokens()->delete();

        return response()->json(['message' => 'Sessions revoked.', 'revoked' => $revoked]);
    }

    public function orders(Request $request, Client $client): AnonymousResourceCollection
    {
        $this->allow($request, 'clients.view');

        return OrderResource::collection(
            $client->orders()->with('items')->latest('id')->paginate(min(max((int) $request->query('per_page', 15), 1), 100))
        );
    }

    private function syncVerification(Client $client, StoreClientRequest $request): void
    {
        if ($request->has('email_verified')) {
            $client->forceFill(['email_verified_at' => $request->boolean('email_verified') ? ($client->email_verified_at ?? now()) : null])->save();
        }
    }
}
