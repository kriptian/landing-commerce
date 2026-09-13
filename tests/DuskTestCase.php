<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Collection;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;

abstract class DuskTestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        if (! $this->app) {
            $this->refreshApplication();
        }

        $database = $this->app->make('db')->connection()->getDatabaseName();

        if (! $this->app->environment('testing') || $database !== 'testing') {
            throw new \RuntimeException(
                "Refusing to run Dusk outside the testing database. Environment: {$this->app->environment()}, database: {$database}."
            );
        }

        parent::setUp();
    }

    /**
     * Prepare for Dusk test execution.
     */
    #[BeforeClass]
    public static function prepare(): void
    {
        if (! static::runningInSail()) {
            static::startChromeDriver(['--port=9515']);
        }
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $applicationHost = $_ENV['DUSK_APP_HOST'] ?? env('DUSK_APP_HOST', 'laravel.test');
        $options = (new ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
            '--host-resolver-rules=MAP localhost '.$applicationHost,
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? (static::runningInSail()
                ? 'http://selenium:4444/wd/hub'
                : 'http://localhost:9515'),
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }
}
