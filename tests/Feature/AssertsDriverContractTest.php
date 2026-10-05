<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use Simtabi\Laranail\Package\Tools\Testing\AssertsDriverContract;
use Simtabi\Laranail\Package\Tools\Tests\Fixtures\DriverContract\FixtureManager;

uses(AssertsDriverContract::class);

/**
 * The guard on the guard. A shared test helper that cannot fail is worse than none, because every
 * package that adopts it inherits a green tick instead of a check.
 */
it('passes when every configured driver has a create method', function (): void {
    $this->assertEveryDriverIsBuildable(FixtureManager::class, ['alpha', 'lemon-squeezy']);
    expect(true)->toBeTrue();
});

it('studlies a hyphenated driver name the way Manager does', function (): void {
    // driver('lemon-squeezy') resolves createLemonSqueezyDriver(), not createLemon-squeezyDriver().
    expect($this->managerCreateMethod('lemon-squeezy'))->toBe('createLemonSqueezyDriver')
        ->and($this->managerCreateMethod('telnyx'))->toBe('createTelnyxDriver');
});

it('fails, naming the driver, when a configured name has no create method', function (): void {
    $this->assertEveryDriverIsBuildable(FixtureManager::class, ['alpha', 'telnyx']);
})->throws(AssertionFailedError::class, 'telnyx => createTelnyxDriver()');

it('refuses to pass vacuously on an empty driver list', function (): void {
    // The failure mode this catches is a fixture that silently read nothing.
    $this->assertEveryDriverIsBuildable(FixtureManager::class, []);
})->throws(AssertionFailedError::class, 'proves nothing');

it('passes when no source file reads the bare config key', function (): void {
    $this->assertReadsConfigAtRegisteredKey(__DIR__ . '/../fixtures/DriverContract', 'anything');
    expect(true)->toBeTrue();
});

it('catches a bare config read, including a multi-segment key', function (): void {
    $dir = sys_get_temp_dir() . '/laranail-bare-' . bin2hex(random_bytes(5));
    @mkdir($dir, 0777, true);
    // The multi-segment case is the one a naive pattern misses: anchoring the closing quote after
    // the first segment matches 'sms.path' but not 'sms.encryption.driver'.
    file_put_contents($dir . '/Reader.php', "<?php\n\$x = config('sms.encryption.driver', 'a');\n");

    try {
        $this->assertReadsConfigAtRegisteredKey($dir, 'sms');
    } finally {
        @unlink($dir . '/Reader.php');
        @rmdir($dir);
    }
})->throws(AssertionFailedError::class, 'sms.encryption.driver');

it('leaves a different registry alone when it is declared exempt', function (): void {
    $dir = sys_get_temp_dir() . '/laranail-exempt-' . bin2hex(random_bytes(5));
    @mkdir($dir, 0777, true);
    // Container tags and gate abilities share the prefix but are not config, and must keep their
    // own bare names -- renaming them is a separate breaking decision.
    file_put_contents($dir . '/Tagged.php', "<?php\n\$x = \$app->get('sms.observers');\n");

    try {
        $this->assertReadsConfigAtRegisteredKey($dir, 'sms', ['observers']);
    } finally {
        @unlink($dir . '/Tagged.php');
        @rmdir($dir);
    }

    expect(true)->toBeTrue();
});

/**
 * Each form below was missed by the line-at-a-time scan this assertion shipped with (research R1,
 * 2026-10-04): 41 named-argument sites, 63 facade sites, and multi-line calls across the family.
 * None was a live defect on the day it was measured; each would have been invisible if it became one.
 *
 * @param array<string, string> $files basename => source
 */
function scanFixture(array $files): string
{
    $dir = sys_get_temp_dir() . '/laranail-scan-' . bin2hex(random_bytes(5));
    @mkdir($dir, 0777, true);

    foreach ($files as $name => $source) {
        file_put_contents($dir . '/' . $name, $source);
    }

    return $dir;
}

function dropFixture(string $dir): void
{
    foreach (glob($dir . '/*') ?: [] as $file) {
        @unlink($file);
    }

    @rmdir($dir);
}

it('catches every form of a bare config read', function (string $source, string $reported): void {
    $dir = scanFixture(['Reader.php' => "<?php\n\n" . $source . "\n"]);

    try {
        expect(fn () => $this->assertReadsConfigAtRegisteredKey($dir, 'sms'))
            ->toThrow(AssertionFailedError::class, $reported);
    } finally {
        dropFixture($dir);
    }
})->with([
    'named argument to config()' => ["\$x = config(key: 'sms.driver');", 'Reader.php:3'],
    'named argument to ->get()'  => ["\$x = \$config->get(key: 'sms.driver');", 'Reader.php:3'],
    'Config::get facade'         => ["\$x = Config::get('sms.driver');", 'Reader.php:3'],
    'Config::has facade'         => ["\$x = Config::has('sms');", 'Reader.php:3'],
    'fully qualified facade'     => ["\$x = \\Illuminate\\Support\\Facades\\Config::get('sms.driver');", 'Reader.php:3'],
    '->has() on the repository'  => ["\$x = \$config->has('sms.driver');", 'Reader.php:3'],
    'digit in a segment'         => ["\$x = config('sms.v2.driver');", 'sms.v2.driver'],
    'hyphen in a segment'        => ["\$x = config('sms.fall-back');", 'sms.fall-back'],
    'interpolated after a dot'   => ['$x = config("sms.{$suffix}");', 'Reader.php:3'],
    'multi-line call'            => ["\$x = config(\n    'sms.driver',\n    'log',\n);", 'Reader.php:3'],
    'multi-line named argument'  => ["\$x = Config::get(\n    key: 'sms.driver',\n);", 'Reader.php:3'],
]);

it('reports the line the read starts on, in a whole-file scan', function (): void {
    $dir = scanFixture(['Reader.php' => "<?php\n\n\n\n\$x = config(\n    'sms.driver',\n);\n"]);

    try {
        expect(fn () => $this->assertReadsConfigAtRegisteredKey($dir, 'sms'))
            ->toThrow(AssertionFailedError::class, 'Reader.php:5');
    } finally {
        dropFixture($dir);
    }
});

it('handles a hyphenated bare key', function (): void {
    // env-kit is the case that motivated the guard: sixteen bare reads at `env-kit.*`.
    $dir = scanFixture(['Reader.php' => "<?php\n\$x = config('env-kit.encryption.driver');\n"]);

    try {
        expect(fn () => $this->assertReadsConfigAtRegisteredKey($dir, 'env-kit'))
            ->toThrow(AssertionFailedError::class, 'env-kit.encryption.driver');
    } finally {
        dropFixture($dir);
    }
});

it('does not flag reads that are not at the bare key', function (): void {
    // Negative controls: a key sharing the prefix, the namespaced key, a write, and a call to a
    // function whose name merely ends in "config".
    $dir = scanFixture(['Reader.php' => implode("\n", [
        '<?php',
        "\$a = config('smsx.driver');",
        "\$b = config('laranail.sms.driver');",
        "\$c = config(key: 'laranail.sms.driver');",
        "\$d = Config::get('laranail.sms');",
        "Config::set('sms.driver', 'log');",
        "config(['sms.driver' => 'log']);",
        "\$e = myconfig('sms.driver');",
        "\$f = config('sms_gateway.driver');",
        '',
    ])]);

    try {
        $this->assertReadsConfigAtRegisteredKey($dir, 'sms');
    } finally {
        dropFixture($dir);
    }

    expect(true)->toBeTrue();
});

it('applies the exemption to the named-argument and facade forms too', function (): void {
    $dir = scanFixture(['Tagged.php' => "<?php\n\$x = Config::get(key: 'sms.observers');\n\$y = config(key: 'sms.observers.list');\n"]);

    try {
        $this->assertReadsConfigAtRegisteredKey($dir, 'sms', ['observers']);
    } finally {
        dropFixture($dir);
    }

    expect(true)->toBeTrue();
});

it('refuses to pass vacuously on a directory with no PHP source', function (): void {
    // A path typo or a renamed src/ directory scans nothing and would report a clean tree.
    $dir = scanFixture(['notes.txt' => "config('sms.driver')"]);

    try {
        $this->assertReadsConfigAtRegisteredKey($dir, 'sms');
    } finally {
        dropFixture($dir);
    }
})->throws(AssertionFailedError::class, 'proved nothing');
