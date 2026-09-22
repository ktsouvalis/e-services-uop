<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Single-row settings for the native Pangolin monitor poller — node IP,
// Pangolin Integration API URL, and API key, editable via an admin form
// instead of the old env-driven multi-node topology. Mirrors
// authentik_monitor_settings exactly (see CLAUDE.md's Pangolin module
// section — the Monitor tab is now single-node like Authentik's; the real
// multi-node HA topology in config/pangolin.php is still used by the Logs
// tab's config.yml generation, untouched by this change).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pangolin_monitor_settings', function (Blueprint $table) {
            $table->id();
            $table->string('node_ip')->nullable();
            $table->string('pangolin_url')->nullable();
            // Encrypted at rest via Crypt::encryptString(), same convention
            // as AuthentikMonitorSettings::api_token / Chatbot::api_key —
            // not an `encrypted` cast, to match that existing pattern.
            $table->text('api_key')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pangolin_monitor_settings');
    }
};
