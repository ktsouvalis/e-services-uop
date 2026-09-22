<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Same bug class fixed for authentik_monitor_statuses in
// 2026_09_21_130000_fix_authentik_monitor_statuses_unique_key.php, hit here
// pre-emptively rather than live: unique(['service', 'node_ip']) lets
// PollCluster's upsert fork a new row per service every time node_ip changes
// (e.g. the pre-config placeholder '-' -> a real IP once Monitor settings are
// first saved), instead of updating the one row this table is documented as
// holding per service. The Monitor tab is now single-node/DB-configurable
// (see CLAUDE.md's Pangolin module section), so node_ip is no longer part of
// a row's identity — narrow the unique index to `service` alone before that
// can ever happen here too.
return new class extends Migration
{
    public function up(): void
    {
        $keepIds = DB::table('pangolin_monitor_statuses')
            ->selectRaw('MAX(id) as id')
            ->groupBy('service')
            ->pluck('id');

        DB::table('pangolin_monitor_statuses')->whereNotIn('id', $keepIds)->delete();

        Schema::table('pangolin_monitor_statuses', function (Blueprint $table) {
            $table->dropUnique(['service', 'node_ip']);
            $table->unique('service');
        });
    }

    public function down(): void
    {
        Schema::table('pangolin_monitor_statuses', function (Blueprint $table) {
            $table->dropUnique(['service']);
            $table->unique(['service', 'node_ip']);
        });
    }
};
