<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('hostname');
            $table->string('php_version')->default('8.4');
            $table->string('size')->default('large');
            $table->string('repository_url');
            $table->string('repository_branch')->default('main');
            $table->timestamps();

            $table->unique(['server_id', 'hostname']);
        });
    }
};
