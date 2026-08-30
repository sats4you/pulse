<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\FirstSats\Operator;

use PDO;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\FirstSats\Operator\PdoDashboardStore;

final class PdoDashboardStoreTest extends TestCase
{
    public function testDashboardAggregatesOnlyAllowlistedDataAndKeepsSurveyVersionsSeparate(): void
    {
        $db = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db->exec('CREATE TABLE first_sats_anonymous_counters (metric_key TEXT NOT NULL, content_version TEXT NOT NULL, locale TEXT NOT NULL, answer_key TEXT NOT NULL, count_value INTEGER NOT NULL, updated_at TEXT NOT NULL)');
        $insert = $db->prepare('INSERT INTO first_sats_anonymous_counters VALUES (:metric,:version,:locale,:answer,:count,:updated)');

        $this->insert($insert, 'created', 'flow-v1', 'de', '', 7, '2026-08-23 10:00:00');
        $this->insert($insert, 'created', 'flow-v1', 'fr', '', 5, '2026-08-23 10:01:00');
        $this->insert($insert, 'payment_requested', 'flow-v1', 'de', 'phoenix', 4, '2026-08-23 10:02:00');
        $this->insert($insert, 'payment_requested', 'flow-v1', 'de', 'lnbc-sensitive-value', 99, '2026-08-23 10:03:00');
        $this->insert($insert, 'fraction_known_before', 'content-v1', 'de', 'yes', 20, '2026-08-23 10:04:00');
        $this->insert($insert, 'fraction_known_before', 'content-v2', 'de', 'no', 6, '2026-08-24 10:00:00');
        $this->insert($insert, 'fraction_known_before', 'content-v2', 'fr', 'yes', 5, '2026-08-24 10:01:00');
        $this->insert($insert, 'fraction_known_before', 'content-v2', 'de', 'private-free-text', 50, '2026-08-24 10:02:00');

        $data = (new PdoDashboardStore($db))->data();

        self::assertSame(12, $data->funnel['created']);
        self::assertSame(4, $data->funnel['payment_requested']);
        self::assertSame(['wos' => 0, 'phoenix' => 4, 'other' => 0], $data->wallets);
        self::assertSame('content-v2', $data->surveyVersion);
        self::assertSame(['yes' => 5, 'no' => 6, 'unsure' => 0, 'skipped' => 0], $data->survey);
    }

    private function insert(\PDOStatement $insert,string $metric,string $version,string $locale,string $answer,int $count,string $updated):void
    {
        $insert->execute(compact('metric','version','locale','answer','count','updated'));
    }
}
