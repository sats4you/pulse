<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\Platform\Database;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NativeNamedParameterCompatibilityTest extends TestCase
{
    /** @param list<string> $parameters */
    #[DataProvider('sqlBlocks')]
    public function testEachNamedParameterOccursOnlyOncePerPreparedSqlBlock(
        string $source,
        int $blockNumber,
        array $parameters,
    ): void {
        $counts = array_count_values($parameters);
        $duplicates = array_keys(array_filter($counts, static fn (int $count): bool => $count > 1));

        self::assertSame(
            [],
            $duplicates,
            sprintf(
                '%s SQL block %d repeats native PDO placeholders: %s',
                $source,
                $blockNumber,
                implode(', ', $duplicates),
            ),
        );
    }

    /** @return iterable<string, array{string, int, list<string>}> */
    public static function sqlBlocks(): iterable
    {
        $projectRoot = dirname(__DIR__, 3);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($projectRoot . '/app'),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            if (!is_string($source)) {
                continue;
            }

            preg_match_all("/<<<'SQL'\\R(.*?)\\R\\s*SQL/s", $source, $blocks);
            foreach ($blocks[1] as $index => $sql) {
                preg_match_all('/:[a-z][a-z0-9_]*/i', $sql, $parameters);
                yield $file->getFilename() . '#' . ($index + 1) => [
                    $file->getFilename(),
                    $index + 1,
                    $parameters[0],
                ];
            }
        }
    }
}
