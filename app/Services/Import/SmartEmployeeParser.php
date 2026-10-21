<?php

namespace App\Services\Import;

use App\Models\Bank;
use App\Models\Employee;
use App\Services\EmployeeColumnMapper;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Makes sense of employee lists in whatever shape they arrive:
 *
 *  - Excel / CSV files of any layout: title rows above the headings, several
 *    sheets, unusual column names, or no headings at all (columns are then
 *    recognised from their values: NRCs, phones, emails, amounts, dates…).
 *  - Pasted spreadsheet selections.
 *  - Messy free text: a WhatsApp message, an email, notes — one person per
 *    paragraph, per line, per numbered item or per "Name:" label.
 *
 * Everything is returned in the shape the import wizard already uses
 * (headers, samples, mapping header→field, rows keyed by header), so the
 * person can still check and correct the matching before anything is saved.
 * Nothing here writes to the database.
 */
class SmartEmployeeParser
{
    public const MAX_HEADER_SCAN = 15;

    private const NRC = '/\b(\d{6})\s*\/\s*(\d{2})\s*\/\s*(\d)\b/';
    private const EMAIL = '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i';
    private const PHONE = '/(?<![\d\/])(?:\+?\s?260[\s\-]?|\(?0)\)?\s?([79](?:[\s\-]?\d){8})(?![\d\/])/';
    private const MONEY = '/(?:\b(?:K|ZMW|ZK)\s?(?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d{1,2})?\b|\b(?:\d{1,3}(?:,\d{3})+|\d{3,7})(?:\.\d{1,2})?\s?(?:K|ZMW|kwacha)\b)/i';
    private const DATE = '/\b(\d{1,2}[\/.\-]\d{1,2}[\/.\-]\d{2,4}|\d{4}-\d{2}-\d{2}|\d{1,2}(?:st|nd|rd|th)?[\s\-](?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\.?[\s\-,]+\d{2,4}|(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\.?\s+\d{1,2}(?:st|nd|rd|th)?,?\s+\d{4})\b/i';

    /** Words that label a value in free text → field. Longest first matters. */
    private const LABELS = [
        'next of kin phone' => 'next_of_kin_phone', 'next of kin' => 'next_of_kin_name', 'emergency phone' => 'emergency_phone',
        'emergency contact' => 'emergency_name', 'take home' => 'net_salary', 'take-home' => 'net_salary', 'net pay' => 'net_salary',
        'net salary' => 'net_salary', 'basic pay' => 'salary', 'basic salary' => 'salary', 'gross pay' => 'salary', 'gross salary' => 'salary',
        'date of birth' => 'date_of_birth', 'start date' => 'contract_start', 'date joined' => 'contract_start', 'date engaged' => 'contract_start',
        'employed since' => 'contract_start', 'started' => 'contract_start', 'joined' => 'contract_start', 'engaged' => 'contract_start',
        'born' => 'date_of_birth', 'dob' => 'date_of_birth', 'd.o.b' => 'date_of_birth', 'birthday' => 'date_of_birth',
        'contract end' => 'contract_end', 'contract expires' => 'contract_end',
        'account number' => 'bank_account_no', 'account no' => 'bank_account_no', 'acc no' => 'bank_account_no', 'a/c no' => 'bank_account_no',
        'a/c' => 'bank_account_no', 'acc' => 'bank_account_no', 'account' => 'bank_account_no',
        'bank branch' => 'bank_branch', 'bank' => 'bank_name',
        'napsa no' => 'ssn', 'napsa' => 'ssn', 'social security' => 'ssn', 'ssn' => 'ssn',
        'nhima no' => 'nhima_no', 'nhima' => 'nhima_no', 'tpin' => 'tpin', 'tax pin' => 'tpin',
        'nrc no' => 'nrc_no', 'nrc' => 'nrc_no', 'national id' => 'nrc_no', 'passport' => 'passport_number',
        'employee number' => 'employee_id', 'employee no' => 'employee_id', 'staff no' => 'employee_id', 'man no' => 'employee_id', 'emp no' => 'employee_id',
        'job title' => 'position', 'position' => 'position', 'designation' => 'position', 'role' => 'position', 'title' => 'position',
        'department' => 'department', 'dept' => 'department', 'section' => 'department',
        'branch' => 'branch', 'station' => 'branch', 'location' => 'branch', 'site' => 'branch',
        'supervisor' => 'supervisor', 'manager' => 'supervisor', 'reports to' => 'supervisor',
        'leave days' => 'leave_days_entitled', 'leave balance' => 'leave_days_balance',
        'personal email' => 'personal_email', 'email' => 'company_email', 'e-mail' => 'company_email',
        'mobile' => 'primary_phone', 'phone' => 'primary_phone', 'cell' => 'primary_phone', 'tel' => 'primary_phone', 'contact' => 'primary_phone',
        'gender' => 'gender', 'sex' => 'gender', 'nationality' => 'nationality',
        'first name' => 'first_name', 'surname' => 'last_name', 'last name' => 'last_name', 'middle name' => 'middle_name',
        'full name' => 'name', 'names' => 'name', 'name' => 'name',
        'salary' => 'salary', 'gross' => 'salary', 'basic' => 'salary', 'net' => 'net_salary', 'pay' => 'salary', 'wage' => 'salary',
        'type' => 'employment_nature', 'contract' => 'employment_nature', 'employment' => 'employment_nature',
    ];

    private const JOB_WORDS = ['accountant', 'driver', 'cashier', 'manager', 'clerk', 'supervisor', 'officer', 'assistant', 'secretary',
        'guard', 'cleaner', 'teacher', 'nurse', 'engineer', 'technician', 'mechanic', 'operator', 'rep', 'representative', 'receptionist',
        'administrator', 'director', 'coordinator', 'controller', 'analyst', 'developer', 'storekeeper', 'store keeper', 'messenger',
        'cook', 'chef', 'waiter', 'waitress', 'electrician', 'plumber', 'welder', 'labourer', 'laborer', 'gardener', 'attendant', 'agent',
        'auditor', 'lecturer', 'pharmacist', 'consultant', 'executive', 'intern', 'handyman', 'foreman', 'bookkeeper', 'buyer', 'lead', 'head'];

    private const FALLBACK_BANKS = ['Zanaco', 'Stanbic', 'Absa', 'Barclays', 'FNB', 'First National Bank', 'Atlas Mara', 'BancABC', 'Indo Zambia',
        'Access Bank', 'Ecobank', 'Citibank', 'Standard Chartered', 'UBA', 'United Bank for Africa', 'Investrust', 'ZICB', 'First Capital',
        'AB Bank', 'Bank of China', 'Natsave', 'Zambia National Commercial Bank', 'Indo-Zambia'];

    private array $notes = [];
    private ?array $banks = null;
    private ?array $vocab = null;

    /* ================================================================== */
    /*  Entry points                                                       */
    /* ================================================================== */

    /**
     * @return array{source:string, mode:string, headers:array, samples:array, mapping:array, rows:array, notes:array}
     */
    public function fromFile(UploadedFile $file): array
    {
        $this->notes = [];
        $ext = strtolower($file->getClientOriginalExtension());
        $name = $file->getClientOriginalName();

        if (in_array($ext, ['xlsx', 'xls', 'ods'], true)) {
            return $this->fromSheets($this->readWorkbook($file->getRealPath()), $name);
        }

        $content = (string) file_get_contents($file->getRealPath());
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        return $this->fromText($content, $name, $ext === 'tsv' ? "\t" : null);
    }

    public function fromText(string $text, string $source = 'Pasted list', ?string $delimiter = null): array
    {
        if ($delimiter === null) {
            $this->notes = [];
        }
        $text = trim(str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text));
        if ($text === '') {
            return $this->result($source, 'text', [], [], []);
        }

        if ($grid = $this->asGrid($text, $delimiter)) {
            $table = $this->fromSheets(['Pasted' => $grid], $source, true);
            if ($table['rows'] || $delimiter) {
                return $table;
            }
            $this->notes = [];
        }

        $people = $this->freeText($text);

        return $this->peopleResult($people, $source);
    }

    /**
     * @param  array<string, array<int, array<int, mixed>>>  $sheets  sheet name => raw grid
     */
    public function fromSheets(array $sheets, string $source, bool $pasted = false): array
    {
        $tables = [];

        foreach ($sheets as $sheetName => $grid) {
            $table = $this->readTable($this->trimGrid($grid));
            if ($table && $table['rows'] && $table['has_name']) {
                $table['sheet'] = (string) $sheetName;
                $tables[] = $table;
            }
        }

        if (! $tables) {
            // No sheet looked like a table — maybe it's a sheet of notes. Read it as text.
            $text = collect($sheets)->flatten(1)->map(fn ($r) => implode(' ', array_filter(array_map(fn ($c) => trim((string) $c), (array) $r), 'strlen')))
                ->filter()->implode("\n");
            if (trim($text) !== '' && ! $pasted) {
                $people = $this->freeText($text);
                if ($people) {
                    $this->notes[] = 'The file had no clear columns, so we read it like a note.';

                    return $this->peopleResult($people, $source);
                }
            }

            return $this->result($source, 'table', [], [], []);
        }

        // Merge every sheet that holds people (e.g. one sheet per branch). Columns are lined up
        // by what they hold, not by their heading, so differently laid-out sheets still combine.
        $headers = [];
        $mapping = [];
        $rows = [];
        $multi = count($tables) > 1;
        $fieldHeader = []; // field => merged heading
        foreach ($tables as $t) {
            $rename = [];
            foreach ($t['headers'] as $h) {
                $f = $t['mapping'][$h] ?? null;
                if ($multi && $f && isset($fieldHeader[$f])) {
                    $rename[$h] = $fieldHeader[$f];

                    continue;
                }
                $target = $h;
                if ($multi && $f && preg_match('/^Column [A-Z]+$/', $h)) {
                    $target = EmployeeColumnMapper::FIELDS[$f][0];
                }
                while ($multi && in_array($target, $headers, true) && ($mapping[$target] ?? null) !== $f) {
                    $target .= ' (' . $t['sheet'] . ')';
                }
                if (! in_array($target, $headers, true)) {
                    $headers[] = $target;
                    $mapping[$target] = $f;
                }
                if ($f) {
                    $fieldHeader[$f] = $target;
                }
                $rename[$h] = $target;
            }
            foreach ($t['rows'] as $r) {
                $out = $multi ? ['Sheet' => $t['sheet']] : [];
                foreach ($r as $h => $v) {
                    $key = $rename[$h] ?? $h;
                    if (($out[$key] ?? '') === '') {
                        $out[$key] = $v;
                    }
                }
                $rows[] = $out;
            }
            if (! $pasted) {
                $this->notes[] = ($multi ? "Sheet “{$t['sheet']}”: " : '') . count($t['rows']) . ' ' . (count($t['rows']) === 1 ? 'person' : 'people')
                    . ($t['header_row'] ? ' · headings found on row ' . $t['header_row'] : ' · no heading row, so columns were recognised from their values')
                    . ($t['skipped'] ? ' · skipped ' . $t['skipped'] . ' title/total row' . ($t['skipped'] === 1 ? '' : 's') : '');
            }
        }
        if ($multi) {
            array_unshift($headers, 'Sheet');
            $mapping = ['Sheet' => (! isset($fieldHeader['branch']) && $this->sheetLooksLikeBranch($tables)) ? 'branch' : null] + $mapping;
        }

        // Make sure every row has every header (sheets can differ).
        $rows = array_map(fn ($r) => array_merge(array_fill_keys($headers, ''), $r), $rows);
        $mapping = $this->dedupeMapping($mapping);

        return $this->result($source, 'table', $headers, $mapping, $this->cleanRows($rows, $mapping));
    }

    /**
     * The parsed result as one array of ZamPayroll fields per person
     * (used by AI mode, which skips the column-matching step).
     */
    public static function employees(array $result): array
    {
        return array_values(array_filter(EmployeeColumnMapper::apply($result['rows'], $result['mapping'])));
    }

    /* ================================================================== */
    /*  Workbooks and grids                                                */
    /* ================================================================== */

    /** @return array<string, array<int, array<int, mixed>>> */
    public function readWorkbook(string $path): array
    {
        $book = IOFactory::load($path);
        $sheets = [];
        foreach ($book->getWorksheetIterator() as $ws) {
            if ($ws->getSheetState() !== 'visible') {
                continue;
            }
            $maxRow = min($ws->getHighestDataRow(), 5000);
            $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($ws->getHighestDataColumn());
            $maxCol = min($maxCol, 80);
            $grid = [];
            for ($r = 1; $r <= $maxRow; $r++) {
                $row = [];
                for ($c = 1; $c <= $maxCol; $c++) {
                    $cell = $ws->getCell([$c, $r]);
                    $row[] = $this->cellValue($cell);
                }
                $grid[] = $row;
            }
            $sheets[$ws->getTitle()] = $grid;
        }

        return $sheets;
    }

    private function cellValue($cell): string
    {
        try {
            $v = $cell->getCalculatedValue();
        } catch (\Throwable $e) {
            $v = $cell->getValue();
        }
        if ($v === null) {
            return '';
        }
        if ($v instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
            return trim($v->getPlainText());
        }
        if (is_bool($v)) {
            return $v ? 'Yes' : 'No';
        }
        if (is_numeric($v) && ExcelDate::isDateTime($cell) && (float) $v > 0 && (float) $v < 80000) {
            return ExcelDate::excelToDateTimeObject((float) $v)->format('Y-m-d');
        }
        if (is_float($v) && floor($v) == $v && abs($v) >= 1e6) {
            return sprintf('%.0f', $v); // long numbers (accounts) without scientific notation
        }

        return trim((string) $v);
    }

    /** Remove empty rows/columns. */
    private function trimGrid(array $grid): array
    {
        // Keys are kept so we can tell people the real row number of the headings.
        $grid = array_filter(array_map(fn ($r) => array_map(fn ($c) => trim((string) $c), array_values((array) $r)), $grid),
            fn ($r) => implode('', $r) !== '');
        if (! $grid) {
            return [];
        }
        $width = max(array_map('count', $grid));
        $keep = [];
        for ($c = 0; $c < $width; $c++) {
            foreach ($grid as $r) {
                if (($r[$c] ?? '') !== '') {
                    $keep[] = $c;
                    break;
                }
            }
        }

        return array_map(fn ($r) => array_map(fn ($c) => $r[$c] ?? '', $keep), $grid); // keys preserved
    }

    /**
     * Find the heading row (anywhere in the first rows), the data under it,
     * and the field for every column.
     */
    private function readTable(array $grid): ?array
    {
        if (count($grid) < 1) {
            return null;
        }
        $rowNumbers = array_keys($grid);
        $grid = array_values($grid);

        // 1. Heading row = the row whose cells best match known column names.
        $best = null;
        $bestScore = 0;
        foreach (array_slice($grid, 0, self::MAX_HEADER_SCAN, true) as $i => $row) {
            $score = 0;
            foreach ($row as $cell) {
                if ($cell !== '' && ! preg_match('/\d{3,}/', $cell) && EmployeeColumnMapper::guess($cell)) {
                    $score++;
                }
            }
            if ($score > $bestScore) {
                [$best, $bestScore] = [$i, $score];
            }
        }

        $width = count($grid[0]);
        if ($best !== null && ($bestScore >= 2 || ($bestScore >= 1 && $width <= 2))) {
            $headerRow = $grid[$best];
            // Two-row headings ("Bank" over "Name | Account") — join with the row above when it has text over blanks.
            $headers = [];
            foreach ($headerRow as $c => $h) {
                $above = $best > 0 ? ($grid[$best - 1][$c] ?? '') : '';
                $h = $h !== '' ? $h : ($above !== '' ? $above : 'Column ' . $this->colLetter($c));
                while (in_array($h, $headers, true)) {
                    $h .= ' (2)';
                }
                $headers[] = $h;
            }
            $data = array_slice($grid, $best + 1);
            $headerAt = $rowNumbers[$best] + 1;
        } else {
            $headers = array_map(fn ($c) => 'Column ' . $this->colLetter($c), range(0, max(0, $width - 1)));
            $data = $grid;
            $headerAt = 0;
        }

        $mapping = EmployeeColumnMapper::guessAll($headers);

        // 2. Rows: drop repeated headings, titles, totals and blank lines.
        $rows = [];
        $skipped = 0;
        $headerSig = strtolower(implode('|', $headers));
        foreach ($data as $r) {
            $cells = array_pad(array_slice($r, 0, count($headers)), count($headers), '');
            $filled = array_filter($cells, fn ($c) => $c !== '');
            $first = strtolower(trim((string) (array_values($filled)[0] ?? '')));
            if (count($filled) <= 1 && count($headers) > 2
                || strtolower(implode('|', $cells)) === $headerSig
                || preg_match('/^(grand\s+)?(sub\s*)?totals?\b|^count\b|^prepared by|^approved by|^signed/', $first)) {
                $skipped++;

                continue;
            }
            $rows[] = array_combine($headers, $cells);
        }

        // 3. Columns the headings didn't explain: recognise them from their values.
        $used = array_filter($mapping);
        foreach ($headers as $h) {
            if ($mapping[$h]) {
                continue;
            }
            $values = array_values(array_filter(array_column($rows, $h), fn ($v) => $v !== ''));
            if (! $values) {
                continue;
            }
            $field = $this->guessFromValues($values, $used);
            if ($field) {
                $mapping[$h] = $field;
                $used[$h] = $field;
            }
        }

        // A headerless list often starts with a name column: first mostly-text column.
        if (! array_intersect(['name', 'first_name', 'last_name'], array_filter($mapping))) {
            foreach ($headers as $h) {
                if ($mapping[$h]) {
                    continue;
                }
                $values = array_values(array_filter(array_column($rows, $h), fn ($v) => $v !== ''));
                if ($values && $this->share($values, fn ($v) => $this->looksLikeName($v)) >= 0.6) {
                    $mapping[$h] = $this->share($values, fn ($v) => str_word_count($v) >= 2) >= 0.6 ? 'name' : 'first_name';
                    if ($mapping[$h] === 'first_name') {
                        // and the next text column is probably the surname
                        $idx = array_search($h, $headers, true);
                        $next = $headers[$idx + 1] ?? null;
                        if ($next && ! $mapping[$next]) {
                            $nv = array_values(array_filter(array_column($rows, $next), fn ($v) => $v !== ''));
                            if ($nv && $this->share($nv, fn ($v) => $this->looksLikeName($v)) >= 0.6) {
                                $mapping[$next] = 'last_name';
                            }
                        }
                    }
                    break;
                }
            }
        }

        // Keep people with a name, and rows with real details but no name (the review step flags them).
        $rows = array_values(array_filter($rows, function ($r) use ($mapping) {
            $filled = 0;
            foreach ($mapping as $h => $f) {
                if (! $f || trim((string) ($r[$h] ?? '')) === '') {
                    continue;
                }
                if (in_array($f, ['name', 'first_name', 'last_name'], true)) {
                    return true;
                }
                $filled++;
            }

            return $filled >= 2;
        }));

        return [
            'headers' => $headers,
            'mapping' => $mapping,
            'rows' => $rows,
            'skipped' => $skipped,
            'header_row' => $headerAt,
            'has_name' => (bool) array_intersect(['name', 'first_name', 'last_name'], array_filter($mapping)),
        ];
    }

    /** What kind of values are these? Only answers when most of them agree. */
    public function guessFromValues(array $values, array $used = []): ?string
    {
        $values = array_slice($values, 0, 60);
        $taken = fn ($f) => in_array($f, $used, true);
        $checks = [
            'nrc_no'          => fn ($v) => (bool) preg_match('/^\d{6}\s*\/\s*\d{2}\s*\/\s*\d$/', $v),
            'company_email'   => fn ($v) => (bool) filter_var($v, FILTER_VALIDATE_EMAIL),
            'primary_phone'   => fn ($v) => $this->phone($v) !== null && strlen(preg_replace('/\D/', '', $v)) <= 12,
            'gender'          => fn ($v) => (bool) preg_match('/^(m|f|male|female)$/i', $v),
            'employment_nature' => fn ($v) => (bool) preg_match('/^(permanent|contract|casual|temporary|temp|intern|probation|fixed[\s-]term)/i', $v),
            'bank_name'       => fn ($v) => $this->bankIn($v) !== null && str_word_count($v) <= 6,
        ];
        foreach ($checks as $field => $test) {
            if (! $taken($field) && $this->share($values, $test) >= 0.7) {
                if ($field === 'company_email' && $taken('company_email')) {
                    return 'personal_email';
                }

                return $field;
            }
        }
        if ($taken('company_email') && ! $taken('personal_email') && $this->share($values, fn ($v) => (bool) filter_var($v, FILTER_VALIDATE_EMAIL)) >= 0.7) {
            return 'personal_email';
        }
        if ($taken('primary_phone') && ! $taken('secondary_phone') && $this->share($values, fn ($v) => $this->phone($v) !== null) >= 0.7) {
            return 'secondary_phone';
        }

        // Dates: born long ago → date of birth, otherwise start date.
        if ($this->share($values, fn ($v) => $this->date($v) !== null) >= 0.7) {
            $years = array_filter(array_map(fn ($v) => (int) substr((string) $this->date($v), 0, 4), $values));
            $median = $years ? $years[intdiv(count($years), 2)] : 0;
            sort($years);
            $median = $years ? $years[intdiv(count($years), 2)] : 0;
            $field = $median && $median < (int) date('Y') - 17 ? 'date_of_birth' : 'contract_start';
            if ($taken($field)) {
                $field = $field === 'date_of_birth' ? 'contract_start' : 'contract_end';
            }

            return $taken($field) ? null : $field;
        }

        // Long digit strings → bank account numbers.
        if (! $taken('bank_account_no') && $this->share($values, fn ($v) => (bool) preg_match('/^\d{8,18}$/', preg_replace('/[\s\-]/', '', $v)) && ! str_starts_with(preg_replace('/\D/', '', $v), '0' . '9')) >= 0.7) {
            return 'bank_account_no';
        }

        // Money: amounts in the hundreds or more.
        if ($this->share($values, fn ($v) => $this->amount($v) !== null && $this->amount($v) >= 100) >= 0.8) {
            foreach (['salary', 'net_salary'] as $f) {
                if (! $taken($f)) {
                    return $f;
                }
            }
        }

        // Job titles / departments from words the company already uses.
        $vocab = $this->vocab();
        if (! $taken('position') && $this->share($values, fn ($v) => $this->jobTitle($v) !== null || in_array(Str::lower($v), $vocab['positions'], true)) >= 0.6) {
            return 'position';
        }
        if (! $taken('department') && $vocab['departments'] && $this->share($values, fn ($v) => in_array(Str::lower($v), $vocab['departments'], true)) >= 0.6) {
            return 'department';
        }

        return null;
    }

    /* ================================================================== */
    /*  Free text                                                          */
    /* ================================================================== */

    /**
     * Pasted text that is really a table (tabs, or consistent commas/semicolons/pipes).
     */
    private function asGrid(string $text, ?string $delimiter): ?array
    {
        $lines = array_values(array_filter(explode("\n", $text), fn ($l) => trim($l) !== ''));
        if (count($lines) < 2 && ! $delimiter) {
            return null;
        }

        $candidates = $delimiter ? [$delimiter] : ["\t", ';', '|', ','];
        foreach ($candidates as $d) {
            $counts = array_map(fn ($l) => count(str_getcsv($l, $d)), $lines);
            $common = array_count_values($counts);
            arsort($common);
            $width = (int) array_key_first($common);
            $agree = $common[$width] / count($lines);
            if ($width < 2 || $agree < 0.8) {
                continue;
            }
            $grid = array_map(fn ($l) => array_map('trim', str_getcsv($l, $d)), $lines);
            // "Name: X; NRC: Y" lines are labelled notes, not a table.
            $labelled = count(array_filter(array_merge(...$grid), fn ($c) => preg_match('/^[A-Za-z\/ .]{2,25}\s*[:=]\s*\S/', $c)));
            if ($labelled >= count($lines) * 2) {
                continue;
            }
            if ($d === "\t" || $delimiter) {
                return $grid;
            }
            // Commas also appear in prose: only accept when the first row looks like headings
            // or most columns hold one kind of value.
            $headerHits = count(array_filter($grid[0], fn ($c) => EmployeeColumnMapper::guess($c)));
            if ($headerHits >= 2 || ($width >= 3 && $this->columnsAreConsistent($grid))) {
                return $grid;
            }
        }

        return null;
    }

    private function columnsAreConsistent(array $grid): bool
    {
        $width = count($grid[0]);
        $recognised = 0;
        for ($c = 0; $c < $width; $c++) {
            $vals = array_values(array_filter(array_column($grid, $c), fn ($v) => $v !== ''));
            if ($vals && ($this->guessFromValues($vals) || $this->share($vals, fn ($v) => $this->looksLikeName($v)) >= 0.8)) {
                $recognised++;
            }
        }

        return $recognised >= max(2, (int) ceil($width * 0.6));
    }

    /** @return array<int, array<string, string>> field => value per person */
    public function freeText(string $text): array
    {
        $chunks = $this->splitPeople($text);
        $people = [];
        foreach ($chunks as $chunk) {
            $p = $this->readPerson($chunk);
            if (! empty($p['first_name']) || ! empty($p['name'])) {
                $people[] = $p;
            }
        }

        // A greeting or sign-off ("Hi HR", "Regards, Bupe") reads like a name with nothing else —
        // drop those when the other people do carry details.
        $nameOnly = fn ($p) => ! array_diff(array_keys($p), ['first_name', 'middle_name', 'last_name', 'name', 'gender']);
        $detailed = array_filter($people, fn ($p) => ! $nameOnly($p));
        if (count($detailed) >= 1 && count($detailed) < count($people) && count($people) > 1) {
            $dropped = count($people) - count($detailed);
            $people = array_values($detailed);
            $this->notes[] = "Ignored {$dropped} line" . ($dropped === 1 ? '' : 's') . ' with a name but no other details (like a greeting or signature).';
        }

        return $people;
    }

    /** Break text into one chunk per person. */
    private function splitPeople(string $text): array
    {
        $text = trim($text);

        // Explicit "Name:" labels — split before each one.
        if (preg_match_all('/(^|\n|[;,|]\s*)\s*(?:\d+[.)]\s*)?(?:full\s+)?names?\s*[:=\-]/i', $text) > 1) {
            $parts = preg_split('/(?=(?:^|\n)\s*(?:\d+[.)]\s*)?(?:full\s+)?names?\s*[:=\-])|(?<=[;|])\s*(?=(?:full\s+)?names?\s*[:=\-])/i', $text);

            return array_values(array_filter(array_map('trim', $parts)));
        }

        // Numbered or bulleted items: "1. ...", "2) ...", "- ...", "• ..."
        if (preg_match_all('/^\s*(?:\d{1,4}[.)]|[-*•▪●])\s+/m', $text) > 1) {
            $parts = preg_split('/^\s*(?:\d{1,4}[.)]|[-*•▪●])\s+/m', $text);

            return array_values(array_filter(array_map('trim', $parts)));
        }

        // Paragraphs separated by blank lines.
        $blocks = array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/', $text))));
        if (count($blocks) > 1) {
            return $blocks;
        }

        // One person per line when most lines carry their own name plus details.
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text))));
        if (count($lines) > 1) {
            $selfContained = count(array_filter($lines, fn ($l) => $this->leadingName($l) && $this->hasAnchor($l)));
            if ($selfContained >= max(2, count($lines) * 0.6)) {
                return $lines;
            }
            // Short lines with name then details on following lines → new person at each name-only line.
            $groups = [];
            foreach ($lines as $l) {
                if ($this->leadingName($l) && ! $this->labelledLine($l) && (! $groups || $this->hasAnchor(end($groups)))) {
                    $groups[] = $l;
                } elseif ($groups) {
                    $groups[count($groups) - 1] .= "\n" . $l;
                } else {
                    $groups[] = $l;
                }
            }
            if (count($groups) > 1) {
                return $groups;
            }
        }

        return [$text];
    }

    private function hasAnchor(string $s): bool
    {
        return (bool) (preg_match(self::NRC, $s) || preg_match(self::EMAIL, $s) || preg_match(self::PHONE, $s) || preg_match(self::MONEY, $s));
    }

    private function labelledLine(string $l): bool
    {
        return (bool) preg_match('/^\s*[A-Za-z\/ .]{2,25}\s*[:=]/', $l);
    }

    /** Pull every detail we can recognise out of one person's text. */
    public function readPerson(string $chunk): array
    {
        $p = [];
        $rest = ' ' . preg_replace('/[ \t]+/', ' ', $chunk) . ' ';

        // 1. "Label: value" pairs (split on newlines, semicolons, pipes and commas followed by another label).
        $segments = preg_split('/\n|;|\||,(?=\s*[A-Za-z\/ .]{2,25}\s*[:=])/', $chunk);
        foreach ($segments as $seg) {
            if (! preg_match('/^\s*([A-Za-z\/][A-Za-z\/ .\-]{0,28}?)\s*[:=]\s*(.+?)\s*$/u', $seg, $m)) {
                continue;
            }
            $field = $this->labelField($m[1]);
            $value = trim($m[2], " \t,.");
            if (! $field || $value === '' || isset($p[$field])) {
                continue;
            }
            $remainder = '';
            if (! in_array($field, ['name', 'next_of_kin_address', 'salary', 'net_salary', 'date_of_birth', 'contract_start', 'contract_end', 'probation_end'], true) && str_contains($value, ',')) {
                [$value, $remainder] = array_map('trim', explode(',', $value, 2));
            }
            if ($field === 'bank_name' && ($bank = $this->bankIn($value))) {
                $remainder = trim(trim(str_ireplace($bank, '', $value)) . ' ' . $remainder);
                $value = $bank;
            }
            $value = $this->cleanValue($field, $value);
            if ($value !== null && $value !== '') {
                $p[$field] = $value;
                $rest = str_replace($seg, ' ' . $remainder . ' ', $rest);
            }
        }

        // 2. Unlabelled values recognised by their shape.
        if (! isset($p['nrc_no']) && preg_match(self::NRC, $rest, $m)) {
            $p['nrc_no'] = "{$m[1]}/{$m[2]}/{$m[3]}";
        }
        $rest = preg_replace(self::NRC, ' ', $rest);

        if (preg_match_all(self::EMAIL, $rest, $m)) {
            foreach ($m[0] as $email) {
                $email = Str::lower(rtrim($email, '.'));
                if (! isset($p['company_email'])) {
                    $p['company_email'] = $email;
                } elseif (! isset($p['personal_email']) && $email !== $p['company_email']) {
                    $p['personal_email'] = $email;
                }
            }
            $rest = preg_replace(self::EMAIL, ' ', $rest);
        }

        // Labelled numbers in prose ("NAPSA 912345678", "TPIN 1001234567", "acc 0123…").
        foreach (['tpin' => 'tpin|tax\s*pin', 'ssn' => 'napsa(?:\s*no\.?)?|ssn', 'nhima_no' => 'nhima(?:\s*no\.?)?', 'employee_id' => 'man\s*no\.?|staff\s*no\.?|emp(?:loyee)?\s*(?:no|number|id)\.?',
                  'bank_account_no' => 'a\/c(?:\s*no\.?)?|acc(?:ount)?(?:\s*(?:no|number))?\.?'] as $field => $label) {
            if (! isset($p[$field]) && preg_match('/\b(?:' . $label . ')\s*[#:.\-]?\s*([A-Z0-9][A-Z0-9\-\/ ]{3,24}?\d)(?=\s|,|;|$)/i', $rest, $m)) {
                $p[$field] = preg_replace('/\s+/', '', $m[1]);
                $rest = str_replace($m[0], ' ', $rest);
            }
        }

        if (preg_match_all(self::PHONE, $rest, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $phone = '0' . preg_replace('/\D/', '', $hit[1]);
                if (! isset($p['primary_phone'])) {
                    $p['primary_phone'] = $phone;
                } elseif (! isset($p['secondary_phone']) && $phone !== $p['primary_phone']) {
                    $p['secondary_phone'] = $phone;
                }
                $rest = str_replace($hit[0], ' ', $rest);
            }
        }

        // Dates: "born 12/03/1990", "started 1 Feb 2024", or bare.
        if (preg_match_all('/(?:\b(born|dob|d\.o\.b|birth\w*|start\w*|joined|engaged|employed|since|from|end\w*|expir\w*|until)\b[^0-9A-Za-z\n]{0,6}(?:on\s+)?)?' . substr(self::DATE, 1, -2) . '/i', $rest, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $d = $this->date($hit[2]);
                if (! $d) {
                    continue;
                }
                $label = Str::lower($hit[1] ?? '');
                $field = match (true) {
                    (bool) preg_match('/born|dob|d\.o\.b|birth/', $label) => 'date_of_birth',
                    (bool) preg_match('/end|expir|until/', $label) => 'contract_end',
                    $label !== '' => 'contract_start',
                    (int) substr($d, 0, 4) < (int) date('Y') - 17 => 'date_of_birth',
                    default => 'contract_start',
                };
                if (! isset($p[$field])) {
                    $p[$field] = $d;
                }
                $rest = str_replace($hit[0], ' ', $rest);
            }
        }

        // Money: with a label ("net 8,500", "basic K12000", "earns 9000") or a currency ("K 8,500", "8500 ZMW").
        $num = '(\d{1,3}(?:,\d{3})+(?:\.\d{1,2})?|\d{3,7}(?:\.\d{1,2})?)';
        $moneyRe = '/\b(net|take[\s-]?home|basic|gross|salary|pay|earns|earning|wage)\b[^0-9\n]{0,14}?(?:K|ZMW|ZK)?\s?' . $num . '(?![\d\/])'
            . '|\b(?:K|ZMW|ZK)\s?' . $num . '(?![\d\/])|\b' . $num . '\s?(?:K|ZMW|kwacha)\b/i';
        if (preg_match_all($moneyRe, $rest, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $raw = ($hit[2] ?? '') !== '' ? $hit[2] : ((($hit[3] ?? '') !== '') ? $hit[3] : ($hit[4] ?? ''));
                $amount = $this->amount($raw);
                if ($amount === null || $amount < 50) {
                    continue;
                }
                $label = Str::lower($hit[1] ?? '');
                $field = preg_match('/net|take/', $label) ? 'net_salary' : 'salary';
                if (! isset($p[$field])) {
                    $p[$field] = (string) $amount;
                }
                $rest = str_replace($hit[0], ' ', $rest);
            }
        }

        // Bank name and a long number beside it → account number.
        if (! isset($p['bank_name']) && ($bank = $this->bankIn($rest))) {
            $p['bank_name'] = $bank;
            $rest = preg_replace('/\b' . preg_quote($bank, '/') . '(\s+bank)?\b/i', ' ', $rest, 1);
        }
        if (! isset($p['bank_account_no']) && preg_match('/(?<![\d\/])(\d[\d\- ]{6,20}\d)(?![\d\/])/', $rest, $m)) {
            $digits = preg_replace('/\D/', '', $m[1]);
            if (strlen($digits) >= 8 && strlen($digits) <= 18) {
                $p['bank_account_no'] = $digits;
                $rest = str_replace($m[1], ' ', $rest);
            }
        }

        // Gender, employment type.
        if (! isset($p['gender']) && preg_match('/\b(male|female|man|woman|mr|mrs|ms|miss)\b\.?/i', $rest, $m)) {
            $g = Str::lower($m[1]);
            $p['gender'] = in_array($g, ['female', 'woman', 'mrs', 'ms', 'miss'], true) ? 'Female' : 'Male';
            if (in_array($g, ['male', 'female'], true)) {
                $rest = str_ireplace($m[0], ' ', $rest);
            }
        }
        if (! isset($p['employment_nature']) && preg_match('/\b(permanent|contract|casual|temporary|intern(?:ship)?|on probation|fixed[\s-]term)\b/i', $rest, $m)) {
            $p['employment_nature'] = ucfirst(Str::lower($m[1]));
            $rest = str_ireplace($m[0], ' ', $rest);
        }

        // Job title and department from words.
        if (! isset($p['position']) && ($job = $this->jobTitle($rest))) {
            $p['position'] = $job;
            $rest = str_ireplace($job, ' ', $rest);
        }
        if (! isset($p['department'])) {
            if (preg_match('/\b(?:in|dept\.?|department)\s+(?:of\s+)?(?:the\s+)?([A-Z][A-Za-z&]+(?:\s+[A-Z&][A-Za-z&]+)?)(?:\s+(?:dept|department|section))?/', $rest, $m)
                && ! $this->bankIn($m[1]) && ! preg_match('/^(Lusaka|Kitwe|Ndola|Livingstone|Kabwe|Chingola|Solwezi|Chipata|Kasama|Mansa|Mongu|Choma)$/', $m[1])) {
                $p['department'] = trim($m[1]);
                $rest = str_replace($m[0], ' ', $rest);
            } else {
                foreach ($this->vocab()['departments'] as $dep) {
                    if (strlen($dep) > 2 && preg_match('/\b' . preg_quote($dep, '/') . '\b/i', $rest, $m)) {
                        $p['department'] = $m[0];
                        $rest = str_replace($m[0], ' ', $rest);
                        break;
                    }
                }
            }
        }
        if (! isset($p['branch']) && preg_match('/\b(?:at|based in|branch:?)\s+(?:the\s+)?(Lusaka|Kitwe|Ndola|Livingstone|Kabwe|Chingola|Solwezi|Chipata|Kasama|Mansa|Mongu|Choma|Mazabuka|Mufulira|Luanshya|Kafue|Chililabombwe|Kalulushi)\b/i', $rest, $m)) {
            $p['branch'] = ucfirst(Str::lower($m[1]));
            $rest = str_replace($m[0], ' ', $rest);
        }

        // 3. The name: labelled, or the leading capitalised words.
        if (empty($p['name']) && empty($p['first_name'])) {
            $name = $this->leadingName($rest) ?? $this->leadingName($chunk);
            if ($name) {
                $p['name'] = $name;
            }
        }
        if (! empty($p['name']) && empty($p['first_name'])) {
            $parts = preg_split('/\s+/', trim(preg_replace('/^(mr|mrs|ms|miss|dr)\.?\s+/i', '', $p['name'])));
            // "MWILA, Chanda" → surname first
            if (str_contains($p['name'], ',')) {
                [$last, $first] = array_map('trim', explode(',', $p['name'], 2));
                $p['first_name'] = Str::title($first);
                $p['last_name'] = Str::title($last);
            } elseif (count($parts) >= 2) {
                $p['first_name'] = Str::title(array_shift($parts));
                $p['last_name'] = Str::title(array_pop($parts));
                if ($parts) {
                    $p['middle_name'] = Str::title(implode(' ', $parts));
                }
            } else {
                $p['first_name'] = Str::title($parts[0] ?? '');
            }
            unset($p['name']);
        }

        return $p;
    }

    /** First 2–4 words that read like a person's name. */
    private function leadingName(string $s): ?string
    {
        $s = trim(preg_replace('/^\s*(?:\d{1,4}[.)]|[-*•▪●])\s+/', '', $s));
        $s = preg_replace('/^(?:full\s+)?names?\s*[:=\-]\s*/i', '', $s);
        if (preg_match('/^((?:(?:Mr|Mrs|Ms|Miss|Dr)\.?\s+)?[A-Z][A-Za-z\'\-]+(?:,?\s+[A-Z][A-Za-z\'\-]+){1,3})/u', $s, $m)) {
            $words = preg_split('/[\s,]+/', $m[1]);
            $out = [];
            foreach ($words as $w) {
                if ($this->labelField($w) || $this->isStopWord($w) || $this->jobTitle($w) || $this->bankIn($w)) {
                    break;
                }
                $out[] = $w;
            }
            if (count($out) >= 2 || (count($out) === 1 && strlen($out[0]) > 2 && count($words) === 1)) {
                return trim(implode(' ', $out), ', ');
            }
        }
        // ALL CAPS names ("CHANDA MWILA")
        if (preg_match('/^([A-Z][A-Z\'\-]+(?:\s+[A-Z][A-Z\'\-]+){1,3})\b/', $s, $m)) {
            $words = array_filter(preg_split('/\s+/', $m[1]), fn ($w) => ! $this->labelField($w) && ! $this->isStopWord($w) && ! $this->bankIn($w));
            if (count($words) >= 2) {
                return Str::title(implode(' ', $words));
            }
        }

        return null;
    }

    private function looksLikeName(string $v): bool
    {
        $v = trim($v);

        return (bool) preg_match('/^[A-Za-z][A-Za-z\'\-.]+(?:,?\s+[A-Za-z][A-Za-z\'\-.]+){0,3}$/', $v)
            && ! $this->jobTitle($v) && ! $this->bankIn($v) && ! preg_match('/^(m|f|male|female|yes|no|permanent|contract|casual|active)$/i', $v);
    }

    private function isStopWord(string $w): bool
    {
        return in_array(Str::lower(trim($w, '.,')), ['add', 'import', 'onboard', 'create', 'hi', 'hello', 'dear', 'good', 'morning', 'afternoon', 'here', 'please', 'thanks', 'thank', 'regards', 'kind', 'best', 'hr', 'team', 'list', 'below', 'new', 'these', 'are', 'staff', 'employees', 'the', 'and', 'is', 'a', 'an', 'he', 'she', 'works', 'working', 'at', 'in', 'as', 'our', 'new', 'from',
            'phone', 'cell', 'mobile', 'nrc', 'email', 'tel', 'born', 'started', 'joined', 'male', 'female', 'permanent', 'contract', 'casual',
            'salary', 'net', 'basic', 'gross', 'pay', 'bank', 'account', 'acc', 'dept', 'department', 'k', 'zmw', 'employee', 'staff', 'mr', 'mrs', 'ms', 'dr'], true);
    }

    /* ================================================================== */
    /*  Small recognisers                                                   */
    /* ================================================================== */

    private function labelField(string $label): ?string
    {
        $l = Str::of($label)->lower()->replace(['_', '#', '*'], ' ')->squish()->trim(' .:')->value();
        if ($l === '') {
            return null;
        }
        if (isset(self::LABELS[$l])) {
            return self::LABELS[$l];
        }

        return EmployeeColumnMapper::guess($l);
    }

    private function cleanValue(string $field, string $value): ?string
    {
        return match ($field) {
            'nrc_no' => preg_match(self::NRC, $value, $m) ? "{$m[1]}/{$m[2]}/{$m[3]}" : $value,
            'primary_phone', 'secondary_phone', 'emergency_phone', 'next_of_kin_phone' => $this->phone($value) ?? $value,
            'salary', 'net_salary' => ($a = $this->amount($value)) !== null ? (string) $a : null,
            'date_of_birth', 'contract_start', 'contract_end', 'probation_end' => $this->date($value) ?? $value,
            'company_email', 'personal_email' => preg_match(self::EMAIL, $value, $m) ? Str::lower($m[0]) : $value,
            'bank_account_no', 'tpin', 'ssn', 'nhima_no' => preg_replace('/\s+/', '', $value),
            'name', 'first_name', 'last_name', 'middle_name' => Str::title(Str::lower($value)) === $value || ! ctype_upper(str_replace([' ', '-', "'"], '', $value)) ? $value : Str::title(Str::lower($value)),
            default => $value,
        };
    }

    public function phone(string $v): ?string
    {
        $digits = preg_replace('/\D/', '', $v);
        if (preg_match('/^(?:260|0)?([79]\d{8})$/', $digits, $m)) {
            return '0' . $m[1];
        }

        return null;
    }

    public function amount(string $v): ?float
    {
        $v = trim(preg_replace('/\b(K|ZMW|ZK|kwacha)\b|^K(?=\d)/i', '', $v));
        if (! preg_match('/(\d{1,3}(?:[,\s]\d{3})+|\d+)(\.\d{1,2})?/', $v, $m)) {
            return null;
        }
        $n = (float) (preg_replace('/[,\s]/', '', $m[1]) . ($m[2] ?? ''));

        return $n > 0 ? round($n, 2) : null;
    }

    /** A date in any common format → Y-m-d (day before month, as used in Zambia). */
    public function date(string $v): ?string
    {
        $v = trim($v);
        if (! preg_match(self::DATE, $v, $m)) {
            return null;
        }
        $s = $m[1];
        try {
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $x)) {
                $d = \Carbon\Carbon::create((int) $x[1], (int) $x[2], (int) $x[3]);
            } elseif (preg_match('#^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{2,4})$#', $s, $x)) {
                $y = strlen($x[3]) === 2 ? (int) ('20' . $x[3]) : (int) $x[3];
                if ($y > (int) date('Y') + 1) {
                    $y -= 100;
                }
                [$day, $mon] = (int) $x[2] > 12 ? [(int) $x[2], (int) $x[1]] : [(int) $x[1], (int) $x[2]];
                if ($mon < 1 || $mon > 12 || $day < 1 || $day > 31) {
                    return null;
                }
                $d = \Carbon\Carbon::create($y, $mon, $day);
            } else {
                $clean = preg_replace('/(\d)(st|nd|rd|th)\b/i', '$1', str_replace([',', '.'], ' ', $s));
                $d = \Carbon\Carbon::parse($clean);
                if (preg_match('/\b(\d{2})$/', trim($clean), $yy) && $d->year > (int) date('Y') + 1) {
                    $d->subYears(100);
                }
            }

            return ($d->year >= 1920 && $d->year <= (int) date('Y') + 5) ? $d->toDateString() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function jobTitle(string $s): ?string
    {
        $words = implode('|', array_map(fn ($w) => preg_quote($w, '/'), self::JOB_WORDS));
        if (preg_match('/\b((?:(?:senior|junior|assistant|chief|head|general|sales|finance|accounts|hr|human resources|it|ict|office|security|site|shop|store|stores|fleet|operations|marketing|credit|loans|customer service|branch|plant|machine|truck|lorry|delivery|personal|production|quality|project|procurement|logistics)\s+){0,3}(?:' . $words . ')s?)\b/i', $s, $m)) {
            $t = trim($m[1]);
            if (preg_match('/^(head|lead)$/i', $t)) {
                return null;
            }

            return Str::title(Str::lower($t)) === $t ? $t : (ctype_upper(str_replace(' ', '', $t)) || ctype_lower(str_replace(' ', '', $t)) ? Str::title(Str::lower($t)) : $t);
        }
        foreach ($this->vocab()['positions'] as $pos) {
            if (strlen($pos) > 3 && preg_match('/\b' . preg_quote($pos, '/') . '\b/i', $s, $m)) {
                return $m[0];
            }
        }

        return null;
    }

    private function bankIn(string $s): ?string
    {
        foreach ($this->banks() as $bank) {
            if (preg_match('/\b' . preg_quote($bank, '/') . '\b/i', $s, $m)) {
                return $m[0] === Str::lower($m[0]) ? Str::title($m[0]) : $m[0];
            }
        }

        return null;
    }

    private function banks(): array
    {
        if ($this->banks === null) {
            $names = self::FALLBACK_BANKS;
            try {
                foreach (Bank::query()->active()->get() as $b) {
                    $names = array_merge($names, array_filter([$b->name, $b->short_name]), (array) ($b->aliases ?? []));
                }
            } catch (\Throwable $e) {
                // banks table not available — fallback list is enough
            }
            $names = array_values(array_unique(array_filter(array_map('trim', $names), fn ($n) => strlen($n) >= 3)));
            usort($names, fn ($a, $b) => strlen($b) <=> strlen($a)); // longest first
            $this->banks = $names;
        }

        return $this->banks;
    }

    /** Job titles and departments this company already uses. */
    private function vocab(): array
    {
        if ($this->vocab === null) {
            $this->vocab = ['positions' => [], 'departments' => []];
            try {
                $this->vocab['positions'] = Employee::query()->whereNotNull('position')->distinct()->limit(200)->pluck('position')
                    ->map(fn ($v) => Str::lower(trim($v)))->filter()->values()->all();
                $this->vocab['departments'] = Employee::query()->whereNotNull('department')->distinct()->limit(100)->pluck('department')
                    ->map(fn ($v) => Str::lower(trim($v)))->filter()->values()->all();
            } catch (\Throwable $e) {
            }
            $this->vocab['departments'] = array_values(array_unique(array_merge($this->vocab['departments'],
                ['finance', 'accounts', 'sales', 'operations', 'administration', 'human resources', 'marketing', 'procurement', 'logistics', 'security', 'production', 'ict', 'transport', 'stores', 'customer service'])));
        }

        return $this->vocab;
    }

    private function share(array $values, callable $test): float
    {
        if (! $values) {
            return 0;
        }

        return count(array_filter($values, fn ($v) => $test(trim((string) $v)))) / count($values);
    }

    private function colLetter(int $i): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
    }

    private function sheetLooksLikeBranch(array $tables): bool
    {
        return count(array_filter($tables, fn ($t) => ! preg_match('/^sheet\s*\d+$/i', $t['sheet']))) === count($tables);
    }

    private function dedupeMapping(array $mapping): array
    {
        $used = [];
        foreach ($mapping as $h => $f) {
            if ($f && isset($used[$f])) {
                $mapping[$h] = null;
            } elseif ($f) {
                $used[$f] = true;
            }
        }

        return $mapping;
    }

    /** Tidy values in recognised columns (phones stored as numbers lose their 0, NRC spacing…). */
    private function cleanRows(array $rows, array $mapping): array
    {
        return array_map(function ($r) use ($mapping) {
            foreach ($mapping as $h => $f) {
                if (! $f || ! isset($r[$h]) || $r[$h] === '') {
                    continue;
                }
                $v = (string) $r[$h];
                $r[$h] = match ($f) {
                    'primary_phone', 'secondary_phone', 'emergency_phone', 'next_of_kin_phone' => $this->phone($v) ?? $v,
                    'nrc_no' => preg_match(self::NRC, $v, $m) ? "{$m[1]}/{$m[2]}/{$m[3]}" : $v,
                    'name' => $this->tidyName($v),
                    'first_name', 'last_name', 'middle_name' => ctype_upper(preg_replace('/[^A-Za-z]/', '', $v)) ? Str::title(Str::lower($v)) : $v,
                    'date_of_birth', 'contract_start', 'contract_end', 'probation_end' => $this->date($v) ?? $v,
                    default => $v,
                };
            }

            return $r;
        }, $rows);
    }

    /** "MWILA Chanda" / "Mwila, Chanda" → "Chanda Mwila"; ALL CAPS → Title Case. */
    public function tidyName(string $v): string
    {
        $v = trim(preg_replace('/\s+/', ' ', $v));
        if (str_contains($v, ',')) {
            [$last, $first] = array_map('trim', explode(',', $v, 2));
            $v = trim($first . ' ' . $last);
        } else {
            $words = explode(' ', $v);
            $caps = fn ($w) => strlen($w) > 1 && ctype_upper(preg_replace('/[^A-Za-z]/', '', $w));
            if (count($words) >= 2 && $caps($words[0]) && ! array_filter(array_slice($words, 1), $caps)) {
                $v = implode(' ', array_slice($words, 1)) . ' ' . $words[0];
            }
        }

        return ctype_upper(preg_replace('/[^A-Za-z]/', '', $v)) || preg_match('/\b[A-Z]{2,}\b/', $v) ? Str::title(Str::lower($v)) : $v;
    }

    /* ================================================================== */

    private function peopleResult(array $people, string $source): array
    {
        $fields = [];
        foreach ($people as $p) {
            foreach (array_keys($p) as $f) {
                $fields[$f] = true;
            }
        }
        // Keep the wizard's natural order.
        $order = array_keys(EmployeeColumnMapper::FIELDS);
        $fields = array_values(array_filter($order, fn ($f) => isset($fields[$f])));

        $headers = [];
        $mapping = [];
        foreach ($fields as $f) {
            $label = EmployeeColumnMapper::FIELDS[$f][0];
            $headers[] = $label;
            $mapping[$label] = $f;
        }
        $rows = array_map(function ($p) use ($fields) {
            $r = [];
            foreach ($fields as $f) {
                $r[EmployeeColumnMapper::FIELDS[$f][0]] = (string) ($p[$f] ?? '');
            }

            return $r;
        }, $people);

        if ($people) {
            array_unshift($this->notes, 'We read your text and found ' . count($people) . ' ' . (count($people) === 1 ? 'person' : 'people') . '. Check the details on the next step — nothing is saved until you import.');
            if (in_array('salary', $fields, true)) {
                $this->notes[] = 'Amounts without “net” or “take-home” were treated as basic salary — change the match below if they are take-home pay.';
            }
        }

        return $this->result($source, 'text', $headers, $mapping, $rows);
    }

    private function result(string $source, string $mode, array $headers, array $mapping, array $rows): array
    {
        $samples = [];
        foreach ($headers as $h) {
            $samples[$h] = collect(array_column($rows, $h))->filter(fn ($v) => $v !== '' && $v !== null)->take(3)->values()->all();
        }

        return [
            'source' => $source,
            'mode' => $mode,
            'headers' => $headers,
            'samples' => $samples,
            'mapping' => $mapping,
            'rows' => array_values($rows),
            'notes' => array_values(array_unique($this->notes)),
        ];
    }
}
