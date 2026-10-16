<?php

namespace App\Services\Ai;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Turns an attached CSV / Excel / text file into plain text (and rows) that
 * the assistant can read — e.g. an employee list exported from another payroll system.
 */
class AttachmentReader
{
    public const ACCEPT = ['csv', 'txt', 'tsv', 'xlsx', 'xls'];

    /**
     * @return array{name:string, text:string, rows:array<int,array<string,string>>}
     */
    public function read(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $rows = [];

        if (in_array($ext, ['xlsx', 'xls'], true)) {
            $sheet = IOFactory::load($file->getRealPath())->getActiveSheet();
            $raw = $sheet->toArray(null, true, true, false);
        } elseif (in_array($ext, ['csv', 'tsv'], true)) {
            $content = file_get_contents($file->getRealPath());
            $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
            $delimiter = $ext === 'tsv' ? "\t" : $this->guessDelimiter($content);
            $raw = array_map(fn ($line) => str_getcsv($line, $delimiter), preg_split('/\r\n|\r|\n/', trim($content)));
        } else {
            $text = file_get_contents($file->getRealPath());

            return ['name' => $file->getClientOriginalName(), 'text' => $text, 'rows' => []];
        }

        $rows = $this->combine($raw);
        $headers = $rows ? array_keys($rows[0]) : [];

        $text = collect([$headers])->merge(array_map('array_values', $rows))
            ->map(fn ($r) => implode(' | ', $r))->implode("\n");

        return ['name' => $file->getClientOriginalName(), 'text' => $text, 'rows' => $rows];
    }

    /**
     * Rows from text pasted out of Excel / Google Sheets (tab separated) or a CSV.
     *
     * @return array<int,array<string,string>>
     */
    public function rowsFromText(string $content): array
    {
        $content = trim(preg_replace('/^\xEF\xBB\xBF/', '', $content));
        if ($content === '') {
            return [];
        }
        $delimiter = $this->guessDelimiter($content);
        $raw = array_map(fn ($line) => str_getcsv($line, $delimiter), preg_split('/\r\n|\r|\n/', $content));

        return $this->combine($raw);
    }

    /** First non-empty row becomes the headings; blank/duplicate headings get names. */
    public function combine(array $raw): array
    {
        $raw = array_values(array_filter($raw, fn ($r) => array_filter((array) $r, fn ($c) => trim((string) $c) !== '')));
        if (! $raw) {
            return [];
        }
        $headers = [];
        foreach (array_shift($raw) as $i => $h) {
            $h = trim((string) $h) ?: 'Column ' . ($i + 1);
            while (in_array($h, $headers, true)) {
                $h .= ' (2)';
            }
            $headers[] = $h;
        }
        $rows = [];
        foreach ($raw as $r) {
            $cells = array_map(fn ($c) => trim((string) $c), array_slice(array_values($r), 0, count($headers)));
            $rows[] = array_combine($headers, array_pad($cells, count($headers), ''));
        }

        return $rows;
    }

    private function guessDelimiter(string $content): string
    {
        $first = strtok($content, "\n");
        $counts = [',' => substr_count($first, ','), ';' => substr_count($first, ';'), "\t" => substr_count($first, "\t"), '|' => substr_count($first, '|')];
        arsort($counts);

        return array_key_first($counts);
    }
}
