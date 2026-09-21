<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('mgmt_ip');
            $table->string('vendor'); // 'huawei' | 'cisco'
            $table->string('role'); // 'l2' | 'core'
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_polled_at')->nullable();
            $table->string('last_poll_status')->nullable(); // 'ok' | 'error'
            $table->string('last_poll_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_devices');
    }
};
