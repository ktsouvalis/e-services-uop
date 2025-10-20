<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sheetmailers', function (Blueprint $table) {
            if (!Schema::hasColumn('sheetmailers', 'is_public')) {
                $table->boolean('is_public')->default(false)->after('user_id');
            }
        });

        // Backfill existing rows to private just in case (explicitly set to false)
        if (Schema::hasColumn('sheetmailers', 'is_public')) {
            DB::table('sheetmailers')->update(['is_public' => false]);
        }
    }

    public function down(): void
    {
        Schema::table('sheetmailers', function (Blueprint $table) {
            if (Schema::hasColumn('sheetmailers', 'is_public')) {
                $table->dropColumn('is_public');
            }
        });
    }
};
