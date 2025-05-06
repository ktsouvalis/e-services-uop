<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Sheetmailer;
use romanzipp\QueueMonitor\Traits\IsMonitored;

class MailSheetMailer extends Mailable implements ShouldQueue
{
    use SerializesModels, isMonitored;
    public $sheetmailer;
    public $additionalData;
    public $username; //the user who triggered the mail job
    /**
     * Create a new message instance.
     */
    public function __construct(Sheetmailer $sheetmailer, $additionalData, $username)
    {
        $this->sheetmailer = $sheetmailer;
        $this->additionalData = $additionalData;
        $this->username = $username;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@uop.gr', 'Πανεπιστήμιο Πελοποννήσου'),
            subject: $this->sheetmailer->subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'sheetmailers.mail',
            with: [
                'user' => $this->username,
                'sheetmailer' => $this->sheetmailer,
                'additionalData' => $this->additionalData,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    // If you want to keep monitoring only for failure, you can override this method
    // public static function keepMonitorOnSuccess(): bool
    // {
    //     return false;
    // }
}
