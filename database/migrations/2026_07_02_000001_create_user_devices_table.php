<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_devices')) {
            Schema::create('user_devices', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id')->nullable();
                $table->uuid('user_id');
                // Token FCM do dispositivo; único globalmente (um device = um token vigente).
                $table->string('fcm_token', 512)->unique();
                $table->string('platform', 20)->default('android'); // android | ios (futuro)
                $table->string('app_version', 20)->nullable();
                $table->string('device_name', 120)->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->index(['tenant_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
