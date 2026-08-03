<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authentik_monitor_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('service');
            $table->string('node_name');
            $table->string('node_ip');
            $table->string('status');
            $table->string('role')->nullable();
            $table->json('metrics')->nullable();
            $table->string('message')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique(['service', 'node_ip']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authentik_monitor_statuses');
    }
};
