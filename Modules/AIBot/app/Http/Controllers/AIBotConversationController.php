<?php

namespace Modules\AIBot\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AIBot\Services\ConversationService;
use Modules\Business\Models\Business;

class AIBotConversationController extends Controller
{
    public function index(Request $request, ConversationService $conversations): JsonResponse
    {
        $business = Business::currentForNavbar($request->user());

        $items = $conversations->listForUser($request->user(), $business)
            ->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title ?: 'New chat',
                'last_message_at' => optional($c->last_message_at)->toIso8601String(),
            ])
            ->values();

        return response()->json(['conversations' => $items]);
    }

    public function show(int $conversation, Request $request, ConversationService $conversations): JsonResponse
    {
        $model = $conversations->findOwned($request->user(), $conversation);
        if ($model === null) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        $model = $conversations->withMessages($model);

        return response()->json([
            'id' => $model->id,
            'title' => $model->title,
            'messages' => $model->messages->map(fn ($m) => [
                'role' => $m->role,
                'content' => $m->content,
                'is_voice' => (bool) $m->is_voice,
            ])->values(),
        ]);
    }
}
