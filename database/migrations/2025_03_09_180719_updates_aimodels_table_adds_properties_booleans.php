<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_models', function (Blueprint $table) {
            $table->boolean('accepts_developer_messages')->default(false);
            $table->boolean('accepts_system_messages')->default(false);
            $table->boolean('reasoning_effort')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
