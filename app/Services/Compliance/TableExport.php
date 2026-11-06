<?php

namespace App\Services\Compliance;

use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Download a simple table as CSV, Excel or PDF. */
class TableExport
{
    /** @param list<string> $columns @param list<list<scalar|null>> $rows */
    public static function download(string $title, array $columns, array $rows, string $format)
    {
        $base = \Illuminate\Support\Str::slug($title) . '-' . now()->format('Y-m-d');

        if ($format === 'xlsx') {
            $book = new Spreadsheet();
            $sheet = $book->getActiveSheet();
            $sheet->setTitle(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $title), 0, 31) ?: 'Report');
            $sheet->fromArray($columns, null, 'A1');
            $sheet->fromArray(array_map(fn ($r) => array_map(fn ($v) => self::safeCell($v), $r), $rows), null, 'A2');
            $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setBold(true);
            foreach (range(1, count($columns)) as $i) {
                $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
            }
            $tmp = tempnam(sys_get_temp_dir(), 'xl');
            (new Xlsx($book))->save($tmp);

            return response()->download($tmp, $base . '.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
        }

        if ($format === 'pdf') {
            $html = '<style>body{font-family:DejaVu Sans,sans-serif;font-size:8pt}h1{font-size:13pt;color:#0B3D2E}table{border-collapse:collapse;width:100%}th,td{border:1px solid #c9d3cc;padding:3pt 4pt;text-align:left;vertical-align:top}th{background:#eef3ef}</style>'
                . '<h1>' . e($title) . '</h1><p>' . e(\App\Support\Tenancy::company()?->name) . ' · ' . now()->format('j M Y H:i') . '</p><table><tr>';
            foreach ($columns as $c) {
                $html .= '<th>' . e($c) . '</th>';
            }
            $html .= '</tr>';
            foreach ($rows as $r) {
                $html .= '<tr>' . implode('', array_map(fn ($v) => '<td>' . e((string) $v) . '</td>', $r)) . '</tr>';
            }
            $html .= '</table>';

            return Pdf::loadHTML($html)->setPaper('a4', count($columns) > 6 ? 'landscape' : 'portrait')->download($base . '.pdf');
        }

        return response()->streamDownload(function () use ($columns, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $columns);
            foreach ($rows as $r) {
                fputcsv($out, array_map(fn ($v) => self::safeCell($v), $r));
            }
            fclose($out);
        }, $base . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Stop spreadsheet formula injection from user-entered text. */
    private static function safeCell($v)
    {
        return is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@'], true) && ! is_numeric($v) ? "'" . $v : $v;
    }
}
