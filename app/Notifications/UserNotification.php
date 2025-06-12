<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use romanzipp\QueueMonitor\Traits\IsMonitored;

class UserNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use isMonitored;

    private $message;
    private $summary;
    /**
     * Create a new notification instance.
     */
    public function __construct($message, $summary)
    {
        //
        $this->message = $message;
        $this->summary = $summary;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->line('The introduction to the notification.')
                    ->action('Notification Action', url('/'))
                    ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => $this->message,
            'summary' => $this->summary,
        ];
    }
}