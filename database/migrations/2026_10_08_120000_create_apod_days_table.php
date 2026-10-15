<?php

use Voyager\Database\Migrations\Migration;
use Voyager\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app('db.schema')->create('apod_days', function (Blueprint $table) {
            $table->id();
            $table->string('date')->unique();
            $table->string('title')->nullable();
            $table->text('explanation')->nullable();
            $table->text('url')->nullable();
            $table->text('hdurl')->nullable();
            $table->string('media_type')->nullable();
            $table->text('copyright')->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->text('permalink')->nullable();
            $table->text('alt')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app('db.schema')->dropIfExists('apod_days');
    }
};
