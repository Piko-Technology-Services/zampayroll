<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Compliance\CompanyDocument;
use App\Models\Compliance\CompanyDocumentVersion;
use App\Models\Compliance\DocumentCategory;
use App\Models\Department;
use App\Models\Employee;
use App\Services\Compliance\DocumentAccess;
use App\Services\Compliance\TableExport;
use App\Services\Compliance\Vault;
use App\Support\Audit;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The company document repository: secure storage for documents made outside ZamPayroll.
 * Storing a document here does not mean it has been checked or legally validated.
 */
class DocumentController extends Controller
{
    public const MAX_MB = 20;

    /** Allowed extensions and the content types we accept for each (checked from the file's bytes, not its name). */
    public const TYPES = [
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xls'  => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'jpg'  => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
    ];

    public const MIME_OUT = ['pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];

    private function query(Request $request)
    {
        return DocumentAccess::scopeForStaff(CompanyDocument::query(), $request->user());
    }

    private function findVisible(Request $request, CompanyDocument $document): CompanyDocument
    {
        abort_unless(DocumentAccess::canStaffView($request->user(), $document), 404);

        return $document;
    }

    public function index(Request $request)
    {
        DocumentCategory::ensureDefaults();
        $q = $this->query($request)->with(['category.parent', 'latestVersion', 'employee']);
        $q->where('status', $request->query('status') === 'archived' ? 'archived' : 'active');

        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('title', 'like', "%{$term}%")->orWhere('reference', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")->orWhere('tags', 'like', '%' . str_replace(['%', '_'], '', $term) . '%'));
        }
        if ($cat = (int) $request->query('category')) {
            $ids = DocumentCategory::where('parent_id', $cat)->pluck('id')->push($cat);
            $q->whereIn('category_id', $ids);
        }
        if ($emp = (int) $request->query('employee')) {
            $q->where('employee_id', $emp);
        }
        if ($dept = $request->query('department')) {
            $q->where('department', $dept);
        }
        if ($type = $request->query('type')) {
            $q->whereHas('latestVersion', fn ($v) => $v->whereIn('extension', $type === 'image' ? ['jpg', 'jpeg', 'png'] : ($type === 'word' ? ['doc', 'docx'] : ($type === 'excel' ? ['xls', 'xlsx'] : ['pdf']))));
        }
        if ($tag = trim((string) $request->query('tag'))) {
            $q->where('tags', 'like', '%"' . str_replace(['%', '_', '"'], '', $tag) . '"%');
        }
        match ($request->query('expiry')) {
            'soon' => $q->whereNotNull('expiry_date')->whereBetween('expiry_date', [now()->startOfDay(), now()->addDays(30)]),
            'expired' => $q->whereNotNull('expiry_date')->where('expiry_date', '<', now()->startOfDay()),
            'review' => $q->whereNotNull('review_date')->where('review_date', '<=', now()->addDays(30)),
            default => null,
        };
        foreach (['uploaded' => 'created_at', 'issued' => 'issue_date', 'expires' => 'expiry_date'] as $f => $col) {
            if ($from = $request->query($f . '_from')) {
                $q->whereDate($col, '>=', $from);
            }
            if ($to = $request->query($f . '_to')) {
                $q->whereDate($col, '<=', $to);
            }
        }
        $sort = $request->query('sort', 'recent');
        match ($sort) {
            'title' => $q->orderBy('title'),
            'expiry' => $q->orderByRaw('expiry_date IS NULL')->orderBy('expiry_date'),
            'updated' => $q->latest('updated_at'),
            default => $q->latest('id'),
        };

        $base = $this->query($request)->where('status', 'active');

        return view('compliance.documents.index', [
            'documents' => $q->paginate(30)->withQueryString(),
            'categories' => DocumentCategory::with('children')->whereNull('parent_id')->orderBy('sort')->get(),
            'employees' => Employee::orderBy('first_name')->get(['id', 'first_name', 'last_name', 'employee_id']),
            'departments' => Employee::whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
            'view' => $request->query('view') === 'grid' ? 'grid' : 'list',
            'canManage' => DocumentAccess::isManager($request->user()),
            'summary' => [
                'total' => (clone $base)->count(),
                'expiring' => (clone $base)->whereNotNull('expiry_date')->whereBetween('expiry_date', [now()->startOfDay(), now()->addDays(30)])->count(),
                'expired' => (clone $base)->whereNotNull('expiry_date')->where('expiry_date', '<', now()->startOfDay())->count(),
                'review' => (clone $base)->whereNotNull('review_date')->where('review_date', '<=', now()->addDays(30))->count(),
                'recent' => (clone $base)->where('created_at', '>=', now()->subDays(7))->count(),
            ],
            'byCategory' => (clone $base)->selectRaw('category_id, count(*) n')->groupBy('category_id')->pluck('n', 'category_id'),
        ]);
    }

    public function create(Request $request)
    {
        DocumentCategory::ensureDefaults();

        return view('compliance.documents.create', [
            'categories' => DocumentCategory::with('children')->whereNull('parent_id')->orderBy('sort')->get(),
            'employees' => Employee::orderBy('first_name')->get(['id', 'first_name', 'last_name', 'employee_id', 'department']),
            'departments' => Employee::whereNotNull('department')->distinct()->orderBy('department')->pluck('department')
                ->merge(class_exists(Department::class) ? Department::pluck('name') : [])->unique()->sort()->values(),
            'maxMb' => self::MAX_MB,
        ]);
    }

    private function metaRules(): array
    {
        return [
            'title' => 'nullable|string|max:200',
            'description' => 'nullable|string|max:2000',
            'category_id' => 'nullable|integer',
            'reference' => 'nullable|string|max:80',
            'issue_date' => 'nullable|date', 'effective_date' => 'nullable|date',
            'expiry_date' => 'nullable|date', 'review_date' => 'nullable|date',
            'department' => 'nullable|string|max:120', 'branch' => 'nullable|string|max:120',
            'access_level' => 'required|in:' . implode(',', array_keys(CompanyDocument::ACCESS)),
            'access_department' => 'nullable|required_if:access_level,department|string|max:120',
            'employee_id' => 'nullable|required_if:access_level,employee|integer',
            'share_with_employees' => 'nullable|boolean',
            'compliance_requirement' => 'nullable|string|max:160',
            'tags' => 'nullable|string|max:300',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    private function meta(array $d): array
    {
        if (! empty($d['category_id'])) {
            DocumentCategory::findOrFail($d['category_id']);
        }
        if (! empty($d['employee_id'])) {
            Employee::findOrFail($d['employee_id']);
        }
        $tags = collect(explode(',', (string) ($d['tags'] ?? '')))->map(fn ($t) => mb_substr(trim($t), 0, 40))->filter()->unique()->take(15)->values()->all();

        return [
            'category_id' => $d['category_id'] ?? null, 'description' => $d['description'] ?? null, 'reference' => $d['reference'] ?? null,
            'issue_date' => $d['issue_date'] ?? null, 'effective_date' => $d['effective_date'] ?? null, 'expiry_date' => $d['expiry_date'] ?? null,
            'review_date' => $d['review_date'] ?? null, 'department' => $d['department'] ?? null, 'branch' => $d['branch'] ?? null,
            'access_level' => $d['access_level'], 'access_department' => $d['access_level'] === 'department' ? ($d['access_department'] ?? null) : null,
            'employee_id' => $d['employee_id'] ?? null, 'share_with_employees' => (bool) ($d['share_with_employees'] ?? false),
            'compliance_requirement' => $d['compliance_requirement'] ?? null, 'tags' => $tags ?: null, 'notes' => $d['notes'] ?? null,
        ];
    }

    public function store(Request $request)
    {
        $d = $request->validate($this->metaRules() + [
            'files' => 'required|array|min:1|max:25',
            'files.*' => 'file|max:' . (self::MAX_MB * 1024 * 5),
        ], ['files.required' => 'Choose at least one file to upload.']);
        $meta = $this->meta($d);

        // Expand ZIPs safely, validate everything first, then store.
        $items = [];
        foreach ($request->file('files') as $file) {
            $ext = strtolower($file->getClientOriginalExtension());
            if ($ext === 'zip') {
                foreach ($this->extractZip($file) as $entry) {
                    $items[] = $entry;
                }
                continue;
            }
            $bytes = file_get_contents($file->getRealPath());
            $this->validateBytes($file->getClientOriginalName(), $bytes);
            $items[] = ['name' => $file->getClientOriginalName(), 'bytes' => $bytes];
        }
        if (! $items) {
            throw ValidationException::withMessages(['files' => 'No supported files found.']);
        }

        $created = DB::transaction(function () use ($items, $meta, $d, $request) {
            $docs = [];
            foreach ($items as $item) {
                $title = count($items) === 1 && ! empty($d['title']) ? $d['title'] : Str::limit(pathinfo($item['name'], PATHINFO_FILENAME), 190, '');
                $doc = CompanyDocument::create($meta + ['title' => $title, 'status' => 'active', 'current_version' => 1,
                    'uploaded_by' => $request->user()->id, 'uploaded_by_name' => $request->user()->name]);
                $this->storeVersion($doc, 1, $item['name'], $item['bytes'], 'Original upload', $request);
                $docs[] = $doc;
            }

            return $docs;
        });
        foreach ($created as $doc) {
            Audit::record('compliance.document_uploaded', $doc, "Uploaded \"{$doc->title}\"", ['access' => $doc->access_level]);
        }

        $msg = count($created) === 1 ? 'Document uploaded.' : count($created) . ' documents uploaded.';
        if ($request->expectsJson()) {
            return response()->json(['created' => count($created), 'message' => $msg,
                'redirect' => count($created) === 1 ? route('compliance.documents.show', $created[0]) : route('compliance.documents.index')]);
        }

        return redirect(count($created) === 1 ? route('compliance.documents.show', $created[0]) : route('compliance.documents.index'))->with('success', $msg);
    }

    private function validateBytes(string $name, string $bytes): string
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (! isset(self::TYPES[$ext])) {
            throw ValidationException::withMessages(['files' => "\"{$name}\": only PDF, Word, Excel, JPG and PNG files (or a ZIP of them) can be uploaded."]);
        }
        if (strlen($bytes) > self::MAX_MB * 1024 * 1024) {
            throw ValidationException::withMessages(['files' => "\"{$name}\" is larger than " . self::MAX_MB . ' MB.']);
        }
        if (strlen($bytes) === 0) {
            throw ValidationException::withMessages(['files' => "\"{$name}\" is empty."]);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream';
        if (! in_array($mime, self::TYPES[$ext], true)) {
            throw ValidationException::withMessages(['files' => "\"{$name}\" does not look like a real .{$ext} file."]);
        }
        // Office files must actually be Office files, not something else renamed.
        if (in_array($ext, ['docx', 'xlsx'], true) && ! str_starts_with($bytes, "PK\x03\x04")) {
            throw ValidationException::withMessages(['files' => "\"{$name}\" is not a valid .{$ext} file."]);
        }
        if (in_array($ext, ['doc', 'xls'], true) && ! str_starts_with($bytes, "\xD0\xCF\x11\xE0")) {
            throw ValidationException::withMessages(['files' => "\"{$name}\" is not a valid .{$ext} file."]);
        }

        return $mime;
    }

    /** Read a ZIP without ever writing its paths to disk: names are reduced to a basename and every file is checked. */
    private function extractZip(UploadedFile $file): array
    {
        $bytes = file_get_contents($file->getRealPath());
        if (! str_starts_with($bytes, "PK\x03\x04")) {
            throw ValidationException::withMessages(['files' => 'That ZIP file could not be opened.']);
        }
        $zip = new \ZipArchive();
        if ($zip->open($file->getRealPath()) !== true) {
            throw ValidationException::withMessages(['files' => 'That ZIP file could not be opened.']);
        }
        $out = [];
        $total = 0;
        if ($zip->numFiles > 100) {
            throw ValidationException::withMessages(['files' => 'A ZIP may hold up to 100 files.']);
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = (string) $stat['name'];
            if (str_ends_with($name, '/') || str_starts_with(basename($name), '.') || str_contains($name, '__MACOSX')) {
                continue;
            }
            $base = basename(str_replace('\\', '/', $name));
            $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
            if (! isset(self::TYPES[$ext])) {
                continue; // unsupported files inside a ZIP are skipped, not stored
            }
            $total += (int) $stat['size'];
            if ((int) $stat['size'] > self::MAX_MB * 1024 * 1024 || $total > 200 * 1024 * 1024) {
                throw ValidationException::withMessages(['files' => 'The ZIP is too large once unpacked (max ' . self::MAX_MB . ' MB per file, 200 MB in total).']);
            }
            $content = $zip->getFromIndex($i, self::MAX_MB * 1024 * 1024 + 1);
            if ($content === false) {
                continue;
            }
            $this->validateBytes($base, $content);
            $out[] = ['name' => $base, 'bytes' => $content];
        }
        $zip->close();

        return $out;
    }

    private function storeVersion(CompanyDocument $doc, int $version, string $name, string $bytes, string $note, Request $request): CompanyDocumentVersion
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream';

        return CompanyDocumentVersion::create([
            'document_id' => $doc->id, 'version' => $version, 'path' => Vault::putContents($bytes, 'documents', $ext),
            'original_name' => mb_substr($name, 0, 255), 'mime' => $mime, 'extension' => $ext, 'size' => strlen($bytes),
            'sha256' => hash('sha256', $bytes), 'note' => $note, 'uploaded_by' => $request->user()->id, 'uploaded_by_name' => $request->user()->name,
        ]);
    }

    public function show(Request $request, CompanyDocument $document)
    {
        $this->findVisible($request, $document);
        $document->load(['category.parent', 'employee', 'versions']);
        Audit::record('compliance.document_viewed', $document, "Opened \"{$document->title}\"");

        return view('compliance.documents.show', [
            'document' => $document,
            'current' => $document->versions->firstWhere('version', $document->current_version) ?? $document->versions->first(),
            'canManage' => DocumentAccess::isManager($request->user()),
            'categories' => DocumentCategory::with('children')->whereNull('parent_id')->orderBy('sort')->get(),
            'employees' => Employee::orderBy('first_name')->get(['id', 'first_name', 'last_name', 'employee_id']),
            'departments' => Employee::whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
            'history' => \App\Models\AuditLog::where('company_id', Tenancy::companyId())->where('subject_type', 'CompanyDocument')->where('subject_id', $document->id)->latest('id')->limit(40)->get(),
        ]);
    }

    public function update(Request $request, CompanyDocument $document)
    {
        $d = $request->validate($this->metaRules() + ['title' => 'required|string|max:200']);
        $before = $document->only(['access_level', 'access_department', 'employee_id', 'share_with_employees']);
        $document->fill($this->meta($d) + ['title' => $d['title']])->save();
        $after = $document->only(array_keys($before));
        Audit::record('compliance.document_updated', $document, "Details of \"{$document->title}\" updated");
        if ($before != $after) {
            Audit::record('compliance.document_permissions', $document, "Access to \"{$document->title}\" changed", ['from' => $before, 'to' => $after]);
        }

        return back()->with('success', 'Details saved.');
    }

    /** Upload a new version. The previous file is kept and stays downloadable. */
    public function replace(Request $request, CompanyDocument $document)
    {
        $d = $request->validate(['file' => 'required|file|max:' . (self::MAX_MB * 1024), 'note' => 'nullable|string|max:255']);
        $file = $request->file('file');
        $bytes = file_get_contents($file->getRealPath());
        $this->validateBytes($file->getClientOriginalName(), $bytes);
        DB::transaction(function () use ($document, $file, $bytes, $d, $request) {
            $document->current_version++;
            $this->storeVersion($document, $document->current_version, $file->getClientOriginalName(), $bytes, $d['note'] ?? 'Replaced', $request);
            $document->save();
        });
        Audit::record('compliance.document_replaced', $document, "New version {$document->current_version} of \"{$document->title}\"", ['note' => $d['note'] ?? null]);

        return back()->with('success', "Version {$document->current_version} uploaded. Earlier versions are kept.");
    }

    private function versionRow(CompanyDocument $document, ?int $version): CompanyDocumentVersion
    {
        return CompanyDocumentVersion::where('document_id', $document->id)->where('version', $version ?? $document->current_version)->firstOrFail();
    }

    public function download(Request $request, CompanyDocument $document, ?int $version = null)
    {
        $this->findVisible($request, $document);
        $v = $this->versionRow($document, $version);

        return $this->serve($document, $v, 'attachment');
    }

    public function preview(Request $request, CompanyDocument $document, ?int $version = null)
    {
        $this->findVisible($request, $document);
        $v = $this->versionRow($document, $version);
        abort_unless($v->previewable(), 415, 'This file type cannot be previewed. Download it instead.');

        return $this->serve($document, $v, 'inline');
    }

    /** Stream a stored file after checking it belongs to this company and has not been altered. */
    public static function serve(CompanyDocument $document, CompanyDocumentVersion $v, string $disposition)
    {
        abort_unless(Vault::belongsTo($v->path, (int) $document->company_id), 404);
        $bytes = Vault::get($v->path);
        abort_unless($bytes !== null, 404);
        abort_unless(hash_equals($v->sha256, hash('sha256', $bytes)), 410, 'This file failed its integrity check.');
        Audit::record($disposition === 'inline' ? 'compliance.document_previewed' : 'compliance.document_downloaded', $document, "\"{$document->title}\" v{$v->version}");
        $filename = Str::slug(pathinfo($v->original_name, PATHINFO_FILENAME)) . '.' . $v->extension;

        return response($bytes, 200, [
            'Content-Type' => self::MIME_OUT[$v->extension] ?? 'application/octet-stream',
            'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function archive(Request $request, CompanyDocument $document)
    {
        $document->update(['status' => 'archived']);
        Audit::record('compliance.document_archived', $document, "Archived \"{$document->title}\"", ['reason' => $request->input('reason')]);

        return redirect()->route('compliance.documents.index')->with('success', 'Archived. The file and its versions are kept and can be restored.');
    }

    public function restore(CompanyDocument $document)
    {
        $document->update(['status' => 'active']);
        Audit::record('compliance.document_restored', $document, "Restored \"{$document->title}\"");

        return back()->with('success', 'Restored.');
    }

    public function storeCategory(Request $request)
    {
        $d = $request->validate(['name' => 'required|string|max:120', 'parent_id' => 'nullable|integer']);
        if (! empty($d['parent_id'])) {
            abort_unless(DocumentCategory::whereKey($d['parent_id'])->whereNull('parent_id')->exists(), 422);
        }
        DocumentCategory::create(['name' => $d['name'], 'parent_id' => $d['parent_id'] ?? null, 'sort' => 99]);

        return back()->with('success', 'Category added.');
    }

    public function destroyCategory(DocumentCategory $category)
    {
        $ids = DocumentCategory::where('parent_id', $category->id)->pluck('id')->push($category->id);
        if (CompanyDocument::whereIn('category_id', $ids)->exists()) {
            return back()->with('error', 'Move the documents in this category first.');
        }
        DocumentCategory::whereIn('id', $ids)->delete();

        return back()->with('success', 'Category removed.');
    }

    /** The document register: every stored document with its category, dates and status. */
    public function register(Request $request)
    {
        $rows = $this->query($request)->with(['category.parent', 'latestVersion', 'employee'])->orderBy('title')->get()->map(fn (CompanyDocument $d) => [
            $d->title, $d->reference, $d->category?->fullName(), $d->employee?->full_name, $d->department, CompanyDocument::ACCESS[$d->access_level] ?? $d->access_level,
            optional($d->issue_date)->format('Y-m-d'), optional($d->expiry_date)->format('Y-m-d'), optional($d->review_date)->format('Y-m-d'),
            $d->status === 'archived' ? 'Archived' : (['expired' => 'Expired', 'expiring' => 'Expiring soon'][$d->expiryState()] ?? 'Active'),
            'v' . $d->current_version, strtoupper((string) $d->latestVersion?->extension), $d->uploaded_by_name, $d->created_at->format('Y-m-d'),
        ])->all();
        Audit::record('compliance.register_exported', null, 'Document register exported (' . count($rows) . ' documents)');

        return TableExport::download('Document register', ['Title', 'Reference', 'Category', 'Employee', 'Department', 'Access', 'Issued', 'Expires', 'Review', 'Status', 'Version', 'Type', 'Uploaded by', 'Uploaded'],
            $rows, in_array($request->query('format'), ['csv', 'xlsx', 'pdf'], true) ? $request->query('format') : 'xlsx');
    }
}
