<?php

use App\Enum\OrderItemPricingTypeEnum;
use App\Enum\OrderSalesChannelEnum;
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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('sales_channel', 30)
                ->default(OrderSalesChannelEnum::ONLINE->value)
                ->after('status');
            $table->foreignId('created_by')
                ->nullable()
                ->after('tickets_pdf_path')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('notes')
                ->nullable()
                ->after('created_by');

            $table->index('sales_channel');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('pricing_type', 30)
                ->default(OrderItemPricingTypeEnum::PAID->value)
                ->after('quantity');

            $table->index('pricing_type');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('reference_name', 255)
                ->nullable()
                ->after('payment_method');
            $table->string('external_reference_id', 255)
                ->nullable()
                ->after('reference_name');
            $table->text('notes')
                ->nullable()
                ->after('failure_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'reference_name',
                'external_reference_id',
                'notes',
            ]);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['pricing_type']);
            $table->dropColumn('pricing_type');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['sales_channel']);
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn([
                'sales_channel',
                'notes',
            ]);
        });
    }
};
