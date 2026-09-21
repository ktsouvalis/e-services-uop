<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_poll_runs', function (Blueprint $table) {
            $table->id();
            // How many devices PollAllDevices dispatched jobs for - compared
            // against how many network_devices rows carry this run's id to
            // tell an in-progress/stuck run from a fully-reported one.
            $table->unsignedInteger('device_count');
            $table->timestamp('started_at');
            $table->timestamps();
        });

        Schema::table('network_devices', function (Blueprint $table) {
            $table->foreignId('last_poll_run_id')->nullable()->after('last_poll_error')
                ->constrained('network_poll_runs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('network_devices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_poll_run_id');
        });

        Schema::dropIfExists('network_poll_runs');
    }
};
