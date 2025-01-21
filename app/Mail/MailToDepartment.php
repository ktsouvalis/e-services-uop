<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Contracts\Queue\ShouldQueue;
use romanzipp\QueueMonitor\Traits\IsMonitored;

class MailToDepartment extends Mailable implements ShouldQueue
{
    use SerializesModels, isMonitored;
    public $subject;
    public $signature;
    public $body;
    public $files;
    public $username; //the user who triggered the mail job
    /**
     * Create a new message instance.
     */
    public function __construct($subject, $signature, $body, $files, $username)
    {
        //
        $this->subject = $subject;
        $this->signature = $signature;
        $this->body = $body;
        $this->files = $files;
        $this->username = $username;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@uop.gr', 'Πανεπιστήμιο Πελοποννήσου'),
            subject: $this->subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mailers.mail',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return array_map(function ($file) {
            return Attachment::fromStorage($file);
        }, $this->files);
    }
}
