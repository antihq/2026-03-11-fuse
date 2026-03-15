<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('name');
            $table->string('ip_address');
            $table->unsignedInteger('ram_mb');
            $table->text('authorized_keys')->nullable();
            $table->string('ssh_setup_token')->nullable()->unique();
            $table->timestamp('ssh_ready_at')->nullable();
            $table->enum('provision_status', ['pending', 'ssh_setup', 'provisioning', 'provisioned', 'failed'])->default('pending');
            $table->foreignId('provision_task_id')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->string('sites_user')->default('deploy');
            $table->text('mysql_root_password')->nullable();
            $table->text('deploy_user_password')->nullable();
            $table->text('server_public_key')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('provision_task_id');
        });
    }
};
