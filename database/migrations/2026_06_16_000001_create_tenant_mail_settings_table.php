<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Configuração de SMTP (e IMAP para a pasta Enviados) por tenant — e-mail white-label.
        Schema::create('tenant_mail_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->boolean('enabled')->default(false);

            // SMTP (envio)
            $table->string('host')->nullable();
            $table->integer('port')->default(587);
            $table->string('encryption', 10)->nullable();   // tls | ssl | null
            $table->string('username')->nullable();
            $table->text('password')->nullable();            // encrypted
            $table->string('from_address')->nullable();
            $table->string('from_name')->nullable();

            // IMAP (cópia na pasta Enviados)
            $table->boolean('save_to_sent')->default(false);
            $table->string('imap_host')->nullable();
            $table->integer('imap_port')->default(993);
            $table->string('imap_encryption', 10)->default('ssl'); // ssl | tls | null
            $table->string('imap_username')->nullable();
            $table->text('imap_password')->nullable();        // encrypted
            $table->string('sent_folder')->default('Sent');

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_mail_settings');
    }
};
