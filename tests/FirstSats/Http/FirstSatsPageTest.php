<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\FirstSats\Http;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\FirstSats\Http\FirstSatsPage;
use Sats4you\Pulse\FirstSats\Invitation\Invitation;
use Sats4you\Pulse\Platform\I18n\TranslatorFactory;

final class FirstSatsPageTest extends TestCase
{
    public function testZeroSatsConclusionDoesNotPretendThatParticipantReceivedSats(): void
    {
        $now = new DateTimeImmutable('2026-08-28T10:00:00+02:00');
        $invitation = new Invitation(
            id: 'invitation-id', publicId: 'public-id', locale: 'de',
            inviterLabel: 'Andi', inviteeLabel: 'Nico', personalMessage: null, satsAmount: 0,
            invitationAccessDigest: 'invitation-digest', invitationAccessVersion: 1,
            managementAccessDigest: 'management-digest', managementAccessVersion: 1,
            recoveryDigest: 'recovery-digest', recoveryVersion: 1, status: 'accepted',
            termsVersion: '2026-08-23', termsAcceptedAt: $now, createdAt: $now,
            acceptUntil: $now->modify('+30 days'), participantTermsVersion: '2026-08-23',
            participantTermsAcceptedAt: $now, acceptedAt: $now, completeUntil: $now->modify('+30 days'),
            stageOneCompletedAt: $now, fractionSurveyState: 'skipped', stageTwoCompletedAt: $now,
            stageThreeCompletedAt: $now, stageFourCompletedAt: $now,
        );
        $translator = TranslatorFactory::create('de', dirname(__DIR__, 3) . '/resources/first-sats');
        $html = html_entity_decode((new FirstSatsPage())->nextSteps($translator, 'de', ['de' => 'Deutsch'], $invitation), ENT_QUOTES, 'UTF-8');

        self::assertStringContainsString('Das Gelernte wirken lassen', $html);
        self::assertStringContainsString('Eigene Lernreise erstellen', $html);
        self::assertStringContainsString('class="next-link-symbol" aria-hidden="true">→</span>', $html);
        self::assertStringNotContainsString('Du kannst die Sats einfach in deiner Wallet lassen', $html);
        self::assertStringNotContainsString('Die Zahlung erfolgt weiterhin direkt aus deiner Wallet', $html);
    }
}
