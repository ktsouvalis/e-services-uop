<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class MailSentListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

   /**
     * Handle the event.
     *
     * @param  MessageSent  $event
     * @return void
     */
    public function handle(MessageSent $event)
    {
        $header = $event->message->getHeaders()->get('To')->getAddresses()[0];
        Log::channel('mails_delivery')->info("Mail sent: ". $header->getAddress());
    }
}