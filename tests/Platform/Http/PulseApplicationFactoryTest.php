<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\Platform\Http;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Sats4you\Pulse\Platform\Http\PulseApplicationFactory;
use Sats4you\Pulse\Platform\Http\SameOriginGuard;
use Sats4you\Pulse\Platform\Http\CsrfToken;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Platform\Security\SecretGenerator;
use Sats4you\Pulse\Pulse\Attendance\AttendanceService;
use Sats4you\Pulse\Pulse\Administration\AdminBootstrapPage;
use Sats4you\Pulse\Pulse\Administration\AdminEventService;
use Sats4you\Pulse\Pulse\Administration\AdminPage;
use Sats4you\Pulse\Pulse\Credentials\CredentialPage;
use Sats4you\Pulse\Pulse\Credentials\CredentialRotationService;
use Sats4you\Pulse\Pulse\Credentials\RecoveryBootstrapPage;
use Sats4you\Pulse\Pulse\Event\EventAccessPolicy;
use Sats4you\Pulse\Pulse\Event\PublicationState;
use Sats4you\Pulse\Pulse\PublicAccess\AccessExchange;
use Sats4you\Pulse\Pulse\PublicAccess\AccessSessionCodec;
use Sats4you\Pulse\Pulse\PublicAccess\BootstrapPage;
use Sats4you\Pulse\Pulse\PublicAccess\ParticipantFlow;
use Sats4you\Pulse\Pulse\PublicAccess\ParticipantPage;
use Sats4you\Pulse\Pulse\PublicAccess\PulseLandingPage;
use Sats4you\Pulse\Pulse\Privacy\PrivacyPage;
use Sats4you\Pulse\Pulse\Retention\RetentionSchedule;
use Sats4you\Pulse\Tests\Support\SqlitePulseStore;
use Sats4you\Pulse\Tests\Support\InMemoryAdminEventStore;
use Slim\Psr7\Factory\ServerRequestFactory;

final class PulseApplicationFactoryTest extends TestCase
{
    private const ROUND_ID = '0123456789abcdef0123456789abcdef';
    private const EVENT_ID = '11111111111111111111111111111111';
    private const PUBLIC_EVENT_ID = '22222222222222222222222222222222';
    private const SLUG = 'bern-bitcoin';
    private const ORIGIN = 'https://sats4you.ch';

    public function testAnonymousParticipantCanJoinAndWithdrawWithoutAProfile(): void
    {
        [$app, $connection] = $this->application();
        $requests = new ServerRequestFactory();

        $exchange = $app->handle(
            $requests->createServerRequest('POST', '/pulse/api/access/participant/' . self::SLUG)
                ->withParsedBody(['secret' => 'test-participant-secret']),
        );
        self::assertSame(200, $exchange->getStatusCode());
        $accessCookie = $this->cookieValue($exchange, 'pulse_access');

        $events = $app->handle(
            $requests->createServerRequest('GET', '/pulse/r/' . self::SLUG . '/events?lang=de')
                ->withQueryParams(['lang' => 'de'])
                ->withCookieParams(['pulse_access' => $accessCookie]),
        );
        self::assertSame(200, $events->getStatusCode());
        self::assertStringContainsString('Bern Monthly Bitcoin Meetup', (string) $events->getBody());
        self::assertStringContainsString('Ich bin dabei', (string) $events->getBody());

        $join = $app->handle(
            $requests->createServerRequest('POST', '/pulse/r/' . self::SLUG . '/events/' . self::PUBLIC_EVENT_ID . '/rsvp')
                ->withHeader('Origin', self::ORIGIN)
                ->withParsedBody(['lang' => 'de'])
                ->withCookieParams(['pulse_access' => $accessCookie]),
        );
        self::assertSame(303, $join->getStatusCode());
        self::assertSame('/pulse/r/' . self::SLUG . '/events?lang=de', $join->getHeaderLine('Location'));
        $rsvpCookieName = 'pulse_rsvp_' . self::PUBLIC_EVENT_ID;
        $rsvpCookie = $this->cookieValue($join, $rsvpCookieName);
        self::assertSame(5, $this->attendanceCount($connection));

        $joinedEvents = $app->handle(
            $requests->createServerRequest('GET', '/pulse/r/' . self::SLUG . '/events?lang=de')
                ->withQueryParams(['lang' => 'de'])
                ->withCookieParams([
                    'pulse_access' => $accessCookie,
                    $rsvpCookieName => $rsvpCookie,
                ]),
        );
        self::assertStringContainsString('<strong>5</strong>', (string) $joinedEvents->getBody());
        self::assertStringContainsString('Zusage zurückziehen', (string) $joinedEvents->getBody());

        $withdraw = $app->handle(
            $requests->createServerRequest('POST', '/pulse/r/' . self::SLUG . '/events/' . self::PUBLIC_EVENT_ID . '/withdraw')
                ->withHeader('Sec-Fetch-Site', 'same-origin')
                ->withParsedBody(['lang' => 'de'])
                ->withCookieParams([
                    'pulse_access' => $accessCookie,
                    $rsvpCookieName => $rsvpCookie,
                ]),
        );
        self::assertSame(303, $withdraw->getStatusCode());
        self::assertStringContainsString('Max-Age=0', $withdraw->getHeaderLine('Set-Cookie'));
        self::assertSame(4, $this->attendanceCount($connection));
    }

    public function testCrossSiteRsvpIsRejected(): void
    {
        [$app] = $this->application();
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/pulse/r/' . self::SLUG . '/events/' . self::PUBLIC_EVENT_ID . '/rsvp')
            ->withHeader('Origin', 'https://example.org');

        self::assertSame(403, $app->handle($request)->getStatusCode());
    }

    public function testAdministratorCanCreatePublishedEventWithCsrfProtection(): void
    {
        [$app, , $adminStore] = $this->application();
        $requests = new ServerRequestFactory();
        $exchange = $app->handle(
            $requests->createServerRequest('POST', '/pulse/api/access/admin/' . self::SLUG)
                ->withParsedBody(['secret' => 'test-admin-secret']),
        );
        self::assertSame(200, $exchange->getStatusCode());
        $adminCookie = $this->cookieValue($exchange, 'pulse_admin');

        $page = $app->handle(
            $requests->createServerRequest('GET', '/pulse/manage/r/' . self::SLUG . '/events?new=1&lang=de')
                ->withQueryParams(['new' => '1', 'lang' => 'de'])
                ->withCookieParams(['pulse_admin' => $adminCookie]),
        );
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('Neuen Termin erfassen', (string) $page->getBody());
        self::assertStringContainsString('data-event-form', (string) $page->getBody());
        self::assertStringNotContainsString('type="datetime-local"', (string) $page->getBody());
        self::assertStringContainsString('name="starts_at_date"', (string) $page->getBody());
        preg_match('/<select name="starts_at_minute" required>(.*?)<\/select>/s', (string) $page->getBody(), $minuteSelect);
        self::assertSame(5, substr_count($minuteSelect[1] ?? '', '<option'));
        self::assertStringContainsString('<option value="" selected>–</option>', $minuteSelect[1] ?? '');
        foreach (['00', '15', '30', '45'] as $minute) {
            self::assertStringContainsString('value="' . $minute . '"', $minuteSelect[1] ?? '');
        }
        preg_match('/name="csrf" value="([^"]+)"/', (string) $page->getBody(), $matches);
        self::assertNotEmpty($matches[1] ?? null);

        $eventStart = (new DateTimeImmutable('+20 days', new DateTimeZone('Europe/Zurich')))->setTime(18, 30);
        $createBody = [
            'csrf' => $matches[1],
            'lang' => 'de',
            'intent' => 'publish',
            'title' => 'September-Treffen',
            'starts_at_date' => $eventStart->format('Y-m-d'),
            'starts_at_hour' => '18',
            'starts_at_minute' => '30',
            'ends_at_date' => '',
            'ends_at_hour' => '21',
            'ends_at_minute' => '00',
            'location' => 'Restaurant in Bern',
            'note' => '',
            'publish_at_date' => '',
            'publish_at_hour' => '12',
            'publish_at_minute' => '00',
        ];
        $invalidEnd = $eventStart->modify('-1 hour');
        $invalidTiming = $app->handle(
            $requests->createServerRequest('POST', '/pulse/manage/r/' . self::SLUG . '/events')
                ->withHeader('Origin', self::ORIGIN)
                ->withParsedBody(array_merge($createBody, [
                    'ends_at_date' => $invalidEnd->format('Y-m-d'),
                    'ends_at_hour' => $invalidEnd->format('H'),
                    'ends_at_minute' => $invalidEnd->format('i'),
                ]))
                ->withCookieParams(['pulse_admin' => $adminCookie]),
        );
        self::assertSame(303, $invalidTiming->getStatusCode());
        self::assertStringContainsString('notice=timing', $invalidTiming->getHeaderLine('Location'));
        self::assertCount(0, $adminStore->events);

        $invalidTimeStep = $app->handle(
            $requests->createServerRequest('POST', '/pulse/manage/r/' . self::SLUG . '/events')
                ->withHeader('Origin', self::ORIGIN)
                ->withParsedBody(array_merge($createBody, [
                    'starts_at_minute' => '07',
                ]))
                ->withCookieParams(['pulse_admin' => $adminCookie]),
        );
        self::assertSame(303, $invalidTimeStep->getStatusCode());
        self::assertStringContainsString('notice=time_step', $invalidTimeStep->getHeaderLine('Location'));
        self::assertCount(0, $adminStore->events);

        $create = $app->handle(
            $requests->createServerRequest('POST', '/pulse/manage/r/' . self::SLUG . '/events')
                ->withHeader('Origin', self::ORIGIN)
                ->withParsedBody($createBody)
                ->withCookieParams(['pulse_admin' => $adminCookie]),
        );
        self::assertSame(303, $create->getStatusCode());
        self::assertCount(1, $adminStore->events);
        $createdEvent = array_values($adminStore->events)[0];
        self::assertSame(PublicationState::Published, $createdEvent->publicationState);

        $adminList = $app->handle(
            $requests->createServerRequest('GET', '/pulse/manage/r/' . self::SLUG . '/events?lang=de')
                ->withQueryParams(['lang' => 'de'])
                ->withCookieParams(['pulse_admin' => $adminCookie]),
        );
        self::assertStringContainsString('data-confirm="Neue Zusagen wirklich schliessen?', (string) $adminList->getBody());
        self::assertStringContainsString('sort=asc', (string) $adminList->getBody());
        self::assertStringContainsString('Nächste zuerst ↑', (string) $adminList->getBody());

        $close = $app->handle(
            $requests->createServerRequest(
                'POST',
                '/pulse/manage/r/' . self::SLUG . '/events/' . $createdEvent->publicId,
            )
                ->withHeader('Origin', self::ORIGIN)
                ->withParsedBody(['csrf' => $matches[1], 'lang' => 'de', 'intent' => 'close'])
                ->withCookieParams(['pulse_admin' => $adminCookie]),
        );
        self::assertSame(303, $close->getStatusCode());
        self::assertNotNull($adminStore->events[$createdEvent->publicId]->rsvpClosedAt);

        $open = $app->handle(
            $requests->createServerRequest(
                'POST',
                '/pulse/manage/r/' . self::SLUG . '/events/' . $createdEvent->publicId,
            )
                ->withHeader('Origin', self::ORIGIN)
                ->withParsedBody(['csrf' => $matches[1], 'lang' => 'de', 'intent' => 'open'])
                ->withCookieParams(['pulse_admin' => $adminCookie]),
        );
        self::assertSame(303, $open->getStatusCode());
        self::assertNull($adminStore->events[$createdEvent->publicId]->rsvpClosedAt);

        $publishedDelete = $app->handle(
            $requests->createServerRequest(
                'POST',
                '/pulse/manage/r/' . self::SLUG . '/events/' . $createdEvent->publicId,
            )
                ->withHeader('Origin', self::ORIGIN)
                ->withParsedBody(['csrf' => $matches[1], 'lang' => 'de', 'intent' => 'delete'])
                ->withCookieParams(['pulse_admin' => $adminCookie]),
        );
        self::assertSame(303, $publishedDelete->getStatusCode());
        self::assertStringContainsString('notice=failed', $publishedDelete->getHeaderLine('Location'));
        self::assertArrayHasKey($createdEvent->publicId, $adminStore->events);

        $draftBody = array_merge($createBody, ['intent' => 'save', 'title' => 'Unveröffentlichter Entwurf']);
        $draftCreate = $app->handle(
            $requests->createServerRequest('POST', '/pulse/manage/r/' . self::SLUG . '/events')
                ->withHeader('Origin', self::ORIGIN)
                ->withParsedBody($draftBody)
                ->withCookieParams(['pulse_admin' => $adminCookie]),
        );
        self::assertSame(303, $draftCreate->getStatusCode());
        $draft = array_values(array_filter(
            $adminStore->events,
            static fn ($event): bool => $event->publicationState === PublicationState::Draft,
        ))[0];

        $draftDelete = $app->handle(
            $requests->createServerRequest(
                'POST',
                '/pulse/manage/r/' . self::SLUG . '/events/' . $draft->publicId,
            )
                ->withHeader('Origin', self::ORIGIN)
                ->withParsedBody(['csrf' => $matches[1], 'lang' => 'de', 'intent' => 'delete'])
                ->withCookieParams(['pulse_admin' => $adminCookie]),
        );
        self::assertSame(303, $draftDelete->getStatusCode());
        self::assertArrayNotHasKey($draft->publicId, $adminStore->events);

        $scheduledBody = array_merge($createBody, [
            'intent' => 'schedule',
            'title' => 'Geplante Veröffentlichung',
            'starts_at_date' => $eventStart->modify('+30 days')->format('Y-m-d'),
            'publish_at_date' => (new DateTimeImmutable('+1 day', new DateTimeZone('Europe/Zurich')))
                ->format('Y-m-d'),
        ]);
        $scheduledCreate = $app->handle(
            $requests->createServerRequest('POST', '/pulse/manage/r/' . self::SLUG . '/events')
                ->withHeader('Origin', self::ORIGIN)
                ->withParsedBody($scheduledBody)
                ->withCookieParams(['pulse_admin' => $adminCookie]),
        );
        self::assertSame(303, $scheduledCreate->getStatusCode());
        $scheduled = array_values(array_filter(
            $adminStore->events,
            static fn ($event): bool => $event->publicationState === PublicationState::Scheduled,
        ))[0];
        $scheduledList = $app->handle(
            $requests->createServerRequest('GET', '/pulse/manage/r/' . self::SLUG . '/events?lang=de')
                ->withQueryParams(['lang' => 'de'])
                ->withCookieParams(['pulse_admin' => $adminCookie]),
        );
        self::assertStringContainsString('Veröffentlichung geplant', (string) $scheduledList->getBody());
        self::assertStringContainsString('Geplante Veröffentlichung', (string) $scheduledList->getBody());
        $descendingHtml = (string) $scheduledList->getBody();
        self::assertLessThan(
            strpos($descendingHtml, 'September-Treffen'),
            strpos($descendingHtml, 'Geplante Veröffentlichung'),
        );

        $ascendingList = $app->handle(
            $requests->createServerRequest('GET', '/pulse/manage/r/' . self::SLUG . '/events?lang=de&sort=asc')
                ->withQueryParams(['lang' => 'de', 'sort' => 'asc'])
                ->withCookieParams(['pulse_admin' => $adminCookie]),
        );
        self::assertSame(200, $ascendingList->getStatusCode());
        $ascendingHtml = (string) $ascendingList->getBody();
        self::assertStringContainsString('sort=desc', $ascendingHtml);
        self::assertStringContainsString('Späteste zuerst ↓', $ascendingHtml);
        self::assertLessThan(
            strpos($ascendingHtml, 'Geplante Veröffentlichung'),
            strpos($ascendingHtml, 'September-Treffen'),
        );

        $scheduledPublish = $app->handle(
            $requests->createServerRequest(
                'POST',
                '/pulse/manage/r/' . self::SLUG . '/events/' . $scheduled->publicId,
            )
                ->withHeader('Origin', self::ORIGIN)
                ->withParsedBody(['csrf' => $matches[1], 'lang' => 'de', 'intent' => 'publish'])
                ->withCookieParams(['pulse_admin' => $adminCookie]),
        );
        self::assertSame(303, $scheduledPublish->getStatusCode());
        self::assertStringContainsString('notice=saved', $scheduledPublish->getHeaderLine('Location'));
        self::assertSame(PublicationState::Published, $adminStore->events[$scheduled->publicId]->publicationState);

        $scheduledDeleteCreate = $app->handle(
            $requests->createServerRequest('POST', '/pulse/manage/r/' . self::SLUG . '/events')
                ->withHeader('Origin', self::ORIGIN)
                ->withParsedBody(array_merge($scheduledBody, ['title' => 'Geplanter Löschtest']))
                ->withCookieParams(['pulse_admin' => $adminCookie]),
        );
        self::assertSame(303, $scheduledDeleteCreate->getStatusCode());
        $scheduledForDeletion = array_values(array_filter(
            $adminStore->events,
            static fn ($event): bool => $event->publicationState === PublicationState::Scheduled,
        ))[0];

        $scheduledDelete = $app->handle(
            $requests->createServerRequest(
                'POST',
                '/pulse/manage/r/' . self::SLUG . '/events/' . $scheduledForDeletion->publicId,
            )
                ->withHeader('Origin', self::ORIGIN)
                ->withParsedBody(['csrf' => $matches[1], 'lang' => 'de', 'intent' => 'delete'])
                ->withCookieParams(['pulse_admin' => $adminCookie]),
        );
        self::assertSame(303, $scheduledDelete->getStatusCode());
        self::assertArrayNotHasKey($scheduledForDeletion->publicId, $adminStore->events);

        $withoutCsrf = $app->handle(
            $requests->createServerRequest('POST', '/pulse/manage/r/' . self::SLUG . '/events')
                ->withHeader('Origin', self::ORIGIN)
                ->withParsedBody(array_merge($createBody, ['csrf' => '']))
                ->withCookieParams(['pulse_admin' => $adminCookie]),
        );
        self::assertSame(403, $withoutCsrf->getStatusCode());
    }

    public function testParticipantSecretCannotOpenAdministration(): void
    {
        [$app] = $this->application();
        $response = $app->handle(
            (new ServerRequestFactory())
                ->createServerRequest('POST', '/pulse/api/access/admin/' . self::SLUG)
                ->withParsedBody(['secret' => 'test-participant-secret']),
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testRecoveryReplacesBothAdministratorSecretsAndInvalidatesOldSession(): void
    {
        [$app, $connection] = $this->application();
        $requests = new ServerRequestFactory();
        $oldAdminExchange = $app->handle(
            $requests->createServerRequest('POST', '/pulse/api/access/admin/' . self::SLUG)
                ->withParsedBody(['secret' => 'test-admin-secret']),
        );
        $oldAdminCookie = $this->cookieValue($oldAdminExchange, 'pulse_admin');
        $recoveryExchange = $app->handle(
            $requests->createServerRequest('POST', '/pulse/api/access/recovery/' . self::SLUG)
                ->withParsedBody(['secret' => 'test-recovery-secret']),
        );
        self::assertSame(200, $recoveryExchange->getStatusCode());
        $recoveryCookie = $this->cookieValue($recoveryExchange, 'pulse_recovery');
        self::assertStringContainsString('HttpOnly', $recoveryExchange->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('SameSite=Strict', $recoveryExchange->getHeaderLine('Set-Cookie'));

        $confirmation = $app->handle(
            $requests->createServerRequest('GET', '/pulse/recover/r/' . self::SLUG . '/confirm?lang=de')
                ->withQueryParams(['lang' => 'de'])
                ->withCookieParams(['pulse_recovery' => $recoveryCookie]),
        );
        self::assertSame(200, $confirmation->getStatusCode());
        preg_match('/name="csrf" value="([^"]+)"/', (string) $confirmation->getBody(), $matches);
        self::assertNotEmpty($matches[1] ?? null);

        $crossSite = $app->handle(
            $requests->createServerRequest('POST', '/pulse/recover/r/' . self::SLUG . '/rotate')
                ->withHeader('Origin', 'https://example.org')
                ->withParsedBody(['csrf' => $matches[1], 'lang' => 'de', 'confirm' => 'rotate'])
                ->withCookieParams(['pulse_recovery' => $recoveryCookie]),
        );
        self::assertSame(403, $crossSite->getStatusCode());

        $rotation = $app->handle(
            $requests->createServerRequest('POST', '/pulse/recover/r/' . self::SLUG . '/rotate')
                ->withHeader('Origin', self::ORIGIN)
                ->withParsedBody(['csrf' => $matches[1], 'lang' => 'de', 'confirm' => 'rotate'])
                ->withCookieParams(['pulse_recovery' => $recoveryCookie]),
        );
        self::assertSame(200, $rotation->getStatusCode());
        self::assertStringContainsString('Neue Zugänge sichern', (string) $rotation->getBody());
        self::assertStringContainsString(self::ORIGIN . '/pulse/manage/r/' . self::SLUG . '#', (string) $rotation->getBody());
        self::assertStringContainsString('<code>', (string) $rotation->getBody());
        self::assertCount(2, $rotation->getHeader('Set-Cookie'));
        $round = $connection->query('SELECT * FROM rounds')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(2, (int) $round['admin_version']);
        self::assertSame(2, (int) $round['recovery_version']);

        $oldAdminPage = $app->handle(
            $requests->createServerRequest('GET', '/pulse/manage/r/' . self::SLUG . '/events')
                ->withCookieParams(['pulse_admin' => $oldAdminCookie]),
        );
        self::assertSame(403, $oldAdminPage->getStatusCode());
        self::assertSame(403, $app->handle(
            $requests->createServerRequest('POST', '/pulse/api/access/admin/' . self::SLUG)
                ->withParsedBody(['secret' => 'test-admin-secret']),
        )->getStatusCode());
        self::assertSame(403, $app->handle(
            $requests->createServerRequest('POST', '/pulse/api/access/recovery/' . self::SLUG)
                ->withParsedBody(['secret' => 'test-recovery-secret']),
        )->getStatusCode());
        $reusedRecovery = $app->handle(
            $requests->createServerRequest('POST', '/pulse/recover/r/' . self::SLUG . '/rotate')
                ->withHeader('Origin', self::ORIGIN)
                ->withParsedBody(['csrf' => $matches[1], 'lang' => 'de', 'confirm' => 'rotate'])
                ->withCookieParams(['pulse_recovery' => $recoveryCookie]),
        );
        self::assertSame(403, $reusedRecovery->getStatusCode());
    }

    public function testAdministratorCanRotateParticipantLinkWithoutChangingRsvps(): void
    {
        [$app, $connection] = $this->application();
        $requests = new ServerRequestFactory();
        $participantExchange = $app->handle(
            $requests->createServerRequest('POST', '/pulse/api/access/participant/' . self::SLUG)
                ->withParsedBody(['secret' => 'test-participant-secret']),
        );
        $oldParticipantCookie = $this->cookieValue($participantExchange, 'pulse_access');
        $adminExchange = $app->handle(
            $requests->createServerRequest('POST', '/pulse/api/access/admin/' . self::SLUG)
                ->withParsedBody(['secret' => 'test-admin-secret']),
        );
        $adminCookie = $this->cookieValue($adminExchange, 'pulse_admin');
        $adminPage = $app->handle(
            $requests->createServerRequest('GET', '/pulse/manage/r/' . self::SLUG . '/events?lang=de')
                ->withQueryParams(['lang' => 'de'])
                ->withCookieParams(['pulse_admin' => $adminCookie]),
        );
        preg_match('/name="csrf" value="([^"]+)"/', (string) $adminPage->getBody(), $matches);

        $withoutConfirmation = $app->handle(
            $requests->createServerRequest('POST', '/pulse/manage/r/' . self::SLUG . '/participant-link/rotate')
                ->withHeader('Origin', self::ORIGIN)
                ->withParsedBody(['csrf' => $matches[1], 'lang' => 'de'])
                ->withCookieParams(['pulse_admin' => $adminCookie]),
        );
        self::assertSame(403, $withoutConfirmation->getStatusCode());

        $rotation = $app->handle(
            $requests->createServerRequest('POST', '/pulse/manage/r/' . self::SLUG . '/participant-link/rotate')
                ->withHeader('Origin', self::ORIGIN)
                ->withParsedBody(['csrf' => $matches[1], 'lang' => 'de', 'confirm' => 'rotate'])
                ->withCookieParams(['pulse_admin' => $adminCookie]),
        );
        self::assertSame(200, $rotation->getStatusCode());
        self::assertStringContainsString('Neuer Teilnehmerlink', (string) $rotation->getBody());
        self::assertStringContainsString(self::ORIGIN . '/pulse/r/' . self::SLUG . '#', (string) $rotation->getBody());
        self::assertSame(4, $this->attendanceCount($connection));

        $oldParticipantPage = $app->handle(
            $requests->createServerRequest('GET', '/pulse/r/' . self::SLUG . '/events')
                ->withCookieParams(['pulse_access' => $oldParticipantCookie]),
        );
        self::assertSame(403, $oldParticipantPage->getStatusCode());
        self::assertSame(403, $app->handle(
            $requests->createServerRequest('POST', '/pulse/api/access/participant/' . self::SLUG)
                ->withParsedBody(['secret' => 'test-participant-secret']),
        )->getStatusCode());
        $currentAdminPage = $app->handle(
            $requests->createServerRequest('GET', '/pulse/manage/r/' . self::SLUG . '/events')
                ->withCookieParams(['pulse_admin' => $adminCookie]),
        );
        self::assertSame(200, $currentAdminPage->getStatusCode());
    }

    public function testPublicLandingAndPrivacyExplanationNeedNoAccessSecret(): void
    {
        [$app] = $this->application();
        $requests = new ServerRequestFactory();

        $root = $app->handle($requests->createServerRequest('GET', '/'));
        self::assertSame(302, $root->getStatusCode());
        self::assertSame('/pulse', $root->getHeaderLine('Location'));

        $landing = $app->handle(
            $requests->createServerRequest('GET', '/pulse?lang=rm')->withQueryParams(['lang' => 'rm']),
        );
        self::assertSame(200, $landing->getStatusCode());
        self::assertStringContainsString('Coordinaziun privata da gruppas', (string) $landing->getBody());
        self::assertSame('private, no-store', $landing->getHeaderLine('Cache-Control'));
        self::assertStringContainsString("frame-ancestors 'none'", $landing->getHeaderLine('Content-Security-Policy'));

        $privacy = $app->handle(
            $requests->createServerRequest('GET', '/pulse/privacy?lang=de')->withQueryParams(['lang' => 'de']),
        );
        self::assertSame(200, $privacy->getStatusCode());
        self::assertStringContainsString('Was pulse über dich weiss', (string) $privacy->getBody());
    }

    public function testGenericErrorsDoNotReflectSecrets(): void
    {
        [$app] = $this->application();
        $response = $app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/pulse/not-found/marked-test-secret'),
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertStringNotContainsString('marked-test-secret', (string) $response->getBody());
        self::assertSame('no-referrer', $response->getHeaderLine('Referrer-Policy'));
    }

    /** @return array{\Slim\App, PDO, InMemoryAdminEventStore} */
    private function application(): array
    {
        $connection = new PDO('sqlite::memory:', options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $connection->exec('CREATE TABLE rounds (id TEXT PRIMARY KEY, slug TEXT UNIQUE, participant_digest BLOB, access_version INTEGER, admin_digest BLOB, admin_version INTEGER, recovery_digest BLOB, recovery_version INTEGER)');
        $connection->exec('CREATE TABLE events (id TEXT PRIMARY KEY, public_id TEXT UNIQUE, round_id TEXT, title TEXT, starts_at TEXT, ends_at TEXT, location TEXT, note TEXT, publication_state TEXT, publish_at TEXT, rsvp_closed_at TEXT, material_changed_at TEXT)');
        $connection->exec('CREATE TABLE attendance (event_id TEXT, digest BLOB, created_at TEXT, delete_at TEXT, UNIQUE(event_id, digest))');
        $connection->exec('CREATE TABLE notification_changes (id TEXT PRIMARY KEY, event_id TEXT, change_type TEXT, occurred_at TEXT, delete_at TEXT)');

        $digester = new SecretDigester(str_repeat('h', 32));
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $round = $connection->prepare('INSERT INTO rounds VALUES (:id, :slug, :digest, 1, :admin_digest, 1, :recovery_digest, 1)');
        $round->bindValue('id', self::ROUND_ID);
        $round->bindValue('slug', self::SLUG);
        $round->bindValue('digest', $digester->digest('test-participant-secret'), PDO::PARAM_LOB);
        $round->bindValue('admin_digest', $digester->digest('test-admin-secret'), PDO::PARAM_LOB);
        $round->bindValue('recovery_digest', $digester->digest('test-recovery-secret'), PDO::PARAM_LOB);
        $round->execute();
        $connection->prepare('INSERT INTO events VALUES (:id, :public_id, :round_id, :title, :starts_at, NULL, NULL, :note, :state, :publish_at, NULL, NULL)')->execute([
            'id' => self::EVENT_ID,
            'public_id' => self::PUBLIC_EVENT_ID,
            'round_id' => self::ROUND_ID,
            'title' => 'Bern Monthly Bitcoin Meetup',
            'starts_at' => $now->modify('+12 days')->format('Y-m-d H:i:s.u'),
            'note' => 'Geschlossener Pulse-Pilot.',
            'state' => 'published',
            'publish_at' => $now->modify('-1 day')->format('Y-m-d H:i:s.u'),
        ]);
        $attendance = $connection->prepare('INSERT INTO attendance VALUES (:event_id, :digest, :created_at, :delete_at)');
        foreach (range(1, 4) as $index) {
            $attendance->bindValue('event_id', self::EVENT_ID);
            $attendance->bindValue('digest', $digester->digest('fixture-' . $index), PDO::PARAM_LOB);
            $attendance->bindValue('created_at', $now->format('Y-m-d H:i:s.u'));
            $attendance->bindValue('delete_at', $now->modify('+19 days')->format('Y-m-d H:i:s.u'));
            $attendance->execute();
        }

        $store = new SqlitePulseStore($connection);
        $policy = new EventAccessPolicy();
        $attendanceService = new AttendanceService(
            $store,
            $policy,
            new RetentionSchedule(),
            new SecretGenerator(),
            $digester,
        );
        $adminStore = new InMemoryAdminEventStore();
        $factory = new PulseApplicationFactory(
            new AccessExchange($store, $digester, new AccessSessionCodec(str_repeat('s', 32))),
            new ParticipantFlow($store, $store, $attendanceService, $digester),
            new AdminEventService($adminStore, new RetentionSchedule()),
            new CredentialRotationService($store, new SecretGenerator(), $digester),
            new BootstrapPage(),
            new ParticipantPage($policy),
            new PulseLandingPage(),
            new AdminBootstrapPage(),
            new AdminPage(),
            new RecoveryBootstrapPage(),
            new CredentialPage(),
            new PrivacyPage(),
            new SameOriginGuard(self::ORIGIN),
            new CsrfToken(str_repeat('c', 32)),
            dirname(__DIR__, 3) . '/resources/translations',
            'Bern Monthly Bitcoin Meetup',
            self::ORIGIN,
            true,
        );

        return [$factory->create(), $connection, $adminStore];
    }

    private function cookieValue(ResponseInterface $response, string $name): string
    {
        foreach ($response->getHeader('Set-Cookie') as $header) {
            $pair = explode(';', $header, 2)[0];
            [$cookieName, $value] = array_pad(explode('=', $pair, 2), 2, '');
            if (rawurldecode($cookieName) === $name) {
                return rawurldecode($value);
            }
        }

        self::fail('Expected cookie was not set: ' . $name);
    }

    private function attendanceCount(PDO $connection): int
    {
        return (int) $connection->query('SELECT COUNT(*) FROM attendance')->fetchColumn();
    }
}
