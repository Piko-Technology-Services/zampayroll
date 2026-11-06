<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Compliance\DocumentTemplate;
use App\Models\Compliance\DocumentTemplateVersion;
use App\Models\Compliance\Letterhead;
use App\Services\Compliance\DocumentPdf;
use App\Services\Compliance\DocxImport;
use App\Services\Compliance\HtmlSanitizer;
use App\Services\Compliance\Placeholders;
use App\Services\Compliance\TemplateLibrary;
use App\Support\Audit;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Master templates (ZamPayroll) are read-only; a company duplicates one to customise it. Company templates keep a version history.
 * Contract templates need "compliance.contracts", policy templates "compliance.policies".
 */
class TemplateController extends Controller
{
    private function kinds(Request $request): array
    {
        $u = $request->user();

        return array_values(array_filter([$u->hasPermission('compliance.contracts') ? 'contract' : null, $u->hasPermission('compliance.policies') ? 'policy' : null]));
    }

    private function authorizeKind(Request $request, string $kind): void
    {
        abort_unless(in_array($kind, $this->kinds($request), true), 403, 'You do not have access to these templates.');
    }

    public function index(Request $request)
    {
        $kinds = $this->kinds($request);
        abort_if(! $kinds, 403, 'You do not have access to templates.');
        $kind = in_array($request->query('kind'), $kinds, true) ? $request->query('kind') : $kinds[0];
        $templates = DocumentTemplate::with('source')->kind($kind)->orderByRaw('company_id IS NULL')->orderBy('name')->get();

        return view('compliance.templates.index', compact('templates', 'kind', 'kinds'));
    }

    public function show(Request $request, DocumentTemplate $template)
    {
        $this->authorizeKind($request, $template->kind);

        return view('compliance.templates.show', [
            'template' => $template,
            'versions' => $template->isMaster() ? collect() : $template->versions()->limit(20)->get(),
            'body' => Placeholders::fill($template->body, [], true),
            'placeholders' => Placeholders::used($template->body),
        ]);
    }

    public function preview(Request $request, DocumentTemplate $template)
    {
        $this->authorizeKind($request, $template->kind);
        $values = Placeholders::company(Tenancy::company());
        $pdf = DocumentPdf::render(Placeholders::fill($template->body, $values, true), Letterhead::defaultForCompany(), Tenancy::company(),
            ['title' => $template->name, 'watermark' => 'TEMPLATE', 'footer_note' => $template->name . ' · v' . $template->version]);

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="template.pdf"']);
    }

    public function duplicate(Request $request, DocumentTemplate $template)
    {
        $this->authorizeKind($request, $template->kind);
        $copy = DB::transaction(function () use ($template, $request) {
            $copy = new DocumentTemplate([
                'kind' => $template->kind, 'key' => $template->key, 'name' => $template->name . ($template->isMaster() ? '' : ' (copy)'),
                'description' => $template->description, 'legal_status' => $template->legal_status, 'legal_note' => $template->legal_note,
                'body' => $template->body, 'version' => 1, 'source_template_id' => $template->isMaster() ? $template->id : $template->source_template_id,
                'source_version' => $template->isMaster() ? $template->version : $template->source_version, 'review_status' => 'unreviewed',
                'is_active' => true, 'created_by' => $request->user()->id,
            ]);
            $copy->company_id = Tenancy::companyId();
            $copy->save();
            DocumentTemplateVersion::create(['company_id' => $copy->company_id, 'template_id' => $copy->id, 'version' => 1, 'body' => $copy->body,
                'change_note' => 'Copied from ' . $template->name . ' v' . $template->version, 'created_by' => $request->user()->id]);

            return $copy;
        });
        Audit::record('compliance.template_duplicated', $copy, "Template \"{$copy->name}\" copied for the company", ['from' => $template->id]);

        return redirect()->route('compliance.templates.edit', $copy)->with('success', 'Your own copy is ready to edit. The ZamPayroll master stays unchanged.');
    }

    public function create(Request $request)
    {
        $kind = $request->query('kind') === 'policy' ? 'policy' : 'contract';
        $this->authorizeKind($request, $kind);

        return view('compliance.templates.edit', ['template' => new DocumentTemplate(['kind' => $kind, 'key' => $kind === 'contract' ? 'permanent' : 'custom', 'is_active' => true]), 'kind' => $kind]);
    }

    public function store(Request $request)
    {
        $kind = $request->input('kind') === 'policy' ? 'policy' : 'contract';
        $this->authorizeKind($request, $kind);
        $d = $this->validated($request, $kind);
        $body = $d['body'] ?? '';
        if ($file = $request->file('word_file')) {
            $body = DocxImport::toHtml($file->getRealPath());
            if ($body === '') {
                return back()->withInput()->withErrors(['word_file' => 'We could not read any text from that Word file. Check that it is a .docx file.']);
            }
        }
        $t = new DocumentTemplate(['kind' => $kind, 'key' => $d['key'], 'name' => $d['name'], 'description' => $d['description'] ?? null,
            'legal_status' => $kind === 'policy' ? ($d['legal_status'] ?? 'recommended') : null, 'body' => HtmlSanitizer::clean($body) ?: '<p></p>',
            'version' => 1, 'is_active' => true, 'review_status' => 'unreviewed', 'created_by' => $request->user()->id]);
        $t->company_id = Tenancy::companyId();
        $t->save();
        DocumentTemplateVersion::create(['company_id' => $t->company_id, 'template_id' => $t->id, 'version' => 1, 'body' => $t->body,
            'change_note' => $request->file('word_file') ? 'Imported from Word' : 'Created', 'created_by' => $request->user()->id]);
        Audit::record('compliance.template_created', $t, "Template \"{$t->name}\" created");

        return redirect()->route('compliance.templates.edit', $t)->with('success', 'Template created.');
    }

    public function edit(Request $request, DocumentTemplate $template)
    {
        $this->authorizeKind($request, $template->kind);
        abort_unless($template->isOwnedByCurrentCompany(), 403, 'ZamPayroll master templates are read-only. Make your own copy to edit it.');

        return view('compliance.templates.edit', ['template' => $template, 'kind' => $template->kind]);
    }

    public function update(Request $request, DocumentTemplate $template)
    {
        $this->authorizeKind($request, $template->kind);
        abort_unless($template->isOwnedByCurrentCompany(), 403);
        $d = $this->validated($request, $template->kind);
        $body = HtmlSanitizer::clean($d['body'] ?? '');
        DB::transaction(function () use ($template, $d, $body, $request) {
            $changed = $body !== $template->body;
            $template->fill(['name' => $d['name'], 'key' => $d['key'], 'description' => $d['description'] ?? null,
                'legal_status' => $template->kind === 'policy' ? ($d['legal_status'] ?? $template->legal_status) : null, 'body' => $body]);
            if ($changed) {
                $template->version++;
                DocumentTemplateVersion::create(['company_id' => $template->company_id, 'template_id' => $template->id, 'version' => $template->version,
                    'body' => $body, 'change_note' => $d['change_note'] ?? 'Edited', 'created_by' => $request->user()->id]);
            }
            $template->save();
        });
        Audit::record('compliance.template_updated', $template, "Template \"{$template->name}\" saved as v{$template->version}");

        return redirect()->route('compliance.templates.show', $template)->with('success', 'Template saved. Documents already created from it are not changed.');
    }

    public function toggle(Request $request, DocumentTemplate $template)
    {
        $this->authorizeKind($request, $template->kind);
        abort_unless($template->isOwnedByCurrentCompany(), 403);
        $template->update(['is_active' => ! $template->is_active]);

        return back()->with('success', $template->is_active ? 'Template activated.' : 'Template deactivated. It will not be offered for new documents.');
    }

    private function validated(Request $request, string $kind): array
    {
        return $request->validate([
            'name' => 'required|string|max:160',
            'key' => $kind === 'contract' ? 'required|in:' . implode(',', array_keys(TemplateLibrary::CONTRACT_TYPES)) : 'required|string|max:60|regex:/^[a-z0-9_]+$/',
            'description' => 'nullable|string|max:1000',
            'legal_status' => 'nullable|in:required,conditional,recommended',
            'body' => 'nullable|string|max:400000',
            'change_note' => 'nullable|string|max:255',
            'word_file' => 'nullable|file|max:10240|mimes:docx',
        ]);
    }
}
