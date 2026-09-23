<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Persisted, deduped Newt ACCESS sessions — one row per (agent, session id),
// upserted by App\Services\Pangolin\NewtConnectionSync on every "Fetch now".
// No duration column: computed from started_at/ended_at in the view rather
// than stored derived data. See CLAUDE.md's Pangolin module section.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pangolin_newt_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newt_agent_id')->nullable()->constrained('pangolin_newt_agents')->nullOnDelete();
            // Snapshot at fetch time so history survives the agent row being
            // edited/deleted later.
            $table->string('agent_name');
            $table->string('agent_ip');

            $table->string('session_id');
            // The log line's "resource=" field — actually a Pangolin siteId,
            // not a siteResourceId (see NewtAccessLogResolver's docblock).
            $table->unsignedInteger('resource_id');
            $table->string('proto', 10);
            $table->string('src_ip', 45);
            $table->string('src_port', 10);
            $table->string('dst_ip', 45);
            $table->string('dst_port', 10);

            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();

            // Resolved via a direct Postgres read against Pangolin's own DB
            // (NewtAccessLogResolver::buildLookupMaps()) — null when that
            // lookup failed or the client/site/resource wasn't found, in
            // which case the view falls back to src_ip/resource_id.
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('client_name')->nullable();
            $table->string('site_name')->nullable();
            $table->string('resource_name')->nullable();

            $table->timestamps();

            $table->unique(['newt_agent_id', 'session_id']);
            $table->index('started_at');
            $table->index('user_email');
            $table->index('resource_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pangolin_newt_connections');
    }
};
