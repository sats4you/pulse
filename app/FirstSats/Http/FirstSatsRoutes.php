<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Http;

use DateInterval;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sats4you\Pulse\FirstSats\Invitation\InvitationAccess;
use Sats4you\Pulse\FirstSats\Invitation\InvitationCreator;
use Sats4you\Pulse\FirstSats\Invitation\InvitationRecovery;
use Sats4you\Pulse\FirstSats\Invitation\InvitationLifecycle;
use Sats4you\Pulse\FirstSats\Invitation\InvitationInput;
use Sats4you\Pulse\FirstSats\Invitation\ParticipantJourney;
use Sats4you\Pulse\FirstSats\Learning\LearningJourney;
use Sats4you\Pulse\FirstSats\Wallet\WalletJourney;
use Sats4you\Pulse\FirstSats\Payment\Bolt11Inspector;
use Sats4you\Pulse\FirstSats\Payment\PaymentRequest;
use Sats4you\Pulse\FirstSats\Payment\PaymentRequestStore;
use Sats4you\Pulse\FirstSats\Payment\HandoverStore;
use Sats4you\Pulse\FirstSats\Metrics\MetricsRecorder;
use Sats4you\Pulse\Platform\Http\CookieHeader;
use Sats4you\Pulse\Platform\Http\CsrfToken;
use Sats4you\Pulse\Platform\Http\SameOriginGuard;
use Sats4you\Pulse\Platform\I18n\TranslatorFactory;
use Sats4you\Pulse\Platform\Security\SecretGenerator;
use Sats4you\Pulse\Pulse\PublicAccess\AccessRole;
use Slim\App;

final readonly class FirstSatsRoutes
{
    private const TERMS_VERSION = 'inviter-2026-08-30-v1';
    private const PARTICIPANT_TERMS_VERSION = 'participant-2026-08-30-v1';
    private const LEGAL_INFORMATION_VERSION = '2026-08-30-v1';
    private const LEGAL_INFORMATION_EFFECTIVE_DATE = '30.08.2026';
    private const LANGUAGES = [
        'de' => 'Deutsch',
        'fr' => 'Français',
        'it' => 'Italiano · In revisione dalla comunità',
        'rm' => 'Rumantsch Grischun · En revisiun da la communitad',
    ];

    /** @var array<string, string> */
    private array $languages;

    public function __construct(
        private InvitationCreator $creator,
        private InvitationRecovery $recovery,
        private InvitationLifecycle $lifecycle,
        private InvitationAccess $access,
        private ParticipantJourney $journey,
        private LearningJourney $learning,
        private WalletJourney $wallet,
        private Bolt11Inspector $bolt11,
        private PaymentRequestStore $payments,
        private HandoverStore $handovers,
        private FirstSatsPage $page,
        private SameOriginGuard $originGuard,
        private CsrfToken $csrf,
        private SecretGenerator $secrets,
        private string $translationDirectory,
        private string $baseUrl,
        private bool $secureCookies,
        private ?MetricsRecorder $metrics=null,
        array $enabledLocales=['de', 'fr', 'it', 'rm'],
    ) {
        $enabledLocales = array_values(array_unique($enabledLocales));
        $unknownLocales = array_diff($enabledLocales, array_keys(self::LANGUAGES));
        if ($unknownLocales !== []) {
            throw new InvalidArgumentException('Unbekannte Erste-Sats-Sprache: ' . implode(', ', $unknownLocales));
        }
        if (!in_array('de', $enabledLocales, true)) {
            throw new InvalidArgumentException('Deutsch muss als sichere Rückfallsprache aktiviert sein.');
        }

        $this->languages = array_intersect_key(self::LANGUAGES, array_flip($enabledLocales));
    }

    public function register(App $app): void
    {
        $app->get('/erste-sats', $this->form(...));
        $app->get('/erste-sats/', $this->form(...));
        $app->post('/erste-sats', $this->create(...));
        $app->get('/erste-sats/recover', $this->recoveryForm(...));
        $app->post('/erste-sats/recover', $this->recover(...));
        $app->get('/erste-sats/faq', fn ($req, $res) => $this->info($req, $res, 'info.faq_title', ['info.faq_1', 'info.faq_2', 'info.faq_3', 'info.faq_4']));
        $app->get('/erste-sats/chf-methodology', $this->chfMethodology(...));
        $app->get('/erste-sats/terms', fn ($req, $res) => $this->legalInfo($req, $res, 'terms', 9));
        $app->get('/erste-sats/privacy', fn ($req, $res) => $this->legalInfo($req, $res, 'privacy', 10));
        $app->get('/erste-sats/i/{id}', fn ($req, $res, $args) => $this->bootstrap($req, $res, $args, 'invitation'));
        $app->get('/erste-sats/manage/{id}', fn ($req, $res, $args) => $this->bootstrap($req, $res, $args, 'management'));
        $app->post('/erste-sats/api/access/{role}/{id}', $this->exchange(...));
        $app->get('/erste-sats/i/{id}/view', $this->invitation(...));
        $app->post('/erste-sats/i/{id}/accept', $this->accept(...));
        $app->post('/erste-sats/i/{id}/continuation', $this->continuation(...));
        $app->post('/erste-sats/i/{id}/end', $this->endParticipation(...));
        $app->get('/erste-sats/i/{id}/learn/1', $this->stageOne(...));
        $app->post('/erste-sats/i/{id}/learn/1', $this->answerStageOne(...));
        $app->get('/erste-sats/i/{id}/learn/1/survey', $this->fractionSurvey(...));
        $app->post('/erste-sats/i/{id}/learn/1/survey', $this->answerFractionSurvey(...));
        $app->get('/erste-sats/i/{id}/learn/2', $this->stageTwo(...));
        $app->post('/erste-sats/i/{id}/learn/2', $this->answerStageTwo(...));
        $app->get('/erste-sats/i/{id}/learn/3', $this->stageThree(...));
        $app->post('/erste-sats/i/{id}/learn/3', $this->answerStageThree(...));
        $app->get('/erste-sats/i/{id}/learn/4', $this->stageFour(...));
        $app->post('/erste-sats/i/{id}/learn/4', $this->answerStageFour(...));
        $app->get('/erste-sats/i/{id}/wallet', $this->walletChoice(...));
        $app->post('/erste-sats/i/{id}/wallet', $this->selectWallet(...));
        $app->get('/erste-sats/i/{id}/wallet/setup', $this->walletSetup(...));
        $app->get('/erste-sats/i/{id}/payment-request', $this->paymentRequest(...));
        $app->post('/erste-sats/i/{id}/payment-request', $this->submitPaymentRequest(...));
        $app->post('/erste-sats/i/{id}/received', fn($req,$res,$args)=>$this->participantConfirmation($req,$res,$args,true));
        $app->post('/erste-sats/i/{id}/not-received', fn($req,$res,$args)=>$this->participantConfirmation($req,$res,$args,false));
        $app->get('/erste-sats/i/{id}/next', $this->nextSteps(...));
        $app->post('/erste-sats/manage/{id}/sent', $this->confirmSent(...));
        $app->post('/erste-sats/manage/{id}/extend', $this->extend(...));
        $app->post('/erste-sats/manage/{id}/revoke', $this->revoke(...));
        $app->post('/erste-sats/manage/{id}/reset', $this->resetParticipation(...));
        $app->get('/erste-sats/manage/{id}/view', $this->management(...));
    }

    private function form(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $locale = $this->locale($request);
        $cookie = (string) ($request->getCookieParams()['first_sats_form'] ?? '');
        if ($cookie === '') {
            $cookie = $this->secrets->generate();
            $response = $response->withAddedHeader('Set-Cookie', CookieHeader::create(
                'first_sats_form', $cookie, '/erste-sats', (new DateTimeImmutable())->add(new DateInterval('PT2H')), $this->secureCookies,
            ));
        }
        $t = TranslatorFactory::create($locale, $this->translationDirectory);
        $response->getBody()->write($this->page->form($t, $locale, $this->languages, $this->csrf->issue($cookie, 'first-sats-create'), ['sats' => '5000']));

        return $this->html($response);
    }

    private function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = is_array($request->getParsedBody()) ? $request->getParsedBody() : [];
        $locale = $this->bodyLocale($body);
        $cookie = (string) ($request->getCookieParams()['first_sats_form'] ?? '');
        if (!$this->originGuard->allows($request->getHeaderLine('Origin'), $request->getHeaderLine('Sec-Fetch-Site'))
            || !$this->csrf->isValid((string) ($body['csrf'] ?? ''), $cookie, 'first-sats-create')) {
            return $this->renderError($response->withStatus(403), $locale, $cookie, $body, 'csrf');
        }
        $rawSats = preg_replace("/['’\\s]/u", '', (string) ($body['sats'] ?? '')) ?? '';
        if ($rawSats === '' || preg_match('/^[0-9]+$/', $rawSats) !== 1) {
            return $this->renderError($response->withStatus(422), $locale, $cookie, $body, 'invalid');
        }
        try {
            $created = $this->creator->create(new InvitationInput(
                $locale,
                (string) ($body['inviter'] ?? ''),
                (string) ($body['invitee'] ?? ''),
                null,
                (int) $rawSats,
                ($body['adult'] ?? '') === '1',
                ($body['terms'] ?? '') === '1',
                self::TERMS_VERSION,
                ($body['high_amount'] ?? '') === '1',
            ), new DateTimeImmutable());
        } catch (DomainException) {
            return $this->renderError($response->withStatus(422), $locale, $cookie, $body, 'invalid');
        }
        $id = rawurlencode($created->invitation->publicId);
        $inviteLink = $this->baseUrl . '/erste-sats/i/' . $id . '?lang=' . rawurlencode($locale) . '#' . rawurlencode($created->invitationSecret);
        $manageLink = $this->baseUrl . '/erste-sats/manage/' . $id . '?lang=' . rawurlencode($locale) . '#' . rawurlencode($created->managementSecret);
        $t = TranslatorFactory::create($locale, $this->translationDirectory);
        $this->metrics?->record($created->invitation->publicId,'created',new DateTimeImmutable());
        $response->getBody()->write($this->page->created($t, $locale, $this->languages, $created->invitation, $inviteLink, $manageLink, $created->recoveryCode));

        return $this->html($response);
    }

    private function recoveryForm(ServerRequestInterface $request,ResponseInterface $response):ResponseInterface
    {
        $locale=$this->locale($request);$cookie=(string)($request->getCookieParams()['first_sats_recovery']??'');
        if($cookie===''){$cookie=$this->secrets->generate();$response=$response->withAddedHeader('Set-Cookie',CookieHeader::create('first_sats_recovery',$cookie,'/erste-sats/recover',(new DateTimeImmutable())->add(new DateInterval('PT2H')),$this->secureCookies));}
        $t=TranslatorFactory::create($locale,$this->translationDirectory);
        $response->getBody()->write($this->page->recoveryForm($t,$locale,$this->languages,$this->csrf->issue($cookie,'first-sats-recovery')));
        return $this->html($response);
    }

    private function chfMethodology(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $locale = $this->locale($request);
        $t = TranslatorFactory::create($locale, $this->translationDirectory);
        $response->getBody()->write($this->page->chfMethodology($t, $locale, $this->languages));

        return $this->html($response);
    }

    private function recover(ServerRequestInterface $request,ResponseInterface $response):ResponseInterface
    {
        $body=is_array($request->getParsedBody())?$request->getParsedBody():[];$locale=$this->bodyLocale($body);$cookie=(string)($request->getCookieParams()['first_sats_recovery']??'');
        if(!$this->originGuard->allows($request->getHeaderLine('Origin'),$request->getHeaderLine('Sec-Fetch-Site'))||!$this->csrf->isValid((string)($body['csrf']??''),$cookie,'first-sats-recovery'))return $response->withStatus(403);
        $result=$this->recovery->recover((string)($body['recovery_code']??''),$now=new DateTimeImmutable());$t=TranslatorFactory::create($locale,$this->translationDirectory);
        if($result===null){$response->getBody()->write($this->page->recoveryForm($t,$locale,$this->languages,$this->csrf->issue($cookie,'first-sats-recovery'),true));return $this->html($response->withStatus(422));}
        $id=rawurlencode($result->invitation->publicId);$manageLink=$this->baseUrl.'/erste-sats/manage/'.$id.'?lang='.rawurlencode($locale).'#'.rawurlencode($result->managementSecret);
        $response->getBody()->write($this->page->recovered($t,$locale,$this->languages,$manageLink,$result->recoveryCode));
        return $this->html($response->withAddedHeader('Set-Cookie',CookieHeader::create('first_sats_management',$this->access->session($result->invitation,AccessRole::FirstSatsManagement,$now,'PT12H'),'/erste-sats/manage/'.$id,$now->add(new DateInterval('PT12H')),$this->secureCookies)));
    }

    /** @param array<string, string> $args */
    private function bootstrap(ServerRequestInterface $request, ResponseInterface $response, array $args, string $role): ResponseInterface
    {
        $locale = $this->locale($request);
        $publicId = (string) $args['id'];
        $accessRole = $role === 'management' ? AccessRole::FirstSatsManagement : AccessRole::FirstSatsInvitation;
        $cookieName = $role === 'management' ? 'first_sats_management' : 'first_sats_invitation';
        $cookie = (string) ($request->getCookieParams()[$cookieName] ?? '');
        if ($cookie !== '' && $this->access->authorise($publicId, $cookie, $accessRole, new DateTimeImmutable()) !== null) {
            $target = $role === 'management'
                ? '/erste-sats/manage/' . rawurlencode($publicId) . '/view'
                : '/erste-sats/i/' . rawurlencode($publicId) . '/view';

            return $response->withHeader('Location', $target . '?lang=' . rawurlencode($locale))->withStatus(303);
        }
        $t = TranslatorFactory::create($locale, $this->translationDirectory);
        $response->getBody()->write($this->page->bootstrap($t, $locale, $this->languages, $publicId, $role));

        return $this->html($response);
    }

    /** @param array<string, string> $args */
    private function exchange(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if (!$this->originGuard->allows($request->getHeaderLine('Origin'), $request->getHeaderLine('Sec-Fetch-Site'))) {
            return $response->withStatus(403);
        }
        $role = match ((string) $args['role']) {
            'invitation' => AccessRole::FirstSatsInvitation,
            'management' => AccessRole::FirstSatsManagement,
            default => null,
        };
        if ($role === null) {
            return $response->withStatus(404);
        }
        $body = is_array($request->getParsedBody()) ? $request->getParsedBody() : [];
        $cookie = $this->access->exchange((string) $args['id'], (string) ($body['secret'] ?? ''), $role, new DateTimeImmutable());
        if ($cookie === null) {
            return $response->withStatus(403);
        }
        if($role===AccessRole::FirstSatsInvitation)$this->metrics?->record((string)$args['id'],'opened',new DateTimeImmutable());
        $name = $role === AccessRole::FirstSatsInvitation ? 'first_sats_invitation' : 'first_sats_management';
        $path = $role === AccessRole::FirstSatsInvitation ? '/erste-sats/i/' : '/erste-sats/manage/';
        $response = $response->withAddedHeader('Set-Cookie', CookieHeader::create($name, $cookie, $path . rawurlencode((string) $args['id']), (new DateTimeImmutable())->add(new DateInterval($role === AccessRole::FirstSatsInvitation ? 'P30D' : 'PT12H')), $this->secureCookies));
        $response->getBody()->write('{"ok":true}');

        return $response->withHeader('Content-Type', 'application/json');
    }

    /** @param array<string, string> $args */
    private function invitation(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $cookie = (string) ($request->getCookieParams()['first_sats_invitation'] ?? '');
        $i = $this->access->authorise((string) $args['id'], $cookie, AccessRole::FirstSatsInvitation, new DateTimeImmutable());
        if ($i === null) return $response->withStatus(403);
        $locale = $this->locale($request);
        $t = TranslatorFactory::create($locale, $this->translationDirectory);
        $token = $this->csrf->issue($cookie, $this->participantAction($i->publicId));
        $response->getBody()->write($i->status === 'accepted'
            ? $this->page->participantHome(
                $t, $locale, $this->languages, $i, $token,
                $this->payments->find($i->id), $this->handovers->find($i->id),
            )
            : $this->page->invitation($t, $locale, $this->languages, $i, $token));
        return $this->html($response);
    }

    /** @param array<string, string> $args */
    private function accept(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (string) $args['id'];
        $cookie = (string) ($request->getCookieParams()['first_sats_invitation'] ?? '');
        $now = new DateTimeImmutable();
        $i = $this->access->authorise($id, $cookie, AccessRole::FirstSatsInvitation, $now);
        if ($i === null) return $response->withStatus(403);
        $body = is_array($request->getParsedBody()) ? $request->getParsedBody() : [];
        $locale = $this->bodyLocale($body);
        if (!$this->originGuard->allows($request->getHeaderLine('Origin'), $request->getHeaderLine('Sec-Fetch-Site'))
            || !$this->csrf->isValid((string) ($body['csrf'] ?? ''), $cookie, $this->participantAction($id))) {
            return $response->withStatus(403);
        }
        try {
            $accepted = $this->journey->accept(
                $i,
                ($body['adult'] ?? '') === '1',
                ($body['terms'] ?? '') === '1',
                self::PARTICIPANT_TERMS_VERSION,
                $now,
            );
        } catch (DomainException) {
            $t = TranslatorFactory::create($locale, $this->translationDirectory);
            $response->getBody()->write($this->page->invitation(
                $t, $locale, $this->languages, $i,
                $this->csrf->issue($cookie, $this->participantAction($id)), true,
            ));
            return $this->html($response->withStatus(422));
        }
        if ($accepted === null) return $response->withStatus(409);
        $this->metrics?->record($accepted->publicId,'started',$now);

        $newCookie = $this->access->session($accepted, AccessRole::FirstSatsInvitation, $now);
        return $response
            ->withAddedHeader('Set-Cookie', CookieHeader::create(
                'first_sats_invitation', $newCookie, '/erste-sats/i/' . rawurlencode($id),
                $now->add(new DateInterval('P30D')), $this->secureCookies,
            ))
            ->withHeader('Location', '/erste-sats/i/' . rawurlencode($id) . '/view?lang=' . rawurlencode($locale))
            ->withStatus(303);
    }

    /** @param array<string, string> $args */
    private function continuation(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (string) $args['id'];
        $cookie = (string) ($request->getCookieParams()['first_sats_invitation'] ?? '');
        $now = new DateTimeImmutable();
        $i = $this->access->authorise($id, $cookie, AccessRole::FirstSatsInvitation, $now);
        if ($i === null || $i->status !== 'accepted') return $response->withStatus(403);
        $body = is_array($request->getParsedBody()) ? $request->getParsedBody() : [];
        if (!$this->originGuard->allows($request->getHeaderLine('Origin'), $request->getHeaderLine('Sec-Fetch-Site'))
            || !$this->csrf->isValid((string) ($body['csrf'] ?? ''), $cookie, $this->participantAction($id))) {
            return $response->withStatus(403);
        }
        $credentials = $this->journey->createContinuation($i, $now);
        if ($credentials === null) return $response->withStatus(409);
        $newCookie = $this->access->session($credentials->invitation, AccessRole::FirstSatsInvitation, $now);
        $locale = $this->locale($request);
        $link = $this->baseUrl . '/erste-sats/i/' . rawurlencode($id) . '?lang=' . rawurlencode($locale) . '#' . rawurlencode($credentials->secret);
        $response->getBody()->write(json_encode(['link' => $link], JSON_THROW_ON_ERROR));

        return $response
            ->withAddedHeader('Set-Cookie', CookieHeader::create(
                'first_sats_invitation', $newCookie, '/erste-sats/i/' . rawurlencode($id),
                $now->add(new DateInterval('P30D')), $this->secureCookies,
            ))
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'private, no-store');
    }

    /** @param array<string,string> $args */
    private function endParticipation(ServerRequestInterface $request,ResponseInterface $response,array $args):ResponseInterface
    {
        $id=(string)$args['id'];$cookie=(string)($request->getCookieParams()['first_sats_invitation']??'');$now=new DateTimeImmutable();
        $i=$this->access->authorise($id,$cookie,AccessRole::FirstSatsInvitation,$now);
        if($i===null||$i->status!=='accepted'||$i->stageFourCompletedAt!==null)return $response->withStatus(403);
        $body=is_array($request->getParsedBody())?$request->getParsedBody():[];$locale=$this->bodyLocale($body);
        if(!$this->originGuard->allows($request->getHeaderLine('Origin'),$request->getHeaderLine('Sec-Fetch-Site'))||!$this->csrf->isValid((string)($body['csrf']??''),$cookie,$this->participantAction($id)))return $response->withStatus(403);
        if(($body['confirm_end']??'')!=='1')return $response->withStatus(422);
        if($this->journey->end($i,$now)===null)return $response->withStatus(409);
        $this->metrics?->record($i->publicId,'participant_ended',$now);
        $t=TranslatorFactory::create($locale,$this->translationDirectory);
        $response->getBody()->write($this->page->participationEnded($t,$locale,$this->languages));
        return $this->html($response->withAddedHeader('Set-Cookie',CookieHeader::expire('first_sats_invitation','/erste-sats/i/'.rawurlencode($id),$this->secureCookies)));
    }

    /** @param array<string, string> $args */
    private function stageOne(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (string) $args['id']; $cookie = (string) ($request->getCookieParams()['first_sats_invitation'] ?? '');
        $i = $this->access->authorise($id, $cookie, AccessRole::FirstSatsInvitation, new DateTimeImmutable());
        if ($i === null || $i->status !== 'accepted') return $response->withStatus(403);
        $locale = $this->locale($request);
        if ($i->stageOneCompletedAt !== null) {
            $target = $i->fractionSurveyState === 'pending' ? '/learn/1/survey' : '/view';
            return $response->withHeader('Location', '/erste-sats/i/' . rawurlencode($id) . $target . '?lang=' . rawurlencode($locale))->withStatus(303);
        }
        $t = TranslatorFactory::create($locale, $this->translationDirectory);
        $response->getBody()->write($this->page->stageOne($t, $locale, $this->languages, $i, $this->csrf->issue($cookie, $this->learningAction($id))));
        return $this->html($response);
    }

    /** @param array<string, string> $args */
    private function answerStageOne(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (string) $args['id']; $cookie = (string) ($request->getCookieParams()['first_sats_invitation'] ?? '');
        $i = $this->access->authorise($id, $cookie, AccessRole::FirstSatsInvitation, new DateTimeImmutable());
        if ($i === null || $i->status !== 'accepted') return $response->withStatus(403);
        $body = is_array($request->getParsedBody()) ? $request->getParsedBody() : []; $locale = $this->bodyLocale($body);
        if (!$this->originGuard->allows($request->getHeaderLine('Origin'), $request->getHeaderLine('Sec-Fetch-Site'))
            || !$this->csrf->isValid((string) ($body['csrf'] ?? ''), $cookie, $this->learningAction($id))) return $response->withStatus(403);
        $result = $this->learning->answerStageOne($i, (string) ($body['answer'] ?? ''), new DateTimeImmutable());
        if (in_array($result, ['correct', 'completed'], true)) {
            return $response->withHeader('Location', '/erste-sats/i/' . rawurlencode($id) . '/learn/1/survey?lang=' . rawurlencode($locale))->withStatus(303);
        }
        if ($result === 'conflict') return $response->withStatus(409);
        $t = TranslatorFactory::create($locale, $this->translationDirectory);
        $response->getBody()->write($this->page->stageOne($t, $locale, $this->languages, $i, $this->csrf->issue($cookie, $this->learningAction($id)), true));
        return $this->html($response->withStatus(422));
    }

    /** @param array<string, string> $args */
    private function fractionSurvey(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (string) $args['id']; $cookie = (string) ($request->getCookieParams()['first_sats_invitation'] ?? '');
        $i = $this->access->authorise($id, $cookie, AccessRole::FirstSatsInvitation, new DateTimeImmutable());
        if ($i === null || $i->status !== 'accepted' || $i->stageOneCompletedAt === null) return $response->withStatus(403);
        $locale = $this->locale($request);
        if ($i->fractionSurveyState !== 'pending') return $response->withHeader('Location', '/erste-sats/i/' . rawurlencode($id) . '/view?lang=' . rawurlencode($locale))->withStatus(303);
        $t = TranslatorFactory::create($locale, $this->translationDirectory);
        $response->getBody()->write($this->page->fractionSurvey($t, $locale, $this->languages, $i, $this->csrf->issue($cookie, $this->learningAction($id))));
        return $this->html($response);
    }

    /** @param array<string, string> $args */
    private function answerFractionSurvey(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (string) $args['id']; $cookie = (string) ($request->getCookieParams()['first_sats_invitation'] ?? '');
        $i = $this->access->authorise($id, $cookie, AccessRole::FirstSatsInvitation, new DateTimeImmutable());
        if ($i === null || $i->status !== 'accepted' || $i->stageOneCompletedAt === null) return $response->withStatus(403);
        $body = is_array($request->getParsedBody()) ? $request->getParsedBody() : []; $locale = $this->bodyLocale($body);
        if (!$this->originGuard->allows($request->getHeaderLine('Origin'), $request->getHeaderLine('Sec-Fetch-Site'))
            || !$this->csrf->isValid((string) ($body['csrf'] ?? ''), $cookie, $this->learningAction($id))) return $response->withStatus(403);
        $ok = ($body['action'] ?? '') === 'skip'
            ? $this->learning->skipFractionSurvey($i, new DateTimeImmutable())
            : $this->learning->answerFractionSurvey($i, (string) ($body['answer'] ?? ''), new DateTimeImmutable());
        if (!$ok) return $response->withStatus(422);
        return $response->withHeader('Location', '/erste-sats/i/' . rawurlencode($id) . '/view?lang=' . rawurlencode($locale))->withStatus(303);
    }

    /** @param array<string, string> $args */
    private function stageTwo(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (string) $args['id']; $cookie = (string) ($request->getCookieParams()['first_sats_invitation'] ?? '');
        $i = $this->access->authorise($id, $cookie, AccessRole::FirstSatsInvitation, new DateTimeImmutable());
        if ($i === null || $i->status !== 'accepted' || $i->stageOneCompletedAt === null) return $response->withStatus(403);
        $locale = $this->locale($request);
        if ($i->fractionSurveyState === 'pending') return $response->withHeader('Location', '/erste-sats/i/' . rawurlencode($id) . '/learn/1/survey?lang=' . rawurlencode($locale))->withStatus(303);
        if ($i->stageTwoCompletedAt !== null) return $response->withHeader('Location', '/erste-sats/i/' . rawurlencode($id) . '/view?lang=' . rawurlencode($locale))->withStatus(303);
        $t = TranslatorFactory::create($locale, $this->translationDirectory);
        $response->getBody()->write($this->page->stageTwo($t, $locale, $this->languages, $i, $this->csrf->issue($cookie, $this->learningAction($id))));
        return $this->html($response);
    }

    /** @param array<string, string> $args */
    private function answerStageTwo(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (string) $args['id']; $cookie = (string) ($request->getCookieParams()['first_sats_invitation'] ?? '');
        $i = $this->access->authorise($id, $cookie, AccessRole::FirstSatsInvitation, new DateTimeImmutable());
        if ($i === null || $i->status !== 'accepted' || $i->stageOneCompletedAt === null || $i->fractionSurveyState === 'pending') return $response->withStatus(403);
        $body = is_array($request->getParsedBody()) ? $request->getParsedBody() : []; $locale = $this->bodyLocale($body);
        if (!$this->originGuard->allows($request->getHeaderLine('Origin'), $request->getHeaderLine('Sec-Fetch-Site'))
            || !$this->csrf->isValid((string) ($body['csrf'] ?? ''), $cookie, $this->learningAction($id))) return $response->withStatus(403);
        $result = $this->learning->answerStageTwo($i, (string) ($body['answer'] ?? ''), new DateTimeImmutable());
        if (in_array($result, ['correct', 'completed'], true)) return $response->withHeader('Location', '/erste-sats/i/' . rawurlencode($id) . '/view?lang=' . rawurlencode($locale))->withStatus(303);
        if (in_array($result, ['conflict', 'locked'], true)) return $response->withStatus($result === 'locked' ? 403 : 409);
        $t = TranslatorFactory::create($locale, $this->translationDirectory);
        $response->getBody()->write($this->page->stageTwo($t, $locale, $this->languages, $i, $this->csrf->issue($cookie, $this->learningAction($id)), true));
        return $this->html($response->withStatus(422));
    }

    /** @param array<string, string> $args */
    private function stageThree(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (string) $args['id']; $cookie = (string) ($request->getCookieParams()['first_sats_invitation'] ?? '');
        $i = $this->access->authorise($id, $cookie, AccessRole::FirstSatsInvitation, new DateTimeImmutable());
        if ($i === null || $i->status !== 'accepted' || $i->stageTwoCompletedAt === null) return $response->withStatus(403);
        $locale = $this->locale($request);
        if ($i->stageThreeCompletedAt !== null) return $response->withHeader('Location', '/erste-sats/i/' . rawurlencode($id) . '/view?lang=' . rawurlencode($locale))->withStatus(303);
        $t = TranslatorFactory::create($locale, $this->translationDirectory);
        $response->getBody()->write($this->page->stageThree($t, $locale, $this->languages, $i, $this->csrf->issue($cookie, $this->learningAction($id))));
        return $this->html($response);
    }

    /** @param array<string, string> $args */
    private function answerStageThree(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (string) $args['id']; $cookie = (string) ($request->getCookieParams()['first_sats_invitation'] ?? '');
        $i = $this->access->authorise($id, $cookie, AccessRole::FirstSatsInvitation, new DateTimeImmutable());
        if ($i === null || $i->status !== 'accepted' || $i->stageTwoCompletedAt === null) return $response->withStatus(403);
        $body = is_array($request->getParsedBody()) ? $request->getParsedBody() : []; $locale = $this->bodyLocale($body);
        if (!$this->originGuard->allows($request->getHeaderLine('Origin'), $request->getHeaderLine('Sec-Fetch-Site'))
            || !$this->csrf->isValid((string) ($body['csrf'] ?? ''), $cookie, $this->learningAction($id))) return $response->withStatus(403);
        $result = $this->learning->answerStageThree($i, (string) ($body['answer'] ?? ''), new DateTimeImmutable());
        if (in_array($result, ['correct', 'completed'], true)) return $response->withHeader('Location', '/erste-sats/i/' . rawurlencode($id) . '/view?lang=' . rawurlencode($locale))->withStatus(303);
        if (in_array($result, ['conflict', 'locked'], true)) return $response->withStatus($result === 'locked' ? 403 : 409);
        $t = TranslatorFactory::create($locale, $this->translationDirectory);
        $response->getBody()->write($this->page->stageThree($t, $locale, $this->languages, $i, $this->csrf->issue($cookie, $this->learningAction($id)), true));
        return $this->html($response->withStatus(422));
    }

    /** @param array<string, string> $args */
    private function stageFour(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (string) $args['id']; $cookie = (string) ($request->getCookieParams()['first_sats_invitation'] ?? '');
        $i = $this->access->authorise($id, $cookie, AccessRole::FirstSatsInvitation, new DateTimeImmutable());
        if ($i === null || $i->status !== 'accepted' || $i->stageThreeCompletedAt === null) return $response->withStatus(403);
        $locale = $this->locale($request);
        if ($i->stageFourCompletedAt !== null) return $response->withHeader('Location', '/erste-sats/i/' . rawurlencode($id) . '/view?lang=' . rawurlencode($locale))->withStatus(303);
        $t = TranslatorFactory::create($locale, $this->translationDirectory);
        $response->getBody()->write($this->page->stageFour($t, $locale, $this->languages, $i, $this->csrf->issue($cookie, $this->learningAction($id))));
        return $this->html($response);
    }

    /** @param array<string, string> $args */
    private function answerStageFour(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (string) $args['id']; $cookie = (string) ($request->getCookieParams()['first_sats_invitation'] ?? '');
        $i = $this->access->authorise($id, $cookie, AccessRole::FirstSatsInvitation, new DateTimeImmutable());
        if ($i === null || $i->status !== 'accepted' || $i->stageThreeCompletedAt === null) return $response->withStatus(403);
        $body = is_array($request->getParsedBody()) ? $request->getParsedBody() : []; $locale = $this->bodyLocale($body);
        if (!$this->originGuard->allows($request->getHeaderLine('Origin'), $request->getHeaderLine('Sec-Fetch-Site'))
            || !$this->csrf->isValid((string) ($body['csrf'] ?? ''), $cookie, $this->learningAction($id))) return $response->withStatus(403);
        $result = $this->learning->answerStageFour($i, (string) ($body['answer'] ?? ''), new DateTimeImmutable());
        if (in_array($result, ['correct', 'completed'], true)) {$this->metrics?->record($i->publicId,'completed',new DateTimeImmutable());return $response->withHeader('Location', '/erste-sats/i/' . rawurlencode($id) . '/view?lang=' . rawurlencode($locale))->withStatus(303);}
        if (in_array($result, ['conflict', 'locked'], true)) return $response->withStatus($result === 'locked' ? 403 : 409);
        $t = TranslatorFactory::create($locale, $this->translationDirectory);
        $response->getBody()->write($this->page->stageFour($t, $locale, $this->languages, $i, $this->csrf->issue($cookie, $this->learningAction($id)), true));
        return $this->html($response->withStatus(422));
    }

    /** @param array<string, string> $args */
    private function walletChoice(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (string) $args['id']; $cookie = (string) ($request->getCookieParams()['first_sats_invitation'] ?? '');
        $i = $this->access->authorise($id, $cookie, AccessRole::FirstSatsInvitation, new DateTimeImmutable());
        if ($i === null || $i->status !== 'accepted' || $i->satsAmount === 0 || $i->stageFourCompletedAt === null) return $response->withStatus(403);
        $locale = $this->locale($request); $t = TranslatorFactory::create($locale, $this->translationDirectory);
        $response->getBody()->write($this->page->walletChoice($t, $locale, $this->languages, $i, $this->csrf->issue($cookie, $this->walletAction($id))));
        return $this->html($response);
    }

    /** @param array<string, string> $args */
    private function selectWallet(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (string) $args['id']; $cookie = (string) ($request->getCookieParams()['first_sats_invitation'] ?? '');
        $i = $this->access->authorise($id, $cookie, AccessRole::FirstSatsInvitation, new DateTimeImmutable());
        if ($i === null || $i->status !== 'accepted' || $i->satsAmount === 0 || $i->stageFourCompletedAt === null) return $response->withStatus(403);
        $body = is_array($request->getParsedBody()) ? $request->getParsedBody() : []; $locale = $this->bodyLocale($body);
        if (!$this->originGuard->allows($request->getHeaderLine('Origin'), $request->getHeaderLine('Sec-Fetch-Site'))
            || !$this->csrf->isValid((string) ($body['csrf'] ?? ''), $cookie, $this->walletAction($id))) return $response->withStatus(403);
        if (!$this->wallet->select($i, (string) ($body['wallet'] ?? ''), new DateTimeImmutable())) return $response->withStatus(422);
        return $response->withHeader('Location', '/erste-sats/i/' . rawurlencode($id) . '/wallet/setup?lang=' . rawurlencode($locale))->withStatus(303);
    }

    /** @param array<string, string> $args */
    private function walletSetup(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (string) $args['id']; $cookie = (string) ($request->getCookieParams()['first_sats_invitation'] ?? '');
        $i = $this->access->authorise($id, $cookie, AccessRole::FirstSatsInvitation, new DateTimeImmutable());
        if ($i === null || $i->status !== 'accepted' || $i->satsAmount === 0 || $i->stageFourCompletedAt === null) return $response->withStatus(403);
        $locale = $this->locale($request);
        if ($i->walletPath === null) return $response->withHeader('Location', '/erste-sats/i/' . rawurlencode($id) . '/wallet?lang=' . rawurlencode($locale))->withStatus(303);
        $t = TranslatorFactory::create($locale, $this->translationDirectory);
        $response->getBody()->write($this->page->walletSetup($t, $locale, $this->languages, $i));
        return $this->html($response);
    }

    /** @param array<string, string> $args */
    private function paymentRequest(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id=(string)$args['id']; $cookie=(string)($request->getCookieParams()['first_sats_invitation']??''); $i=$this->access->authorise($id,$cookie,AccessRole::FirstSatsInvitation,new DateTimeImmutable());
        if($i===null||$i->status!=='accepted'||$i->satsAmount===0||$i->stageFourCompletedAt===null||$i->walletPath===null)return $response->withStatus(403);
        $locale=$this->locale($request);$t=TranslatorFactory::create($locale,$this->translationDirectory);
        $response->getBody()->write($this->page->paymentRequest($t,$locale,$this->languages,$i,$this->csrf->issue($cookie,$this->walletAction($id)),$this->payments->find($i->id),$this->handovers->find($i->id)));
        return $this->html($response);
    }

    /** @param array<string, string> $args */
    private function submitPaymentRequest(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id=(string)$args['id'];$cookie=(string)($request->getCookieParams()['first_sats_invitation']??'');$now=new DateTimeImmutable();$i=$this->access->authorise($id,$cookie,AccessRole::FirstSatsInvitation,$now);
        if($i===null||$i->status!=='accepted'||$i->satsAmount===0||$i->stageFourCompletedAt===null||$i->walletPath===null)return $response->withStatus(403);
        if($this->handovers->find($i->id)?->sentAt!==null)return $response->withStatus(409);
        $body=is_array($request->getParsedBody())?$request->getParsedBody():[];$locale=$this->bodyLocale($body);
        if(!$this->originGuard->allows($request->getHeaderLine('Origin'),$request->getHeaderLine('Sec-Fetch-Site'))||!$this->csrf->isValid((string)($body['csrf']??''),$cookie,$this->walletAction($id)))return $response->withStatus(403);
        try{$parsed=$this->bolt11->inspect((string)($body['invoice']??''),$i->satsAmount,$now);$this->payments->save(new PaymentRequest($i->id,$parsed->bolt11,$parsed->satsAmount,$parsed->expiresAt,$now));$this->metrics?->record($i->publicId,'payment_requested',$now,$i->walletPath);}
        catch(DomainException $e){$t=TranslatorFactory::create($locale,$this->translationDirectory);$response->getBody()->write($this->page->paymentRequest($t,$locale,$this->languages,$i,$this->csrf->issue($cookie,$this->walletAction($id)),$this->payments->find($i->id),$this->handovers->find($i->id),$e->getMessage(),(string)($body['invoice']??'')));return $this->html($response->withStatus(422));}
        return $response->withHeader('Location','/erste-sats/i/'.rawurlencode($id).'/payment-request?lang='.rawurlencode($locale).'&saved=1')->withStatus(303);
    }

    /** @param array<string,string> $args */
    private function participantConfirmation(ServerRequestInterface $request,ResponseInterface $response,array $args,bool $received):ResponseInterface
    {
        $id=(string)$args['id'];$cookie=(string)($request->getCookieParams()['first_sats_invitation']??'');$i=$this->access->authorise($id,$cookie,AccessRole::FirstSatsInvitation,new DateTimeImmutable());
        if($i===null||$i->satsAmount===0)return $response->withStatus(403);
        $body=is_array($request->getParsedBody())?$request->getParsedBody():[];$locale=$this->bodyLocale($body);
        if(!$this->originGuard->allows($request->getHeaderLine('Origin'),$request->getHeaderLine('Sec-Fetch-Site'))||!$this->csrf->isValid((string)($body['csrf']??''),$cookie,$this->walletAction($id)))return $response->withStatus(403);
        $handover=$this->handovers->find($i->id);
        if($handover?->closedWithoutConfirmationAt!==null)return $response->withStatus(409);
        if($handover?->receivedAt!==null)return $response->withHeader('Location','/erste-sats/i/'.rawurlencode($id).'/payment-request?lang='.rawurlencode($locale))->withStatus(303);
        if($this->payments->find($i->id)===null)return $response->withStatus(403);
        if($received&&($body['receipt_confirmed']??'')!=='1')return $response->withStatus(422);
        if($received){$this->handovers->confirmReceived($i->id,$at=new DateTimeImmutable());$this->metrics?->record($i->publicId,'received_confirmed',$at);}else $this->handovers->markNotReceived($i->id,new DateTimeImmutable());
        return $response->withHeader('Location','/erste-sats/i/'.rawurlencode($id).'/payment-request?lang='.rawurlencode($locale))->withStatus(303);
    }

    /** @param array<string,string> $args */
    private function confirmSent(ServerRequestInterface $request,ResponseInterface $response,array $args):ResponseInterface
    {
        $id=(string)$args['id'];$cookie=(string)($request->getCookieParams()['first_sats_management']??'');$now=new DateTimeImmutable();$i=$this->access->authorise($id,$cookie,AccessRole::FirstSatsManagement,$now);
        if($i===null)return $response->withStatus(403);if($this->handovers->find($i->id)?->closedWithoutConfirmationAt!==null)return $response->withStatus(409);$payment=$this->payments->find($i->id);if($payment===null)return $response->withStatus(403);if($payment->expiresAt<=$now)return $response->withStatus(409);$body=is_array($request->getParsedBody())?$request->getParsedBody():[];$locale=$this->bodyLocale($body);
        if(!$this->originGuard->allows($request->getHeaderLine('Origin'),$request->getHeaderLine('Sec-Fetch-Site'))||!$this->csrf->isValid((string)($body['csrf']??''),$cookie,$this->managementAction($id)))return $response->withStatus(403);
        $this->handovers->markSent($i->id,$at=new DateTimeImmutable());$this->metrics?->record($i->publicId,'sent_confirmed',$at);return $response->withHeader('Location','/erste-sats/manage/'.rawurlencode($id).'/view?lang='.rawurlencode($locale))->withStatus(303);
    }

    /** @param array<string,string> $args */
    private function nextSteps(ServerRequestInterface $request,ResponseInterface $response,array $args):ResponseInterface
    {
        $id=(string)$args['id'];$cookie=(string)($request->getCookieParams()['first_sats_invitation']??'');$i=$this->access->authorise($id,$cookie,AccessRole::FirstSatsInvitation,new DateTimeImmutable());
        if($i===null||$i->stageFourCompletedAt===null)return $response->withStatus(403);
        if($i->satsAmount>0&&$this->handovers->find($i->id)?->receivedAt===null)return $response->withStatus(403);
        $locale=$this->locale($request);$t=TranslatorFactory::create($locale,$this->translationDirectory);
        $response->getBody()->write($this->page->nextSteps($t,$locale,$this->languages,$i));
        return $this->html($response);
    }

    /** @param array<string, string> $args */
    private function management(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $i = $this->access->authorise((string) $args['id'], (string) ($request->getCookieParams()['first_sats_management'] ?? ''), AccessRole::FirstSatsManagement, new DateTimeImmutable());
        if ($i === null) return $response->withStatus(403);
        $locale = $this->locale($request);
        $t = TranslatorFactory::create($locale, $this->translationDirectory);
        $cookie=(string)($request->getCookieParams()['first_sats_management']??'');
        $response->getBody()->write($this->page->management($t,$locale,$this->languages,$i,$this->payments->find($i->id),$this->handovers->find($i->id),$this->csrf->issue($cookie,$this->managementAction($i->publicId))));
        return $this->html($response);
    }

    /** @param array<string,string> $args */
    private function extend(ServerRequestInterface $request,ResponseInterface $response,array $args):ResponseInterface
    {
        [$i,$body,$locale]=$this->managementMutation($request,$args);if($i===null)return $response->withStatus(403);
        if($this->lifecycle->extend($i,new DateTimeImmutable())===null)return $response->withStatus(409);
        return $response->withHeader('Location','/erste-sats/manage/'.rawurlencode($i->publicId).'/view?lang='.rawurlencode($locale))->withStatus(303);
    }

    /** @param array<string,string> $args */
    private function revoke(ServerRequestInterface $request,ResponseInterface $response,array $args):ResponseInterface
    {
        [$i,$body,$locale]=$this->managementMutation($request,$args);if($i===null)return $response->withStatus(403);if(($body['confirm_revoke']??'')!=='1')return $response->withStatus(422);
        if($this->lifecycle->revoke($i,new DateTimeImmutable())===null)return $response->withStatus(409);
        return $response->withHeader('Location','/erste-sats/manage/'.rawurlencode($i->publicId).'/view?lang='.rawurlencode($locale))->withStatus(303);
    }

    /** @param array<string,string> $args */
    private function resetParticipation(ServerRequestInterface $request,ResponseInterface $response,array $args):ResponseInterface
    {
        [$i,$body,$locale]=$this->managementMutation($request,$args);if($i===null)return $response->withStatus(403);if(($body['confirm_reset']??'')!=='1')return $response->withStatus(422);
        if($this->payments->find($i->id)!==null||$this->handovers->find($i->id)!==null)return $response->withStatus(409);
        $reset=$this->lifecycle->reset($i,new DateTimeImmutable());if($reset===null)return $response->withStatus(409);
        $inviteLink=$this->baseUrl.'/erste-sats/i/'.rawurlencode($i->publicId).'?lang='.rawurlencode($locale).'#'.rawurlencode($reset->invitationSecret);$t=TranslatorFactory::create($locale,$this->translationDirectory);
        $response->getBody()->write($this->page->resetDone($t,$locale,$this->languages,$reset->invitation,$inviteLink));return $this->html($response);
    }

    /** @param array<string,string> $args @return array{?\Sats4you\Pulse\FirstSats\Invitation\Invitation,array<string,mixed>,string} */
    private function managementMutation(ServerRequestInterface $request,array $args):array
    {
        $id=(string)$args['id'];$cookie=(string)($request->getCookieParams()['first_sats_management']??'');$i=$this->access->authorise($id,$cookie,AccessRole::FirstSatsManagement,new DateTimeImmutable());$body=is_array($request->getParsedBody())?$request->getParsedBody():[];$locale=$this->bodyLocale($body);
        if($i===null||!$this->originGuard->allows($request->getHeaderLine('Origin'),$request->getHeaderLine('Sec-Fetch-Site'))||!$this->csrf->isValid((string)($body['csrf']??''),$cookie,$this->managementAction($id)))return [null,$body,$locale];
        return [$i,$body,$locale];
    }

    /** @param list<string> $paragraphKeys */
    private function info(ServerRequestInterface $request, ResponseInterface $response, string $titleKey, array $paragraphKeys): ResponseInterface
    {
        $locale = $this->locale($request); $t = TranslatorFactory::create($locale, $this->translationDirectory);
        $response->getBody()->write($this->page->information($t, $locale, $this->languages, $t->trans($titleKey), array_map($t->trans(...), $paragraphKeys)));
        return $this->html($response);
    }

    private function legalInfo(ServerRequestInterface $request, ResponseInterface $response, string $document, int $sectionCount): ResponseInterface
    {
        $locale = $this->locale($request);
        $t = TranslatorFactory::create($locale, $this->translationDirectory);
        $response->getBody()->write($this->page->legalInformation(
            $t,
            $locale,
            $this->languages,
            $document,
            $sectionCount,
            self::LEGAL_INFORMATION_VERSION,
            self::LEGAL_INFORMATION_EFFECTIVE_DATE,
        ));

        return $this->html($response);
    }

    /** @param array<string, mixed> $body */
    private function renderError(ResponseInterface $response, string $locale, string $cookie, array $body, string $error): ResponseInterface
    {
        $t = TranslatorFactory::create($locale, $this->translationDirectory);
        $values = array_map(static fn ($value): string => is_scalar($value) ? (string) $value : '', $body);
        $response->getBody()->write($this->page->form($t, $locale, $this->languages, $this->csrf->issue($cookie, 'first-sats-create'), $values, $error));
        return $this->html($response);
    }

    private function locale(ServerRequestInterface $request): string { return $this->validLocale((string) ($request->getQueryParams()['lang'] ?? 'de')); }
    /** @param array<string, mixed> $body */
    private function bodyLocale(array $body): string { return $this->validLocale((string) ($body['lang'] ?? 'de')); }
    private function validLocale(string $locale): string { return array_key_exists($locale, $this->languages) ? $locale : 'de'; }
    private function participantAction(string $publicId): string { return 'first-sats-participant:' . $publicId; }
    private function learningAction(string $publicId): string { return 'first-sats-learning:' . $publicId; }
    private function walletAction(string $publicId): string { return 'first-sats-wallet:' . $publicId; }
    private function managementAction(string $publicId): string { return 'first-sats-management:' . $publicId; }
    private function html(ResponseInterface $response): ResponseInterface { return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withHeader('Cache-Control', 'private, no-store'); }
}
