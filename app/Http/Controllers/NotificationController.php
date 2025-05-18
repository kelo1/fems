<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * List notifications for the authenticated user.
     * Optionally filter by recipient_type.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        
        // Check if $user is an instance of FEMSAdmin
        if($user instanceof \App\Models\FEMSAdmin) {
            $notifications = Notification::where('notifiable_id', $user->id)
                ->where('notifiable_type', get_class($user))
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($notification) {
                    $data = $notification->data ?? [];
                    return [
                        'id'             => $notification->id,
                        'serial_number'  => $data['serial_number'] ?? null,
                        'title'          => $data['title'] ?? null,
                        'message'        => $data['message'] ?? null,
                        'status'         => $data['status'] ?? 'unread',
                        'read_at'        => $notification->read_at,
                        'created_at'     => $notification->created_at,
                        'updated_at'     => $notification->updated_at,
                    ];
                });
            return response()->json(['notifications' => $notifications], 200);
        }


        $notifications = Notification::where('notifiable_id', $user->id)
            ->where('notifiable_type', get_class($user))
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($notification) {
                $data = $notification->data ?? [];
                return [
                    'id'             => $notification->id,
                    'serial_number'  => $data['serial_number'] ?? null,
                    'recipient_type' => $data['recipient_type'] ?? null,
                    'recipient_id'   => $data['recipient_id'] ?? null,
                    'type'           => $data['type'] ?? $notification->type,
                    'title'          => $data['title'] ?? null,
                    'message'        => $data['message'] ?? null,
                    'status'         => $data['status'] ?? 'unread',
                    'qr_code_url'    => $data['qr_code_url'] ?? null,
                    'read_at'        => $notification->read_at,
                    'created_at'     => $notification->created_at,
                    'updated_at'     => $notification->updated_at,
                ];
            });

        return response()->json(['notifications' => $notifications], 200);
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead($id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $notification = Notification::where('id', $id)
            ->where('notifiable_id', $user->id)
            ->where('notifiable_type', get_class($user))
            ->first();

        if (!$notification) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        $data = $notification->data;
        $data['status'] = 'read';
        $notification->data = $data;
        $notification->read_at = now();
        $notification->save();

        return response()->json(['message' => 'Notification marked as read'], 200);
    }

    /**
     * Mark all notifications as read for the authenticated user.
     */
    public function markAllAsRead(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $notifications = Notification::where('notifiable_id', $user->id)
            ->where('notifiable_type', get_class($user))
            ->whereNull('read_at')
            ->get();

        $count = 0;
        foreach ($notifications as $notification) {
            $data = $notification->data;
            $data['status'] = 'read';
            $notification->data = $data;
            $notification->read_at = now();
            $notification->save();
            $count++;
        }

        return response()->json(['message' => "Marked $count notifications as read"], 200);
    }

    

    /**
     * Get a single notification.
     */
    public function show($id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $notification = Notification::where('id', $id)
            ->where('notifiable_id', $user->id)
            ->where('notifiable_type', get_class($user))
            ->first();

        if (!$notification) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        // Get notification data for FEMSAdmin
        if($user instanceof \App\Models\FEMSAdmin) {
            $data = $notification->data ?? [];
            $result = [
                'id'             => $notification->id,
                'serial_number'  => $data['serial_number'] ?? null,
                'title'          => $data['title'] ?? null,
                'message'        => $data['message'] ?? null,
                'status'         => $data['status'] ?? 'unread',
                'read_at'        => $notification->read_at,
                'created_at'     => $notification->created_at,
                'updated_at'     => $notification->updated_at,
            ];
            return response()->json(['notification' => $result], 200);
        }


        $data = $notification->data ?? [];
        $result = [
            'id'             => $notification->id,
            'serial_number'  => $data['serial_number'] ?? null,
            'recipient_type' => $data['recipient_type'] ?? null,
            'recipient_id'   => $data['recipient_id'] ?? null,
            'type'           => $data['type'] ?? $notification->type,
            'title'          => $data['title'] ?? null,
            'message'        => $data['message'] ?? null,
            'status'         => $data['status'] ?? 'unread',
            'qr_code_url'    => $data['qr_code_url'] ?? null,
            'read_at'        => $notification->read_at,
            'created_at'     => $notification->created_at,
            'updated_at'     => $notification->updated_at,
        ];

        return response()->json(['notification' => $result], 200);
    }

    /**
     * Delete a notification.
     */
    public function destroy($id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $notification = Notification::where('id', $id)
            ->where('notifiable_id', $user->id)
            ->where('notifiable_type', get_class($user))
            ->first();

        if (!$notification) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        $notification->delete();

        return response()->json(['message' => 'Notification deleted'], 200);
    }

    /**
     * Get the count of unread notifications for the authenticated user.
     */
    public function getUnreadCount()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $unreadCount = Notification::where('notifiable_id', $user->id)
            ->where('notifiable_type', get_class($user))
            ->where(function ($query) {
                $query->whereNull('read_at')
                      ->orWhereJsonContains('data->status', 'unread');
            })
            ->count();

        return response()->json(['unread_count' => $unreadCount], 200);
    }
}
