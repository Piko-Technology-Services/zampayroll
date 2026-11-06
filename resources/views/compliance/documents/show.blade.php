@extends('layouts.app')
@section('title', $document->title . ' · ZamPayroll')
@include('compliance.partials.head')
@section('content')
@php $st = $document->expiryState(); @endphp
<div class="pr pp cc">
    <div class="pr-head"><div><h1>{{ $document->title }}</h1>
        <p>{{ $document->category?->fullName() ?? 'Uncategorised' }} · version {{ $document->current_version }}
            @if($document->status === 'archived')<span class="cc-pill">Archived</span>@endif
            @if($st === 'expired')<span class="cc-pill red">Expired {{ $document->expiry_date->format('j M Y') }}</span>@elseif($st === 'expiring')<span class="cc-pill amber">Expires {{ $document->expiry_date->format('j M Y') }}</span>@endif
            @if($document->reviewDue())<span class="cc-pill amber">Review due {{ $document->review_date->format('j M Y') }}</span>@endif</p></div>
        <div class="pr-head-actions">
            <a class="pp-btn pp-btn-ghost" href="{{ route('compliance.documents.index') }}"><i class="bi bi-arrow-left"></i> Documents</a>
            <a class="pp-btn pp-btn-primary" href="{{ route('compliance.documents.download', $document) }}"><i class="bi bi-download"></i> Download</a>
        </div></div>
    <div class="pr-layout">
        <div>
            @if($current && $current->previewable())
                @if($current->extension === 'pdf')
                    <iframe class="cc-preview-frame" src="{{ route('compliance.documents.preview', [$document, $current->version]) }}" title="Preview of {{ $document->title }}"></iframe>
                @else
                    <div class="cc-paper" style="text-align:center"><img src="{{ route('compliance.documents.preview', [$document, $current->version]) }}" alt="{{ $document->title }}" style="max-width:100%;max-height:75vh"></div>
                @endif
            @else
                <div class="cc-paper" style="text-align:center;padding:3rem 1rem"><i class="bi bi-file-earmark" style="font-size:2.5rem;color:var(--zp-ink-3)"></i>
                    <p style="margin-top:.6rem">{{ strtoupper($current?->extension) }} files can't be previewed in the browser.</p>
                    <a class="pp-btn pp-btn-primary" href="{{ route('compliance.documents.download', $document) }}"><i class="bi bi-download"></i> Download to open</a></div>
            @endif

            @if($canManage)
            <section class="pr-card" style="margin-top:1rem">
                <div class="pr-card-head"><h2>Details and access</h2></div>
                <form method="POST" action="{{ route('compliance.documents.update', $document) }}">@csrf @method('PUT')
                    @include('compliance.partials.document-meta', ['doc' => $document, 'withTitle' => true])
                    <button class="pp-btn pp-btn-primary" style="margin-top:.8rem"><i class="bi bi-check2"></i> Save details</button>
                </form>
            </section>
            @endif
        </div>
        <aside class="pr-side">
            <section class="pr-card">
                <h2 style="font-size:1.05rem;margin-bottom:.5rem">About</h2>
                <dl style="display:grid;grid-template-columns:auto 1fr;gap:.3rem .7rem;font-size:.86rem;margin:0">
                    @foreach(['Reference' => $document->reference, 'Employee' => $document->employee?->full_name, 'Department' => $document->department, 'Branch' => $document->branch,
                        'Issued' => optional($document->issue_date)->format('j M Y'), 'Effective' => optional($document->effective_date)->format('j M Y'),
                        'Access' => \App\Models\Compliance\CompanyDocument::ACCESS[$document->access_level] ?? $document->access_level, 'In the portal' => $document->share_with_employees ? 'Yes' : 'No',
                        'Requirement' => $document->compliance_requirement, 'Uploaded by' => $document->uploaded_by_name, 'Uploaded' => $document->created_at->format('j M Y H:i')] as $k => $v)
                        @if($v)<dt class="pp-muted" style="font-weight:600">{{ $k }}</dt><dd style="margin:0">{{ $v }}</dd>@endif
                    @endforeach
                </dl>
                @if($document->tags)<div style="display:flex;flex-wrap:wrap;gap:.25rem;margin-top:.6rem">@foreach($document->tags as $t)<a class="cc-pill" href="{{ route('compliance.documents.index', ['tag' => $t]) }}">#{{ $t }}</a>@endforeach</div>@endif
                @if($document->notes)<p class="cc-note" style="margin-top:.6rem">{{ $document->notes }}</p>@endif
                <p class="cc-note" style="margin-top:.6rem">Stored securely. ZamPayroll has not checked or legally validated this document.</p>
            </section>
            <section class="pr-card">
                <h2 style="font-size:1.05rem;margin-bottom:.5rem">Versions</h2>
                <ul class="cc-timeline">
                    @foreach($document->versions as $v)
                        <li><i class="bi bi-file-earmark{{ $v->version === $document->current_version ? '-check' : '' }}"></i><div>
                            v{{ $v->version }}{{ $v->version === $document->current_version ? ' (current)' : '' }} · {{ $v->original_name }}
                            <small>{{ $v->humanSize() }} · {{ $v->uploaded_by_name }} · {{ \Illuminate\Support\Carbon::parse($v->created_at)->format('j M Y H:i') }}{{ $v->note ? ' · ' . $v->note : '' }}</small>
                            <small><a href="{{ route('compliance.documents.download', [$document, $v->version]) }}">Download</a>@if($v->previewable()) · <a href="{{ route('compliance.documents.preview', [$document, $v->version]) }}" target="_blank">Open</a>@endif</small></div></li>
                    @endforeach
                </ul>
                @if($canManage && $document->status === 'active')
                <form method="POST" action="{{ route('compliance.documents.replace', $document) }}" enctype="multipart/form-data" style="display:grid;gap:.4rem;margin-top:.8rem">@csrf
                    <label style="font-size:.85rem;font-weight:700">Upload a new version</label>
                    <input type="file" class="pp-input" name="file" required accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                    <input class="pp-input" name="note" maxlength="255" placeholder="What changed?">
                    <button class="pp-btn pp-btn-ghost pp-btn-sm"><i class="bi bi-arrow-repeat"></i> Replace (keeps the old one)</button>
                    @error('file')<small style="color:var(--zp-red)">{{ $message }}</small>@enderror @error('files')<small style="color:var(--zp-red)">{{ $message }}</small>@enderror
                </form>
                @endif
            </section>
            @if($canManage)
            <section class="pr-card">
                @if($document->status === 'active')
                    <form method="POST" action="{{ route('compliance.documents.archive', $document) }}" onsubmit="return confirm('Archive this document? It is kept and can be restored.')">@csrf<button class="pp-btn pp-btn-ghost pp-btn-sm" style="color:var(--zp-red)"><i class="bi bi-archive"></i> Archive</button></form>
                @else
                    <form method="POST" action="{{ route('compliance.documents.restore', $document) }}">@csrf<button class="pp-btn pp-btn-ghost pp-btn-sm"><i class="bi bi-arrow-counterclockwise"></i> Restore</button></form>
                @endif
                <h3 style="font-size:.9rem;margin:.8rem 0 .4rem">Activity</h3>
                <ul class="cc-timeline">@foreach($history as $h)<li><i class="bi bi-dot"></i><div>{{ $h->summary }}<small>{{ $h->user_name ?? 'ZamPayroll' }} · {{ $h->created_at->format('j M Y H:i') }}</small></div></li>@endforeach</ul>
            </section>
            @endif
        </aside>
    </div>
</div>
@endsection
