<?php

namespace App\Jobs\Sheetmailers;

use App\Mail\MailSheetMailer;
use App\Models\Sheetmailer;
use App\Services\DeliveryLog;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use romanzipp\QueueMonitor\Traits\IsMonitored;
use Throwable;

class SendSheetmailerEmail implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, IsMonitored, Queueable, SerializesModels;

    /**
     * @param array<string, mixed> $placeholders this recipient's {{column_name}} => value map
     */
    public function __construct(
        private readonly Sheetmailer $sheetmailer,
        private readonly string $email,
        private readonly array $placeholders,
        private readonly string $triggeredBy,
        private readonly string $logPath,
    ) {
    }

    /**
     * Surfaced by the queue-monitor UI (config('queue-monitor.ui.show_custom_data'))
     * so the recipient is visible per-row in /jobs, not just the job class name.
     */
    public function initialMonitorData(): ?array
    {
        return ['recipient' => $this->email];
    }

    public function handle(): void
    {
        // A batch is cancelled (by SheetmailerController::send() never happens
        // today, but Bus::batch()'s own failure handling can cancel remaining
        // jobs) - skip sending to recipients whose job hasn't run yet.
        if ($this->batch()?->cancelled()) {
            return;
        }

        // Belt-and-braces with initialMonitorData(): the 'database'/'redis' queue
        // connections fire JobQueued (which initialMonitorData() hooks) when the
        // batch is dispatched, but the 'sync' connection (used in tests, and
        // anywhere QUEUE_CONNECTION=sync) never fires it - it runs the job inline
        // without ever "queueing" it, so the monitor row only exists once handle()
        // starts. Setting it here too means the recipient shows up either way.
        $this->queueData(['recipient' => $this->email], merge: true);

        Mail::to($this->email)->send(new MailSheetMailer($this->sheetmailer, $this->placeholders));

        Log::channel('sheetmailers')->info(
            'Sheetmailer ' . $this->sheetmailer->id . ' mail sent to ' . $this->email . ' by ' . $this->triggeredBy
        );

        DeliveryLog::append($this->logPath, "Sent to {$this->email}");
    }

    public function failed(Throwable $exception): void
    {
        Log::channel('sheetmailers')->error(
            'Sheetmailer ' . $this->sheetmailer->id . ' mail NOT sent to ' . $this->email . ' (sent by ' . $this->triggeredBy . ')',
            ['error' => $exception->getMessage()]
        );

        DeliveryLog::append($this->logPath, "FAILED to {$this->email}: {$exception->getMessage()}");
    }
}
