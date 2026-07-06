<?php

declare(strict_types=1);

// regenerates src/Enums/Timezone.php from the current tzdata. run after php
// or tzdata upgrades:  php tools/generate-timezone-enum.php

$identifiers = DateTimeZone::listIdentifiers();

$caseName = static function (string $identifier): string {
    $name = str_replace(['+', '-'], [' Plus ', ' Minus '], $identifier);
    $name = str_replace(['/', '_'], ' ', $name);
    $name = str_replace(' ', '', ucwords(strtolower($name)));

    return $name;
};

$cases = '';
foreach ($identifiers as $identifier) {
    $cases .= sprintf("    case %s = '%s';\n", $caseName($identifier), $identifier);
}

$code = <<<PHP
<?php

declare(strict_types=1);

namespace Simtabi\\Laranail\\Package\\Tools\\Enums;

use DateTimeZone;

/**
 * every iana timezone identifier php knows, as a typed case. GENERATED —
 * never edit by hand; regenerate with:
 *
 *   php tools/generate-timezone-enum.php
 */
enum Timezone: string
{
{$cases}
    public function toDateTimeZone(): DateTimeZone
    {
        return new DateTimeZone(\$this->value);
    }
}

PHP;

file_put_contents(__DIR__ . '/../src/Enums/Timezone.php', $code);
echo count($identifiers) . " identifiers written\n";
