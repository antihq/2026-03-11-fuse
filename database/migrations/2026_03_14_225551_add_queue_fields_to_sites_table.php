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
        Schema::table('sites', function (Blueprint $table) {
            $table->boolean('queue_enabled')->default(false)->after('database_password');
            $table->integer('queue_processes')->default(1)->after('queue_enabled');
        });
    }
};
