<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Failed-dial marking for Newt connections. Rows stay one-per-raw-flow (a
// single Newt ACCESS START/END pair, not a logical user session — those are
// built at read time by App\Services\Pangolin\NewtSessionConsolidator).
// `failed` is set only when NewtAccessLogParser correlated a
// "TCP Forwarder: Failed to connect to ..." line to the session, never from
// duration alone (a timed-out dial happens to last exactly Newt's 5s
// tcpConnectTimeout, a refused one ~0s, but so can real short flows).
// failure_reason is refused|timeout|unreachable|other; failure_detail is the
// raw dial error text.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pangolin_newt_connections', function (Blueprint $table) {
            $table->boolean('failed')->default(false)->index()->after('ended_at');
            $table->string('failure_reason', 20)->nullable()->after('failed');
            $table->string('failure_detail')->nullable()->after('failure_reason');
        });
    }

    public function down(): void
    {
        Schema::table('pangolin_newt_connections', function (Blueprint $table) {
            $table->dropIndex(['failed']);
            $table->dropColumn(['failed', 'failure_reason', 'failure_detail']);
        });
    }
};
