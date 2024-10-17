<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Sheetmailer as ModelSheetmailer;

class SheetMailer extends Mailable
{
    use Queueable, SerializesModels;
    public $sheetmailer;
    public $additionalData;
    /**
     * Create a new message instance.
     */
    public function __construct(ModelSheetmailer $sheetmailer, $additionalData)
    {
        $this->sheetmailer = $sheetmailer;
        $this->additionalData = $additionalData;
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
}
