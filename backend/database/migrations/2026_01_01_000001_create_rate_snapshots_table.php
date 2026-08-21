<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('base', 3);
            $table->string('quote', 3);
            $table->decimal('rate', 16, 6);
            $table->timestamp('captured_at')->index();
            $table->timestamps();

            $table->index(['quote', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_snapshots');
    }
};
