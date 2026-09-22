<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// The entire Authentik feature module (Monitor + Logs dashboard at
// /authentik) was removed 2026-09-22 — kept only App\Http\Middleware\
// AuthentikSsoAuth (production forward-auth login), which has nothing to do
// with this dashboard and isn't touched here. Historical create-table
// migrations for these are left in place (not deleted) since they already
// ran in production — this is a new migration to undo them, per the
// project's usual convention of never editing/removing migrations that have
// already run elsewhere.
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('authentik_monitor_statuses');
        Schema::dropIfExists('authentik_monitor_settings');
        Schema::dropIfExists('authentik_log_runs');
    }

    public function down(): void
    {
        // Not reversible — the feature and its models/migrations are gone
        // from the tree, so there's no schema left to recreate against.
    }
};
