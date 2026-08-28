<?php

declare(strict_types=1);

namespace EKvedaras\SubscriptionEngineIlluminate\Tests;

use DateTimeImmutable;
use EKvedaras\SubscriptionEngineIlluminate\IlluminateSubscriptionStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Clock\ClockInterface;
use Wwwision\SubscriptionEngine\Store\SubscriptionCriteria;
use Wwwision\SubscriptionEngine\Subscription\RunMode;
use Wwwision\SubscriptionEngine\Subscription\Subscription;
use Wwwision\SubscriptionEngine\Subscription\SubscriptionStatus;

#[CoversClass(IlluminateSubscriptionStore::class)]
final class IlluminateSubscriptionStoreTest extends TestCase
{
    use RefreshDatabase;

    private IlluminateSubscriptionStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = new IlluminateSubscriptionStore(
            connection: $this->app->make('db')->connection(),
            tableName: 'subscriptions',
            clock: new class implements ClockInterface {
                public function now(): DateTimeImmutable
                {
                    return new DateTimeImmutable('2025-01-01 00:00:00');
                }
            },
        );

        $this->store->add($this->subscription('a', SubscriptionStatus::ACTIVE));
        $this->store->add($this->subscription('b', SubscriptionStatus::NEW));
        $this->store->add($this->subscription('c', SubscriptionStatus::DETACHED));
    }

    #[Test]
    public function it_finds_all_subscriptions_without_constraints(): void
    {
        self::assertSame(['a', 'b', 'c'], $this->foundIds(SubscriptionCriteria::noConstraints()));
    }

    #[Test]
    public function it_filters_by_ids(): void
    {
        self::assertSame(['a', 'c'], $this->foundIds(SubscriptionCriteria::create(ids: ['a', 'c'])));
    }

    #[Test]
    public function it_filters_by_status(): void
    {
        self::assertSame(['b'], $this->foundIds(SubscriptionCriteria::create(status: [SubscriptionStatus::NEW])));
    }

    #[Test]
    public function it_filters_by_ids_and_status(): void
    {
        $criteria = SubscriptionCriteria::create(ids: ['a', 'b'], status: [SubscriptionStatus::NEW]);

        self::assertSame(['b'], $this->foundIds($criteria));
    }

    #[Test]
    public function it_returns_no_subscriptions_when_nothing_matches(): void
    {
        self::assertSame([], $this->foundIds(SubscriptionCriteria::create(ids: ['unknown'])));
    }

    #[Test]
    public function it_returns_the_same_subscriptions_as_find_by_criteria_for_update(): void
    {
        $criteria = SubscriptionCriteria::create(status: [SubscriptionStatus::ACTIVE]);

        self::assertEquals(
            $this->store->findByCriteriaForUpdate($criteria),
            $this->store->findByCriteria($criteria),
        );
    }

    /** @return list<string> */
    private function foundIds(SubscriptionCriteria $criteria): array
    {
        return array_values($this->store->findByCriteria($criteria)->map(
            static fn (Subscription $subscription): string => $subscription->id->value,
        ));
    }

    private function subscription(string $id, SubscriptionStatus $status): Subscription
    {
        return Subscription::create($id, RunMode::FROM_BEGINNING, $status);
    }

    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [\EKvedaras\SubscriptionEngineIlluminate\SubscriptionEngineServiceProvider::class];
    }
}
