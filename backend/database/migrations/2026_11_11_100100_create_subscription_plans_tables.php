<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Subscription plans and their prices (docs/SUBSCRIPTIONS.md). The default
 * plans of config/billing.php are copied in once; platform admins edit them
 * afterwards through the admin API.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name_en', 80);
            $table->string('name_ar', 80);
            $table->string('description_en', 255)->nullable();
            $table->string('description_ar', 255)->nullable();
            // null = unlimited.
            $table->unsignedInteger('max_branches')->nullable();
            $table->unsignedInteger('max_queues')->nullable();
            $table->unsignedInteger('max_members')->nullable();
            $table->json('features');
            $table->boolean('is_free')->default(false);
            // Offered to businesses (an inactive plan keeps its subscribers).
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('subscription_plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->string('billing_period', 10);
            $table->char('currency', 3);
            // Minor units: 1500 = 15.00.
            $table->unsignedBigInteger('amount_minor');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['plan_id', 'billing_period', 'currency']);
        });

        $now = now();
        foreach ((array) config('billing.plans') as $code => $plan) {
            $id = DB::table('subscription_plans')->insertGetId([
                'code' => $code,
                'name_en' => $plan['name']['en'],
                'name_ar' => $plan['name']['ar'],
                'description_en' => $plan['description']['en'] ?? null,
                'description_ar' => $plan['description']['ar'] ?? null,
                'max_branches' => $plan['limits']['branches'] ?? null,
                'max_queues' => $plan['limits']['queues'] ?? null,
                'max_members' => $plan['limits']['members'] ?? null,
                'features' => json_encode(array_values($plan['features'] ?? [])),
                'is_free' => (bool) ($plan['is_free'] ?? false),
                'is_active' => true,
                'sort_order' => (int) ($plan['sort_order'] ?? 0),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach ($plan['prices'] ?? [] as $price) {
                DB::table('subscription_plan_prices')->insert([
                    'plan_id' => $id,
                    'billing_period' => $price['billing_period'],
                    'currency' => strtoupper($price['currency']),
                    'amount_minor' => (int) $price['amount_minor'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_prices');
        Schema::dropIfExists('subscription_plans');
    }
};
