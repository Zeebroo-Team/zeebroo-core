<?php

namespace Modules\CRM\Support;

use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Reads an uploaded lead file into a header row and data rows of strings.
 *
 * CSV files come in different shapes: our template and Google Forms exports are UTF-8 and
 * comma separated, a Facebook Lead Ads export is UTF-16 and tab separated, and Excel in some
 * locales saves with semicolons. The encoding is taken from the byte order mark and the
 * delimiter from the header line. XLSX / XLS files are read through PhpSpreadsheet.
 */
final class LeadImportReader
{
    public const MAX_ROWS = 5000;

    public const EXTENSIONS = ['csv', 'txt', 'tsv', 'xlsx', 'xls'];

    /**
     * @return array{headers: list<string>, rows: array<int, list<string>>} rows keyed by their line number in the file
     */
    public function read(string $path, string $extension): array
    {
        $table = in_array(strtolower($extension), ['xlsx', 'xls'], true)
            ? $this->readSpreadsheet($path)
            : $this->readCsv($path);

        $headerRow = array_shift($table);
        if ($headerRow === null || $this->isBlank($headerRow)) {
            throw ValidationException::withMessages(['file' => 'The file is empty — the first row must hold the column names.']);
        }

        $headers = [];
        foreach ($headerRow as $i => $header) {
            $header    = trim(preg_replace('/^\x{FEFF}/u', '', (string) $header));
            $headers[] = $header === '' ? 'Column ' . ($i + 1) : $header;
        }

        $rows = [];
        foreach ($table as $i => $cells) {
            if ($this->isBlank($cells)) {
                continue;
            }
            $cells = array_slice(array_pad(array_map(fn ($v) => trim((string) $v), $cells), count($headers), ''), 0, count($headers));
            // Line 1 is the header row
            $rows[$i + 2] = $cells;

            if (count($rows) > self::MAX_ROWS) {
                throw ValidationException::withMessages(['file' => 'The file has more than ' . number_format(self::MAX_ROWS) . ' rows. Split it into smaller files.']);
            }
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'The file has a header row but no leads.']);
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /** @return list<list<string>> */
    private function readCsv(string $path): array
    {
        $text = $this->toUtf8((string) file_get_contents($path));

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $text);
        rewind($handle);

        $delimiter = $this->delimiter(strtok($text, "\n") ?: '');
        $table     = [];
        while (($line = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $table[] = $line;
        }
        fclose($handle);

        return $table;
    }

    /** @return list<list<string>> */
    private function readSpreadsheet(string $path): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $sheet  = $reader->load($path)->getActiveSheet();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => 'The spreadsheet could not be read. Save it again as .xlsx or .csv and retry.']);
        }

        return array_map(
            fn (array $row) => array_map(fn ($v) => $v === null ? '' : (is_float($v) && floor($v) === $v ? (string) (int) $v : (string) $v), $row),
            $sheet->toArray(null, true, false, false),
        );
    }

    private function toUtf8(string $bytes): string
    {
        return match (true) {
            str_starts_with($bytes, "\xEF\xBB\xBF") => substr($bytes, 3),
            str_starts_with($bytes, "\xFF\xFE")     => mb_convert_encoding(substr($bytes, 2), 'UTF-8', 'UTF-16LE'),
            str_starts_with($bytes, "\xFE\xFF")     => mb_convert_encoding(substr($bytes, 2), 'UTF-8', 'UTF-16BE'),
            mb_check_encoding($bytes, 'UTF-8')      => $bytes,
            default                                 => mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252'),
        };
    }

    /** The separator used most in the header line, outside quotes. */
    private function delimiter(string $headerLine): string
    {
        $unquoted = preg_replace('/"[^"]*"/', '', $headerLine);
        $counts   = ['\t' => substr_count($unquoted, "\t"), ',' => substr_count($unquoted, ','), ';' => substr_count($unquoted, ';')];
        arsort($counts);
        $best = array_key_first($counts);

        return $counts[$best] === 0 ? ',' : ($best === '\t' ? "\t" : $best);
    }

    private function isBlank(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
