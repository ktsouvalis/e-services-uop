<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_arp_histories', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address');
            $table->string('mac_address'); // canonical lowercase colon-separated form
            $table->string('vlan')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();

            // "current MAC" for an IP = the row with the latest last_seen_at.
            $table->index(['ip_address', 'last_seen_at']);
            $table->index('mac_address');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_arp_histories');
    }
};
