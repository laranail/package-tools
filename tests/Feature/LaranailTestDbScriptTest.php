<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/*
 * bin/laranail-test-db against a fake dbpantry (a Makefile that records its targets, writes the
 * project's password file and answers `endpoints` the way dbpantry's fixture does) and a consuming
 * package whose vendor/bin/pest is a stub printing the DB_* it was given. That pins the helper's own
 * behaviour, every make call and every variable, without Docker.
 */
function laranailTestDbWorld(array $testDb): array
{
    $world = sys_get_temp_dir() . '/laranail-test-db-' . bin2hex(random_bytes(5));
    $pantry = $world . '/simtabi/packages/dbpantry';
    $consumer = $world . '/laranail/packages/shop';

    @mkdir($pantry . '/secrets/fixture', 0777, true);
    @mkdir($consumer . '/vendor/bin', 0777, true);

    // The fake dbpantry: every target goes to fake.php, which logs the call, writes a project's
    // password file where new-project would (secrets/fixture/ under FIXTURE=1) and answers endpoints.
    file_put_contents($pantry . '/Makefile', "PHP ?= php\nfixture-up fixture-down new-project endpoints:\n\t@\$(PHP) fake.php \$@ '\$(PROFILES)' '\$(NAME)' '\$(ENGINE)' '\$(PROJECT)' '\$(FIXTURE)'\n");
    file_put_contents($pantry . '/fake.php', <<<'PHP'
        <?php
        [, $target, $profiles, $name, $engine, $project, $fixture] = $argv;
        $call = match ($target) {
            'fixture-up' => "fixture-up {$profiles}",
            'new-project' => "new-project {$name} {$engine} {$fixture}",
            'endpoints' => "endpoints {$profiles} {$project} {$fixture}",
            default => $target,
        };
        file_put_contents(__DIR__ . '/calls.log', $call . "\n", FILE_APPEND);
        if ($target === 'new-project') {
            file_put_contents(__DIR__ . "/secrets/fixture/{$engine}_{$name}_user_password.txt", "pw-{$name}-{$engine}\n");
        }
        if ($target === 'endpoints') {
            $endpoints = [];
            foreach (['pg' => 55000, 'mysql' => 55001] as $e => $port) {
                $endpoints[] = [
                    'engine' => $e,
                    'direct' => ['published' => getenv('FAKE_PUBLISH') === 'none' ? null : ['port' => $port]],
                    'principals' => [['role' => 'app', 'user' => "{$project}_user", 'password_file' => "/run/secrets/fixture/{$e}_{$project}_user_password.txt"]],
                ];
            }
            echo json_encode(['fixture' => true, 'endpoints' => $endpoints]);
        }
        PHP);

    file_put_contents($consumer . '/composer.json', json_encode([
        'name'  => 'laranail/shop',
        'extra' => ['laranail' => ['test-db' => $testDb]],
    ]));
    // A stub Pest: one line per run with the database variables and the arguments it received.
    file_put_contents($consumer . '/vendor/bin/pest', <<<'PHP'
        <?php
        $vars = [];
        foreach (['DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $k) {
            $vars[] = $k . '=' . getenv($k);
        }
        echo implode(' ', $vars), ' ARGS=', implode(' ', array_slice($argv, 1)), "\n";
        exit(getenv('DB_CONNECTION') === getenv('STUB_FAIL_ON') ? 1 : 0);
        PHP);

    return [$world, $pantry, $consumer];
}

function laranailTestDb(string $consumer, array $args = [], array $env = []): Process
{
    $process = new Process(
        array_merge([PHP_BINARY, dirname(__DIR__, 2) . '/bin/laranail-test-db'], $args),
        $consumer,
        array_merge(['DBPANTRY_DIR' => false, 'PHP' => PHP_BINARY], $env),
    );
    $process->run();

    return $process;
}

it('creates one isolated project per run and runs the group on each connection', function (): void {
    [, $pantry, $consumer] = laranailTestDbWorld(['connections' => ['mysql', 'pgsql']]);

    $process = laranailTestDb($consumer, ['--', '--stop-on-failure']);

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());

    $lines = explode("\n", trim($process->getOutput()));
    expect($lines)->toHaveCount(2);
    preg_match('/DB_DATABASE=(shop_r[0-9a-f]{8})/', $lines[0], $m);
    $run = $m[1] ?? 'none';

    expect($lines[0])->toBe("DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=55001 DB_DATABASE={$run} DB_USERNAME={$run}_user DB_PASSWORD=pw-{$run}-mysql ARGS=--group=database --stop-on-failure")
        ->and($lines[1])->toBe("DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55000 DB_DATABASE={$run} DB_USERNAME={$run}_user DB_PASSWORD=pw-{$run}-pg ARGS=--group=database --stop-on-failure");

    // The fixture, never the stack: every new-project and endpoints call carries FIXTURE=1.
    expect(file($pantry . '/calls.log', FILE_IGNORE_NEW_LINES))->toBe([
        'fixture-up mysql,pg',
        "new-project {$run} mysql 1",
        "new-project {$run} pg 1",
        "endpoints mysql,pg {$run} 1",
    ]);
});

it('gives two runs two different databases', function (): void {
    [, , $consumer] = laranailTestDbWorld(['connections' => ['pgsql']]);

    $first = laranailTestDb($consumer);
    $second = laranailTestDb($consumer);
    preg_match('/DB_DATABASE=(\S+)/', $first->getOutput(), $a);
    preg_match('/DB_DATABASE=(\S+)/', $second->getOutput(), $b);

    expect($a[1] ?? null)->toStartWith('shop_r')
        ->and($b[1] ?? null)->toStartWith('shop_r')
        ->and($a[1])->not->toBe($b[1]);
});

it('fails, and says which connection failed, when the tests fail on one', function (): void {
    [, , $consumer] = laranailTestDbWorld(['connections' => ['mysql', 'pgsql']]);

    $process = laranailTestDb($consumer, [], ['STUB_FAIL_ON' => 'pgsql']);

    expect($process->getExitCode())->toBe(1)
        ->and(substr_count($process->getOutput(), 'DB_CONNECTION='))->toBe(2)
        ->and($process->getErrorOutput())->toContain('failed on: pgsql');
});

it('prints one connection\'s variables with env, the password masked', function (): void {
    [, , $consumer] = laranailTestDbWorld(['connections' => ['mysql', 'pgsql'], 'project' => 'My-Shop']);

    $process = laranailTestDb($consumer, ['env', 'pgsql']);

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and($process->getOutput())->toMatch('/^DB_CONNECTION=pgsql\nDB_HOST=127\.0\.0\.1\nDB_PORT=55000\nDB_DATABASE=my_shop_r[0-9a-f]{8}\nDB_USERNAME=my_shop_r[0-9a-f]{8}_user\nDB_PASSWORD=\*\*\*\n$/');
});

it('prints the password with env --reveal', function (): void {
    [, , $consumer] = laranailTestDbWorld(['connections' => ['pgsql']]);

    $process = laranailTestDb($consumer, ['env', 'pgsql', '--reveal']);

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and($process->getOutput())->toMatch('/\nDB_PASSWORD=pw-shop_r[0-9a-f]{8}-pg\n$/');
});

it('stops the fixture with down', function (): void {
    [, $pantry, $consumer] = laranailTestDbWorld(['connections' => ['pgsql']]);

    $process = laranailTestDb($consumer, ['down']);

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and(file_get_contents($pantry . '/calls.log'))->toBe("fixture-down\n");
});

it('refuses a package with no test-db configuration', function (): void {
    [, , $consumer] = laranailTestDbWorld([]);

    $process = laranailTestDb($consumer);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain('no extra.laranail.test-db.connections');
});

it('refuses an unknown connection', function (): void {
    [, , $consumer] = laranailTestDbWorld(['connections' => ['sqlite']]);

    $process = laranailTestDb($consumer);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain('unknown connection "sqlite"');
});

it('refuses when dbpantry publishes no host port', function (): void {
    [, , $consumer] = laranailTestDbWorld(['connections' => ['pgsql']]);

    $process = laranailTestDb($consumer, [], ['FAKE_PUBLISH' => 'none']);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain('set MDB_PUBLISH=direct');
});

it('says how to point at dbpantry when it cannot find it', function (): void {
    [$world, , $consumer] = laranailTestDbWorld(['connections' => ['pgsql']]);
    // Move the consumer out of reach of the simtabi/packages/dbpantry search.
    $elsewhere = sys_get_temp_dir() . '/laranail-test-db-orphan-' . bin2hex(random_bytes(5));
    rename($consumer, $elsewhere);

    $process = laranailTestDb($elsewhere);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain('set DBPANTRY_DIR');
});

it('prints its whole usage with --help', function (): void {
    [, , $consumer] = laranailTestDbWorld(['connections' => ['pgsql']]);

    $process = laranailTestDb($consumer, ['--help']);

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toStartWith('Run a package\'s database tests')
        ->toContain('env <connection> [--reveal]')
        ->toContain('up for the next run; `down` stops it and drops every run\'s password files with it.');
});

it('keeps every connection\'s output when redirected into a file with >', function (): void {
    [, , $consumer] = laranailTestDbWorld(['connections' => ['mysql', 'pgsql']]);
    $log = $consumer . '/out.log';

    // A truncating redirect is what lost the second run's output (laranail/settings, 2026-11-17):
    // the helper handed its STDOUT/STDERR streams to proc_open, and PHP seeked each to a stale
    // position, so the next writer overwrote what came before.
    $process = Process::fromShellCommandline(
        sprintf('%s %s > %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg(dirname(__DIR__, 2) . '/bin/laranail-test-db'), escapeshellarg($log)),
        $consumer,
        ['DBPANTRY_DIR' => false, 'PHP' => PHP_BINARY],
    );
    $process->run();

    $out = (string) file_get_contents($log);
    expect($process->getExitCode())->toBe(0, $out)
        ->and(substr_count($out, 'DB_CONNECTION=mysql '))->toBe(1)
        ->and(substr_count($out, 'DB_CONNECTION=pgsql '))->toBe(1)
        ->and($out)->toContain('database group on mysql')
        ->toContain('database group on pgsql')
        ->toContain('all connections passed')
        ->toMatch('/group on mysql.*\nDB_CONNECTION=mysql .*\n.*group on pgsql.*\nDB_CONNECTION=pgsql .*\n.*all connections passed/s');
});
