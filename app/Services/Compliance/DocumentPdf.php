<?php

namespace App\Services\Compliance;

use App\Models\Company;
use App\Models\Compliance\ContractSignature;
use App\Models\Compliance\EmploymentContract;
use App\Models\Compliance\Letterhead;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Turns a document body into a PDF on the company's letterhead (dompdf, already used for payslips).
 */
class DocumentPdf
{
    /**
     * @param array{title?:string, watermark?:string, footer_note?:string, append?:string} $opts
     */
    public static function render(string $bodyHtml, ?Letterhead $letterhead, Company $company, array $opts = []): string
    {
        $frame = Letterheads::pdfFrame($letterhead, $company, $opts['footer_note'] ?? null);
        $wm = ! empty($opts['watermark']) ? '<div class="wm">' . e($opts['watermark']) . '</div>' : '';
        $html = '<!doctype html><html><head><meta charset="utf-8"><title>' . e($opts['title'] ?? 'Document') . '</title><style>' . $frame['css'] . '</style></head><body>'
            . $frame['head'] . $frame['foot'] . $wm . '<main>' . $bodyHtml . '</main>' . ($opts['append'] ?? '') . '</body></html>';

        $pdf = Pdf::loadHTML($html)->setPaper('a4')->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
        if ($frame['page_numbers']) {
            $dompdf = $pdf->getDomPDF();
            $dompdf->render();
            $canvas = $dompdf->getCanvas();
            $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
            $pt = fn ($mm) => $mm * 72 / 25.4;
            $canvas->page_text($canvas->get_width() - $pt($frame['pager_right_mm']) - 62, $pt($frame['pager_y_mm']), 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 7.5, [0.33, 0.38, 0.35]);
        }

        return $pdf->output();
    }

    /** The signature page appended to a contract: signatures, approvals and integrity details. */
    public static function contractSignaturePage(EmploymentContract $contract, string $versionHash): string
    {
        $sigs = $contract->signatures()->where('version', $contract->current_version)->get();
        $emp = $sigs->firstWhere('signer_type', 'employee');
        $co = $sigs->where('signer_type', 'company')->last();

        $box = function (?ContractSignature $s, string $role, string $fallbackName) {
            $img = $s ? Vault::dataUri($s->image_path) : null;

            return '<td style="width:50%;border:1px solid #c9d3cc;padding:8pt;vertical-align:top">'
                . '<div style="font-size:8pt;color:#55615a;text-transform:uppercase;letter-spacing:.6pt">' . e($role) . '</div>'
                . '<div style="height:22mm;margin:4pt 0">' . ($img ? '<img src="' . $img . '" style="max-height:22mm;max-width:70mm">' : '') . '</div>'
                . '<div style="border-top:1px solid #1b1b1b;padding-top:3pt"><strong>' . e($s->signer_name ?? $fallbackName) . '</strong>'
                . ($s && $s->signer_title ? '<br>' . e($s->signer_title) : '')
                . '<br><span style="font-size:8.5pt">' . ($s ? 'Signed electronically on ' . $s->signed_at->timezone(config('app.timezone'))->format('j M Y, H:i') : 'Not yet signed') . '</span></div></td>';
        };

        $rows = '';
        foreach ($contract->approvals()->where('round', $contract->round)->whereIn('status', ['approved'])->get() as $a) {
            $rows .= '<tr><td>' . e($a->label) . '</td><td>' . e($a->acted_by_name) . ($a->acted_as_delegate ? ' (delegate)' : '') . '</td><td>'
                . e(ucfirst($a->action === 'sign' ? 'signed' : ($a->action === 'review' ? 'reviewed' : 'approved'))) . '</td><td>' . e(optional($a->acted_at)->format('j M Y, H:i')) . '</td></tr>';
        }

        return '<div style="page-break-before:always"></div><h2>Signatures</h2>'
            . '<table style="width:100%;border-collapse:collapse"><tr>' . $box($emp, 'Employee', $contract->employee?->full_name ?? 'Employee')
            . $box($co, 'For and on behalf of the Employer', 'Authorised signatory') . '</tr></table>'
            . ($rows ? '<h3>Approval record</h3><table><tr><th>Step</th><th>By</th><th>Action</th><th>Date</th></tr>' . $rows . '</table>' : '')
            . '<h3>Document details</h3><table>'
            . '<tr><th style="width:35%">Reference</th><td>' . e($contract->reference) . '</td></tr>'
            . '<tr><th>Version</th><td>' . (int) $contract->current_version . '</td></tr>'
            . '<tr><th>Content fingerprint (SHA-256)</th><td style="font-family:DejaVu Sans Mono,monospace;font-size:7.5pt">' . e($versionHash) . '</td></tr>'
            . ($emp ? '<tr><th>Employee consent</th><td style="font-size:8.5pt">' . e($emp->consent_text) . '</td></tr>' : '')
            . '</table><p style="font-size:8pt;color:#55615a">Signed electronically in ZamPayroll. Each signer signed in to their own account; the signatures, times and the fingerprint above are kept in the audit trail.</p>';
    }
}
