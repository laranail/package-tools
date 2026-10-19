<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use Simtabi\Laranail\Package\Tools\Testing\TestCredentials;
use Simtabi\Laranail\Package\Tools\Testing\CredentialLiterals;
use Simtabi\Laranail\Package\Tools\Testing\AssertsNoLiteralCredentials;

uses(AssertsNoLiteralCredentials::class);

/**
 * A sample source with the name and the value filled in apart, so this file is not itself a
 * name-and-literal pair for secret scanning to report.
 */
function credentialSource(string $template, string $name = '', string $value = ''): string
{
    return '<?php ' . strtr($template, ['NAME' => $name, 'VALUE' => $value]);
}

/**
 * @return list<string> the rule each finding was reported under
 */
function credentialRules(string $source): array
{
    return array_map(static fn (array $finding): string => $finding[1], CredentialLiterals::in($source));
}

/**
 * A directory holding one PHP file per source, removed after the test.
 *
 * @param array<string, string> $files basename => source
 */
function credentialFixtureDir(array $files): string
{
    $dir = sys_get_temp_dir() . '/no-literal-credentials-' . bin2hex(random_bytes(6));
    mkdir($dir);
    foreach ($files as $name => $source) {
        file_put_contents("{$dir}/{$name}", $source);
    }

    credentialFixtureDirs($dir);

    return $dir;
}

/**
 * Remembers each fixture directory; with no argument, hands them all back and forgets them.
 *
 * @return list<string>
 */
function credentialFixtureDirs(?string $add = null): array
{
    static $dirs = [];

    if ($add !== null) {
        $dirs[] = $add;

        return $dirs;
    }

    [$all, $dirs] = [$dirs, []];

    return $all;
}

afterEach(function (): void {
    foreach (credentialFixtureDirs() as $dir) {
        array_map(unlink(...), glob("{$dir}/*") ?: []);
        rmdir($dir);
    }
});

it('reports a weak literal in every position named for a credential', function (string $template, string $name): void {
    expect(credentialRules(credentialSource($template, $name, 'theirs')))->toBe(['named']);
})->with([
    'array key'        => ["['NAME' => 'VALUE'];", 'password'],
    'dotted array key' => ["['NAME' => 'VALUE'];", 'services.stripe.secret'],
    'env-style key'    => ["['NAME' => 'VALUE'];", 'DB_PASSWORD'],
    'variable'         => ["\$NAME = 'VALUE';", 'adminPassword'],
    'property'         => ["class A { private string \$NAME = 'VALUE'; }", 'apiToken'],
    'this property'    => ["\$this->NAME = 'VALUE';", 'clientSecret'],
    'constant'         => ["const NAME = 'VALUE';", 'POLICY_THEIR_PASSWORD'],
    'typed constant'   => ["final class A { const string NAME = 'VALUE'; }", 'ADMIN_SECRET'],
    'define'           => ["define('NAME', 'VALUE');", 'LIVE_TOKEN'],
    'named argument'   => ["f(applied: true, NAME: 'VALUE');", 'password'],
    'livewire set'     => ["\$c->set('NAME', 'VALUE');", 'password'],
    'livewire assert'  => ["\$c->assertSet('NAME', 'VALUE');", 'generatedPassword'],
    'config set'       => ["Config::set('NAME', 'VALUE');", 'services.zoho.api_key'],
    'double quotes'    => ["['NAME' => \"VALUE\"];", 'private_key'],
]);

it('reports the password in a URL', function (): void {
    $url = 'mysql://doadmin' . ':' . 'pw' . '@db.example.com:25060/defaultdb';

    expect(credentialRules("<?php \$url = '{$url}';"))->toBe(['url']);
});

it('reports a strong-looking literal passed to a Password, and only a strong one', function (): void {
    $strong = 'Ab9' . '!cD3' . '#eF7' . '^gH';

    expect(credentialRules(credentialSource("new Password('VALUE');", value: $strong)))->toBe(['strong'])
        ->and(credentialRules(credentialSource("new \\Acme\\Domain\\Password('VALUE');", value: $strong)))->toBe(['strong'])
        ->and(credentialRules(credentialSource("new Password('VALUE');", value: 'short')))->toBe([]);
});

it('leaves alone what is not a credential', function (string $source): void {
    expect(credentialRules('<?php ' . $source))->toBe([]);
})->with([
    'generated value'      => "['password' => TestCredentials::password()];",
    'env read'             => "['password' => env('DB_PASSWORD')];",
    'empty string'         => "['password' => ''];",
    'prose'                => "\$c->assertSet('flash', 'Its password is unchanged.');",
    'prose under the name' => "['password' => 'The password is incorrect.'];",
    'validation rules'     => "['password' => 'required|string|min:8'];",
    'interpolated'         => "['password' => \"{\$prefix}-x\"];",
    'comparison'           => "if (\$token === 'expected') {}",
    'positional vector'    => "ScramSha256::verifier('pencil', \$salt);",
    'unrelated constant'   => "const RFC7677_SALT = 'W22ZaJ0SNY7soEsUEjb6gQ==';",
    'url without password' => "\$dsn = 'pgsql://db.example.com:5432/app';",
    'url from variables'   => '$dsn = "mysql://{$user}:{$pass}@host/db";',
    'short placeholder'    => "['password' => 'x', 'api_key' => 'k'];",
    'mask'                 => "['password' => '••••••••'];",
    'package name'         => "['Password' => 'illuminate/auth'];",
    'token type'           => "['token_type' => 'Bearer'];",
    'field name'           => "['password_field' => 'secret_hash'];",
    'broker name'          => "['passwords' => 'admins'];",
]);

it('passes over a line marked as a deliberate fixed value, or the line below the marker', function (): void {
    $dir = credentialFixtureDir([
        'Same.php'  => credentialSource("const NAME = 'VALUE'; // @credential-literal-exempt: RFC vector", 'VECTOR_PASSWORD', 'pencil'),
        'Above.php' => credentialSource("\n// @credential-literal-exempt: weak input for the strength rule\n\$NAME = 'VALUE';", 'password', 'abc'),
    ]);

    $this->assertNoLiteralCredentialsIn($dir, minFiles: 2);
    expect(true)->toBeTrue();
});

it('fails on a literal, naming the file and line but not the value', function (): void {
    $value = 'theirs' . bin2hex(random_bytes(4));
    $dir = credentialFixtureDir(['PolicyTest.php' => credentialSource("\n\nconst NAME = 'VALUE';", 'THEIR_PASSWORD', $value)]);

    try {
        $this->assertNoLiteralCredentialsIn($dir);
    } catch (AssertionFailedError $e) {
        expect($e->getMessage())->toContain('PolicyTest.php:3 (named) th…')
            ->not->toContain($value);

        return;
    }

    $this->fail('a literal credential passed the assertion');
});

it('refuses to pass on a scan that inspected too few files', function (): void {
    $this->assertNoLiteralCredentialsIn(credentialFixtureDir(['Only.php' => '<?php ']), minFiles: 5);
})->throws(AssertionFailedError::class, 'fewer than the floor of 5');

it('skips an exempt file by basename', function (): void {
    $dir = credentialFixtureDir([
        'Clean.php'  => '<?php ',
        'Vector.php' => credentialSource("const NAME = 'VALUE';", 'VECTOR_SECRET', 'fixed'),
    ]);

    $this->assertNoLiteralCredentialsIn($dir, exemptFiles: ['Vector.php']);
    expect(true)->toBeTrue();
});

it('keeps literal credentials out of package-tools\' own tests', function (): void {
    $this->assertNoLiteralCredentialsIn(dirname(__DIR__), minFiles: 100);
    expect(true)->toBeTrue();
});

describe('TestCredentials', function (): void {
    it('generates a strong password with every character class', function (): void {
        $password = TestCredentials::password('strength-check', 24);

        expect(strlen($password))->toBe(24)
            ->and(CredentialLiterals::looksLikeAStrongPassword($password))->toBeTrue()
            ->and($password)->toMatch('/^[A-Za-z0-9!*\-=^_~]+$/');
    });

    it('returns the same value for a purpose within a run, and a different one per purpose', function (): void {
        expect(TestCredentials::password('ours'))->toBe(TestCredentials::password('ours'))
            ->and(TestCredentials::password('ours'))->not->toBe(TestCredentials::password('theirs'))
            ->and(TestCredentials::secret('webhook'))->toBe(TestCredentials::secret('webhook'))
            ->and(TestCredentials::key('wrap'))->not->toBe(TestCredentials::key('unwrap'));
    });

    it('sizes secrets and keys to the bytes asked for', function (): void {
        expect(TestCredentials::secret('sized', 20))->toMatch('/^[0-9a-f]{40}$/')
            ->and(strlen(TestCredentials::key('sized', 32)))->toBe(32);
    });

    it('refuses a password too short to hold every class', function (): void {
        TestCredentials::password('tiny', 3);
    })->throws(InvalidArgumentException::class);
});
