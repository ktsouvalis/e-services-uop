<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The original unique(['service', 'node_ip']) let PollCluster's upsert fork
// a new row per service every time node_ip changed (e.g. unset '-' -> a real
// IP once Monitor settings were first saved), instead of updating the one
// row this table is documented as holding per service. Found live: the
// "authentik" row's pre-config placeholder ('-' node_ip) survived forever
// alongside the real-IP row, and unordered reads non-deterministically
// picked either one. See CLAUDE.md's Authentik module section.
return new class extends Migration
{
    public function up(): void
    {
        // Keep only the most-recently-checked row per service before the
        // unique index is narrowed to `service` alone.
        $keepIds = DB::table('authentik_monitor_statuses')
            ->selectRaw('MAX(id) as id')
            ->groupBy('service')
            ->pluck('id');

        DB::table('authentik_monitor_statuses')->whereNotIn('id', $keepIds)->delete();

        Schema::table('authentik_monitor_statuses', function (Blueprint $table) {
            $table->dropUnique(['service', 'node_ip']);
            $table->unique('service');
        });
    }

    public function down(): void
    {
        Schema::table('authentik_monitor_statuses', function (Blueprint $table) {
            $table->dropUnique(['service']);
            $table->unique(['service', 'node_ip']);
        });
    }
};
