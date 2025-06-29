<?php

declare(strict_types=1);

namespace EKvedaras\SubscriptionEngineIlluminate;

use DateTimeImmutable;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Connection;
use Illuminate\Database\SQLiteConnection;
use Psr\Clock\ClockInterface;
use RuntimeException;
use stdClass;
use Webmozart\Assert\Assert;
use Wwwision\SubscriptionEngine\Store\SubscriptionCriteria;
use Wwwision\SubscriptionEngine\Store\SubscriptionStore;
use Wwwision\SubscriptionEngine\Subscription\Position;
use Wwwision\SubscriptionEngine\Subscription\RunMode;
use Wwwision\SubscriptionEngine\Subscription\Subscription;
use Wwwision\SubscriptionEngine\Subscription\SubscriptionError;
use Wwwision\SubscriptionEngine\Subscription\SubscriptionId;
use Wwwision\SubscriptionEngine\Subscription\SubscriptionIds;
use Wwwision\SubscriptionEngine\Subscription\Subscriptions;
use Wwwision\SubscriptionEngine\Subscription\SubscriptionStatus;
use Wwwision\SubscriptionEngine\Subscription\SubscriptionStatusFilter;

final readonly class IlluminateSubscriptionStore implements SubscriptionStore
{
    public function __construct(
        public Connection $connection,
        public string $tableName,
        public ClockInterface $clock,
    ) {
    }

    public function setup(): void
    {
        // see database/migrations
    }

    public function findByCriteriaForUpdate(SubscriptionCriteria $criteria): Subscriptions
    {
        $rows = $this->connection
            ->table($this->tableName)
            ->orderBy('id')
            ->when($criteria->ids, function (Builder $query, SubscriptionIds $ids) {
                $query->whereIn('id', $ids->toStringArray());
            })
            ->when($criteria->status, function (Builder $query, SubscriptionStatusFilter $statuses) {
                $query->whereIn('status', $statuses->toStringArray());
            })
            ->lockForUpdate()
            ->get();

        if ($rows->isEmpty()) {
            return Subscriptions::none();
        }

        return Subscriptions::fromArray($rows->map(self::fromDatabase(...))->all());
    }

    public function add(Subscription $subscription): void
    {
        $row = self::toDatabase($subscription);

        $row['id'] = $subscription->id->value;
        $row['last_saved_at'] = $this->clock->now()->format('Y-m-d H:i:s');

        $this->connection->table($this->tableName)->insert($row);
    }

    public function update(Subscription $subscription): void
    {
        $row = self::toDatabase($subscription);

        $row['last_saved_at'] = $this->clock->now()->format('Y-m-d H:i:s');

        $this->connection
            ->table($this->tableName)
            ->where(['id' => $subscription->id->value])
            ->update($row);
    }

    public function beginTransaction(): void
    {
        if ($this->connection instanceof SQLiteConnection) {
            $this->connection->statement('BEGIN EXCLUSIVE');
        } else {
            $this->connection->beginTransaction();
        }
    }

    public function commit(): void
    {
        if ($this->connection instanceof SQLiteConnection) {
            $this->connection->statement('COMMIT');
        } else {
            $this->connection->commit();
        }
    }

    /** @return array<string, mixed> */
    private static function toDatabase(Subscription $subscription): array
    {
        return [
            'status' => $subscription->status->value,
            'run_mode' => $subscription->runMode->value,
            'position' => $subscription->position->value,
            'error_message' => $subscription->error?->errorMessage,
            'error_previous_status' => $subscription->error?->previousStatus?->value,
            'error_trace' => $subscription->error?->errorTrace,
        ];
    }

    private static function fromDatabase(stdClass $row): Subscription
    {
        Assert::string($row->id);
        Assert::string($row->run_mode);
        Assert::string($row->status);
        Assert::integer($row->position);
        Assert::string($row->last_saved_at);

        if (isset($row->error_message)) {
            Assert::string($row->error_message);
            Assert::string($row->error_previous_status);
            Assert::string($row->error_trace);

            $subscriptionError = new SubscriptionError(
                errorMessage:   $row->error_message,
                previousStatus: SubscriptionStatus::from($row->error_previous_status),
                errorTrace:     $row->error_trace,
            );
        } else {
            $subscriptionError = null;
        }

        $lastSavedAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row->last_saved_at);
        if ($lastSavedAt === false) {
            throw new RuntimeException(sprintf('last_saved_at %s is not a valid date', $row->last_saved_at), 1733602968);
        }

        return new Subscription(
            id:          SubscriptionId::fromString($row->id),
            runMode:     RunMode::from($row->run_mode),
            status:      SubscriptionStatus::from($row->status),
            position:    Position::fromInteger($row->position),
            error:       $subscriptionError,
            lastSavedAt: $lastSavedAt,
        );
    }
}
