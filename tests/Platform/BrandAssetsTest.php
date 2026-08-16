<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\Platform;

use PHPUnit\Framework\TestCase;

final class BrandAssetsTest extends TestCase
{
    public function testBrandAndFontAssetsAreBundledWithoutExternalFontRequests(): void
    {
        $projectRoot = dirname(__DIR__, 2);
        $css = file_get_contents($projectRoot . '/public/assets/pulse.css');

        self::assertIsString($css);
        self::assertStringContainsString('url("/assets/fonts/inter/Inter-Regular.woff2")', $css);
        self::assertStringContainsString('url("/assets/fonts/inter/Inter-Bold.woff2")', $css);
        self::assertStringContainsString('url("/assets/fonts/inter/Inter-ExtraBold.woff2")', $css);
        self::assertStringNotContainsString('fonts.googleapis.com', $css);
        self::assertStringNotContainsString('rsms.me/inter', $css);

        foreach ([
            'Inter-Regular.woff2',
            'Inter-Bold.woff2',
            'Inter-ExtraBold.woff2',
        ] as $font) {
            $bytes = file_get_contents($projectRoot . '/public/assets/fonts/inter/' . $font);
            self::assertIsString($bytes);
            self::assertSame('wOF2', substr($bytes, 0, 4));
        }

        self::assertFileExists($projectRoot . '/public/assets/sats4you-mark.svg');
        self::assertFileExists($projectRoot . '/public/assets/sats4you-favicon.svg');
        self::assertFileExists($projectRoot . '/THIRD_PARTY_LICENSES/Inter-OFL-1.1.txt');
    }
}
