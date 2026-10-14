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
        Schema::create('sensor_data', function (Blueprint $table) {
            $table->id();
            $table->string('device_uuid')->index();
            $table->unsignedBigInteger('timestamp_unix')->index();
            $table->integer('sample_count')->default(0);
            $table->integer('window_seconds')->default(10);
            $table->integer('steps')->default(0);
            $table->integer('rumination_events')->default(0);
            $table->float('temperatura_animal')->nullable();
            $table->json('samples')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sensor_data');
    }
};
