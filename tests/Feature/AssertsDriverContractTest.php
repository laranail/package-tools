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
