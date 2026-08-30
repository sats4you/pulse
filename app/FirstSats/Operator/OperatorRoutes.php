<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Operator;

use DateInterval;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sats4you\Pulse\Platform\Http\CookieHeader;
use Sats4you\Pulse\Platform\Http\SameOriginGuard;
use Sats4you\Pulse\Platform\I18n\TranslatorFactory;
use Slim\App;

final readonly class OperatorRoutes
{
    private const LANGUAGES=['de'=>'Deutsch','fr'=>'Français','it'=>'Italiano · In revisione dalla comunità','rm'=>'Rumantsch Grischun · En revisiun da la communitad'];
    public function __construct(private OperatorAccess $access,private DashboardStore $store,private OperatorPage $page,private SameOriginGuard $origin,private string $translations,private string $baseUrl,private bool $secureCookies){}
    public function register(App $app):void
    {
        $app->get('/erste-sats/operator',$this->bootstrap(...));$app->post('/erste-sats/api/access/operator',$this->exchange(...));$app->get('/erste-sats/operator/dashboard',$this->dashboard(...));
    }
    private function bootstrap(ServerRequestInterface $request,ResponseInterface $response):ResponseInterface
    {$locale=$this->locale($request);$t=TranslatorFactory::create($locale,$this->translations);$response->getBody()->write($this->page->bootstrap($t,$locale,self::LANGUAGES));return $this->html($response);}
    private function exchange(ServerRequestInterface $request,ResponseInterface $response):ResponseInterface
    {
        if(!$this->origin->allows($request->getHeaderLine('Origin'),$request->getHeaderLine('Sec-Fetch-Site')))return $response->withStatus(403);
        $body=is_array($request->getParsedBody())?$request->getParsedBody():[];$now=new DateTimeImmutable();$cookie=$this->access->exchange((string)($body['secret']??''),$now);if($cookie===null)return $response->withStatus(403);
        $response=$response->withAddedHeader('Set-Cookie',CookieHeader::create('first_sats_operator',$cookie,'/erste-sats/operator',$now->add(new DateInterval('PT2H')),$this->secureCookies));$response->getBody()->write('{"ok":true}');return $response->withHeader('Content-Type','application/json')->withHeader('Cache-Control','no-store');
    }
    private function dashboard(ServerRequestInterface $request,ResponseInterface $response):ResponseInterface
    {
        if(!$this->access->authorise((string)($request->getCookieParams()['first_sats_operator']??''),new DateTimeImmutable()))return $response->withStatus(403);
        $locale=$this->locale($request);$t=TranslatorFactory::create($locale,$this->translations);$response->getBody()->write($this->page->dashboard($t,$locale,self::LANGUAGES,$this->store->data(),$this->baseUrl));return $this->html($response)->withHeader('Cache-Control','private, no-store');
    }
    private function locale(ServerRequestInterface $request):string{$locale=(string)($request->getQueryParams()['lang']??'de');return array_key_exists($locale,self::LANGUAGES)?$locale:'de';}
    private function html(ResponseInterface $response):ResponseInterface{return $response->withHeader('Content-Type','text/html; charset=utf-8');}
}
