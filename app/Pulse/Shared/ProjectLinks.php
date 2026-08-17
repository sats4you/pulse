<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Pulse\Shared;

use Symfony\Contracts\Translation\TranslatorInterface;

final class ProjectLinks
{
    public static function render(TranslatorInterface $translator, string $locale): string
    {
        $e = static fn (string $value): string => htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );
        $t = static fn (string $key): string => $e($translator->trans($key));
        $privacyPath = '/pulse/privacy?lang=' . rawurlencode($locale);

        return sprintf(
            '<p class="footnote"><a href="%s">%s</a> · <a href="https://github.com/sats4you/pulse" rel="external">%s</a> · <a href="mailto:security@sats4you.ch">%s</a></p>',
            $e($privacyPath),
            $t('privacy.link'),
            $t('project.source'),
            $t('project.security'),
        );
    }
}
