<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\FirstSats\Http;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\FirstSats\Http\FirstSatsPage;
use Sats4you\Pulse\FirstSats\Http\FirstSatsRoutes;
use Sats4you\Pulse\FirstSats\Invitation\InvitationAccess;
use Sats4you\Pulse\FirstSats\Invitation\InvitationCreator;
use Sats4you\Pulse\FirstSats\Invitation\InvitationRecovery;
use Sats4you\Pulse\FirstSats\Invitation\InvitationLifecycle;
use Sats4you\Pulse\FirstSats\Invitation\ParticipantJourney;
use Sats4you\Pulse\FirstSats\Learning\LearningJourney;
use Sats4you\Pulse\FirstSats\Wallet\WalletJourney;
use Sats4you\Pulse\FirstSats\Payment\Bolt11Inspector;
use Sats4you\Pulse\FirstSats\Payment\PaymentRequest;
use Sats4you\Pulse\FirstSats\Payment\PaymentRequestStore;
use Sats4you\Pulse\Tests\FirstSats\Payment\InMemoryHandoverStore;
use Sats4you\Pulse\Platform\Http\CsrfToken;
use Sats4you\Pulse\Platform\Http\SameOriginGuard;
use Sats4you\Pulse\Platform\Http\SecurityHeadersMiddleware;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Platform\Security\SecretGenerator;
use Sats4you\Pulse\Pulse\PublicAccess\AccessSessionCodec;
use Sats4you\Pulse\Tests\FirstSats\Invitation\InMemoryInvitationStore;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class FirstSatsRoutesTest extends TestCase
{
    private const ORIGIN = 'https://sats4you.ch';

    public function testCreatesPersonalInvitationAndKeepsSecretsOutOfStoredRecord(): void
    {
        [$app, $store, $handover] = $this->application();
        $requests = new ServerRequestFactory();
        $form = $app->handle($requests->createServerRequest('GET', '/erste-sats?lang=de')->withQueryParams(['lang' => 'de']));
        self::assertSame(200, $form->getStatusCode());
        self::assertSame('index, follow', $form->getHeaderLine('X-Robots-Tag'));
        $formHtml = (string) $form->getBody();
        self::assertStringContainsString('name="robots" content="index,follow"', $formHtml);
        self::assertStringContainsString('Schenke die ersten Sats', $formHtml);
        self::assertStringContainsString("data-sats=\"1000\"", $formHtml);
        self::assertStringContainsString("data-sats=\"5000\"", $formHtml);
        self::assertStringContainsString("data-sats=\"10000\"", $formHtml);
        self::assertStringNotContainsString('name="message"', $formHtml);
        self::assertStringContainsString('Kurz und wichtig', $formHtml);
        preg_match('/name="csrf" value="([^"]+)"/', $formHtml, $csrf);
        $formCookie = $this->cookie($form, 'first_sats_form');

        $created = $app->handle($requests->createServerRequest('POST', '/erste-sats')
            ->withHeader('Sec-Fetch-Site', 'same-origin')
            ->withCookieParams(['first_sats_form' => $formCookie])
            ->withParsedBody([
                'csrf' => $csrf[1], 'lang' => 'de', 'sats' => "5'000", 'inviter' => 'Götti Andi',
                'invitee' => 'Nico', 'message' => 'Hallo Nico', 'adult' => '1', 'terms' => '1',
            ]));

        self::assertSame(200, $created->getStatusCode());
        self::assertSame('noindex, nofollow, noarchive', $created->getHeaderLine('X-Robots-Tag'));
        $html = (string) $created->getBody();
        self::assertStringContainsString('Einladung teilen / versenden', $html);
        self::assertStringContainsString('Götti Andi', $html);
        self::assertStringContainsString('Nachricht an Nico versenden', $html);
        self::assertStringContainsString('ich möchte dir mit «Erste Sats»', html_entity_decode($html, ENT_QUOTES, 'UTF-8'));
        self::assertStringContainsString('data-copy-target="manage-link"', $html);
        self::assertStringContainsString('data-copy-target="recovery-code"', $html);
        self::assertStringNotContainsString('id="share-text" readonly', $html);
        self::assertStringContainsString("5'000 Sats", html_entity_decode($html, ENT_QUOTES, 'UTF-8'));
        self::assertStringContainsString('/erste-sats/i/', $html);
        self::assertStringContainsString('?lang=de#', $html);
        self::assertCount(1, $store->invitations);
        $stored = array_values($store->invitations)[0];
        self::assertNull($stored->personalMessage);
        self::assertSame('inviter-2026-08-30-v1', $stored->termsVersion);
        preg_match('~id="manage-link">[^#]+#([^<]+)</code>~', $html, $managementSecret);
        preg_match('~i/[^#]+#([^\s<]+)~', $html, $invitationSecret);
        preg_match('~id="recovery-code">([^<]+)</code>~', $html, $recoveryCode);
        $storedBytes = serialize($stored);
        foreach ([$managementSecret[1] ?? '', $invitationSecret[1] ?? '', $recoveryCode[1] ?? ''] as $secret) {
            self::assertNotSame('', $secret);
            self::assertStringNotContainsString(html_entity_decode($secret, ENT_QUOTES, 'UTF-8'), $storedBytes);
        }

        $recoveryForm = $app->handle($requests->createServerRequest('GET', '/erste-sats/recover?lang=de')->withQueryParams(['lang' => 'de']));
        $recoveryFormHtml = (string) $recoveryForm->getBody();
        self::assertSame(200, $recoveryForm->getStatusCode());
        self::assertStringContainsString('Verwaltungszugang wiederherstellen', $recoveryFormHtml);
        preg_match('/name="csrf" value="([^"]+)"/', $recoveryFormHtml, $recoveryCsrf);
        $recoveryCookie = $this->cookie($recoveryForm, 'first_sats_recovery');
        $recovered = $app->handle($requests->createServerRequest('POST', '/erste-sats/recover')
            ->withHeader('Origin', self::ORIGIN)
            ->withCookieParams(['first_sats_recovery' => $recoveryCookie])
            ->withParsedBody(['csrf' => $recoveryCsrf[1], 'lang' => 'de', 'recovery_code' => html_entity_decode($recoveryCode[1], ENT_QUOTES, 'UTF-8')]));
        $recoveredHtml = (string) $recovered->getBody();
        self::assertSame(200, $recovered->getStatusCode());
        self::assertStringContainsString('Deine neuen Zugangsdaten sind bereit.', $recoveredHtml);
        preg_match('~id="recovered-manage-link">[^#]+#([^<]+)</code>~', $recoveredHtml, $newManagementSecret);
        preg_match('~id="recovered-code">([^<]+)</code>~', $recoveredHtml, $newRecoveryCode);
        self::assertNotSame('', $newManagementSecret[1] ?? '');
        self::assertNotSame('', $newRecoveryCode[1] ?? '');
        self::assertNotSame($managementSecret[1], $newManagementSecret[1]);
        self::assertNotSame($recoveryCode[1], $newRecoveryCode[1]);

        $oldManagement = $app->handle($requests->createServerRequest('POST', '/erste-sats/api/access/management/' . $stored->publicId)
            ->withHeader('Origin', self::ORIGIN)->withParsedBody(['secret' => html_entity_decode($managementSecret[1], ENT_QUOTES, 'UTF-8')]));
        self::assertSame(403, $oldManagement->getStatusCode());
        $newManagement = $app->handle($requests->createServerRequest('POST', '/erste-sats/api/access/management/' . $stored->publicId)
            ->withHeader('Origin', self::ORIGIN)->withParsedBody(['secret' => html_entity_decode($newManagementSecret[1], ENT_QUOTES, 'UTF-8')]));
        self::assertSame(200, $newManagement->getStatusCode());
        $managementCookie = $this->cookie($newManagement, 'first_sats_management');
        $managementPage = $app->handle($requests->createServerRequest('GET', '/erste-sats/manage/' . $stored->publicId . '/view?lang=de')
            ->withQueryParams(['lang' => 'de'])
            ->withCookieParams(['first_sats_management' => $managementCookie]));
        self::assertSame(200, $managementPage->getStatusCode());
        $managementHtml = html_entity_decode((string) $managementPage->getBody(), ENT_QUOTES, 'UTF-8');
        self::assertStringContainsString('Noch nicht angenommen', $managementHtml);
        self::assertStringContainsString('Frist um 30 Tage verlängern', $managementHtml);
        self::assertStringContainsString('Noch nicht angenommene Einladung widerrufen', $managementHtml);
        $reusedCode = $app->handle($requests->createServerRequest('POST', '/erste-sats/recover')
            ->withHeader('Origin', self::ORIGIN)
            ->withCookieParams(['first_sats_recovery' => $recoveryCookie])
            ->withParsedBody(['csrf' => $recoveryCsrf[1], 'lang' => 'de', 'recovery_code' => html_entity_decode($recoveryCode[1], ENT_QUOTES, 'UTF-8')]));
        self::assertSame(422, $reusedCode->getStatusCode());
        self::assertStringContainsString('ungültig oder wurde bereits verwendet', (string) $reusedCode->getBody());

        $exchange = $app->handle($requests->createServerRequest('POST', '/erste-sats/api/access/invitation/' . $stored->publicId)
            ->withHeader('Origin', self::ORIGIN)
            ->withParsedBody(['secret' => html_entity_decode($invitationSecret[1], ENT_QUOTES, 'UTF-8')]));
        self::assertSame(200, $exchange->getStatusCode());
        $participantCookie = $this->cookie($exchange, 'first_sats_invitation');
        $bootstrapWithSession = $app->handle($requests->createServerRequest('GET', '/erste-sats/i/' . $stored->publicId . '?lang=fr')
            ->withQueryParams(['lang' => 'fr'])
            ->withCookieParams(['first_sats_invitation' => $participantCookie]));
        self::assertSame(303, $bootstrapWithSession->getStatusCode());
        self::assertSame('/erste-sats/i/' . $stored->publicId . '/view?lang=fr', $bootstrapWithSession->getHeaderLine('Location'));
        $participant = $app->handle($requests->createServerRequest('GET', '/erste-sats/i/' . $stored->publicId . '/view?lang=de')
            ->withQueryParams(['lang' => 'de'])
            ->withCookieParams(['first_sats_invitation' => $participantCookie]));
        $participantHtml = (string) $participant->getBody();
        self::assertSame(200, $participant->getStatusCode());
        self::assertSame('noindex, nofollow, noarchive', $participant->getHeaderLine('X-Robots-Tag'));
        self::assertStringContainsString('name="robots" content="noindex,nofollow,noarchive"', $participantHtml);
        self::assertStringContainsString('Was dich erwartet', $participantHtml);
        self::assertStringContainsString("Für dich wurden von Götti Andi 5'000 Sats", html_entity_decode($participantHtml, ENT_QUOTES, 'UTF-8'));
        self::assertStringContainsString('data-participant-adult', $participantHtml);
        self::assertStringContainsString('Lernreise starten', $participantHtml);
        self::assertStringNotContainsString('Hallo Nico</blockquote>', $participantHtml);

        preg_match('/name="csrf" value="([^"]+)"/', $participantHtml, $participantCsrf);
        $missingParticipantTerms = $app->handle($requests->createServerRequest('POST', '/erste-sats/i/' . $stored->publicId . '/accept')
            ->withHeader('Origin', self::ORIGIN)
            ->withCookieParams(['first_sats_invitation' => $participantCookie])
            ->withParsedBody(['csrf' => $participantCsrf[1], 'lang' => 'de']));
        self::assertSame(422, $missingParticipantTerms->getStatusCode());

        $accepted = $app->handle($requests->createServerRequest('POST', '/erste-sats/i/' . $stored->publicId . '/accept')
            ->withHeader('Origin', self::ORIGIN)
            ->withCookieParams(['first_sats_invitation' => $participantCookie])
            ->withParsedBody(['csrf' => $participantCsrf[1], 'lang' => 'de', 'adult' => '1', 'terms' => '1']));
        self::assertSame(303, $accepted->getStatusCode());
        $acceptedCookie = $this->cookie($accepted, 'first_sats_invitation');
        $acceptedInvitation = $store->findByPublicId($stored->publicId);
        self::assertNotNull($acceptedInvitation);
        self::assertSame('accepted', $acceptedInvitation->status);
        self::assertSame('participant-2026-08-30-v1', $acceptedInvitation->participantTermsVersion);
        self::assertNotNull($acceptedInvitation->completeUntil);

        $oldSession = $app->handle($requests->createServerRequest('GET', '/erste-sats/i/' . $stored->publicId . '/view')
            ->withCookieParams(['first_sats_invitation' => $participantCookie]));
        self::assertSame(403, $oldSession->getStatusCode());
        $oldSecret = $app->handle($requests->createServerRequest('POST', '/erste-sats/api/access/invitation/' . $stored->publicId)
            ->withHeader('Origin', self::ORIGIN)
            ->withParsedBody(['secret' => html_entity_decode($invitationSecret[1], ENT_QUOTES, 'UTF-8')]));
        self::assertSame(403, $oldSecret->getStatusCode());

        $home = $app->handle($requests->createServerRequest('GET', '/erste-sats/i/' . $stored->publicId . '/view?lang=de')
            ->withQueryParams(['lang' => 'de'])
            ->withCookieParams(['first_sats_invitation' => $acceptedCookie]));
        $homeHtml = (string) $home->getBody();
        self::assertSame(200, $home->getStatusCode());
        self::assertStringContainsString('Bereit, Nico?', $homeHtml);
        self::assertStringContainsString('Geheimen Fortsetzungslink erstellen', $homeHtml);
        self::assertStringNotContainsString('#', html_entity_decode($homeHtml, ENT_QUOTES, 'UTF-8'));
        preg_match('/data-csrf="([^"]+)"/', $homeHtml, $continuationCsrf);

        $continuation = $app->handle($requests->createServerRequest('POST', '/erste-sats/i/' . $stored->publicId . '/continuation')
            ->withHeader('Origin', self::ORIGIN)
            ->withCookieParams(['first_sats_invitation' => $acceptedCookie])
            ->withParsedBody(['csrf' => html_entity_decode($continuationCsrf[1], ENT_QUOTES, 'UTF-8')]));
        self::assertSame(200, $continuation->getStatusCode());
        $continuationCookie = $this->cookie($continuation, 'first_sats_invitation');
        $continuationPayload = json_decode((string) $continuation->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsString($continuationPayload['link'] ?? null);
        self::assertStringContainsString('/erste-sats/i/' . $stored->publicId . '?lang=de#', $continuationPayload['link']);
        $rotatedInvitation = $store->findByPublicId($stored->publicId);
        self::assertNotNull($rotatedInvitation);
        self::assertStringNotContainsString((string) $continuationPayload['link'], serialize($rotatedInvitation));

        $rotatedHome = $app->handle($requests->createServerRequest('GET', '/erste-sats/i/' . $stored->publicId . '/view')
            ->withCookieParams(['first_sats_invitation' => $continuationCookie]));
        self::assertSame(200, $rotatedHome->getStatusCode());
        self::assertStringContainsString('Erste Etappe starten', (string) $rotatedHome->getBody());

        $stage = $app->handle($requests->createServerRequest('GET', '/erste-sats/i/' . $stored->publicId . '/learn/1?lang=de')
            ->withQueryParams(['lang' => 'de'])
            ->withCookieParams(['first_sats_invitation' => $continuationCookie]));
        $stageHtml = html_entity_decode((string) $stage->getBody(), ENT_QUOTES, 'UTF-8');
        self::assertSame(200, $stage->getStatusCode());
        self::assertStringContainsString('Bitcoin gibt es auch in klein.', $stageHtml);
        self::assertStringContainsString("Was sind 5'000 Sats?", $stageHtml);
        self::assertLessThan(strpos($stageHtml, 'Ein kleiner Teil eines Bitcoin'), strpos($stageHtml, 'Eine eigene Kryptowährung'));
        self::assertStringContainsString('Quellen & Einordnung', $stageHtml);
        self::assertStringContainsString('Technischer Primärbeleg', $stageHtml);
        self::assertStringContainsString('Inhaltsversion 2026-08-23-v1', $stageHtml);
        self::assertStringContainsString('Fachinhalt redaktionell freigegeben am 30.08.2026', $stageHtml);
        preg_match('/name="csrf" value="([^"]+)"/', $stageHtml, $stageCsrf);

        $wrong = $app->handle($requests->createServerRequest('POST', '/erste-sats/i/' . $stored->publicId . '/learn/1')
            ->withHeader('Origin', self::ORIGIN)
            ->withCookieParams(['first_sats_invitation' => $continuationCookie])
            ->withParsedBody(['csrf' => $stageCsrf[1], 'lang' => 'de', 'answer' => 'coin']));
        self::assertSame(422, $wrong->getStatusCode());
        self::assertStringContainsString('Nochmals versuchen.', (string) $wrong->getBody());
        self::assertNull($store->findByPublicId($stored->publicId)?->stageOneCompletedAt);

        $correct = $app->handle($requests->createServerRequest('POST', '/erste-sats/i/' . $stored->publicId . '/learn/1')
            ->withHeader('Origin', self::ORIGIN)
            ->withCookieParams(['first_sats_invitation' => $continuationCookie])
            ->withParsedBody(['csrf' => $stageCsrf[1], 'lang' => 'de', 'answer' => 'part']));
        self::assertSame(303, $correct->getStatusCode());
        self::assertStringContainsString('/learn/1/survey?lang=de', $correct->getHeaderLine('Location'));
        self::assertNotNull($store->findByPublicId($stored->publicId)?->stageOneCompletedAt);

        $survey = $app->handle($requests->createServerRequest('GET', '/erste-sats/i/' . $stored->publicId . '/learn/1/survey?lang=de')
            ->withQueryParams(['lang' => 'de'])
            ->withCookieParams(['first_sats_invitation' => $continuationCookie]));
        $surveyHtml = (string) $survey->getBody();
        self::assertSame(200, $survey->getStatusCode());
        self::assertStringContainsString('Etappe 1 geschafft!', $surveyHtml);
        self::assertStringContainsString('anonymen Zähler', $surveyHtml);
        preg_match('/name="csrf" value="([^"]+)"/', $surveyHtml, $surveyCsrf);
        $surveyAnswer = $app->handle($requests->createServerRequest('POST', '/erste-sats/i/' . $stored->publicId . '/learn/1/survey')
            ->withHeader('Origin', self::ORIGIN)
            ->withCookieParams(['first_sats_invitation' => $continuationCookie])
            ->withParsedBody(['csrf' => $surveyCsrf[1], 'lang' => 'de', 'action' => 'answer', 'answer' => 'no']));
        self::assertSame(303, $surveyAnswer->getStatusCode());
        self::assertSame('answered', $store->findByPublicId($stored->publicId)?->fractionSurveyState);

        $afterSurvey = $app->handle($requests->createServerRequest('GET', '/erste-sats/i/' . $stored->publicId . '/view?lang=de')
            ->withQueryParams(['lang' => 'de'])
            ->withCookieParams(['first_sats_invitation' => $continuationCookie]));
        self::assertStringContainsString('Zweite Etappe starten', (string) $afterSurvey->getBody());
        $stageTwo = $app->handle($requests->createServerRequest('GET', '/erste-sats/i/' . $stored->publicId . '/learn/2?lang=de')
            ->withQueryParams(['lang' => 'de'])
            ->withCookieParams(['first_sats_invitation' => $continuationCookie]));
        $stageTwoHtml = html_entity_decode((string) $stageTwo->getBody(), ENT_QUOTES, 'UTF-8');
        self::assertSame(200, $stageTwo->getStatusCode());
        self::assertStringContainsString('Die Wallet ist dein Zugang.', $stageTwoHtml);
        self::assertStringContainsString('Was machst du mit geheimen Wiederherstellungswörtern?', $stageTwoHtml);
        self::assertGreaterThan(strpos($stageTwoHtml, 'Bei «Erste Sats» eingeben'), strpos($stageTwoHtml, 'Sicher und nur für mich aufbewahren'));
        self::assertStringContainsString('Sicherheitsanleitung', $stageTwoHtml);
        self::assertStringContainsString('nicht als Produktempfehlung', $stageTwoHtml);
        preg_match('/name="csrf" value="([^"]+)"/', $stageTwoHtml, $stageTwoCsrf);
        $stageTwoWrong = $app->handle($requests->createServerRequest('POST', '/erste-sats/i/' . $stored->publicId . '/learn/2')
            ->withHeader('Origin', self::ORIGIN)
            ->withCookieParams(['first_sats_invitation' => $continuationCookie])
            ->withParsedBody(['csrf' => $stageTwoCsrf[1], 'lang' => 'de', 'answer' => 'platform']));
        self::assertSame(422, $stageTwoWrong->getStatusCode());
        self::assertStringContainsString('Niemals fotografieren', (string) $stageTwoWrong->getBody());
        self::assertNull($store->findByPublicId($stored->publicId)?->stageTwoCompletedAt);
        $stageTwoCorrect = $app->handle($requests->createServerRequest('POST', '/erste-sats/i/' . $stored->publicId . '/learn/2')
            ->withHeader('Origin', self::ORIGIN)
            ->withCookieParams(['first_sats_invitation' => $continuationCookie])
            ->withParsedBody(['csrf' => $stageTwoCsrf[1], 'lang' => 'de', 'answer' => 'private']));
        self::assertSame(303, $stageTwoCorrect->getStatusCode());
        self::assertNotNull($store->findByPublicId($stored->publicId)?->stageTwoCompletedAt);
        $afterStageTwo = $app->handle($requests->createServerRequest('GET', '/erste-sats/i/' . $stored->publicId . '/view?lang=de')
            ->withQueryParams(['lang' => 'de'])
            ->withCookieParams(['first_sats_invitation' => $continuationCookie]));
        self::assertStringContainsString('Etappe 2 von 4 geschafft', (string) $afterStageTwo->getBody());
        self::assertStringContainsString('Dritte Etappe starten', (string) $afterStageTwo->getBody());
        $stageThree = $app->handle($requests->createServerRequest('GET', '/erste-sats/i/' . $stored->publicId . '/learn/3?lang=de')
            ->withQueryParams(['lang' => 'de'])
            ->withCookieParams(['first_sats_invitation' => $continuationCookie]));
        $stageThreeHtml = html_entity_decode((string) $stageThree->getBody(), ENT_QUOTES, 'UTF-8');
        self::assertSame(200, $stageThree->getStatusCode());
        self::assertStringContainsString('So kommen die Sats zu dir.', $stageThreeHtml);
        self::assertStringContainsString("genau 5'000 Sats", $stageThreeHtml);
        self::assertStringContainsString('es ist keine andere Währung', $stageThreeHtml);
        self::assertLessThan(strpos($stageThreeHtml, 'Meine Wiederherstellungswörter'), strpos($stageThreeHtml, 'Die Zahlungsanforderung aus meiner Wallet'));
        self::assertStringContainsString('BOLT 11 definiert das technische Rechnungsformat', $stageThreeHtml);
        preg_match('/name="csrf" value="([^"]+)"/', $stageThreeHtml, $stageThreeCsrf);
        $stageThreeWrong = $app->handle($requests->createServerRequest('POST', '/erste-sats/i/' . $stored->publicId . '/learn/3')
            ->withHeader('Origin', self::ORIGIN)
            ->withCookieParams(['first_sats_invitation' => $continuationCookie])
            ->withParsedBody(['csrf' => $stageThreeCsrf[1], 'lang' => 'de', 'answer' => 'recovery']));
        self::assertSame(422, $stageThreeWrong->getStatusCode());
        self::assertStringContainsString('Wallet-Geheimnisse darf niemand verlangen', (string) $stageThreeWrong->getBody());
        self::assertNull($store->findByPublicId($stored->publicId)?->stageThreeCompletedAt);
        $stageThreeCorrect = $app->handle($requests->createServerRequest('POST', '/erste-sats/i/' . $stored->publicId . '/learn/3')
            ->withHeader('Origin', self::ORIGIN)
            ->withCookieParams(['first_sats_invitation' => $continuationCookie])
            ->withParsedBody(['csrf' => $stageThreeCsrf[1], 'lang' => 'de', 'answer' => 'request']));
        self::assertSame(303, $stageThreeCorrect->getStatusCode());
        self::assertNotNull($store->findByPublicId($stored->publicId)?->stageThreeCompletedAt);
        $afterStageThree = $app->handle($requests->createServerRequest('GET', '/erste-sats/i/' . $stored->publicId . '/view?lang=de')
            ->withQueryParams(['lang' => 'de'])
            ->withCookieParams(['first_sats_invitation' => $continuationCookie]));
        self::assertStringContainsString('Etappe 3 von 4 geschafft', (string) $afterStageThree->getBody());
        self::assertStringContainsString('Vierte Etappe starten', (string) $afterStageThree->getBody());
        $stageFour = $app->handle($requests->createServerRequest('GET', '/erste-sats/i/' . $stored->publicId . '/learn/4?lang=de')
            ->withQueryParams(['lang' => 'de'])
            ->withCookieParams(['first_sats_invitation' => $continuationCookie]));
        $stageFourHtml = html_entity_decode((string) $stageFour->getBody(), ENT_QUOTES, 'UTF-8');
        self::assertSame(200, $stageFour->getStatusCode());
        self::assertStringContainsString('Möglichkeiten haben auch Grenzen.', $stageFourHtml);
        self::assertStringContainsString('keine Garantie für die Wertentwicklung', $stageFourHtml);
        self::assertStringContainsString('Das macht es nicht automatisch zur besseren Wahl', $stageFourHtml);
        self::assertLessThan(strpos($stageFourHtml, 'Bitcoin wird TWINT'), strpos($stageFourHtml, 'Neue Möglichkeiten, aber auch Risiken'));
        self::assertStringContainsString('Kritische Gegenperspektive', $stageFourHtml);
        self::assertStringContainsString('Schweizer Behördenquelle', $stageFourHtml);
        self::assertStringContainsString('BIZ Working Paper 1049 · Adoption, Handel und Risiken', $stageFourHtml);
        self::assertStringContainsString('hash=1E5AECBC7B13A7C82FD484B51CCD0BF5', $stageFourHtml);
        self::assertStringNotContainsString('src/validation.cpp', $stageFourHtml);
        preg_match('/name="csrf" value="([^"]+)"/', $stageFourHtml, $stageFourCsrf);
        $stageFourWrong = $app->handle($requests->createServerRequest('POST', '/erste-sats/i/' . $stored->publicId . '/learn/4')
            ->withHeader('Origin', self::ORIGIN)
            ->withCookieParams(['first_sats_invitation' => $continuationCookie])
            ->withParsedBody(['csrf' => $stageFourCsrf[1], 'lang' => 'de', 'answer' => 'guaranteed']));
        self::assertSame(422, $stageFourWrong->getStatusCode());
        self::assertStringContainsString('Niemand kann die zukünftige Wertentwicklung', (string) $stageFourWrong->getBody());
        self::assertNull($store->findByPublicId($stored->publicId)?->stageFourCompletedAt);
        $stageFourCorrect = $app->handle($requests->createServerRequest('POST', '/erste-sats/i/' . $stored->publicId . '/learn/4')
            ->withHeader('Origin', self::ORIGIN)
            ->withCookieParams(['first_sats_invitation' => $continuationCookie])
            ->withParsedBody(['csrf' => $stageFourCsrf[1], 'lang' => 'de', 'answer' => 'balanced']));
        self::assertSame(303, $stageFourCorrect->getStatusCode());
        self::assertNotNull($store->findByPublicId($stored->publicId)?->stageFourCompletedAt);
        $journeyComplete = $app->handle($requests->createServerRequest('GET', '/erste-sats/i/' . $stored->publicId . '/view?lang=de')
            ->withQueryParams(['lang' => 'de'])
            ->withCookieParams(['first_sats_invitation' => $continuationCookie]));
        self::assertStringContainsString('Etappe 4 von 4 geschafft', (string) $journeyComplete->getBody());
        self::assertStringContainsString('Bravo!', (string) $journeyComplete->getBody());
        self::assertStringContainsString('Lernreise abgeschlossen!', (string) $journeyComplete->getBody());
        self::assertStringContainsString('Du hast dein Bitcoin-Wissen erweitert', (string) $journeyComplete->getBody());
        self::assertStringContainsString('Wallet auswählen', (string) $journeyComplete->getBody());

        $walletChoice = $app->handle($requests->createServerRequest('GET', '/erste-sats/i/' . $stored->publicId . '/wallet?lang=de')
            ->withQueryParams(['lang' => 'de'])
            ->withCookieParams(['first_sats_invitation' => $continuationCookie]));
        $walletHtml = html_entity_decode((string) $walletChoice->getBody(), ENT_QUOTES, 'UTF-8');
        self::assertSame(200, $walletChoice->getStatusCode());
        self::assertStringContainsString('Wie möchtest du starten?', $walletHtml);
        self::assertStringContainsString('Wallet of Satoshi', $walletHtml);
        self::assertStringContainsString('Je nach Region und gewähltem App-Modus', $walletHtml);
        self::assertStringContainsString('selbstverwahrte Lightning-Wallet', $walletHtml);
        preg_match('/name="csrf" value="([^"]+)"/', $walletHtml, $walletCsrf);
        $walletSelected = $app->handle($requests->createServerRequest('POST', '/erste-sats/i/' . $stored->publicId . '/wallet')
            ->withHeader('Origin', self::ORIGIN)
            ->withCookieParams(['first_sats_invitation' => $continuationCookie])
            ->withParsedBody(['csrf' => $walletCsrf[1], 'lang' => 'de', 'wallet' => 'wos']));
        self::assertSame(303, $walletSelected->getStatusCode());
        self::assertSame('wos', $store->findByPublicId($stored->publicId)?->walletPath);
        $walletSetup = $app->handle($requests->createServerRequest('GET', '/erste-sats/i/' . $stored->publicId . '/wallet/setup?lang=de')
            ->withQueryParams(['lang' => 'de'])
            ->withCookieParams(['first_sats_invitation' => $continuationCookie]));
        $setupHtml = html_entity_decode((string) $walletSetup->getBody(), ENT_QUOTES, 'UTF-8');
        self::assertSame(200, $walletSetup->getStatusCode());
        self::assertStringContainsString("genau 5'000 Sats", $setupHtml);
        self::assertStringContainsString('mit «lnbc» beginnt', $setupHtml);
        self::assertStringContainsString('Seed Phrase', $setupHtml);
        self::assertStringNotContainsString('name="seed"', $setupHtml);

        $nextBeforeReceipt = $app->handle($requests->createServerRequest('GET', '/erste-sats/i/' . $stored->publicId . '/next?lang=de')
            ->withQueryParams(['lang' => 'de'])
            ->withCookieParams(['first_sats_invitation' => $continuationCookie]));
        self::assertSame(403, $nextBeforeReceipt->getStatusCode());
        $handover->confirmReceived($stored->id, new DateTimeImmutable());
        $receivedHome = $app->handle($requests->createServerRequest('GET', '/erste-sats/i/' . $stored->publicId . '/view?lang=de')
            ->withQueryParams(['lang' => 'de'])
            ->withCookieParams(['first_sats_invitation' => $continuationCookie]));
        $receivedHomeHtml = html_entity_decode((string) $receivedHome->getBody(), ENT_QUOTES, 'UTF-8');
        self::assertStringContainsString('den Empfang der Sats bestätigt', $receivedHomeHtml);
        self::assertStringContainsString('Zum Abschluss «Und jetzt?»', $receivedHomeHtml);
        self::assertStringNotContainsString('Wallet-Einrichtung fortsetzen', $receivedHomeHtml);
        $next = $app->handle($requests->createServerRequest('GET', '/erste-sats/i/' . $stored->publicId . '/next?lang=de')
            ->withQueryParams(['lang' => 'de'])
            ->withCookieParams(['first_sats_invitation' => $continuationCookie]));
        $nextHtml = html_entity_decode((string) $next->getBody(), ENT_QUOTES, 'UTF-8');
        self::assertSame(200, $next->getStatusCode());
        self::assertStringContainsString('Du musst jetzt nichts weiter tun – aber du kannst.', $nextHtml);
        self::assertStringContainsString('Ein paar Sats weitergeben', $nextHtml);
        self::assertStringContainsString('keine davon ist hier eine Empfehlung', $nextHtml);

        $supersededSession = $app->handle($requests->createServerRequest('GET', '/erste-sats/i/' . $stored->publicId . '/view')
            ->withCookieParams(['first_sats_invitation' => $acceptedCookie]));
        self::assertSame(403, $supersededSession->getStatusCode());
    }

    public function testServerRejectsCrossSiteAndMissingRequiredAttestations(): void
    {
        [$app, $store] = $this->application();
        $requests = new ServerRequestFactory();
        $form = $app->handle($requests->createServerRequest('GET', '/erste-sats'));
        preg_match('/name="csrf" value="([^"]+)"/', (string) $form->getBody(), $csrf);
        $cookie = $this->cookie($form, 'first_sats_form');
        $body = ['csrf' => $csrf[1], 'lang' => 'de', 'sats' => '5000', 'inviter' => 'Andi', 'invitee' => 'Nico'];

        $crossSite = $app->handle($requests->createServerRequest('POST', '/erste-sats')->withHeader('Origin', 'https://evil.example')->withCookieParams(['first_sats_form' => $cookie])->withParsedBody($body));
        self::assertSame(403, $crossSite->getStatusCode());
        $missingAttestations = $app->handle($requests->createServerRequest('POST', '/erste-sats')->withHeader('Origin', self::ORIGIN)->withCookieParams(['first_sats_form' => $cookie])->withParsedBody($body));
        self::assertSame(422, $missingAttestations->getStatusCode());
        self::assertCount(0, $store->invitations);
    }

    public function testHighAmountRequiresDedicatedAcknowledgement(): void
    {
        [$app, $store] = $this->application(); $requests = new ServerRequestFactory();
        $form = $app->handle($requests->createServerRequest('GET', '/erste-sats'));
        preg_match('/name="csrf" value="([^"]+)"/', (string) $form->getBody(), $csrf); $cookie = $this->cookie($form, 'first_sats_form');
        $response = $app->handle($requests->createServerRequest('POST', '/erste-sats')->withHeader('Sec-Fetch-Site', 'same-origin')->withCookieParams(['first_sats_form' => $cookie])->withParsedBody(['csrf'=>$csrf[1], 'lang'=>'de', 'sats'=>'50000', 'inviter'=>'Andi', 'invitee'=>'Nico', 'adult'=>'1', 'terms'=>'1']));
        self::assertSame(422, $response->getStatusCode()); self::assertCount(0, $store->invitations);
    }

    public function testVersionedLegalInformationIsCompleteAndAvailableInEveryLocale(): void
    {
        [$app] = $this->application();
        $requests = new ServerRequestFactory();

        foreach (['de', 'fr', 'it', 'rm'] as $locale) {
            $terms = $app->handle($requests->createServerRequest('GET', '/erste-sats/terms?lang=' . $locale)
                ->withQueryParams(['lang' => $locale]));
            $privacy = $app->handle($requests->createServerRequest('GET', '/erste-sats/privacy?lang=' . $locale)
                ->withQueryParams(['lang' => $locale]));
            $termsHtml = html_entity_decode((string) $terms->getBody(), ENT_QUOTES, 'UTF-8');
            $privacyHtml = html_entity_decode((string) $privacy->getBody(), ENT_QUOTES, 'UTF-8');

            self::assertSame(200, $terms->getStatusCode());
            self::assertSame(200, $privacy->getStatusCode());
            self::assertSame('index, follow', $terms->getHeaderLine('X-Robots-Tag'));
            self::assertSame('index, follow', $privacy->getHeaderLine('X-Robots-Tag'));
            self::assertStringContainsString('name="robots" content="index,follow"', $termsHtml);
            self::assertStringContainsString('name="robots" content="index,follow"', $privacyHtml);
            self::assertSame(9, substr_count($termsHtml, 'card legal-section'));
            self::assertSame(10, substr_count($privacyHtml, 'card legal-section'));
            self::assertStringContainsString('2026-08-30-v1', $termsHtml);
            self::assertStringContainsString('2026-08-30-v1', $privacyHtml);
            self::assertStringContainsString('hallo@sats4you.ch', $termsHtml);
            self::assertStringContainsString('security@sats4you.ch', $privacyHtml);
            self::assertStringNotContainsString('legal.terms.', $termsHtml);
            self::assertStringNotContainsString('legal.privacy.', $privacyHtml);
        }

        $germanTerms = html_entity_decode((string) $app->handle(
            $requests->createServerRequest('GET', '/erste-sats/terms?lang=de')->withQueryParams(['lang' => 'de']),
        )->getBody(), ENT_QUOTES, 'UTF-8');
        $germanPrivacy = html_entity_decode((string) $app->handle(
            $requests->createServerRequest('GET', '/erste-sats/privacy?lang=de')->withQueryParams(['lang' => 'de']),
        )->getBody(), ENT_QUOTES, 'UTF-8');
        self::assertStringContainsString("100'000 Sats", $germanTerms);
        self::assertStringContainsString('Andreas Kuoni', $germanPrivacy);
        self::assertStringContainsString('BOLT11-Lightning-Rechnung', $germanPrivacy);
        self::assertStringContainsString('TrafficPlex GmbH', $germanPrivacy);
        self::assertStringContainsString('90 weitere Tage', $germanPrivacy);
        self::assertStringContainsString('keine automatisierte Entscheidung', $germanPrivacy);
    }

    public function testParticipantEndsJourneyAndManagementSeesOnlyNeutralStatus():void
    {
        [$app,$store]=$this->application();$requests=new ServerRequestFactory();$now=new DateTimeImmutable();
        $created=(new InvitationCreator($store,new SecretGenerator(),new SecretDigester(str_repeat('d',32))))->create(
            new \Sats4you\Pulse\FirstSats\Invitation\InvitationInput('de','Andi','Nico',null,5000,true,true,'v1'),$now,
        );
        $exchange=$app->handle($requests->createServerRequest('POST','/erste-sats/api/access/invitation/'.$created->invitation->publicId)->withHeader('Origin',self::ORIGIN)->withParsedBody(['secret'=>$created->invitationSecret]));
        $cookie=$this->cookie($exchange,'first_sats_invitation');
        $view=$app->handle($requests->createServerRequest('GET','/erste-sats/i/'.$created->invitation->publicId.'/view?lang=de')->withQueryParams(['lang'=>'de'])->withCookieParams(['first_sats_invitation'=>$cookie]));
        preg_match('/name="csrf" value="([^"]+)"/',(string)$view->getBody(),$csrf);
        $accepted=$app->handle($requests->createServerRequest('POST','/erste-sats/i/'.$created->invitation->publicId.'/accept')->withHeader('Origin',self::ORIGIN)->withCookieParams(['first_sats_invitation'=>$cookie])->withParsedBody(['csrf'=>$csrf[1],'lang'=>'de','adult'=>'1','terms'=>'1']));
        $participantCookie=$this->cookie($accepted,'first_sats_invitation');
        $home=$app->handle($requests->createServerRequest('GET','/erste-sats/i/'.$created->invitation->publicId.'/view?lang=de')->withQueryParams(['lang'=>'de'])->withCookieParams(['first_sats_invitation'=>$participantCookie]));
        $homeHtml=(string)$home->getBody();self::assertStringContainsString('Lernreise beenden',$homeHtml);preg_match('/name="csrf" value="([^"]+)"/',$homeHtml,$endCsrf);
        $missing=$app->handle($requests->createServerRequest('POST','/erste-sats/i/'.$created->invitation->publicId.'/end')->withHeader('Origin',self::ORIGIN)->withCookieParams(['first_sats_invitation'=>$participantCookie])->withParsedBody(['csrf'=>$endCsrf[1],'lang'=>'de']));
        self::assertSame(422,$missing->getStatusCode());
        $ended=$app->handle($requests->createServerRequest('POST','/erste-sats/i/'.$created->invitation->publicId.'/end')->withHeader('Origin',self::ORIGIN)->withCookieParams(['first_sats_invitation'=>$participantCookie])->withParsedBody(['csrf'=>$endCsrf[1],'lang'=>'de','confirm_end'=>'1']));
        self::assertSame(200,$ended->getStatusCode());self::assertStringContainsString('Deine Lernreise wurde beendet.',(string)$ended->getBody());
        $oldAccess=$app->handle($requests->createServerRequest('GET','/erste-sats/i/'.$created->invitation->publicId.'/view')->withCookieParams(['first_sats_invitation'=>$participantCookie]));
        self::assertSame(403,$oldAccess->getStatusCode());
        $management=$app->handle($requests->createServerRequest('POST','/erste-sats/api/access/management/'.$created->invitation->publicId)->withHeader('Origin',self::ORIGIN)->withParsedBody(['secret'=>$created->managementSecret]));
        $managementCookie=$this->cookie($management,'first_sats_management');
        $managementView=$app->handle($requests->createServerRequest('GET','/erste-sats/manage/'.$created->invitation->publicId.'/view?lang=de')->withQueryParams(['lang'=>'de'])->withCookieParams(['first_sats_management'=>$managementCookie]));
        self::assertStringContainsString('Vom Teilnehmer beendet',(string)$managementView->getBody());
        self::assertStringNotContainsString('zurücksetzen',mb_strtolower((string)$managementView->getBody()));
    }

    public function testPublicLocaleRestrictionHidesUnreleasedLanguagesAndFallsBackToGerman(): void
    {
        [$app] = $this->application(['de', 'fr']);
        $requests = new ServerRequestFactory();

        $italianRequest = $requests->createServerRequest('GET', '/erste-sats?lang=it')
            ->withQueryParams(['lang' => 'it']);
        $italianResponse = $app->handle($italianRequest);
        $html = (string) $italianResponse->getBody();

        self::assertSame(200, $italianResponse->getStatusCode());
        self::assertStringContainsString('<html lang="de">', $html);
        self::assertStringContainsString('Schenke die ersten Sats.', $html);
        self::assertStringContainsString('Deutsch', $html);
        self::assertStringContainsString('Français', $html);
        self::assertStringNotContainsString('Italiano', $html);
        self::assertStringNotContainsString('Rumantsch Grischun', $html);

        $frenchRequest = $requests->createServerRequest('GET', '/erste-sats?lang=fr')
            ->withQueryParams(['lang' => 'fr']);
        $frenchHtml = (string) $app->handle($frenchRequest)->getBody();
        self::assertStringContainsString('<html lang="fr">', $frenchHtml);
        self::assertStringContainsString('Offre les premiers sats.', $frenchHtml);
    }

    /** @return array{\Slim\App, InMemoryInvitationStore, InMemoryHandoverStore} */
    private function application(array $enabledLocales = ['de', 'fr', 'it', 'rm']): array
    {
        $store = new InMemoryInvitationStore(); $digester = new SecretDigester(str_repeat('d', 32));
        $app = AppFactory::create(); $app->addBodyParsingMiddleware();
        $app->add(new SecurityHeadersMiddleware());
        $handover = new InMemoryHandoverStore();
        (new FirstSatsRoutes(
            new InvitationCreator($store, new SecretGenerator(), $digester),
            new InvitationRecovery($store, new SecretGenerator(), $digester),
            new InvitationLifecycle($store, new SecretGenerator(), $digester),
            new InvitationAccess($store, $digester, new AccessSessionCodec(str_repeat('s', 32))),
            new ParticipantJourney($store, new SecretGenerator(), $digester),
            new LearningJourney($store),
            new WalletJourney($store),
            new Bolt11Inspector(),
            new class implements PaymentRequestStore { public ?PaymentRequest $request=null; public function find(string $invitationId): ?PaymentRequest{return $this->request?->invitationId===$invitationId?$this->request:null;} public function save(PaymentRequest $request): void{$this->request=$request;} },
            $handover,
            new FirstSatsPage(), new SameOriginGuard(self::ORIGIN), new CsrfToken(str_repeat('c', 32)),
            new SecretGenerator(), dirname(__DIR__, 3) . '/resources/first-sats', self::ORIGIN, true,
            null,
            $enabledLocales,
        ))->register($app);
        return [$app, $store, $handover];
    }

    private function cookie(\Psr\Http\Message\ResponseInterface $response, string $name): string
    {
        foreach ($response->getHeader('Set-Cookie') as $header) {
            [$pair] = explode(';', $header, 2); [$cookieName, $value] = explode('=', $pair, 2);
            if (rawurldecode($cookieName) === $name) return rawurldecode($value);
        }
        self::fail('Cookie missing: ' . $name);
    }
}
