<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Newt agent monitoring was added back to the (now single-node) Pangolin
// ClusterMonitor — up to 3 hosts all share service='newt', so the
// unique('service') from fix_pangolin_monitor_statuses_unique_key (same
// day) is too narrow: it would let only one Newt row exist at a time,
// clobbering the other two on every poll. Widen to (service, node_name)
// instead — node_name is stable per Newt host (from config('pangolin.
// newt.hosts')) *and* stable for the single-node pangolin/gerbil/api rows
// (always 'Pangolin'/'Gerbil'/'Integration API' regardless of what IP the
// admin has configured), so this still avoids the original duplicate-fork
// bug for those while allowing multiple Newt rows.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pangolin_monitor_statuses', function (Blueprint $table) {
            $table->dropUnique(['service']);
            $table->unique(['service', 'node_name']);
        });
    }

    public function down(): void
    {
        Schema::table('pangolin_monitor_statuses', function (Blueprint $table) {
            $table->dropUnique(['service', 'node_name']);
            $table->unique('service');
        });
    }
};
