<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Personal details plus organisation details (`org_` mirrors `organisations`).
     */
    public function up(): void
    {
        Schema::create('organiser_registrations', function (Blueprint $table) {
            $table->id();

            // Personal details
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone', 30);
            $table->string('country_code', 10);
            $table->string('country', 100);
            $table->string('password');
            $table->string('otp', 6)->nullable();
            $table->timestamp('otp_expired_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();

            // Organisation details (`org_` = organisations table fields)
            $table->string('org_organiser_name')->nullable();
            $table->string('org_name')->nullable();
            $table->string('org_email')->nullable();
            $table->string('org_country_code')->nullable();
            $table->string('org_phone')->nullable();
            $table->string('org_country')->nullable();
            $table->string('org_city')->nullable();
            $table->string('org_address1')->nullable();
            $table->string('org_address2')->nullable();
            $table->string('org_postal_code')->nullable();
            $table->string('org_website')->nullable();
            $table->string('org_logo')->nullable();
            $table->string('org_banner')->nullable();
            $table->string('org_description')->nullable();
            $table->string('org_keywords')->nullable();
            $table->string('org_facebook_link')->nullable();
            $table->string('org_instagram_link')->nullable();
            $table->string('org_twitter_link')->nullable();
            $table->string('org_youtube_link')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organiser_registrations');
    }
};
