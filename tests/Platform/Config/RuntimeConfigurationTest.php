<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\Platform\Config;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sats4you\Pulse\Platform\Config\RuntimeConfiguration;

final class RuntimeConfigurationTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/pulse-config-' . bin2hex(random_bytes(8));
        mkdir($this->projectRoot . '/config', 0700, true);
    }

    protected function tearDown(): void
    {
        putenv('PULSE_TEST_VALUE');
        if (is_file($this->projectRoot . '/config/runtime.php')) {
            unlink($this->projectRoot . '/config/runtime.php');
        }
        rmdir($this->projectRoot . '/config');
        rmdir($this->projectRoot);
    }

    public function testLoadsValueFromNonPublicRuntimeFile(): void
    {
        file_put_contents(
            $this->projectRoot . '/config/runtime.php',
            "<?php return ['PULSE_TEST_VALUE' => 'from-file'];\n",
        );

        self::assertSame(
            'from-file',
            RuntimeConfiguration::fromProjectRoot($this->projectRoot)->required('PULSE_TEST_VALUE'),
        );
    }

    public function testEnvironmentOverridesRuntimeFile(): void
    {
        file_put_contents(
            $this->projectRoot . '/config/runtime.php',
            "<?php return ['PULSE_TEST_VALUE' => 'from-file'];\n",
        );
        putenv('PULSE_TEST_VALUE=from-environment');

        self::assertSame(
            'from-environment',
            RuntimeConfiguration::fromProjectRoot($this->projectRoot)->required('PULSE_TEST_VALUE'),
        );
    }

    public function testRejectsMissingRequiredValueWithoutDisclosingAnotherSecret(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing required runtime configuration: MISSING_VALUE');

        RuntimeConfiguration::fromProjectRoot($this->projectRoot)->required('MISSING_VALUE');
    }
}
