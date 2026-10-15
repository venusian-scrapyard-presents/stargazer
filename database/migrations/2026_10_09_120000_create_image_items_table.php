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
        app('db.schema')->create('image_items', function (Blueprint $table) {
            $table->id();
            $table->string('nasa_id')->unique();
            $table->text('title')->nullable();
            $table->text('description')->nullable();
            $table->text('description_508')->nullable();
            $table->string('center')->nullable();
            $table->string('date_created')->nullable();
            $table->string('media_type')->nullable();
            $table->text('keywords')->nullable();
            $table->text('album')->nullable();
            $table->text('photographer')->nullable();
            $table->text('secondary_creator')->nullable();
            $table->text('location')->nullable();
            // The asset manifest's files, as fetched when the item was opened.
            $table->text('hrefs')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app('db.schema')->dropIfExists('image_items');
    }
};
