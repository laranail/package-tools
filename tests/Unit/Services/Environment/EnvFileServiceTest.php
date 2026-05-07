<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit\Services\Environment;

use LogicException;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\PackageTools\Services\Environment\EnvFileService;
use Simtabi\Laranail\PackageTools\Services\Environment\Events\EnvFileMutated;
use Simtabi\Laranail\PackageTools\Services\Environment\Exceptions\EnvFileNotFound;

final class EnvFileServiceTest extends TestCase
{
    private string $tmpDir;

    private string $envPath;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/laranail-env-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0o755, true);
        $this->envPath = $this->tmpDir . '/.env';
    }

    protected function tearDown(): void
    {
        if (! is_dir($this->tmpDir)) {
            return;
        }
        foreach (scandir($this->tmpDir) ?: [] as $entry) {
            if ($entry === '.') {
                continue;
            }
            if ($entry === '..') {
                continue;
            }
            @unlink($this->tmpDir . '/' . $entry);
        }
        @rmdir($this->tmpDir);
    }

    private function seed(string $contents): EnvFileService
    {
        file_put_contents($this->envPath, $contents);

        return new EnvFileService($this->envPath);
    }

    public function test_exists_is_readable_is_writable(): void
    {
        $svc = $this->seed("APP_NAME=Laravel\n");

        $this->assertTrue($svc->exists());
        $this->assertTrue($svc->isReadable());
        $this->assertTrue($svc->isWritable());
    }

    public function test_throws_when_file_missing(): void
    {
        $svc = new EnvFileService($this->tmpDir . '/no-such-file');

        $this->expectException(EnvFileNotFound::class);
        $svc->all();
    }

    public function test_read_returns_value_or_default(): void
    {
        $svc = $this->seed("APP_NAME=Laravel\n");
        $this->assertSame('Laravel', $svc->read('APP_NAME'));
        $this->assertSame('fallback', $svc->read('MISSING', 'fallback'));
        $this->assertNull($svc->read('MISSING'));
    }

    public function test_append_if_missing_appends_when_absent(): void
    {
        $svc = $this->seed("APP_NAME=Laravel\n");
        $changed = $svc->appendIfMissing('LARANAIL_GITHUB_TOKEN', 'abc123', 'GitHub PAT');

        $this->assertTrue($changed);
        $contents = file_get_contents($this->envPath);
        $this->assertStringContainsString('LARANAIL_GITHUB_TOKEN=abc123', $contents);
        $this->assertStringContainsString('# GitHub PAT', $contents);
        $this->assertSame('Laravel', $svc->read('APP_NAME'));
    }

    public function test_append_if_missing_is_no_op_when_key_present(): void
    {
        $svc = $this->seed("APP_NAME=Laravel\n");
        $svc->appendIfMissing('APP_NAME', 'Other');

        $this->assertSame('Laravel', $svc->read('APP_NAME'));
    }

    public function test_append_block_skips_existing_keys(): void
    {
        $svc = $this->seed("APP_NAME=Laravel\n");
        $count = $svc->appendBlock([
            'APP_NAME' => 'should be skipped',
            'NEW_KEY1' => 'a',
            'NEW_KEY2' => 'b',
        ], sectionTitle: 'laranail/* additions');

        $this->assertSame(2, $count);

        $contents = file_get_contents($this->envPath);
        $this->assertStringContainsString('# === laranail/* additions ===', $contents);
        $this->assertStringContainsString('NEW_KEY1=a', $contents);
        $this->assertStringContainsString('NEW_KEY2=b', $contents);
        $this->assertSame('Laravel', $svc->read('APP_NAME'));
    }

    public function test_creates_backup_on_write(): void
    {
        $svc = $this->seed("APP_NAME=Laravel\n");
        $svc->appendIfMissing('NEW_KEY', 'v');

        $backups = glob($this->envPath . '.bak.*');
        $this->assertNotFalse($backups);
        $this->assertNotEmpty($backups);
        $this->assertStringContainsString('APP_NAME=Laravel', file_get_contents($backups[0]));
    }

    public function test_atomic_write_does_not_leave_tmp_files_after_success(): void
    {
        $svc = $this->seed("APP_NAME=Laravel\n");
        $svc->appendIfMissing('NEW_KEY', 'v');

        $tmps = glob($this->envPath . '.tmp.*');
        $this->assertNotFalse($tmps);
        $this->assertSame([], $tmps);
    }

    public function test_emits_env_file_mutated_on_append_if_missing(): void
    {
        $events = [];
        $svc = new EnvFileService(
            path: $this->envPath,
            eventDispatcher: function (EnvFileMutated $e) use (&$events): void {
                $events[] = $e;
            },
        );
        file_put_contents($this->envPath, "APP_NAME=Laravel\n");

        $svc->appendIfMissing('NEW_KEY', 'v', 'a comment');

        $this->assertCount(1, $events);
        $this->assertSame(['NEW_KEY'], $events[0]->addedKeys);
        $this->assertSame('appendIfMissing', $events[0]->action);
        $this->assertStringStartsWith($this->envPath . '.bak.', $events[0]->backupPath);
    }

    public function test_append_if_missing_handles_no_trailing_newline(): void
    {
        $svc = $this->seed('APP_NAME=Laravel'); // no trailing \n
        $svc->appendIfMissing('NEW_KEY', 'v');

        $contents = file_get_contents($this->envPath);
        // The new key must NOT be fused onto the previous line.
        $this->assertStringNotContainsString('LaravelNEW_KEY', $contents);
        $this->assertStringContainsString("APP_NAME=Laravel\n", $contents);
        $this->assertStringContainsString("NEW_KEY=v\n", $contents);
        // And a roundtrip read still finds both:
        $this->assertSame('Laravel', $svc->read('APP_NAME'));
        $this->assertSame('v', $svc->read('NEW_KEY'));
    }

    public function test_quotes_values_with_special_chars(): void
    {
        $svc = $this->seed("APP_NAME=Laravel\n");
        $svc->appendIfMissing('PASSWORD', 'hunter2 with space and #hash');

        $contents = file_get_contents($this->envPath);
        $this->assertStringContainsString('PASSWORD="hunter2 with space and #hash"', $contents);

        // Round-trip through the parser:
        $this->assertSame('hunter2 with space and #hash', $svc->read('PASSWORD'));
    }

    public function test_force_set_requires_acknowledgement(): void
    {
        $svc = $this->seed("APP_NAME=Laravel\n");

        $this->expectException(LogicException::class);
        $svc->forceSet('APP_NAME', 'Other', acknowledgeDestructive: false);
    }

    public function test_force_set_overwrites_existing_with_acknowledgement(): void
    {
        $svc = $this->seed("APP_NAME=Laravel\nOTHER=keep\n");

        $svc->forceSet('APP_NAME', 'NewName', acknowledgeDestructive: true);

        $this->assertSame('NewName', $svc->read('APP_NAME'));
        $this->assertSame('keep', $svc->read('OTHER'));
    }
}
