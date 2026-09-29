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
        Schema::create('organisation_stripe_external_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_stripe_account_id');
            $table->string('stripe_external_account_id');
            $table->string('object');
            $table->string('bank_name')->nullable();
            $table->string('last4', 4)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('status')->nullable();
            $table->boolean('is_default_for_currency')->default(false);
            $table->string('fingerprint')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->foreign('organisation_stripe_account_id', 'org_stripe_ext_acct_fk')
                ->references('id')
                ->on('organisation_stripe_accounts')
                ->cascadeOnDelete();

            $table->unique('stripe_external_account_id', 'org_stripe_ext_acct_stripe_id_uq');
            $table->index(['organisation_stripe_account_id', 'is_default_for_currency'], 'org_stripe_ext_acct_default_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organisation_stripe_external_accounts');
    }
};
