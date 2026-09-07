<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;

class MailToDepartment extends Mailable
{
    // Not ShouldQueue: sending is now driven by App\Jobs\Mailers\SendMailerFile,
    // dispatched per department (individually, or one per job inside a Bus::batch()
    // for send_all's live progress) - that job (not this Mailable) is what's
    // actually queued and monitored.
    use SerializesModels;
    public $subject;
    public $signature;
    public $body;
    public $files;
    /**
     * Create a new message instance.
     */
    public function __construct($subject, $signature, $body, $files)
    {
        $this->subject = $subject;
        $this->signature = $signature;
        $this->body = $body;
        $this->files = $files;
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
