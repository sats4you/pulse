<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\Pulse\Notifications;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\Platform\I18n\TranslatorFactory;
use Sats4you\Pulse\Pulse\Notifications\NotificationBatch;
use Sats4you\Pulse\Pulse\Notifications\NotificationDispatcher;
use Sats4you\Pulse\Pulse\Notifications\NotificationMailer;
use Sats4you\Pulse\Pulse\Notifications\NotificationMessage;
use Sats4you\Pulse\Pulse\Notifications\NotificationMessageFactory;
use Sats4you\Pulse\Pulse\Notifications\NotificationOutboxStore;

final class NotificationDispatcherTest extends TestCase
{
    private DateTimeImmutable $now;
    private FakeNotificationOutboxStore $store;
    private FakeNotificationMailer $mailer;
    private NotificationDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-08-17T18:00:00Z');
        $this->store = new FakeNotificationOutboxStore();
        $this->mailer = new FakeNotificationMailer();
        $this->dispatcher = new NotificationDispatcher(
            $this->store,
            $this->mailer,
            new NotificationMessageFactory(
                TranslatorFactory::create('de', dirname(__DIR__, 3) . '/resources/translations'),
                'de',
                new DateTimeZone('Europe/Zurich'),
            ),
            new DateInterval('PT5M'),
        );
    }

    public function testReadyChangesAreBundledSentAndDeleted(): void
    {
        $this->store->batches = [$this->batch()];

        $report = $this->dispatcher->dispatch($this->now);

        self::assertTrue($report->lockAcquired);
        self::assertSame(1, $report->sent);
        self::assertSame(0, $report->failed);
        self::assertSame(0, $report->expired);
        self::assertEquals($this->now->modify('-5 minutes'), $this->store->readyBefore);
        self::assertSame([['aabbccddeeff00112233445566778899']], $this->store->deletedChanges);
        self::assertTrue($this->store->released);
        self::assertCount(1, $this->mailer->messages);
        self::assertStringContainsString('2 Anmeldungen, 1 Abmeldungen', $this->mailer->messages[0]->body);
        self::assertStringContainsString('Aktueller Stand: 4 Zusagen', $this->mailer->messages[0]->body);
        self::assertStringNotContainsString('secret', strtolower($this->mailer->messages[0]->body));
    }

    public function testFailedMailRemainsQueuedForRetry(): void
    {
        $this->store->batches = [$this->batch()];
        $this->mailer->succeeds = false;

        $report = $this->dispatcher->dispatch($this->now);

        self::assertSame(0, $report->sent);
        self::assertSame(1, $report->failed);
        self::assertSame([], $this->store->deletedChanges);
        self::assertTrue($this->store->released);
    }

    public function testConcurrentDispatcherDoesNoWork(): void
    {
        $this->store->lockAvailable = false;

        $report = $this->dispatcher->dispatch($this->now);

        self::assertFalse($report->lockAcquired);
        self::assertSame(0, $report->sent);
        self::assertSame(0, $report->failed);
        self::assertSame(0, $report->expired);
        self::assertNull($this->store->readyBefore);
        self::assertFalse($this->store->released);
    }

    private function batch(): NotificationBatch
    {
        return new NotificationBatch(
            'event-id',
            'Bern Monthly Bitcoin Meetup',
            $this->now->modify('+20 days'),
            2,
            1,
            4,
            ['aabbccddeeff00112233445566778899'],
        );
    }
}

final class FakeNotificationOutboxStore implements NotificationOutboxStore
{
    public bool $lockAvailable = true;
    public bool $released = false;
    public ?DateTimeImmutable $readyBefore = null;

    /** @var list<NotificationBatch> */
    public array $batches = [];

    /** @var list<list<string>> */
    public array $deletedChanges = [];

    public function acquireDispatchLock(): bool
    {
        return $this->lockAvailable;
    }

    public function releaseDispatchLock(): void
    {
        $this->released = true;
    }

    public function deleteExpired(DateTimeImmutable $now): int
    {
        return 0;
    }

    public function pendingBatches(DateTimeImmutable $readyBefore): array
    {
        $this->readyBefore = $readyBefore;

        return $this->batches;
    }

    public function deleteChanges(array $changeIds): void
    {
        $this->deletedChanges[] = $changeIds;
    }
}

final class FakeNotificationMailer implements NotificationMailer
{
    public bool $succeeds = true;

    /** @var list<NotificationMessage> */
    public array $messages = [];

    public function send(NotificationMessage $message): bool
    {
        $this->messages[] = $message;

        return $this->succeeds;
    }
}
