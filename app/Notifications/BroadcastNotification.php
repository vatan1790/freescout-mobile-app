<?php

/**
 * Used to display website notifications in the menu and browser push notifications.
 */

namespace App\Notifications;

use App\Conversation;
use App\Subscription;
use App\Thread;
use App\Channels\RealtimeBroadcastChannel;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class BroadcastNotification extends Notification
{
    public $conversation;
    public $thread;
    public $mediums;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($conversation, $thread, $mediums)
    {
        $this->conversation = $conversation;
        $this->thread = $thread;
        $this->mediums = $mediums;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $user
     * @return array
     */
    public function via($user)
    {
        return [\App\Channels\RealtimeBroadcastChannel::class];
        // Standard "broadcast" channel creates a queuable event which runs broadcast for the broadcaster.
        //return ['broadcast'];
    }

    /**
     * Get the broadcastable representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return BroadcastMessage
     */
    public function toBroadcast($user)
    {
        return new BroadcastMessage([
            'thread_id' => $this->thread->id,
            'number'    => $this->conversation->number,
            'mediums'   => $this->mediums,
        ]);
    }

    public static function fetchPayloadData($payload)
    {
        $data = [];

        if (empty($payload->thread_id) || empty($payload->mediums)) {
            return $data;
        }

        $thread = Thread::find($payload->thread_id);

        if (empty($thread)) {
            return $data;
        }

        // Dummy DB notification to pass to the template
        $db_notification = new \Illuminate\Notifications\DatabaseNotification();

        // HTML for the website notification
        if (in_array(Subscription::MEDIUM_EMAIL, $payload->mediums)) {

            $web_notifications_info = [];

            //$db_notification->id = 'dummy';
            $web_notifications_info['notification'] = $db_notification;
            $web_notifications_info['created_at'] = \Carbon\Carbon::now();
            // ['notification']->read_at
            // ['notification']->id
            $web_notifications_info['conversation'] = $thread->conversation;
            $web_notifications_info['thread'] = $thread;
            $web_notifications_info['last_thread_body'] = $thread->body;

            $data['web']['html'] = view('users/partials/web_notifications', [
                'web_notifications_info_data' => [$web_notifications_info],
            ])->render();
        }

        // Text and url for the browser push notification
        if (in_array(Subscription::MEDIUM_BROWSER, $payload->mediums)) {
            $data['browser']['text'] = strip_tags($thread->getActionDescription($thread->conversation->number));
            $data['browser']['url'] = $thread->conversation->url(null, $thread->id, ['mark_as_read' => $db_notification->id]);
        }

        return $data;
    }
}