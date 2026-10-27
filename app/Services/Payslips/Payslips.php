<?php

namespace App\Services\Payslips;

use App\Models\Company;
use App\Models\Payroll;
use App\Models\PayrollRun;
use App\Support\Tenancy;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * One payslip template for everything: the PDF download, the admin "all payslips"
 * screen, the single payslip screen, the employee portal and the payslip email.
 * The data for each slip is built here so every place shows the same figures.
 */
class Payslips
{
    /** Statutory item codes written by PayrollEngine. */
    public const PAYE = 'D00';
    public const NAPSA = 'D01';
    public const NHIMA = 'D02';

    /** Data for one payslip. */
    public function data(Payroll $payroll, ?Company $company = null): array
    {
        $payroll->loadMissing(['employee', 'items', 'run']);
        $run = $payroll->run;
        $e = $payroll->employee;
        $company ??= Company::on(Tenancy::landlordConnection())->find($run?->company_id ?? $e?->company_id) ?? Tenancy::company();

        $items = $payroll->items->filter(fn ($i) => (float) $i->amount != 0.0);
        $earnings = $items->where('type', 'earning')->values();
        $deductions = $items->where('type', 'deduction')->sortBy(fn ($i) => [self::PAYE => 0, self::NAPSA => 1, self::NHIMA => 2][$i->code] ?? 9)->values();
        $code = fn ($c) => (float) $deductions->where('code', $c)->sum('amount');

        $period = $run?->period_date ?? $this->periodDate($run?->period ?? $payroll->pay_period);

        return [
            'payroll'     => $payroll,
            'run'         => $run,
            'employee'    => $e,
            'company'     => $company,
            'companyName' => $company?->name ?? 'ZamPayroll',
            'logo'        => $this->logo($company),
            'period'      => $period,
            'periodLabel' => $period ? $period->format('F Y') : (string) ($run?->period ?? $payroll->pay_period),
            'periodRange' => $period ? $period->copy()->startOfMonth()->format('j M') . ' – ' . $period->copy()->endOfMonth()->format('j M Y') : null,
            'runName'     => $run?->alias,
            'reference'   => strtoupper(implode('-', array_filter(['PS', $company?->company_code, $period?->format('Ym'), $run?->id, $e?->employee_id]))),
            'earnings'    => $earnings,
            'deductions'  => $deductions,
            'gross'       => (float) $payroll->total_income,
            'totalDeductions' => (float) $payroll->total_deductions,
            'net'         => (float) $payroll->net_pay,
            'netWords'    => $this->words((float) $payroll->net_pay),
            'paye'        => $code(self::PAYE),
            'napsa'       => $code(self::NAPSA),
            'nhima'       => $code(self::NHIMA),
            'employerNapsa' => $code(self::NAPSA),   // employer matches the employee's 5%
            'employerNhima' => $code(self::NHIMA),   // employer matches the employee's 1%
            'ytd'         => $this->ytd($payroll, $period),
            'bank'        => trim(($e?->bank_name ?? '') . ' ' . $this->mask($e?->bank_account_no)),
            'leaveBalance' => $e?->leave_days_balance,
            'status'      => $run?->statusLabel() ?? '',
            'final'       => $run ? $run->isLocked() : false,
            'generatedAt' => now(),
        ];
    }

    /** @param Collection<int, Payroll> $payrolls */
    public function many(Collection $payrolls): Collection
    {
        return $payrolls->map(fn (Payroll $p) => $this->data($p));
    }

    /** PDF bytes for one or more payslips (one page each). */
    public function pdf(Collection|Payroll $payrolls): \Barryvdh\DomPDF\PDF
    {
        $slips = $this->many($payrolls instanceof Payroll ? collect([$payrolls]) : $payrolls);

        return Pdf::loadView('payslips.document', ['slips' => $slips, 'mode' => 'pdf'])
            ->setPaper('a4', 'portrait')
            ->setOption(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
    }

    public function filename(Payroll $payroll): string
    {
        $payroll->loadMissing(['employee', 'run']);
        $name = trim(preg_replace('/[^A-Za-z0-9]+/', '-', strtolower(($payroll->employee->first_name ?? '') . ' ' . ($payroll->employee->last_name ?? ''))), '-');
        $period = $payroll->run?->period_date?->format('Y-m') ?? str_replace(' ', '-', strtolower((string) $payroll->pay_period));

        return sprintf('payslip-%s-%s-%s.pdf', $payroll->employee->employee_id ?? $payroll->id, $name, $period);
    }

    /* ------------------------------------------------------------------ */

    /**
     * The company's own logo when they've uploaded one, otherwise the ZamPayroll logo.
     * Converted to greyscale so the payslip prints cleanly; returned as a data URI.
     */
    public function logo(?Company $company): array
    {
        $own = $company?->logo_path ? storage_path('app/public/' . ltrim($company->logo_path, '/')) : null;
        $file = $own && is_file($own) ? $own : public_path('assets/images/logo.png');

        return ['src' => $this->greyscale($file), 'own' => $file === $own];
    }

    private function greyscale(string $file): ?string
    {
        static $cache = [];
        $key = $file . '@' . @filemtime($file);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $bytes = @file_get_contents($file);
        if ($bytes === false) {
            return null;
        }

        $uri = 'data:' . (mime_content_type($file) ?: 'image/png') . ';base64,' . base64_encode($bytes);
        if (function_exists('imagecreatefromstring') && ($img = @imagecreatefromstring($bytes))) {
            $w = imagesx($img);
            $h = imagesy($img);
            $scale = min(1, 360 / max($w, $h));
            $out = imagecreatetruecolor(max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
            imagealphablending($out, false);
            imagesavealpha($out, true);
            imagefill($out, 0, 0, imagecolorallocatealpha($out, 255, 255, 255, 127));
            imagecopyresampled($out, $img, 0, 0, 0, 0, imagesx($out), imagesy($out), $w, $h);
            imagefilter($out, IMG_FILTER_GRAYSCALE);
            ob_start();
            imagepng($out);
            $uri = 'data:image/png;base64,' . base64_encode(ob_get_clean());
            imagedestroy($img);
            imagedestroy($out);
        }

        return $cache[$key] = $uri;
    }

    /** Year-to-date from approved or paid runs in the same calendar (tax) year, up to and including this payslip. */
    public function ytd(Payroll $payroll, ?Carbon $period): array
    {
        $blank = ['gross' => (float) $payroll->total_income, 'paye' => 0.0, 'napsa' => 0.0, 'nhima' => 0.0, 'net' => (float) $payroll->net_pay];
        if (! $period) {
            return $blank;
        }

        $runs = PayrollRun::query()->withoutGlobalScope('visible')->get(['id', 'period', 'status', 'company_id'])
            ->filter(fn ($r) => $r->id === $payroll->payroll_run_id
                || ($r->period_date && $r->period_date->year === $period->year && $r->period_date->lte($period) && $r->isLocked()))
            ->pluck('id');

        $rows = Payroll::query()->with('items')->where('employee_id', $payroll->employee_id)->whereIn('payroll_run_id', $runs)->get();
        $sum = fn ($c) => (float) $rows->flatMap->items->where('code', $c)->sum('amount');

        return [
            'gross' => (float) $rows->sum('total_income'),
            'paye'  => $sum(self::PAYE),
            'napsa' => $sum(self::NAPSA),
            'nhima' => $sum(self::NHIMA),
            'net'   => (float) $rows->sum('net_pay'),
        ];
    }

    public function mask(?string $account): string
    {
        $digits = preg_replace('/\s+/', '', (string) $account);

        return $digits === '' ? '' : '•••• ' . substr($digits, -4);
    }

    private function periodDate(?string $period): ?Carbon
    {
        foreach (['Y-m', 'F Y', 'M Y'] as $f) {
            try {
                return Carbon::createFromFormat($f, (string) $period)->startOfMonth();
            } catch (\Throwable $e) {
            }
        }

        return null;
    }

    /** 8250.50 → "Eight thousand two hundred and fifty kwacha and fifty ngwee". */
    public function words(float $amount): string
    {
        $k = (int) floor(round($amount, 2));
        $n = (int) round(($amount - $k) * 100);
        $text = ucfirst($this->spell($k)) . ' kwacha';
        if ($n > 0) {
            $text .= ' and ' . $this->spell($n) . ' ngwee';
        }

        return $text . ' only';
    }

    private function spell(int $n): string
    {
        $ones = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
        $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
        if ($n < 20) {
            return $ones[$n];
        }
        if ($n < 100) {
            return $tens[intdiv($n, 10)] . ($n % 10 ? '-' . $ones[$n % 10] : '');
        }
        if ($n < 1000) {
            return $ones[intdiv($n, 100)] . ' hundred' . ($n % 100 ? ' and ' . $this->spell($n % 100) : '');
        }
        foreach ([1000000000 => 'billion', 1000000 => 'million', 1000 => 'thousand'] as $size => $word) {
            if ($n >= $size) {
                $rest = $n % $size;

                return $this->spell(intdiv($n, $size)) . ' ' . $word . ($rest ? ($rest < 100 ? ' and ' : ' ') . $this->spell($rest) : '');
            }
        }

        return (string) $n;
    }
}
