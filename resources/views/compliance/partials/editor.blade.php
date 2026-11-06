{{-- Rich text editor for document bodies. Copies its HTML into the hidden "body" field on submit. --}}
@php $editorId = $id ?? 'ccEditor'; @endphp
<div class="cc-editor-bar" role="toolbar" aria-label="Formatting">
    <button type="button" data-cmd="formatBlock" data-val="h2" title="Section heading">H2</button>
    <button type="button" data-cmd="formatBlock" data-val="h3" title="Sub-heading">H3</button>
    <button type="button" data-cmd="formatBlock" data-val="p" title="Normal text">¶</button>
    <span class="sep"></span>
    <button type="button" data-cmd="bold" title="Bold" aria-label="Bold"><b>B</b></button>
    <button type="button" data-cmd="italic" title="Italic" aria-label="Italic"><i>I</i></button>
    <button type="button" data-cmd="underline" title="Underline" aria-label="Underline"><u>U</u></button>
    <span class="sep"></span>
    <button type="button" data-cmd="insertUnorderedList" title="Bulleted list" aria-label="Bulleted list"><i class="bi bi-list-ul"></i></button>
    <button type="button" data-cmd="insertOrderedList" title="Numbered list" aria-label="Numbered list"><i class="bi bi-list-ol"></i></button>
    <button type="button" data-table title="Insert a table" aria-label="Insert table"><i class="bi bi-table"></i></button>
    <button type="button" data-cmd="insertHorizontalRule" title="Line" aria-label="Line"><i class="bi bi-dash-lg"></i></button>
    <span class="sep"></span>
    <button type="button" data-cmd="undo" title="Undo" aria-label="Undo"><i class="bi bi-arrow-counterclockwise"></i></button>
    <button type="button" data-cmd="redo" title="Redo" aria-label="Redo"><i class="bi bi-arrow-clockwise"></i></button>
    @if(!empty($placeholders))
    <span class="sep"></span>
    <select data-insert aria-label="Insert a detail">
        <option value="">Insert a detail…</option>
        @foreach($placeholders as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
    </select>
    @endif
</div>
<div id="{{ $editorId }}" class="cc-editor" contenteditable="true" spellcheck="true" aria-label="Document text">{!! $html !!}</div>
<textarea name="{{ $name ?? 'body' }}" id="{{ $editorId }}Field" hidden>{{ $html }}</textarea>
@push('scripts')
<script>
(() => {
  const ed = document.getElementById(@json($editorId));
  const field = document.getElementById(@json($editorId . 'Field'));
  const bar = ed.previousElementSibling;
  const sync = () => { field.value = ed.innerHTML; };
  bar.querySelectorAll('[data-cmd]').forEach(b => b.addEventListener('click', () => {
    ed.focus(); document.execCommand(b.dataset.cmd, false, b.dataset.val ? '<' + b.dataset.val + '>' : null); sync();
  }));
  const t = bar.querySelector('[data-table]');
  t && t.addEventListener('click', () => {
    ed.focus();
    document.execCommand('insertHTML', false, '<table><tr><th>Heading</th><th>Heading</th></tr><tr><td>&nbsp;</td><td>&nbsp;</td></tr><tr><td>&nbsp;</td><td>&nbsp;</td></tr></table><p></p>');
    sync();
  });
  const ins = bar.querySelector('[data-insert]');
  ins && ins.addEventListener('change', () => {
    if (!ins.value) return; ed.focus();
    const o = String.fromCharCode(123, 123), c = String.fromCharCode(125, 125);
    document.execCommand('insertText', false, o + ins.value + c); ins.value = ''; sync();
  });
  ed.addEventListener('input', sync);
  // Paste as plain text to keep documents clean.
  ed.addEventListener('paste', e => { e.preventDefault(); document.execCommand('insertText', false, (e.clipboardData || window.clipboardData).getData('text')); });
  ed.closest('form') && ed.closest('form').addEventListener('submit', sync);
})();
</script>
@endpush
