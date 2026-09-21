<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Re-created after 2026_09_21_092319_drop_authentik_monitor_statuses_table
// (which removed the old TUI-embedding approach's table) — the Monitor tab
// went from "shell out to akropolis behind a browser terminal" to "poll the
// (single-node) Authentik stack natively over HTTP", so this table is back,
// same shape as before it was dropped. See CLAUDE.md's Authentik module
// section.
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
