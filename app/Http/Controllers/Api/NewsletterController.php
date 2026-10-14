<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\NewsletterSubscribeRequest;
use App\Services\NewsletterService;
use Illuminate\Http\JsonResponse;

class NewsletterController extends Controller
{
    public function __construct(private NewsletterService $newsletter) {}

    public function flashDrops(NewsletterSubscribeRequest $request): JsonResponse
    {
        $created = $this->newsletter->subscribeToFlashDrops($request->validated('email'));

        return response()->json([
            'message' => $created ? 'Signal locked. You will receive flash drop alerts.' : 'This address is already on the priority signal list.',
            'subscribed' => true,
        ], $created ? 201 : 200);
    }
}
