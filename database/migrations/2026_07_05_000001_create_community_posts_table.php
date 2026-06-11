<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('condominium_id');
            $table->uuid('author_user_id')->nullable();
            $table->uuid('author_person_id')->nullable();
            $table->string('post_type', 20)->default('classified'); // notice | classified
            $table->string('status', 20)->default('pending'); // pending | published | rejected | archived
            $table->string('category', 60)->nullable();
            $table->string('title', 180);
            $table->text('body');
            $table->decimal('price', 12, 2)->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('contact_email')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->uuid('moderated_by')->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('condominium_id')->references('id')->on('condominiums')->cascadeOnDelete();
            $table->foreign('author_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('author_person_id')->references('id')->on('persons')->nullOnDelete();
            $table->foreign('moderated_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'condominium_id', 'status']);
            $table->index(['post_type', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_posts');
    }
};
