<?php

use App\Enum\StripeOnboardingStatusEnum;
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
        Schema::create('organisation_stripe_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')
                ->unique()
                ->constrained('organisations')
                ->cascadeOnDelete();
            $table->string('stripe_account_id')->unique();
            $table->string('account_type')->default('custom');
            $table->string('country', 2);
            $table->string('default_currency', 3)->default('gbp');
            $table->string('business_type')->nullable();
            $table->string('email')->nullable();
            $table->boolean('charges_enabled')->default(false);
            $table->boolean('payouts_enabled')->default(false);
            $table->boolean('details_submitted')->default(false);
            $table->string('onboarding_status')->default(StripeOnboardingStatusEnum::NOT_STARTED->value);
            $table->json('requirements_currently_due')->nullable();
            $table->json('requirements_past_due')->nullable();
            $table->string('requirements_disabled_reason')->nullable();
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('onboarding_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organisation_stripe_accounts');
    }
};
