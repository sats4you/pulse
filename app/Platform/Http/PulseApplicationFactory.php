<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Platform\Http;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sats4you\Pulse\Platform\I18n\TranslatorFactory;
use Sats4you\Pulse\Pulse\Administration\AdminBootstrapPage;
use Sats4you\Pulse\Pulse\Administration\AdminEventService;
use Sats4you\Pulse\Pulse\Administration\AdminPage;
use Sats4you\Pulse\Pulse\Administration\EventDetails;
use Sats4you\Pulse\Pulse\Credentials\CredentialPage;
use Sats4you\Pulse\Pulse\Credentials\CredentialRotationService;
use Sats4you\Pulse\Pulse\Credentials\RecoveryBootstrapPage;
use Sats4you\Pulse\Pulse\Event\PublicationState;
use Sats4you\Pulse\Pulse\PublicAccess\AccessExchange;
use Sats4you\Pulse\Pulse\PublicAccess\BootstrapPage;
use Sats4you\Pulse\Pulse\PublicAccess\ParticipantFlow;
use Sats4you\Pulse\Pulse\PublicAccess\ParticipantPage;
use Sats4you\Pulse\Pulse\PublicAccess\PulseLandingPage;
use Sats4you\Pulse\Pulse\Privacy\PrivacyPage;
use Slim\Factory\AppFactory;
use Slim\Interfaces\RouteCollectorProxyInterface;

final readonly class PulseApplicationFactory
{
    private const LANGUAGES = [
        'de' => 'Deutsch',
        'fr' => 'Français',
        'it' => 'Italiano',
        'rm' => 'Rumantsch Grischun',
    ];

    public function __construct(
        private AccessExchange $accessExchange,
        private ParticipantFlow $participantFlow,
        private AdminEventService $adminEvents,
        private CredentialRotationService $credentialRotation,
        private BootstrapPage $bootstrapPage,
        private ParticipantPage $participantPage,
        private PulseLandingPage $landingPage,
        private AdminBootstrapPage $adminBootstrapPage,
        private AdminPage $adminPage,
        private RecoveryBootstrapPage $recoveryBootstrapPage,
        private CredentialPage $credentialPage,
        private PrivacyPage $privacyPage,
        private SameOriginGuard $originGuard,
        private CsrfToken $csrfToken,
        private string $translationDirectory,
        private string $groupName,
        private string $baseUrl,
        private bool $secureCookies,
    ) {
    }

    public function create(): \Slim\App
    {
        $app = AppFactory::create();
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();
        $app->addErrorMiddleware(false, false, false);
        $app->add(new SecurityHeadersMiddleware());

        $app->get('/pulse', $this->landing(...));
        $app->get('/pulse/', $this->landing(...));
        $app->group('/pulse', function (RouteCollectorProxyInterface $routes): void {
            $routes->get('/privacy', $this->privacy(...));
            $routes->get('/r/{slug}', $this->bootstrap(...));
            $routes->post('/api/access/participant/{slug}', $this->exchange(...));
            $routes->get('/r/{slug}/events', $this->events(...));
            $routes->post('/r/{slug}/events/{event}/rsvp', $this->join(...));
            $routes->post('/r/{slug}/events/{event}/withdraw', $this->withdraw(...));
            $routes->get('/manage/r/{slug}', $this->adminBootstrap(...));
            $routes->post('/api/access/admin/{slug}', $this->exchangeAdmin(...));
            $routes->get('/recover/r/{slug}', $this->recoveryBootstrap(...));
            $routes->post('/api/access/recovery/{slug}', $this->exchangeRecovery(...));
            $routes->get('/recover/r/{slug}/confirm', $this->recoveryConfirmation(...));
            $routes->post('/recover/r/{slug}/rotate', $this->recoverAdministrator(...));
            $routes->get('/manage/r/{slug}/events', $this->adminEventList(...));
            $routes->post('/manage/r/{slug}/events', $this->createAdminEvent(...));
            $routes->post('/manage/r/{slug}/events/{event}', $this->mutateAdminEvent(...));
            $routes->post('/manage/r/{slug}/participant-link/rotate', $this->rotateParticipantLink(...));
        });

        return $app;
    }

    private function landing(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $locale = $this->locale($request);
        $translator = TranslatorFactory::create($locale, $this->translationDirectory);
        $response->getBody()->write($this->landingPage->render($translator, $locale, self::LANGUAGES));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function privacy(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $locale = $this->locale($request);
        $translator = TranslatorFactory::create($locale, $this->translationDirectory);
        $response->getBody()->write($this->privacyPage->render($translator, $locale, self::LANGUAGES));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function bootstrap(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $slug = (string) $args['slug'];
        $locale = $this->locale($request);
        $translator = TranslatorFactory::create($locale, $this->translationDirectory);
        $html = $this->bootstrapPage->render(
            $translator,
            $locale,
            $slug,
            '/pulse/api/access/participant/' . rawurlencode($slug),
            '/pulse/r/' . rawurlencode($slug) . '/events',
            self::LANGUAGES,
        );
        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function exchange(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $body = $request->getParsedBody();
        $secret = is_array($body) ? (string) ($body['secret'] ?? '') : '';
        $slug = (string) $args['slug'];
        $cookie = $this->accessExchange->exchangeParticipant($slug, $secret, new DateTimeImmutable());
        if ($cookie === null) {
            return $this->json($response, ['ok' => false], 403);
        }

        $expiresAt = (new DateTimeImmutable())->add(new DateInterval('P180D'));
        $response = $response->withAddedHeader('Set-Cookie', CookieHeader::create(
            'pulse_access',
            $cookie,
            '/pulse/r/' . rawurlencode($slug),
            $expiresAt,
            $this->secureCookies,
        ));

        return $this->json($response, ['ok' => true]);
    }

    private function adminBootstrap(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $slug = (string) $args['slug'];
        $locale = $this->locale($request);
        $translator = TranslatorFactory::create($locale, $this->translationDirectory);
        $response->getBody()->write($this->adminBootstrapPage->render(
            $translator,
            $locale,
            $slug,
            '/pulse/api/access/admin/' . rawurlencode($slug),
            '/pulse/manage/r/' . rawurlencode($slug) . '/events',
            self::LANGUAGES,
        ));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function exchangeAdmin(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $body = $request->getParsedBody();
        $secret = is_array($body) ? (string) ($body['secret'] ?? '') : '';
        $slug = (string) $args['slug'];
        $cookie = $this->accessExchange->exchangeAdministrator($slug, $secret, new DateTimeImmutable());
        if ($cookie === null) {
            return $this->json($response, ['ok' => false], 403);
        }

        $response = $response->withAddedHeader('Set-Cookie', CookieHeader::create(
            'pulse_admin',
            $cookie,
            '/pulse/manage/r/' . rawurlencode($slug),
            (new DateTimeImmutable())->add(new DateInterval('PT12H')),
            $this->secureCookies,
        ));

        return $this->json($response, ['ok' => true]);
    }

    private function recoveryBootstrap(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $slug = (string) $args['slug'];
        $locale = $this->locale($request);
        $translator = TranslatorFactory::create($locale, $this->translationDirectory);
        $response->getBody()->write($this->recoveryBootstrapPage->render(
            $translator,
            $locale,
            $slug,
            '/pulse/api/access/recovery/' . rawurlencode($slug),
            '/pulse/recover/r/' . rawurlencode($slug) . '/confirm',
            self::LANGUAGES,
        ));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function exchangeRecovery(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $body = $request->getParsedBody();
        $secret = is_array($body) ? (string) ($body['secret'] ?? '') : '';
        $slug = (string) $args['slug'];
        $now = new DateTimeImmutable();
        $cookie = $this->accessExchange->exchangeRecovery($slug, $secret, $now);
        if ($cookie === null) {
            return $this->json($response, ['ok' => false], 403);
        }

        $response = $response->withAddedHeader('Set-Cookie', CookieHeader::create(
            'pulse_recovery',
            $cookie,
            '/pulse/recover/r/' . rawurlencode($slug),
            $now->add(new DateInterval('PT10M')),
            $this->secureCookies,
        ));

        return $this->json($response, ['ok' => true]);
    }

    private function recoveryConfirmation(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $slug = (string) $args['slug'];
        $cookie = (string) ($request->getCookieParams()['pulse_recovery'] ?? '');
        if ($this->accessExchange->validateRecovery($cookie, $slug, new DateTimeImmutable()) === null) {
            return $response->withStatus(403);
        }

        $locale = $this->locale($request);
        $translator = TranslatorFactory::create($locale, $this->translationDirectory);
        $response->getBody()->write($this->credentialPage->renderRecoveryConfirmation(
            $translator,
            $locale,
            $slug,
            $this->csrfToken->issue($cookie, 'recovery/' . $slug),
            self::LANGUAGES,
        ));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function recoverAdministrator(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $slug = (string) $args['slug'];
        $cookie = (string) ($request->getCookieParams()['pulse_recovery'] ?? '');
        $body = is_array($request->getParsedBody()) ? $request->getParsedBody() : [];
        if (!$this->originGuard->allows(
            $request->getHeaderLine('Origin'),
            $request->getHeaderLine('Sec-Fetch-Site'),
        ) || !$this->csrfToken->isValid(
            (string) ($body['csrf'] ?? ''),
            $cookie,
            'recovery/' . $slug,
        ) || ($body['confirm'] ?? '') !== 'rotate') {
            return $response->withStatus(403);
        }
        $grant = $this->accessExchange->validateRecovery($cookie, $slug, new DateTimeImmutable());
        if ($grant === null) {
            return $response->withStatus(403);
        }

        try {
            $result = $this->credentialRotation->recoverAdministrator($grant, $slug, new DateTimeImmutable());
        } catch (DomainException) {
            return $response->withStatus(409);
        }
        $locale = $this->bodyLocale($body);
        $translator = TranslatorFactory::create($locale, $this->translationDirectory);
        $administratorLink = $this->baseUrl . '/pulse/manage/r/' . rawurlencode($slug) . '#' . rawurlencode($result->administratorSecret);
        $recoveryLink = $this->baseUrl . '/pulse/recover/r/' . rawurlencode($slug) . '#' . rawurlencode($result->recoverySecret);
        $response->getBody()->write($this->credentialPage->renderRecoveryResult(
            $translator,
            $locale,
            $administratorLink,
            $result->recoverySecret,
            $recoveryLink,
            self::LANGUAGES,
        ));
        $response = $response->withAddedHeader('Set-Cookie', CookieHeader::expire(
            'pulse_recovery',
            '/pulse/recover/r/' . rawurlencode($slug),
            $this->secureCookies,
        ));
        $response = $response->withAddedHeader('Set-Cookie', CookieHeader::expire(
            'pulse_admin',
            '/pulse/manage/r/' . rawurlencode($slug),
            $this->secureCookies,
        ));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function events(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $slug = (string) $args['slug'];
        $grant = $this->participantGrant($request, $slug);
        if ($grant === null) {
            return $response->withStatus(403);
        }

        $now = new DateTimeImmutable();
        $events = $this->participantFlow->events($grant, $now);
        $cookies = $request->getCookieParams();
        $secrets = [];
        foreach ($events as $event) {
            $secrets[$event->publicId] = (string) ($cookies[$this->rsvpCookieName($event->publicId)] ?? '');
        }
        $locale = $this->locale($request);
        $translator = TranslatorFactory::create($locale, $this->translationDirectory);
        $html = $this->participantPage->render(
            $translator,
            $locale,
            $this->groupName,
            $slug,
            $events,
            $this->participantFlow->joinedByPublicId($events, $secrets),
            self::LANGUAGES,
            $now,
        );
        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function join(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if (!$this->originGuard->allows(
            $request->getHeaderLine('Origin'),
            $request->getHeaderLine('Sec-Fetch-Site'),
        )) {
            return $response->withStatus(403);
        }

        $slug = (string) $args['slug'];
        $event = (string) $args['event'];
        $grant = $this->participantGrant($request, $slug);
        if ($grant === null) {
            return $response->withStatus(403);
        }

        $cookieName = $this->rsvpCookieName($event);
        try {
            $result = $this->participantFlow->join(
                $grant,
                $event,
                $request->getCookieParams()[$cookieName] ?? null,
                new DateTimeImmutable(),
            );
        } catch (\DomainException) {
            return $response->withStatus(409);
        }

        $response = $response->withAddedHeader('Set-Cookie', CookieHeader::create(
            $cookieName,
            $result->participantSecret,
            '/pulse/r/' . rawurlencode($slug),
            $result->expiresAt,
            $this->secureCookies,
        ));

        return $this->redirectToEvents($request, $response, $slug);
    }

    private function withdraw(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if (!$this->originGuard->allows(
            $request->getHeaderLine('Origin'),
            $request->getHeaderLine('Sec-Fetch-Site'),
        )) {
            return $response->withStatus(403);
        }

        $slug = (string) $args['slug'];
        $event = (string) $args['event'];
        $grant = $this->participantGrant($request, $slug);
        $cookieName = $this->rsvpCookieName($event);
        $secret = (string) ($request->getCookieParams()[$cookieName] ?? '');
        if ($grant === null || $secret === '') {
            return $response->withStatus(403);
        }

        try {
            $this->participantFlow->withdraw($grant, $event, $secret, new DateTimeImmutable());
        } catch (\DomainException) {
            return $response->withStatus(409);
        }

        $response = $response->withAddedHeader('Set-Cookie', CookieHeader::expire(
            $cookieName,
            '/pulse/r/' . rawurlencode($slug),
            $this->secureCookies,
        ));

        return $this->redirectToEvents($request, $response, $slug);
    }

    private function adminEventList(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $slug = (string) $args['slug'];
        $adminCookie = (string) ($request->getCookieParams()['pulse_admin'] ?? '');
        $grant = $this->accessExchange->validateAdministrator($adminCookie, $slug, new DateTimeImmutable());
        if ($grant === null) {
            return $response->withStatus(403);
        }

        $query = $request->getQueryParams();
        $editing = null;
        if (isset($query['edit']) && is_string($query['edit'])) {
            $editing = $this->adminEvents->find($grant, $query['edit']);
        }
        $locale = $this->locale($request);
        $translator = TranslatorFactory::create($locale, $this->translationDirectory);
        $notice = match ((string) ($query['notice'] ?? '')) {
            'saved' => 'admin.saved',
            'invalid' => 'admin.invalid',
            'timing' => 'admin.invalid_timing',
            'time_step' => 'admin.invalid_time_step',
            'failed' => 'admin.action_failed',
            default => null,
        };
        $response->getBody()->write($this->adminPage->render(
            $translator,
            $locale,
            $this->groupName,
            $slug,
            $this->adminEvents->events($grant),
            $editing,
            isset($query['new']),
            $this->csrfToken->issue($adminCookie, $slug),
            self::LANGUAGES,
            $notice,
            new DateTimeImmutable(),
        ));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function createAdminEvent(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $authorisation = $this->adminMutationAuthorisation($request, (string) $args['slug']);
        if ($authorisation === null) {
            return $response->withStatus(403);
        }
        [$grant, $slug] = $authorisation;
        $body = is_array($request->getParsedBody()) ? $request->getParsedBody() : [];
        $now = new DateTimeImmutable();

        try {
            $details = $this->eventDetails($body);
            $intent = (string) ($body['intent'] ?? 'save');
            $state = match ($intent) {
                'publish' => PublicationState::Published,
                'schedule' => PublicationState::Scheduled,
                default => PublicationState::Draft,
            };
            $this->adminEvents->create(
                $grant,
                $details,
                $state,
                $state === PublicationState::Scheduled ? $this->formDateFromBody($body, 'publish_at') : null,
                $now,
            );
        } catch (InvalidArgumentException $error) {
            $this->logAdminEventFailure('create', $error->getMessage());
            return $this->redirectToAdmin(
                $request,
                $response,
                $slug,
                $this->invalidEventNotice($error),
            );
        } catch (DomainException $error) {
            $this->logAdminEventFailure('create', $error->getMessage());
            return $this->redirectToAdmin($request, $response, $slug, 'failed');
        }

        return $this->redirectToAdmin($request, $response, $slug, 'saved');
    }

    private function mutateAdminEvent(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $authorisation = $this->adminMutationAuthorisation($request, (string) $args['slug']);
        if ($authorisation === null) {
            return $response->withStatus(403);
        }
        [$grant, $slug] = $authorisation;
        $eventId = (string) $args['event'];
        $body = is_array($request->getParsedBody()) ? $request->getParsedBody() : [];
        $intent = (string) ($body['intent'] ?? 'save');
        $now = new DateTimeImmutable();

        try {
            if (in_array($intent, ['save', 'publish', 'schedule'], true)) {
                $this->adminEvents->update($grant, $eventId, $this->eventDetails($body), $now);
            }
            match ($intent) {
                'publish' => $this->adminEvents->publish($grant, $eventId, $now),
                'schedule' => $this->adminEvents->schedule(
                    $grant,
                    $eventId,
                    $this->formDateFromBody($body, 'publish_at'),
                    $now,
                ),
                'cancel' => $this->adminEvents->cancel($grant, $eventId, $now),
                'close' => $this->adminEvents->closeRsvps($grant, $eventId, $now),
                'open' => $this->adminEvents->openRsvps($grant, $eventId, $now),
                'duplicate' => $this->adminEvents->duplicate($grant, $eventId, $now),
                'delete' => $this->adminEvents->deleteUnpublished($grant, $eventId, $now),
                'save' => null,
                default => throw new InvalidArgumentException('Unknown event operation.'),
            };
        } catch (InvalidArgumentException $error) {
            $this->logAdminEventFailure('mutate', $error->getMessage());
            return $this->redirectToAdmin(
                $request,
                $response,
                $slug,
                $this->invalidEventNotice($error),
            );
        } catch (DomainException $error) {
            $this->logAdminEventFailure('mutate', $error->getMessage());
            return $this->redirectToAdmin($request, $response, $slug, 'failed');
        }

        return $this->redirectToAdmin($request, $response, $slug, 'saved');
    }

    private function rotateParticipantLink(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $authorisation = $this->adminMutationAuthorisation($request, (string) $args['slug']);
        $body = is_array($request->getParsedBody()) ? $request->getParsedBody() : [];
        if ($authorisation === null || ($body['confirm'] ?? '') !== 'rotate') {
            return $response->withStatus(403);
        }
        [$grant, $slug] = $authorisation;
        try {
            $result = $this->credentialRotation->rotateParticipant($grant, $slug, new DateTimeImmutable());
        } catch (DomainException) {
            return $response->withStatus(409);
        }
        $locale = $this->bodyLocale($body);
        $translator = TranslatorFactory::create($locale, $this->translationDirectory);
        $participantLink = $this->baseUrl . '/pulse/r/' . rawurlencode($slug) . '#' . rawurlencode($result->participantSecret);
        $response->getBody()->write($this->credentialPage->renderParticipantResult(
            $translator,
            $locale,
            $participantLink,
            '/pulse/manage/r/' . rawurlencode($slug) . '/events?lang=' . rawurlencode($locale),
            self::LANGUAGES,
        ));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function participantGrant(ServerRequestInterface $request, string $slug): ?\Sats4you\Pulse\Pulse\PublicAccess\AccessGrant
    {
        return $this->accessExchange->validateParticipant(
            (string) ($request->getCookieParams()['pulse_access'] ?? ''),
            $slug,
            new DateTimeImmutable(),
        );
    }

    /** @return array{\Sats4you\Pulse\Pulse\PublicAccess\AccessGrant, string}|null */
    private function adminMutationAuthorisation(ServerRequestInterface $request, string $slug): ?array
    {
        if (!$this->originGuard->allows(
            $request->getHeaderLine('Origin'),
            $request->getHeaderLine('Sec-Fetch-Site'),
        )) {
            $this->logAdministratorDenial('origin check failed');
            return null;
        }
        $cookie = (string) ($request->getCookieParams()['pulse_admin'] ?? '');
        if ($cookie === '') {
            $this->logAdministratorDenial('session cookie missing');
            return null;
        }
        $body = $request->getParsedBody();
        $csrf = is_array($body) ? (string) ($body['csrf'] ?? '') : '';
        if (!$this->csrfToken->isValid($csrf, $cookie, $slug)) {
            $this->logAdministratorDenial('CSRF check failed');
            return null;
        }
        $grant = $this->accessExchange->validateAdministrator($cookie, $slug, new DateTimeImmutable());

        if ($grant === null) {
            $this->logAdministratorDenial('session invalid or expired');
        }

        return $grant === null ? null : [$grant, $slug];
    }

    private function logAdministratorDenial(string $reason): void
    {
        if (PHP_SAPI !== 'cli') {
            error_log('[pulse] Administrator mutation denied: ' . $reason . '.');
        }
    }

    private function logAdminEventFailure(string $operation, string $reason): void
    {
        if (PHP_SAPI !== 'cli') {
            error_log('[pulse] Administrator event ' . $operation . ' failed: ' . $reason . '.');
        }
    }

    /** @param array<string, mixed> $body */
    private function eventDetails(array $body): EventDetails
    {
        return new EventDetails(
            (string) ($body['title'] ?? ''),
            $this->formDateFromBody($body, 'starts_at'),
            $this->formDateFromBody($body, 'ends_at', true),
            isset($body['location']) ? (string) $body['location'] : null,
            isset($body['note']) ? (string) $body['note'] : null,
        );
    }

    private function formDate(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, new DateTimeZone('Europe/Zurich'));
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException('Invalid local date.');
        }
        if ((int) $date->format('i') % 15 !== 0) {
            throw new InvalidArgumentException('Time must use 15-minute increments.');
        }

        return $date;
    }

    /** @param array<string, mixed> $body */
    private function formDateFromBody(array $body, string $name, bool $optional = false): ?DateTimeImmutable
    {
        if (array_key_exists($name, $body)) {
            $legacyValue = (string) $body[$name];
            if ($optional && $legacyValue === '') {
                return null;
            }

            return $this->formDate($legacyValue);
        }

        $date = trim((string) ($body[$name . '_date'] ?? ''));
        if ($date === '' && $optional) {
            return null;
        }
        $hour = (string) ($body[$name . '_hour'] ?? '');
        $minute = (string) ($body[$name . '_minute'] ?? '');
        if (preg_match('/^(?:[01][0-9]|2[0-3])$/', $hour) !== 1
            || !in_array($minute, ['00', '15', '30', '45'], true)
        ) {
            throw new InvalidArgumentException('Time must use 15-minute increments.');
        }

        return $this->formDate($date . 'T' . $hour . ':' . $minute);
    }

    private function invalidEventNotice(InvalidArgumentException $error): string
    {
        return match ($error->getMessage()) {
            'Event end must be later than event start.' => 'timing',
            'Time must use 15-minute increments.' => 'time_step',
            default => 'invalid',
        };
    }

    private function locale(ServerRequestInterface $request): string
    {
        $locale = (string) ($request->getQueryParams()['lang'] ?? 'de');

        return array_key_exists($locale, self::LANGUAGES) ? $locale : 'de';
    }

    /** @param array<string, mixed> $body */
    private function bodyLocale(array $body): string
    {
        $locale = (string) ($body['lang'] ?? 'de');

        return array_key_exists($locale, self::LANGUAGES) ? $locale : 'de';
    }

    private function redirectToEvents(ServerRequestInterface $request, ResponseInterface $response, string $slug): ResponseInterface
    {
        $body = $request->getParsedBody();
        $locale = is_array($body) && array_key_exists((string) ($body['lang'] ?? ''), self::LANGUAGES)
            ? (string) $body['lang']
            : 'de';

        return $response
            ->withHeader('Location', '/pulse/r/' . rawurlencode($slug) . '/events?lang=' . rawurlencode($locale))
            ->withStatus(303);
    }

    private function redirectToAdmin(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $slug,
        string $notice,
    ): ResponseInterface {
        $body = $request->getParsedBody();
        $locale = is_array($body) && array_key_exists((string) ($body['lang'] ?? ''), self::LANGUAGES)
            ? (string) $body['lang']
            : 'de';

        return $response
            ->withHeader(
                'Location',
                '/pulse/manage/r/' . rawurlencode($slug) . '/events?lang=' . rawurlencode($locale) . '&notice=' . rawurlencode($notice),
            )
            ->withStatus(303);
    }

    private function rsvpCookieName(string $publicEventId): string
    {
        return 'pulse_rsvp_' . strtolower(preg_replace('/[^a-f0-9]/i', '', $publicEventId) ?? '');
    }

    /** @param array<string, bool> $data */
    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
