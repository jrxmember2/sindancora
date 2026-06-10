<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Integração de armazenamento externo por tenant (Google Drive) para descarregar a mídia
        // pesada de WhatsApp. O refresh_token fica encriptado. Os arquivos são do tenant.
        Schema::create('tenant_drive_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('provider', 30)->default('google_drive');
            $table->string('account_email')->nullable();
            $table->text('refresh_token')->nullable();           // encrypted
            $table->string('root_folder_id')->nullable();        // pasta criada via drive.file
            $table->string('status', 20)->default('disconnected'); // connected | disconnected | error
            $table->uuid('connected_by')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_drive_settings');
    }
};
