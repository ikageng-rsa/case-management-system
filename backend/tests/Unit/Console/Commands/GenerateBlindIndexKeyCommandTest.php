<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands;

use Illuminate\Console\Command;
use Tests\TestCase;

class GenerateBlindIndexKeyCommandTest extends TestCase
{
    protected string $environmentPath;

    protected string $environmentFile = '.env.blind-index-testing';

    /*
     * The command rewrites the environment file in place, so every test points
     * the application at a throwaway one instead of the developer's own .env.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->environmentPath = sys_get_temp_dir().'/blind-index-'.uniqid();

        mkdir($this->environmentPath);

        $this->app->useEnvironmentPath($this->environmentPath);
        $this->app->loadEnvironmentFrom($this->environmentFile);
    }

    protected function tearDown(): void
    {
        if (is_file($this->environmentFilePath())) {
            unlink($this->environmentFilePath());
        }

        if (is_dir($this->environmentPath)) {
            rmdir($this->environmentPath);
        }

        parent::tearDown();
    }

    public function test_it_uncomments_and_sets_the_key(): void
    {
        $this->writeEnvironmentFile("APP_KEY=base64:existing\n# BLIND_INDEX_KEY=\nAPP_URL=http://localhost\n");

        $this->artisan('blind-index:generate')
            ->expectsOutputToContain('Blind index key set successfully.')
            ->assertExitCode(Command::SUCCESS);

        $this->assertMatchesRegularExpression(
            '/^BLIND_INDEX_KEY=base64:[A-Za-z0-9+\/]{43}=$/m',
            $this->environmentFileContents(),
        );

        $this->assertStringNotContainsString('# BLIND_INDEX_KEY=', $this->environmentFileContents());
    }

    public function test_it_appends_the_key_when_the_variable_is_missing(): void
    {
        $this->writeEnvironmentFile("APP_KEY=base64:existing\n");

        $this->artisan('blind-index:generate')->assertExitCode(Command::SUCCESS);

        $contents = $this->environmentFileContents();

        $this->assertStringContainsString('APP_KEY=base64:existing', $contents);
        $this->assertMatchesRegularExpression('/^BLIND_INDEX_KEY=base64:/m', $contents);
    }

    public function test_it_leaves_an_existing_key_alone(): void
    {
        $this->writeEnvironmentFile("BLIND_INDEX_KEY=base64:original\n");

        $this->artisan('blind-index:generate')
            ->expectsOutputToContain('BLIND_INDEX_KEY is already set.')
            ->assertExitCode(Command::FAILURE);

        $this->assertStringContainsString('BLIND_INDEX_KEY=base64:original', $this->environmentFileContents());
    }

    public function test_it_replaces_an_existing_key_when_forced(): void
    {
        $this->writeEnvironmentFile("BLIND_INDEX_KEY=base64:original\n");

        $this->artisan('blind-index:generate', ['--force' => true])
            ->assertExitCode(Command::SUCCESS);

        $contents = $this->environmentFileContents();

        $this->assertStringNotContainsString('base64:original', $contents);
        $this->assertMatchesRegularExpression('/^BLIND_INDEX_KEY=base64:/m', $contents);
    }

    /*
     * composer setup aborts its remaining steps on a non-zero exit, so the
     * command has to succeed quietly when a key is already in place.
     */
    public function test_it_succeeds_quietly_when_a_key_exists_and_if_missing_is_given(): void
    {
        $this->writeEnvironmentFile("BLIND_INDEX_KEY=base64:original\n");

        $this->artisan('blind-index:generate', ['--if-missing' => true])
            ->expectsOutputToContain('leaving it alone')
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame("BLIND_INDEX_KEY=base64:original\n", $this->environmentFileContents());
    }

    public function test_it_generates_a_key_when_if_missing_finds_none(): void
    {
        $this->writeEnvironmentFile("# BLIND_INDEX_KEY=\n");

        $this->artisan('blind-index:generate', ['--if-missing' => true])
            ->assertExitCode(Command::SUCCESS);

        $this->assertMatchesRegularExpression('/^BLIND_INDEX_KEY=base64:/m', $this->environmentFileContents());
    }

    public function test_it_treats_an_empty_key_as_unset(): void
    {
        $this->writeEnvironmentFile("BLIND_INDEX_KEY=\n");

        $this->artisan('blind-index:generate')->assertExitCode(Command::SUCCESS);

        $this->assertMatchesRegularExpression('/^BLIND_INDEX_KEY=base64:/m', $this->environmentFileContents());
    }

    public function test_it_shows_a_key_without_touching_the_environment_file(): void
    {
        $this->writeEnvironmentFile("BLIND_INDEX_KEY=base64:original\n");

        $this->artisan('blind-index:generate', ['--show' => true])
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame("BLIND_INDEX_KEY=base64:original\n", $this->environmentFileContents());
    }

    public function test_it_fails_when_there_is_no_environment_file(): void
    {
        $this->artisan('blind-index:generate')
            ->expectsOutputToContain('No environment file found.')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_it_generates_a_different_key_each_time(): void
    {
        $this->writeEnvironmentFile("BLIND_INDEX_KEY=\n");

        $this->artisan('blind-index:generate')->assertExitCode(Command::SUCCESS);
        $first = $this->environmentFileContents();

        $this->artisan('blind-index:generate', ['--force' => true])->assertExitCode(Command::SUCCESS);

        $this->assertNotSame($first, $this->environmentFileContents());
    }

    protected function writeEnvironmentFile(string $contents): void
    {
        file_put_contents($this->environmentFilePath(), $contents);
    }

    protected function environmentFileContents(): string
    {
        return file_get_contents($this->environmentFilePath());
    }

    protected function environmentFilePath(): string
    {
        return $this->environmentPath.'/'.$this->environmentFile;
    }
}
