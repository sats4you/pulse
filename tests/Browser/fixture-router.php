<?php

declare(strict_types=1);

use Sats4you\Pulse\Platform\I18n\TranslatorFactory;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Pulse\PublicAccess\AccessExchange;
use Sats4you\Pulse\Pulse\PublicAccess\AccessSessionCodec;
use Sats4you\Pulse\Pulse\PublicAccess\BootstrapPage;
use Sats4you\Pulse\Tests\Support\InMemoryRoundAccessStore;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$projectRoot = dirname(__DIR__, 2);

if (str_starts_with($path, '/assets/')) {
    $asset = basename($path);
    $file = $projectRoot . '/public/assets/' . $asset;
    if (!is_file($file)) {
        http_response_code(404);
        exit;
    }

    header('Content-Type: ' . (str_ends_with($asset, '.css') ? 'text/css' : 'text/javascript') . '; charset=utf-8');
    readfile($file);
    exit;
}

$locale = $_GET['lang'] ?? 'de';
$translator = TranslatorFactory::create($locale, $projectRoot . '/resources/translations');
$digester = new SecretDigester(str_repeat('h', 32));
$store = new InMemoryRoundAccessStore();
$store->participantDigest = $digester->digest('test-participant-secret');
$exchange = new AccessExchange($store, $digester, new AccessSessionCodec(str_repeat('s', 32)));

header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self'; frame-ancestors 'none'");
header('X-Robots-Tag: noindex, nofollow, noarchive');

if ($path === '/pulse/r/bern-bitcoin') {
    header('Content-Type: text/html; charset=utf-8');
    echo (new BootstrapPage())->render(
        $translator,
        in_array($locale, TranslatorFactory::SUPPORTED_LOCALES, true) ? $locale : 'de',
        'bern-bitcoin',
        '/exchange',
        '/events',
        ['de' => 'Deutsch', 'fr' => 'Français', 'it' => 'Italiano', 'rm' => 'Rumantsch Grischun'],
    );
    exit;
}

if ($path === '/exchange' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $cookie = is_array($input)
        ? $exchange->exchangeParticipant((string) ($input['slug'] ?? ''), (string) ($input['secret'] ?? ''), new DateTimeImmutable())
        : null;
    if ($cookie === null) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo '{"ok":false}';
        exit;
    }

    setcookie('pulse_access', $cookie, [
        'expires' => time() + 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    header('Content-Type: application/json');
    echo '{"ok":true}';
    exit;
}

if ($path === '/events') {
    $grant = $exchange->validateParticipant($_COOKIE['pulse_access'] ?? '', 'bern-bitcoin', new DateTimeImmutable());
    if ($grant === null) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    $heading = htmlspecialchars($translator->trans('participant.heading'), ENT_QUOTES, 'UTF-8');
    $privacy = htmlspecialchars($translator->trans('privacy.short'), ENT_QUOTES, 'UTF-8');
    $join = htmlspecialchars($translator->trans('rsvp.join'), ENT_QUOTES, 'UTF-8');
    header('Content-Type: text/html; charset=utf-8');
    echo "<!doctype html><html lang=\"{$locale}\"><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width\"><link rel=\"stylesheet\" href=\"/assets/pulse.css\"><main class=\"shell\"><p class=\"eyebrow\">pulse · Bern Monthly Bitcoin Meetup</p><h1>{$heading}</h1><p class=\"lead\">{$privacy}</p><section class=\"access-card\"><div class=\"state-icon\">4</div><div><strong>Bern Monthly Bitcoin Meetup</strong><p>Donnerstag, 27. August 2026 · 18:30<br>Ort noch offen</p></div><a class=\"primary\" href=\"#\">{$join}</a></section></main></html>";
    exit;
}

http_response_code(404);
echo 'Not found';
