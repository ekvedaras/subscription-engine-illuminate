<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webmozart\Assert\Assert;
use Wwwision\SubscriptionEngine\Subscription\SubscriptionId;

return new class extends Migration {
    public function up(): void
    {
        $tableNames = Arr::wrap(config('subscription_engine.subscriptions_table_name'));

        foreach ($tableNames as $tableName) {
            Assert::stringNotEmpty($tableName);

            if (Schema::hasTable($tableName)) {
                return;
            }

            Schema::create($tableName, function (Blueprint $table): void {
                $table->string('id', SubscriptionId::MAX_LENGTH)->primary();
                $table->string('run_mode', 32);
                $table->string('status', 32)->index();
                $table->integer('position');
                $table->text('error_message')->nullable();
                $table->string('error_previous_status', 32)->nullable();
                $table->text('error_trace')->nullable();
                $table->timestamp('last_saved_at');

                $table->charset('utf8mb4');
            });
        }
    }

    public function down(): void
    {
        $tableNames = Arr::wrap(config('subscription_engine.subscriptions_table_name'));

        foreach ($tableNames as $tableName) {
            Assert::stringNotEmpty($tableName);
            Schema::dropIfExists($tableName);
        }
    }
};
