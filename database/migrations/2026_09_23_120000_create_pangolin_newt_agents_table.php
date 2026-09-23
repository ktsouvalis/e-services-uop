<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Recreates the table dropped by 2026_09_22_140001_drop_pangolin_monitor_tables.php
// — a new migration rather than reverting that one, per this project's
// convention of never editing/removing migrations that already ran
// elsewhere (see that file's own comment). Newt agents are admin-managed
// (name + IP) via the pangolin.newt-agents CRUD routes, feeding
// App\Services\Pangolin\NewtConnectionSync — see CLAUDE.md's Pangolin
// module section.
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
