<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->string('gateway', 30)->nullable()->after('payment_method');     // asaas
            $table->string('gateway_payment_id')->nullable()->after('gateway');     // id da cobrança no Asaas
            $table->string('gateway_status', 30)->nullable()->after('gateway_payment_id');
            $table->text('invoice_url')->nullable()->after('gateway_status');        // fatura web (boleto + PIX)
            $table->text('bank_slip_url')->nullable()->after('invoice_url');         // PDF do boleto
            $table->string('bank_slip_line')->nullable()->after('bank_slip_url');    // linha digitável
            $table->text('pix_payload')->nullable()->after('bank_slip_line');        // PIX copia-e-cola
            $table->text('pix_qrcode')->nullable()->after('pix_payload');            // QR Code base64
            $table->timestamp('gateway_synced_at')->nullable()->after('pix_qrcode');

            $table->index('gateway_payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropIndex(['gateway_payment_id']);
            $table->dropColumn([
                'gateway', 'gateway_payment_id', 'gateway_status', 'invoice_url',
                'bank_slip_url', 'bank_slip_line', 'pix_payload', 'pix_qrcode', 'gateway_synced_at',
            ]);
        });
    }
};
