<?php

namespace App\Http\Controllers;

use App\Models\FarmNotification;
use App\Models\User;
use App\Models\WebPushSubscription;
use App\Services\FarmNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class NotificationController extends Controller
{
    public function sync(Request $request, FarmNotificationService $service): JsonResponse
    {
        $user = $this->notificationUser($request);
        $farm = $user?->currentFarm();

        if ($farm && $user) {
            $service->syncForFarmAndUser($farm, $user);
        }

        return response()->json([
            'success' => true,
            'unread_count' => $this->unreadCount($request),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        if (! Schema::hasTable('farm_notifications')) {
            return response()->json(['success' => true, 'unread_count' => 0]);
        }

        $user = $this->notificationUser($request);

        FarmNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'unread_count' => 0,
        ]);
    }

    public function read(Request $request, FarmNotification $notification): JsonResponse
    {
        $user = $this->notificationUser($request);

        abort_unless((int) $notification->user_id === (int) $user->id, 403);

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json([
            'success' => true,
            'unread_count' => $this->unreadCount($request),
        ]);
    }

    public function dismiss(Request $request, FarmNotification $notification): JsonResponse
    {
        $user = $this->notificationUser($request);

        abort_unless((int) $notification->user_id === (int) $user->id, 403);

        $dismissedUntil = $notification->source_type === 'subscription_invoice'
            ? now()->addHours(4)
            : null;

        $notification->update([
            'read_at' => $notification->read_at ?: now(),
            'dismissed_at' => now(),
            'dismissed_until' => $dismissedUntil,
        ]);

        return response()->json([
            'success' => true,
            'unread_count' => $this->unreadCount($request),
        ]);
    }

    public function subscribePush(Request $request): JsonResponse
    {
        if (! Schema::hasTable('web_push_subscriptions')) {
            return response()->json([
                'success' => false,
                'message' => 'La tabla de suscripciones push no está lista.',
            ], 503);
        }

        $validated = $request->validate([
            'endpoint' => ['required', 'string'],
            'keys.p256dh' => ['nullable', 'string'],
            'keys.auth' => ['nullable', 'string'],
        ]);

        $user = $this->notificationUser($request);
        $endpointHash = hash('sha256', $validated['endpoint']);

        WebPushSubscription::updateOrCreate(
            ['endpoint_hash' => $endpointHash],
            [
                'user_id' => $user->id,
                'endpoint' => $validated['endpoint'],
                'public_key' => data_get($validated, 'keys.p256dh'),
                'auth_token' => data_get($validated, 'keys.auth'),
                'content_encoding' => 'aes128gcm',
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'last_seen_at' => now(),
            ]
        );

        return response()->json(['success' => true]);
    }

    public function unsubscribePush(Request $request): JsonResponse
    {
        if (! Schema::hasTable('web_push_subscriptions')) {
            return response()->json(['success' => true]);
        }

        $validated = $request->validate([
            'endpoint' => ['required', 'string'],
        ]);

        WebPushSubscription::where('endpoint_hash', hash('sha256', $validated['endpoint']))->delete();

        return response()->json(['success' => true]);
    }

    protected function unreadCount(Request $request): int
    {
        if (! Schema::hasTable('farm_notifications')) {
            return 0;
        }

        $user = $this->notificationUser($request);

        return FarmNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->where(function ($query) {
                $query->whereNull('dismissed_at')
                    ->orWhere(function ($subQuery) {
                        $subQuery->whereNotNull('dismissed_until')
                            ->where('dismissed_until', '<=', now());
                    });
            })
            ->count();
    }

    protected function notificationUser(Request $request): User
    {
        $user = $request->user();

        if ($user?->canAccessAdminPanel() && $request->session()->has('admin_view_client_id')) {
            $client = User::find($request->session()->get('admin_view_client_id'));

            if ($client) {
                return $client;
            }
        }

        return $user;
    }
}
