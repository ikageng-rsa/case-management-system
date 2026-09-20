<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/*
 * The blind index key is the HMAC secret behind every searchable hash of an
 * encrypted column. It is generated once per environment and then left alone:
 * rotating it orphans every stored hash, which can only be rebuilt from the
 * decrypted values.
 */
class GenerateBlindIndexKeyCommand extends Command
{
    protected $signature = 'blind-index:generate
                    {--show : Display the key instead of modifying files}
                    {--force : Replace a key that is already set}
                    {--if-missing : Leave an existing key alone instead of failing}';

    protected $description = 'Set the blind index key used to hash encrypted columns for search';

    public function handle(): int
    {
        $key = $this->generateKey();

        if ($this->option('show')) {
            $this->line($key);

            return self::SUCCESS;
        }

        if ($this->keyIsAlreadySet() && ! $this->option('force')) {
            if ($this->option('if-missing')) {
                $this->comment('BLIND_INDEX_KEY is already set — leaving it alone.');

                return self::SUCCESS;
            }

            $this->error('BLIND_INDEX_KEY is already set.');
            $this->newLine();
            $this->warn('Rotating it invalidates every stored blind index hash.');
            $this->warn('Existing hashes must be rebuilt from the decrypted values before lookups will work again.');
            $this->newLine();
            $this->comment('Re-run with --force if that is what you intend.');

            return self::FAILURE;
        }

        if (! $this->writeKeyToEnvironmentFile($key)) {
            return self::FAILURE;
        }

        config(['blind-index.key' => $key]);

        $this->info('Blind index key set successfully.');

        return self::SUCCESS;
    }

    // Matches the shape of APP_KEY, which is what the config falls back to.
    protected function generateKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }

    /*
     * Read from the environment file rather than the config, because the config
     * falls back to APP_KEY — which would report a key that was never set here.
     */
    protected function keyIsAlreadySet(): bool
    {
        $contents = $this->environmentFileContents();

        if ($contents === null) {
            return false;
        }

        if (preg_match('/^BLIND_INDEX_KEY=(.*)$/m', $contents, $matches) !== 1) {
            return false;
        }

        return trim($matches[1]) !== '';
    }

    protected function writeKeyToEnvironmentFile(string $key): bool
    {
        $contents = $this->environmentFileContents();

        if ($contents === null) {
            $this->error('No environment file found. Copy .env.example to .env first.');

            return false;
        }

        file_put_contents(
            $this->laravel->environmentFilePath(),
            $this->replaceKeyIn($contents, $key),
        );

        return true;
    }

    /*
     * The variable may be set, commented out as it is in .env.example, or
     * missing altogether from an older environment file.
     */
    protected function replaceKeyIn(string $contents, string $key): string
    {
        $line = 'BLIND_INDEX_KEY='.$key;

        foreach (['/^BLIND_INDEX_KEY=.*$/m', '/^#\s*BLIND_INDEX_KEY=.*$/m'] as $pattern) {
            $replaced = preg_replace($pattern, $line, $contents, 1, $count);

            if ($count > 0) {
                return $replaced;
            }
        }

        return rtrim($contents, "\n")."\n\n".$line."\n";
    }

    protected function environmentFileContents(): ?string
    {
        $path = $this->laravel->environmentFilePath();

        return is_file($path) ? file_get_contents($path) : null;
    }
}
