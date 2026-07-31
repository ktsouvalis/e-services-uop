<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pangolin_runs', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // logs | import | normalize
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('queued'); // queued | running | completed | failed
            $table->json('options')->nullable();
            $table->string('input_path')->nullable();
            $table->string('report_path')->nullable();
            $table->string('extra_path')->nullable();
            $table->json('summary')->nullable();
            $table->longText('stdout')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pangolin_runs');
    }
};
