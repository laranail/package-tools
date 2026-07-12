<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Example: a fuller package built on laranail/package-tools.
|------------------------------------------------------------------------------
| This walks a realistic slice of the fluent builder and both styles of
| lifecycle hook. Drop it into a Laravel 13+ app's package directory (or a
| fresh skeleton from laranail/package-scaffolder), point the namespace at
| your composer.json autoload, and you have a package with config, views,
| components (blade + livewire), translations, assets, routes (plain and
| config-gated), migrations, commands, scheduled commands, policies, morph
| maps, observers, rate limiters, a fluent `php artisan about` section, a
| definition-based install command, attribute discovery, db:seed-time
| seeders, and doctor checks in both the definition and plain forms.
|
| Companion example files (same Acme\Hello namespace):
|   Console/HelloCommand.php       a plain Artisan command (hasCommand)
|   Console/GreetCommand.php       #[AsArtisanCommand] discovered command
|   Console/SyncCommand.php        namespaced `acme::hello.sync` command
|   Http/WidgetController.php      WebController + #[AsRoute]
|   Http/WidgetApiController.php   ApiController JSON helpers
|   Http/GreetingComposer.php      #[AsViewComposer]
|   Contracts/GreeterContract.php  #[AsFacade] for the ide-helper command
|   Doctor/HelloHealthCheck.php    config-published check
|   Doctor/StorageWritableCheck.php  warn/fail/skip edge cases
|   Jobs/SyncGreetingsJob.php      HasGuzzleConfig + HasErrorStorage
|   Database/Seeders/GreetingSeeder.php  a package seeder
*/

namespace Acme\Hello;

use Acme\Hello\Console\HelloCommand;
use Acme\Hello\Contracts\GreeterContract;
use Acme\Hello\Database\Seeders\GreetingSeeder;
use Acme\Hello\Database\Seeders\LegacyGreetingSeeder;
use Acme\Hello\Doctor\HelloHealthCheck;
use Acme\Hello\Doctor\StorageWritableCheck;
use Acme\Hello\Livewire\GreetingBoard;
use Acme\Hello\Models\Greeting;
use Acme\Hello\Observers\GreetingObserver;
use Acme\Hello\Policies\GreetingPolicy;
use Acme\Hello\Support\Greeter;
use Acme\Hello\View\Components\Button;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Simtabi\Laranail\Package\Tools\Commands\InstallCommand;
use Simtabi\Laranail\Package\Tools\Enums\Cadence;
use Simtabi\Laranail\Package\Tools\Enums\Environment;
use Simtabi\Laranail\Package\Tools\Enums\Timezone;
use Simtabi\Laranail\Package\Tools\Enums\Weekday;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Package\Tools\Support\Definitions\AboutSectionDefinition;
use Simtabi\Laranail\Package\Tools\Support\Definitions\AutoSeederDefinition;
use Simtabi\Laranail\Package\Tools\Support\Definitions\DoctorCheckDefinition;
use Simtabi\Laranail\Package\Tools\Support\Definitions\InstallCommandDefinition;
use Simtabi\Laranail\Package\Tools\Support\Definitions\ScheduledCommandDefinition;
use Simtabi\Laranail\Package\Tools\Support\Scheduling\TimeOfDay;

final class HelloPackageServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('acme/hello')                                      // vendor extracted, short name is "hello"
            ->hasConfigFile()                                         // publishes config/hello.php
            ->hasViews()                                              // resources/views, namespaced "hello"
            ->hasViewComponents('hello', Button::class)               // <x-hello-...> Blade components by prefix
            ->hasTranslations()                                       // resources/lang
            ->hasAssets()                                             // resources/assets -> public/vendor/hello
            ->hasRoute('web')                                         // routes/web.php
            ->hasRoutesWhen('hello.api.enabled', 'api')               // routes/api.php, only when config is truthy at boot
            ->hasMigration('create_greetings_table')                  // database/migrations/...create_greetings_table.php
            ->runsMigrations()                                        // auto-run package migrations on boot
            ->discoversMigrations()                                   // also pick up any other files in database/migrations
            ->hasCommand(HelloCommand::class)                         // php artisan hello

            // blade component wiring beyond the prefix loader: an exact alias
            // (<x-hello-button>) and a class namespace (<x-hello::card>)
            ->hasBladeComponentAlias('hello-button', Button::class)
            ->hasBladeComponentNamespace('Acme\\Hello\\View\\Components', 'hello')

            // livewire components behind a package-level config gate; they
            // register reactively when livewire binds (no-op if it never does)
            ->hasLivewireComponents(
                ['greeting-board' => GreetingBoard::class],
                whenConfig: 'hello.livewire.enabled',
            )

            // model policies, applied via Gate::policy() at boot
            ->registerPolicies([
                Greeting::class => GreetingPolicy::class,
            ])

            // morph aliases from host config: hello.morph_map holds explicit
            // alias => class entries; the user model resolves from
            // auth.providers.users.model under the 'user' alias. non-enforcing.
            ->registerMorphMapFromConfig('hello.morph_map')

            // model observers, applied via Greeting::observe() at boot
            ->registerObserver(Greeting::class, GreetingObserver::class)

            // a named rate limiter (RateLimiter::for); the closure runs per
            // request, so the config read inside stays lazy
            ->registerRateLimiter(
                'hello-api',
                fn (Request $request): Limit => Limit::perMinute(
                    (int) config('hello.api.rate_limit', 60),
                )->by($request->user()?->id ?? $request->ip()),
            )

            // scheduled command #1: tier-1 cron methods (weekly/at) plus
            // tier-2 event modifiers replayed on the scheduler event, with a
            // truthy config gate evaluated at schedule time
            ->registerScheduledCommand(
                ScheduledCommandDefinition::make('hello:digest')
                    ->weekly(Weekday::Monday)
                    ->at(TimeOfDay::pm(5, 30))
                    ->timezone(Timezone::AfricaNairobi)
                    ->environments(Environment::Production, Environment::Staging)
                    ->withoutOverlapping()
                    ->onOneServer()
                    ->whenConfig('hello.digest.enabled'),
            )

            // scheduled command #2: cadence read from config at schedule time
            // ('hourly', '0 3 * * *', 'dailyAt:03:30', or null to opt out);
            // not-null gate: configured means on
            ->registerScheduledCommand(
                ScheduledCommandDefinition::make('hello:prune')
                    ->cadenceFromConfig('hello.prune.cadence', Cadence::Daily)
                    ->whenConfigNotNull('hello.prune.keep_days'),
            )

            // package seeders: registered at boot, executed only when the host
            // app's DatabaseSeeder resolves (php artisan db:seed) — never at
            // boot. explicit list, minus an ignore list, gated and prioritised.
            ->hasPackageSeeders(
                AutoSeederDefinition::make('acme/hello')
                    ->seeders([GreetingSeeder::class])
                    ->ignoreSeeders([LegacyGreetingSeeder::class])
                    ->inNamespace('Acme\\Hello\\Database\\Seeders')
                    ->whenConfig('hello.seed.enabled')
                    ->priority(10)
                    ->options(['fire_events' => true]), // emits SeedingStarted / SeedingFinished
            )

            // Install command (fluent definition): `php artisan hello:install`,
            // hidden from `php artisan list` by default. Steps run in
            // declaration order — built-ins and custom step()s interleave
            // freely — and the command is built lazily, console-only.
            // publishes() tries the namespaced tag (acme::hello-{tag}) and
            // the legacy hello-{tag} form, so it works either way. The legacy
            // callable form — hasInstallCommand(fn ($cmd) => $cmd
            // ->publishConfigFile()->askToRunMigrations()...) — still works.
            ->hasInstallCommand(
                InstallCommandDefinition::make()
                    ->step('welcome', fn (InstallCommand $cmd) => $cmd->info('Installing the Hello package...'))
                    ->publishes('config', 'migrations', 'assets')     // vendor:publish, namespaced + legacy tags
                    ->asksToRunMigrations()                           // prompts, then `php artisan migrate`
                    ->asksToStarRepo('acme/hello')                    // prompts, then opens the repo in a browser
                    ->step('docs pointer', fn (InstallCommand $cmd) => $cmd->info(
                        'Read the docs at https://opensource.simtabi.com/documentation/laranail/package-tools',
                    )),
            )

            // `php artisan about` section (fluent definition): scalars render
            // as-is, closures resolve per field only when `about` runs, and
            // the whole section is config-gated (evaluated at boot).
            ->hasAboutSection(
                AboutSectionDefinition::make('Acme Hello')
                    ->field('Version', '1.0.0')
                    ->field('Greetings', fn (): string => (string) Greeting::query()->count())
                    ->whenConfig('hello.about.enabled', true),
            )

            // Optionally ship the provider itself so apps can publish & edit it.
            ->publishesServiceProvider('HelloServiceProvider')

            // Scan src/ for #[AsArtisanCommand], #[AsRoute], #[AsViewComposer].
            ->discoversWithAttributes()

            // Doctor checks for `php artisan laranail::package-tools.doctor`:
            // fluent DoctorCheckDefinition factories over the bundled check
            // library, plus two hand-written DoctorCheck classes. The report
            // attributes every check to acme/hello automatically; a failed
            // whenConfig gate means the check is never registered.
            ->hasDoctorChecks([
                DoctorCheckDefinition::phpExtensions(['pdo', 'mbstring'])
                    ->named('hello:extensions'),
                DoctorCheckDefinition::configPresent(['API key' => 'hello.api.key'], required: false)
                    ->describe('The key the hello sync worker needs.')
                    ->whenConfig('hello.doctor.config_check', true),
            ])
            ->hasDoctorCheck(HelloHealthCheck::class)
            ->hasDoctorCheck(StorageWritableCheck::class);

        // Closure lifecycle hooks live on the Package object. They receive the
        // Package instance and fire at the matching point in the boot sequence.
        $package
            ->onBeforeBoot(fn (Package $p) => Log::debug('hello: booting', ['name' => $p->shortName()]))
            ->onAfterBoot(fn (Package $p) => Log::debug('hello: booted'));
    }

    /**
     * Override hook: runs after the package registers. Good for container
     * bindings that the rest of the package depends on.
     */
    public function packageRegistered(): void
    {
        $this->app->singleton(GreeterContract::class, Greeter::class);
    }

    /**
     * Override hook: runs after the package finishes booting. Good for work
     * that needs booted services (events, view shares, scheduled tasks).
     */
    public function packageBooted(): void
    {
        // e.g. share a default greeting with every view, register a listener…
    }
}
