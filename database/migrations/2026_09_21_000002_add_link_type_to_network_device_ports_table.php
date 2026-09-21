<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('network_device_ports', function (Blueprint $table) {
            // 'trunk' | 'access' | 'hybrid' | null (unknown/not yet imported).
            // A trunk carries traffic for many devices across a switch-to-
            // switch link, so a MAC learned there isn't where that device is
            // physically plugged in - PollSwitchMacTable skips these when
            // building history, keeping only the actual access-port location.
            $table->string('link_type')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('network_device_ports', function (Blueprint $table) {
            $table->dropColumn('link_type');
        });
    }
};
