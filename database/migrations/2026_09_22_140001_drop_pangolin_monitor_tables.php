<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// Pangolin's Monitor and Logs tabs were removed 2026-09-22 — only Import and
// Normalize remain (pangolin_runs, untouched, is shared by both and stays).
// Historical create-table migrations for these are left in place (not
// deleted) since they already ran in production — this is a new migration
// to undo them, per the project's usual convention of never editing/removing
// migrations that have already run elsewhere.
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('pangolin_newt_agents');
        Schema::dropIfExists('pangolin_monitor_statuses');
        Schema::dropIfExists('pangolin_monitor_settings');
    }

    public function down(): void
    {
        // Not reversible — the feature and its models/migrations are gone
        // from the tree, so there's no schema left to recreate against.
    }
};
