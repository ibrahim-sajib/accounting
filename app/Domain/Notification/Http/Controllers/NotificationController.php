<?php

namespace App\Domain\Notification\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController
{
    public function index(Request $request): Response
    {
        $companyId = current_company_id();

        $notifications = $request->user()
            ->notifications()
            ->where('data->company_id', $companyId)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $notifications->through(fn (DatabaseNotification $n) => [
            'id' => $n->id,
            'title' => $n->data['title'] ?? 'Notification',
            'body' => $n->data['body'] ?? '',
            'category' => $n->data['category'] ?? 'general',
            'read_at' => $n->read_at?->toISOString(),
            'created_at' => $n->created_at?->toISOString(),
            'approvals_url' => $n->data['approvals_url'] ?? null,
        ]);

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'unread_count' => $request->user()
                ->notifications()
                ->whereNull('read_at')
                ->where('data->company_id', $companyId)
                ->count(),
        ]);
    }

    public function read(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        if ($notification->notifiable_id !== $request->user()->id) {
            abort(403);
        }

        $notification->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()
            ->notifications()
            ->whereNull('read_at')
            ->where('data->company_id', current_company_id())
            ->update(['read_at' => now()]);

        return back();
    }
}