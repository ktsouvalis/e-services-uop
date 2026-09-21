<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Single-row settings for the native Authentik monitor poller — node IP,
// public Authentik URL, and API token, editable via an admin form instead of
// pasted per run. See CLAUDE.md's Authentik module section.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authentik_monitor_settings', function (Blueprint $table) {
            $table->id();
            $table->string('node_ip')->nullable();
            $table->string('authentik_url')->nullable();
            // Encrypted at rest via Crypt::encryptString(), same convention
            // as Chatbot::api_key (see ChatbotController) — not an
            // `encrypted` cast, to match that existing pattern exactly.
            $table->text('api_token')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authentik_monitor_settings');
    }
};
