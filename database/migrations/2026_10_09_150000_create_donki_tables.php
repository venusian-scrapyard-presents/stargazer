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
        // DONKI records as fetched, one row per record; a revision replaces its row.
        app('db.schema')->create('donki_records', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('record_key');
            $table->unsignedBigInteger('event_time')->index();
            $table->text('record');
            $table->timestamps();
            $table->unique(['type', 'record_key']);
        });

        // When each type's days were last fetched, so a range already held is not asked for again.
        app('db.schema')->create('donki_days', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('day');
            $table->unsignedBigInteger('fetched_at');
            $table->timestamps();
            $table->unique(['type', 'day']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app('db.schema')->dropIfExists('donki_days');
        app('db.schema')->dropIfExists('donki_records');
    }
};
