<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Múltiplos telefones/emails por pessoa. O 1º item espelha em phone/email (principal),
        // mantendo WhatsApp/cobranças/notificações funcionando sem alteração.
        Schema::table('persons', function (Blueprint $table) {
            $table->json('phones')->nullable()->after('phone2');
            $table->json('emails')->nullable()->after('phones');
        });

        // Pets cadastrados por unidade.
        Schema::create('pets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->string('name');
            $table->string('species', 20)->default('dog'); // dog|cat|bird|fish|rodent|reptile|other
            $table->string('breed')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
            $table->index(['tenant_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pets');

        Schema::table('persons', function (Blueprint $table) {
            $table->dropColumn(['phones', 'emails']);
        });
    }
};
