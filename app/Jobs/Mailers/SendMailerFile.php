<?php

namespace App\Jobs\Mailers;

use App\Mail\MailToDepartment;
use App\Models\Department;
use App\Models\Mailer;
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

class SendMailerFile implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, IsMonitored, Queueable, SerializesModels;

    public function __construct(
        private readonly Mailer $mailer,
        private readonly Department $department,
        private readonly string $filename,
        private readonly string $triggeredBy,
    ) {
    }

    /**
     * Surfaced by the queue-monitor UI (config('queue-monitor.ui.show_custom_data'))
     * so the department/file are visible per-row in /jobs, not just the job class name.
     */
    public function initialMonitorData(): ?array
    {
        return ['department' => $this->department->name, 'file' => $this->filename];
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        // Belt-and-braces with initialMonitorData(): under QUEUE_CONNECTION=sync
        // (tests, or anywhere it's set that way) JobQueued never fires, so the
        // monitor row only exists once handle() starts - set it here too.
        $this->queueData(['department' => $this->department->name, 'file' => $this->filename], merge: true);

        $path = "/mailers/{$this->mailer->id}/{$this->filename}";

        Mail::to($this->department->email)->send(
            new MailToDepartment($this->mailer->subject, $this->mailer->signature, $this->mailer->body, [$path])
        );

        Log::channel('mailers')->info(
            "Mailer {$this->mailer->id} file '{$this->filename}' to {$this->department->name}: sent by {$this->triggeredBy}"
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::channel('mailers')->error(
            "Mailer {$this->mailer->id} file '{$this->filename}' to {$this->department->name} NOT sent (triggered by {$this->triggeredBy})",
            ['error' => $exception->getMessage()]
        );
    }
}
