<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\FirstSats\Metrics;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\FirstSats\Metrics\PdoMetricsRecorder;

final class PdoMetricsRecorderTest extends TestCase
{
    public function testContributionIsAnonymousAndCountedOnlyOnce(): void
    {
        $db = $this->database();
        $db->exec("INSERT INTO first_sats_invitations (id,public_id,locale) VALUES ('internal-1','public-1','de')");
        $recorder = new PdoMetricsRecorder($db);
        $at = new DateTimeImmutable('2026-08-23 12:00:00');

        self::assertTrue($recorder->record('public-1', 'opened', $at));
        self::assertFalse($recorder->record('public-1', 'opened', $at));
        self::assertSame(1, (int) $db->query("SELECT count_value FROM first_sats_anonymous_counters WHERE metric_key='opened'")->fetchColumn());

        $marker = $db->query('SELECT * FROM first_sats_metric_contributions')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['invitation_id', 'metric_key', 'content_version', 'contributed_at'], array_keys($marker));
        self::assertStringNotContainsString('public-1', implode('|', $marker));

        $db->exec("DELETE FROM first_sats_invitations WHERE id='internal-1'");
        self::assertSame(0, (int) $db->query('SELECT COUNT(*) FROM first_sats_metric_contributions')->fetchColumn());
        self::assertSame(1, (int) $db->query("SELECT count_value FROM first_sats_anonymous_counters WHERE metric_key='opened'")->fetchColumn());
    }

    public function testWalletPathUsesOnlyAnAllowlistedAggregateAnswer(): void
    {
        $db = $this->database();
        $db->exec("INSERT INTO first_sats_invitations (id,public_id,locale) VALUES ('internal-2','public-2','fr')");
        $recorder = new PdoMetricsRecorder($db);

        self::assertTrue($recorder->record('public-2', 'payment_requested', new DateTimeImmutable(), 'phoenix'));
        $row = $db->query("SELECT locale,answer_key,count_value FROM first_sats_anonymous_counters WHERE metric_key='payment_requested'")->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['locale' => 'fr', 'answer_key' => 'phoenix', 'count_value' => 1], $row);

        $this->expectException(\InvalidArgumentException::class);
        $recorder->record('public-2', 'payment_requested', new DateTimeImmutable(), 'lnbc-secret-destination');
    }

    private function database(): PDO
    {
        $db = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db->exec('PRAGMA foreign_keys = ON');
        $db->exec('CREATE TABLE first_sats_invitations (id TEXT PRIMARY KEY, public_id TEXT UNIQUE NOT NULL, locale TEXT NOT NULL)');
        $db->exec('CREATE TABLE first_sats_anonymous_counters (metric_key TEXT NOT NULL, content_version TEXT NOT NULL, locale TEXT NOT NULL, answer_key TEXT NOT NULL, count_value INTEGER NOT NULL DEFAULT 0, updated_at TEXT NOT NULL, PRIMARY KEY(metric_key,content_version,locale,answer_key))');
        $db->exec('CREATE TABLE first_sats_metric_contributions (invitation_id TEXT NOT NULL, metric_key TEXT NOT NULL, content_version TEXT NOT NULL, contributed_at TEXT NOT NULL, PRIMARY KEY(invitation_id,metric_key,content_version), FOREIGN KEY(invitation_id) REFERENCES first_sats_invitations(id) ON DELETE CASCADE)');

        return $db;
    }
}
