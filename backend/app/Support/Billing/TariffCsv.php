<?php

declare(strict_types=1);

namespace App\Support\Billing;

use InvalidArgumentException;

/*
 * The spreadsheet a tariff is uploaded as: the gazette's table, one line per
 * kind of work with its rate on each scale. Gazettes are laid out differently
 * every year, so the firm copies the rates into this one fixed layout rather
 * than the app trying to read the gazette itself. Rates are in rand, such as
 * 49 or 49.50, and a blank scale is one the gazette does not quote. What each
 * role charges is set in the app, not in the file.
 */
class TariffCsv
{
    public const COLUMNS = ['activity_code', 'scale_a', 'scale_b', 'scale_c', 'scale_d'];

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

        $headings = is_array($header) ? static::normaliseHeader($header) : [];

        if ($headings !== self::COLUMNS) {
            fclose($handle);

            throw new InvalidArgumentException(static::headingProblem($headings));
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

    /**
     * What is wrong with the first line, naming the columns to remove or add.
     *
     * @param  list<string>  $headings
     */
    protected static function headingProblem(array $headings): string
    {
        $extra = array_values(array_diff(array_filter($headings, fn (string $heading) => $heading !== ''), self::COLUMNS));
        $missing = array_values(array_diff(self::COLUMNS, $headings));

        $problems = array_filter([
            $extra === [] ? null : 'remove the column'.(count($extra) > 1 ? 's ' : ' ').implode(', ', $extra),
            $missing === [] ? null : 'add the column'.(count($missing) > 1 ? 's ' : ' ').implode(', ', $missing),
            $extra === [] && $missing === [] ? 'put the columns in the template\'s order' : null,
        ]);

        return 'The first line must be the template\'s headings: '.implode(', ', self::COLUMNS).'. To fix it, '.implode(' and ', $problems).'.';
    }

    /** @param  array<int, string|null>  $cells */
    protected static function isBlank(array $cells): bool
    {
        return implode('', array_map(fn ($cell) => trim((string) $cell), $cells)) === '';
    }
}
