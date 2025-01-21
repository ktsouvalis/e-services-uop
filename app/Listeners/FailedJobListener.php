<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class FailedJobListener
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
     */
    public function handle(JobFailed $event)
    {
        $jobName = $event->job->resolveName();
        $payload = json_decode($event->job->getRawBody(), true);
        $command = unserialize($payload['data']['command']);
        $email = $command->mailable->to[0]['address'];
        $username = $command->mailable->username; //this is the username of the user that triggered the job. it is a public property of all mailable classes
        if(str_contains($jobName, 'App\Mail\MailSheetMailer')){
            $log_channel = 'sheetmailers_failure';  
        }
        else if(str_contains($jobName, 'App\Mail\MailToDepartment')){
            $log_channel = 'mailers';
        }
        Log::channel($log_channel)->error("$jobName by $username failed: " . $email. " " . $event->exception->getMessage());
        // else if(str_contains($jobName, 'App\Jobs\UpdateEDirectorateJob')){
        //     $username = $command->username; //this is the username of the user that triggered the job. it is a public property of the job class
        //     Log::channel('commands_executed')->error("$jobName by $username failed: " . $event->exception->getMessage());
        //     foreach(User::all() as $user){
        //         if(Superadmin::where('user_id', $user->id)->exists() or $user->username == $username){
        //             $user->notify(new UserNotification("Η εφαρμογή πρωτοκόλλου δεν ενημερώθηκε για τις αλλαγές στη Βάση Δεδομένων. Το api request έγινε από $username", 'Αποτυχία API UpdateEDirectorateJob'));
        //         }
        //     }
        // }
    }
}
