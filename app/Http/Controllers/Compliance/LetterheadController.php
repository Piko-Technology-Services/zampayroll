<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Compliance\Letterhead;
use App\Services\Compliance\DocumentPdf;
use App\Services\Compliance\Letterheads;
use App\Services\Compliance\Vault;
use App\Support\Audit;
use App\Support\Tenancy;
use Illuminate\Http\Request;

class LetterheadController extends Controller
{
    private function rules(bool $creating): array
    {
        return [
            'name' => 'required|string|max:120',
            'mode' => 'required|in:generated,custom',
            'header_file' => 'nullable|file|max:8192|mimes:png,jpg,jpeg,pdf',
            'footer_file' => 'nullable|file|max:8192|mimes:png,jpg,jpeg,pdf',
            'margin_top' => 'required|numeric|min:0|max:60', 'margin_bottom' => 'required|numeric|min:0|max:60',
            'margin_left' => 'required|numeric|min:5|max:50', 'margin_right' => 'required|numeric|min:5|max:50',
            'header_height' => 'required|numeric|min:10|max:90', 'footer_height' => 'required|numeric|min:5|max:60',
            'logo_position' => 'required|in:left,center,right',
            'font' => 'required|in:' . implode(',', array_keys(Letterhead::FONTS)),
            'font_size' => 'required|numeric|min:8|max:14',
            'accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'page_numbers' => 'nullable|boolean', 'show_registration' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ];
    }

    private function settingsFrom(array $d): array
    {
        $keys = ['margin_top', 'margin_bottom', 'margin_left', 'margin_right', 'header_height', 'footer_height', 'logo_position', 'font', 'font_size', 'accent'];
        $s = array_intersect_key($d, array_flip($keys));
        foreach (['margin_top', 'margin_bottom', 'margin_left', 'margin_right', 'header_height', 'footer_height', 'font_size'] as $k) {
            $s[$k] = (float) $s[$k];
        }
        $s['page_numbers'] = (bool) ($d['page_numbers'] ?? false);
        $s['show_registration'] = (bool) ($d['show_registration'] ?? false);

        return $s;
    }

    public function store(Request $request)
    {
        $d = $request->validate($this->rules(true));
        $lh = new Letterhead(['name' => $d['name'], 'mode' => $d['mode'], 'settings' => $this->settingsFrom($d), 'created_by' => $request->user()->id]);
        if ($error = $this->applyFiles($request, $lh)) {
            return back()->withInput()->withErrors(['header_file' => $error]);
        }
        if ($lh->mode === 'custom' && ! $lh->header_path) {
            return back()->withInput()->withErrors(['header_file' => 'Upload your letterhead header image (PNG or JPG).']);
        }
        $lh->is_default = (bool) ($d['is_default'] ?? false) || ! Letterhead::exists();
        $lh->save();
        if ($lh->is_default) {
            Letterhead::whereKeyNot($lh->id)->update(['is_default' => false]);
        }
        Audit::record('compliance.letterhead_created', $lh, "Letterhead \"{$lh->name}\" created");

        return redirect()->route('compliance.settings')->with('success', 'Letterhead saved. It will be used on new contracts and policies.');
    }

    public function edit(Letterhead $letterhead)
    {
        return view('compliance.letterhead-edit', ['letterhead' => $letterhead, 'company' => Tenancy::company()]);
    }

    public function update(Request $request, Letterhead $letterhead)
    {
        $d = $request->validate($this->rules(false));
        $letterhead->fill(['name' => $d['name'], 'mode' => $d['mode'], 'settings' => $this->settingsFrom($d)]);
        if ($error = $this->applyFiles($request, $letterhead)) {
            return back()->withInput()->withErrors(['header_file' => $error]);
        }
        if ($letterhead->mode === 'custom' && ! $letterhead->header_path) {
            return back()->withInput()->withErrors(['header_file' => 'Upload your letterhead header image (PNG or JPG).']);
        }
        $letterhead->save();
        if ($request->boolean('is_default')) {
            $this->makeDefault($letterhead);
        }
        Audit::record('compliance.letterhead_updated', $letterhead, "Letterhead \"{$letterhead->name}\" updated");

        return redirect()->route('compliance.settings')->with('success', 'Letterhead updated. Documents already executed keep the letterhead they were signed with.');
    }

    /** Preview the letterhead as a PDF before saving (nothing is stored). */
    public function preview(Request $request)
    {
        $d = $request->validate($this->rules(true) + ['letterhead_id' => 'nullable|integer']);
        $lh = $d['letterhead_id'] ?? null ? Letterhead::findOrFail($d['letterhead_id'])->replicate() : new Letterhead();
        $lh->fill(['name' => $d['name'], 'mode' => $d['mode'], 'settings' => $this->settingsFrom($d)]);
        foreach (['header' => 'header_path', 'footer' => 'footer_path'] as $field => $col) {
            $file = $request->file($field . '_file');
            if ($file && in_array(strtolower($file->getClientOriginalExtension()), ['png', 'jpg', 'jpeg'], true) && @getimagesize($file->getRealPath())) {
                // Preview straight from the temporary upload: written to a scratch file in the company's area, deleted after rendering.
                $lh->{$col} = Vault::putUpload($file, 'tmp', strtolower($file->getClientOriginalExtension()) === 'png' ? 'png' : 'jpg');
                $cleanup[] = $lh->{$col};
            }
        }
        $pdf = DocumentPdf::render($this->sampleBody(), $lh, Tenancy::company(), ['title' => 'Letterhead preview', 'footer_note' => 'Preview']);
        foreach ($cleanup ?? [] as $p) {
            Vault::disk()->delete($p);
        }

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="letterhead-preview.pdf"']);
    }

    public function sample(Letterhead $letterhead)
    {
        $pdf = DocumentPdf::render($this->sampleBody(), $letterhead, Tenancy::company(), ['title' => $letterhead->name, 'footer_note' => 'Sample']);

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="letterhead-sample.pdf"']);
    }

    public function makeDefault(Letterhead $letterhead)
    {
        Letterhead::whereKeyNot($letterhead->id)->update(['is_default' => false]);
        $letterhead->update(['is_default' => true]);

        return redirect()->route('compliance.settings')->with('success', "\"{$letterhead->name}\" is now the default letterhead.");
    }

    public function destroy(Letterhead $letterhead)
    {
        $letterhead->delete(); // soft delete: documents that used it can still be re-rendered from history
        if ($letterhead->is_default && ($next = Letterhead::orderBy('id')->first())) {
            $next->update(['is_default' => true]);
        }
        Audit::record('compliance.letterhead_removed', $letterhead, "Letterhead \"{$letterhead->name}\" removed");

        return back()->with('success', 'Letterhead removed.');
    }

    private function applyFiles(Request $request, Letterhead $lh): ?string
    {
        foreach (['header' => 'header_path', 'footer' => 'footer_path'] as $field => $col) {
            if ($file = $request->file($field . '_file')) {
                $r = Letterheads::storeUpload($file);
                if ($r['error']) {
                    return $r['error'];
                }
                $lh->{$col} = $r['path'];
                if ($field === 'header' && $r['source']) {
                    $lh->source_path = $r['source'];
                }
            }
        }
        if ($request->boolean('remove_footer')) {
            $lh->footer_path = null;
        }

        return null;
    }

    private function sampleBody(): string
    {
        return '<h1>SAMPLE LETTER</h1><p>' . e(now()->format('j F Y')) . '</p><p>Dear Employee,</p>'
            . '<h2>1. How your documents will look</h2><p>This page shows how ZamPayroll will lay out contracts, policies and HR letters on this letterhead: the margins, the header and footer, the font and the page numbers.</p>'
            . '<ul><li>Headings use your accent colour.</li><li>Tables and lists keep their formatting.</li></ul>'
            . '<table><tr><th>Item</th><th>Detail</th></tr><tr><td>Basic salary</td><td>K0,000.00</td></tr><tr><td>Start date</td><td>1 January 2027</td></tr></table>'
            . '<p>Yours faithfully,</p><p><strong>Human Resources</strong></p>';
    }
}
