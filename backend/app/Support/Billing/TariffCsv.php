<?php

declare(strict_types=1);

namespace App\Support\Billing;

use InvalidArgumentException;

/*
 * The spreadsheet a tariff is uploaded as. Gazettes are laid out differently
 * every year, so the firm copies the rates into this one fixed layout rather
 * than the app trying to read the gazette itself. Rates are in rand, such as
 * 49 or 49.50, and a blank scale or role means the rate applies to any.
 */
class TariffCsv
{
    public const COLUMNS = ['activity_code', 'scale', 'role', 'unit_rate', 'time_rate', 'time_block_minutes'];

    /**
     * The rows of the file, keyed by column, each with the line it is on.
     *
     * @return list<array<string, string|int>>
     */
    public static function read(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new InvalidArgumentException('The file could not be read.');
        }

        $header = fgetcsv($handle, escape: '');

        if (! is_array($header) || static::normaliseHeader($header) !== self::COLUMNS) {
            fclose($handle);

            throw new InvalidArgumentException('The first line must be the template\'s headings: '.implode(', ', self::COLUMNS).'.');
        }

        $rows = [];
        $line = 1;

        while (($cells = fgetcsv($handle, escape: '')) !== false) {
            $line++;

            if (static::isBlank($cells)) {
                continue;
            }

            $cells = array_pad(array_map(fn ($cell) => trim((string) $cell), $cells), count(self::COLUMNS), '');

            $rows[] = ['line' => $line] + array_combine(self::COLUMNS, array_slice($cells, 0, count(self::COLUMNS)));
        }

        fclose($handle);

        return $rows;
    }

    /** An amount in rand as cents, or null when it is not one. */
    public static function cents(string $amount): ?int
    {
        $amount = str_replace([' ', 'R', 'r'], '', $amount);

        if (preg_match('/^(\d+)(?:[.,](\d{1,2}))?$/', $amount, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1] * 100 + (int) str_pad($matches[2] ?? '0', 2, '0');
    }

    /**
     * A file in the template's layout, with the given rows under the headings.
     *
     * @param  list<list<string>>  $rows
     */
    public static function write(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        foreach ([self::COLUMNS, ...$rows] as $row) {
            fputcsv($handle, $row, escape: '');
        }

        rewind($handle);
        $contents = (string) stream_get_contents($handle);
        fclose($handle);

        return $contents;
    }

    /**
     * @param  array<int, string|null>  $header
     * @return list<string>
     */
    protected static function normaliseHeader(array $header): array
    {
        // A spreadsheet saved as UTF-8 starts with a byte order mark.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);

        return array_map(fn ($cell) => strtolower(trim((string) $cell)), $header);
    }

    /** @param  array<int, string|null>  $cells */
    protected static function isBlank(array $cells): bool
    {
        return implode('', array_map(fn ($cell) => trim((string) $cell), $cells)) === '';
    }
}
