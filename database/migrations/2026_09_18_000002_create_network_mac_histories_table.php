<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_mac_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_device_id')->constrained()->cascadeOnDelete();
            $table->string('mac_address'); // canonical lowercase colon-separated form
            $table->string('port');
            $table->string('vlan')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();

            // "current location" for a MAC = the row with the latest last_seen_at.
            $table->index(['mac_address', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_mac_histories');
    }
};
