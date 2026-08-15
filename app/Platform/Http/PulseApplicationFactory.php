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
        private BootstrapPage $bootstrapPage,
        private ParticipantPage $participantPage,
        private PulseLandingPage $landingPage,
        private AdminBootstrapPage $adminBootstrapPage,
        private AdminPage $adminPage,
        private PrivacyPage $privacyPage,
        private SameOriginGuard $originGuard,
        private CsrfToken $csrfToken,
        private string $translationDirectory,
        private string $groupName,
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
            $routes->get('/manage/r/{slug}/events', $this->adminEventList(...));
            $routes->post('/manage/r/{slug}/events', $this->createAdminEvent(...));
            $routes->post('/manage/r/{slug}/events/{event}', $this->mutateAdminEvent(...));
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
                $state === PublicationState::Scheduled ? $this->formDate((string) ($body['publish_at'] ?? '')) : null,
                $now,
            );
        } catch (InvalidArgumentException) {
            return $this->redirectToAdmin($request, $response, $slug, 'invalid');
        } catch (DomainException) {
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
                    $this->formDate((string) ($body['publish_at'] ?? '')),
                    $now,
                ),
                'cancel' => $this->adminEvents->cancel($grant, $eventId, $now),
                'close' => $this->adminEvents->closeRsvps($grant, $eventId, $now),
                'duplicate' => $this->adminEvents->duplicate($grant, $eventId, $now),
                'save' => null,
                default => throw new InvalidArgumentException('Unknown event operation.'),
            };
        } catch (InvalidArgumentException) {
            return $this->redirectToAdmin($request, $response, $slug, 'invalid');
        } catch (DomainException) {
            return $this->redirectToAdmin($request, $response, $slug, 'failed');
        }

        return $this->redirectToAdmin($request, $response, $slug, 'saved');
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
            return null;
        }
        $cookie = (string) ($request->getCookieParams()['pulse_admin'] ?? '');
        $body = $request->getParsedBody();
        $csrf = is_array($body) ? (string) ($body['csrf'] ?? '') : '';
        if (!$this->csrfToken->isValid($csrf, $cookie, $slug)) {
            return null;
        }
        $grant = $this->accessExchange->validateAdministrator($cookie, $slug, new DateTimeImmutable());

        return $grant === null ? null : [$grant, $slug];
    }

    /** @param array<string, mixed> $body */
    private function eventDetails(array $body): EventDetails
    {
        return new EventDetails(
            (string) ($body['title'] ?? ''),
            $this->formDate((string) ($body['starts_at'] ?? '')),
            ($body['ends_at'] ?? '') === '' ? null : $this->formDate((string) $body['ends_at']),
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

        return $date;
    }

    private function locale(ServerRequestInterface $request): string
    {
        $locale = (string) ($request->getQueryParams()['lang'] ?? 'de');

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
