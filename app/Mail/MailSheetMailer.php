<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use App\Models\Sheetmailer;
use App\Services\Sheetmailers\PlaceholderReplacer;

class MailSheetMailer extends Mailable
{
    // Not ShouldQueue: sending is now driven by App\Jobs\Sheetmailers\SendSheetmailerEmail,
    // one job per recipient dispatched inside a Bus::batch() for live send progress -
    // that job (not this Mailable) is what's actually queued and monitored.
    use SerializesModels;
    public $sheetmailer;
    public $placeholders;
    public $body;
    public $signature;

    /**
     * @param array<string, mixed> $placeholders this recipient's {{column_name}} => value
     *        map, built from the uploaded spreadsheet's header row (see RecipientListParser)
     */
    public function __construct(Sheetmailer $sheetmailer, array $placeholders = [])
    {
        $this->sheetmailer = $sheetmailer;
        $this->placeholders = $placeholders;
        $this->body = PlaceholderReplacer::replace($sheetmailer->body, $placeholders);
        $this->signature = PlaceholderReplacer::replace($sheetmailer->signature, $placeholders);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@uop.gr', 'Πανεπιστήμιο Πελοποννήσου'),
            subject: PlaceholderReplacer::replace($this->sheetmailer->subject, $this->placeholders),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // $body, $signature are already merged with this recipient's placeholders
        // (see the constructor) and exposed to the view automatically -
        // Mailable::buildViewData() forwards all public properties, no explicit with() needed.
        return new Content(
            view: 'sheetmailers.mail',
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
