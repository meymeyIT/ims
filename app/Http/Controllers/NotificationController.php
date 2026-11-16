<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * Low Stock Alerts page: products that are low right now (with a restock
 * suggestion) + the user's notification history.
 */
class NotificationController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:notifications.view')];
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->when($request->query('filter') === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->latest()
            ->paginate(15, ['*'], 'page')
            ->withQueryString();

        $lowStock = Product::query()
            ->active()
            ->lowStock()
            ->with(['category', 'unit'])
            // Most urgent first: lowest stock relative to its minimum.
            ->orderByRaw('CASE WHEN minimum_stock > 0 THEN current_stock / minimum_stock ELSE 0 END')
            ->orderBy('name')
            ->get();

        return view('notifications.index', [
            'notifications' => $notifications,
            'lowStock' => $lowStock,
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    /** Opens a notification: marks it read and goes to the linked page. */
    public function read(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        abort_unless(
            $notification->notifiable_type === $request->user()->getMorphClass()
            && (int) $notification->notifiable_id === $request->user()->id,
            404
        );

        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        // Only follow links inside this application.
        return is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//')
            ? redirect($url)
            : redirect()->route('notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'All notifications were marked as read.');
    }
}
