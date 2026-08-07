<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        return view('notifications.index', ['notifications'=>auth()->user()->notifications]);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        //
        $notification = auth()->user()->notifications->find($id);
        abort_if(!$notification, 404);
        $notification->markAsRead();
        return view('notifications.show', ['notification'=>$notification]);
    }

    public function destroy($notification)
    {
        $the_notification = auth()->user()->notifications->find($notification);
        abort_if(!$the_notification, 404);
        $the_notification->delete();
        return response()->json(['message'=>'notification deleted']);
    }

    public function markAllAsRead()
    {
        auth()->user()->unreadNotifications->markAsRead();
        return redirect()->route('notifications.index')->with('success', 'Επιτυχής σήμανση όλων ως αναγνωσμένα');
    }

    public function markNotificationAsRead($notification)
    {
        $the_notification = auth()->user()->notifications->find($notification);
        abort_if(!$the_notification, 404);
        $the_notification->markAsRead();
        return response()->json(['message'=>'marked as read']);
    }

    public function deleteAll(User $user){
        // $user comes straight from the route URL (see notifications/index.blade.php,
        // which always builds it from the current Auth::user()) - without this check,
        // anyone could delete any other user's notifications just by changing the id
        // in that URL.
        abort_unless(auth()->id() === $user->id, 403);
        foreach ($user->notifications as $notification) {
            $notification->delete();
        }
        return redirect()->route('notifications.index')->with('success', 'Επιτυχής διαγραφή όλων των ειδοποιήσεων');
    }
}