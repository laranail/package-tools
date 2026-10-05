<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Commands\Concerns;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Prints a one-line deprecation when a command is invoked by a deprecated alias, then runs it.
 *
 * Which aliases are deprecated:
 *
 * - **Automatically**, every alias outside the command's own vendor scope. For
 *   `laranail::package-tools.doctor` the scope is `laranail:` (which covers `laranail::` too), so
 *   `package-tools:doctor` warns. The naming convention allows no new bare alias, so one that exists
 *   is there only for backwards compatibility and should say so -- without each command having to
 *   opt in, which is how a convention ends up applied to half the family.
 * - **On request**, any vendor-scoped alias returned by {@see deprecatedAliases()}: a command that
 *   was renamed inside its scope keeps the old scoped name as an alias and lists it there.
 *
 * A vendor-scoped spelling variant (`laranail:package-tools.doctor`) stays silent, and a command
 * whose own name has no vendor segment cannot tell a scope apart, so it warns only for aliases it
 * lists.
 *
 * Detection reads the name the caller actually typed: the input's first argument is the command
 * token for `php artisan`, `Artisan::call()` and `$this->call()` alike, while `getName()` is always
 * the canonical name. Symfony calls initialize() after binding the input and before interact(), so
 * the warning prints before any prompt. Generalised from laranail/package-scaffolder's
 * `WarnsOnDeprecatedAlias`.
 */
trait WarnsOnDeprecatedAliases
{
    /**
     * Whether $alias is one of this command's aliases and deprecated, by the rule above.
     */
    public function isDeprecatedAlias(string $alias): bool
    {
        $name = (string) $this->getName();

        if ($alias === $name || ! in_array($alias, $this->getAliases(), true)) {
            return false;
        }

        if (in_array($alias, $this->deprecatedAliases(), true)) {
            return true;
        }

        $separator = strpos($name, ':');

        if ($separator === false || $separator === 0) {
            return false;
        }

        return ! str_starts_with($alias, substr($name, 0, $separator + 1));
    }

    /**
     * Vendor-scoped aliases this command keeps only for backwards compatibility. Aliases outside the
     * vendor scope are deprecated without being listed here.
     *
     * @return list<string>
     */
    protected function deprecatedAliases(): array
    {
        return [];
    }

    /**
     * The earliest release that may drop a deprecated alias, as worded in the warning.
     */
    protected function deprecatedAliasRemoval(): string
    {
        return 'the next minor after 0.1';
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $invokedAs = $input->getFirstArgument();

        if (! is_string($invokedAs) || ! $this->isDeprecatedAlias($invokedAs)) {
            return;
        }

        $output->writeln(sprintf(
            '<comment>Deprecated:</comment> [%s] is a deprecated alias and will be removed no earlier than %s. Use [%s] instead.',
            $invokedAs,
            $this->deprecatedAliasRemoval(),
            (string) $this->getName(),
        ));
    }
}
