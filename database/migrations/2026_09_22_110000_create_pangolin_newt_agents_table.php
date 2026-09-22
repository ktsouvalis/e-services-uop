<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Replaces the static PANGOLIN_NEWT_HOSTS_JSON env list — the number of Newt
// agents isn't fixed, so this needs to be admin-manageable (add/remove) at
// runtime rather than requiring an env change + redeploy. Single source of
// truth for both the Monitor tab's SSH reachability checks
// (App\Services\Pangolin\ClusterMonitor) and the Logs tab's config.yml
// (App\Services\Pangolin\ConfigYamlWriter, for logs_viewer.py's Newt
// access-log resolution) — see CLAUDE.md's Pangolin module section.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pangolin_newt_agents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ip')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pangolin_newt_agents');
    }
};
