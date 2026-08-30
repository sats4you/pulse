<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\FirstSats\Invitation;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\FirstSats\Invitation\InvitationCreator;
use Sats4you\Pulse\FirstSats\Invitation\InvitationInput;
use Sats4you\Pulse\FirstSats\Invitation\InvitationRecovery;
use Sats4you\Pulse\FirstSats\Invitation\InvitationLifecycle;
use Sats4you\Pulse\FirstSats\Invitation\PdoInvitationStore;
use Sats4you\Pulse\FirstSats\Invitation\ParticipantJourney;
use Sats4you\Pulse\FirstSats\Learning\LearningJourney;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Platform\Security\SecretGenerator;

final class PdoInvitationStoreTest extends TestCase
{
    public function testPersistsOnlyDigestsAndHydratesInvitation(): void
    {
        $pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE TABLE first_sats_invitations (id TEXT PRIMARY KEY, public_id TEXT UNIQUE, locale TEXT, inviter_label TEXT, invitee_label TEXT, personal_message TEXT, sats_amount INTEGER, invitation_access_digest BLOB, invitation_access_version INTEGER, management_access_digest BLOB, management_access_version INTEGER, recovery_digest BLOB, recovery_version INTEGER, status TEXT, inviter_adult_attested INTEGER, inviter_terms_version TEXT, inviter_terms_accepted_at TEXT, participant_adult_attested INTEGER NULL, participant_terms_version TEXT NULL, participant_terms_accepted_at TEXT NULL, accepted_at TEXT NULL, complete_until TEXT NULL, learning_content_version TEXT NOT NULL DEFAULT '2026-08-23-v1', stage_one_completed_at TEXT NULL, fraction_survey_state TEXT NOT NULL DEFAULT 'pending', stage_two_completed_at TEXT NULL, stage_three_completed_at TEXT NULL, stage_four_completed_at TEXT NULL, wallet_path TEXT NULL, created_at TEXT, accept_until TEXT, updated_at TEXT, delete_at TEXT NULL)");
        $pdo->exec('CREATE TABLE first_sats_anonymous_counters (metric_key TEXT NOT NULL, content_version TEXT NOT NULL, locale TEXT NOT NULL, answer_key TEXT NOT NULL, count_value INTEGER NOT NULL DEFAULT 0, updated_at TEXT NOT NULL, PRIMARY KEY (metric_key, content_version, locale, answer_key))');
        $store = new PdoInvitationStore($pdo);
        $digester = new SecretDigester(str_repeat('p', 32));
        $secrets = new SecretGenerator();
        $created = (new InvitationCreator($store, $secrets, $digester))->create(
            new InvitationInput('fr', 'Tata', 'Léo', null, 5000, true, true, 'v1'),
            new DateTimeImmutable('2026-08-23T12:00:00+00:00'),
        );

        $loaded = $store->findByPublicId($created->invitation->publicId);
        self::assertNotNull($loaded);
        self::assertSame('Léo', $loaded->inviteeLabel);
        self::assertSame(5000, $loaded->satsAmount);
        $row = $pdo->query('SELECT * FROM first_sats_invitations')->fetch(PDO::FETCH_ASSOC);
        self::assertStringNotContainsString($created->invitationSecret, serialize($row));
        self::assertStringNotContainsString($created->managementSecret, serialize($row));
        self::assertStringNotContainsString($created->recoveryCode, serialize($row));

        $recovered = (new InvitationRecovery($store, $secrets, $digester))->recover(
            $created->recoveryCode, new DateTimeImmutable('2026-08-23T12:01:00+00:00'),
        );
        self::assertNotNull($recovered);
        self::assertSame(2, $recovered->invitation->managementAccessVersion);
        self::assertSame(2, $recovered->invitation->recoveryVersion);
        self::assertNull((new InvitationRecovery($store, $secrets, $digester))->recover(
            $created->recoveryCode, new DateTimeImmutable('2026-08-23T12:02:00+00:00'),
        ));

        $accepted = (new ParticipantJourney($store, $secrets, $digester))->accept(
            $created->invitation, true, true, 'participant-v1', new DateTimeImmutable('2026-08-24T12:00:00+00:00'),
        );
        self::assertNotNull($accepted);
        self::assertSame('accepted', $accepted->status);
        self::assertSame(2, $accepted->invitationAccessVersion);
        self::assertSame('participant-v1', $accepted->participantTermsVersion);
        self::assertNotNull($accepted->acceptedAt);
        self::assertNotNull($accepted->completeUntil);

        $learning = new LearningJourney($store);
        self::assertSame('wrong', $learning->answerStageOne($accepted, 'coin', new DateTimeImmutable('2026-08-24T12:05:00+00:00')));
        self::assertNull($store->findByPublicId($accepted->publicId)?->stageOneCompletedAt);
        self::assertSame('correct', $learning->answerStageOne($accepted, 'part', new DateTimeImmutable('2026-08-24T12:06:00+00:00')));
        $stageComplete = $store->findByPublicId($accepted->publicId);
        self::assertNotNull($stageComplete?->stageOneCompletedAt);
        self::assertTrue($learning->answerFractionSurvey($stageComplete, 'no', new DateTimeImmutable('2026-08-24T12:07:00+00:00')));
        $counter = $pdo->query('SELECT * FROM first_sats_anonymous_counters')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('fraction_known_before', $counter['metric_key']);
        self::assertSame('no', $counter['answer_key']);
        self::assertSame(1, (int) $counter['count_value']);
        self::assertArrayNotHasKey('invitation_id', $counter);
        self::assertFalse($learning->answerFractionSurvey($store->findByPublicId($accepted->publicId), 'yes', new DateTimeImmutable('2026-08-24T12:08:00+00:00')));
        self::assertSame(1, (int) $pdo->query('SELECT SUM(count_value) FROM first_sats_anonymous_counters')->fetchColumn());
        $readyForStageTwo = $store->findByPublicId($accepted->publicId);
        self::assertSame('wrong', $learning->answerStageTwo($readyForStageTwo, 'platform', new DateTimeImmutable('2026-08-24T12:09:00+00:00')));
        self::assertNull($store->findByPublicId($accepted->publicId)?->stageTwoCompletedAt);
        self::assertSame('correct', $learning->answerStageTwo($readyForStageTwo, 'private', new DateTimeImmutable('2026-08-24T12:10:00+00:00')));
        self::assertNotNull($store->findByPublicId($accepted->publicId)?->stageTwoCompletedAt);
        $readyForStageThree = $store->findByPublicId($accepted->publicId);
        self::assertSame('wrong', $learning->answerStageThree($readyForStageThree, 'password', new DateTimeImmutable('2026-08-24T12:11:00+00:00')));
        self::assertNull($store->findByPublicId($accepted->publicId)?->stageThreeCompletedAt);
        self::assertSame('correct', $learning->answerStageThree($readyForStageThree, 'request', new DateTimeImmutable('2026-08-24T12:12:00+00:00')));
        self::assertNotNull($store->findByPublicId($accepted->publicId)?->stageThreeCompletedAt);
        $readyForStageFour = $store->findByPublicId($accepted->publicId);
        self::assertSame('wrong', $learning->answerStageFour($readyForStageFour, 'guaranteed', new DateTimeImmutable('2026-08-24T12:13:00+00:00')));
        self::assertNull($store->findByPublicId($accepted->publicId)?->stageFourCompletedAt);
        self::assertSame('correct', $learning->answerStageFour($readyForStageFour, 'balanced', new DateTimeImmutable('2026-08-24T12:14:00+00:00')));
        self::assertNotNull($store->findByPublicId($accepted->publicId)?->stageFourCompletedAt);
        $readyForWallet = $store->findByPublicId($accepted->publicId);
        self::assertNotNull($store->selectWalletPath($readyForWallet->publicId, $readyForWallet->invitationAccessVersion, 'phoenix', new DateTimeImmutable('2026-08-24T12:15:00+00:00')));
        self::assertSame('phoenix', $store->findByPublicId($accepted->publicId)?->walletPath);
        $reset = (new InvitationLifecycle($store, $secrets, $digester))->reset(
            $store->findByPublicId($accepted->publicId), new DateTimeImmutable('2026-08-24T12:16:00+00:00'),
        );
        self::assertNotNull($reset);
        self::assertSame('open', $reset->invitation->status);
        self::assertNull($reset->invitation->participantTermsVersion);
        self::assertNull($reset->invitation->stageFourCompletedAt);
        self::assertNull($reset->invitation->walletPath);
        $revoked = (new InvitationLifecycle($store, $secrets, $digester))->revoke(
            $reset->invitation, new DateTimeImmutable('2026-08-24T12:17:00+00:00'),
        );
        self::assertSame('revoked', $revoked?->status);
        self::assertNotNull($pdo->query('SELECT delete_at FROM first_sats_invitations')->fetchColumn());
    }
}
