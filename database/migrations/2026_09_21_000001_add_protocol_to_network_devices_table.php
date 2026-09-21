<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('network_devices', function (Blueprint $table) {
            // Confirmed live: all 10 Cisco-vendor devices in this network only
            // accept telnet (no working SSH auth), the Huawei devices are all
            // SSH-only - kept independent of `vendor` rather than inferred
            // from it, since a future device could break that 1:1 mapping.
            $table->string('protocol')->default('ssh')->after('vendor');
        });
    }

    public function down(): void
    {
        Schema::table('network_devices', function (Blueprint $table) {
            $table->dropColumn('protocol');
        });
    }
};
