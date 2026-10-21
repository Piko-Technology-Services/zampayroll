<div class="pp-field full" style="margin:0">
    <label>Name</label>
    <input class="pp-input" name="name" value="{{ $t->name }}" required maxlength="80" placeholder="e.g. Study leave">
</div>
<div class="pp-field full" style="margin:0">
    <label>Short description <small>(shown to employees)</small></label>
    <input class="pp-input" name="description" value="{{ $t->description }}" maxlength="255">
</div>
<label class="rq-toggle"><input type="checkbox" name="is_paid" value="1" @checked($t->is_paid)> Paid</label>
<label class="rq-toggle"><input type="checkbox" name="deducts_balance" value="1" @checked($t->deducts_balance)> Uses the annual leave balance</label>
<label class="rq-toggle"><input type="checkbox" name="employees_can_apply" value="1" @checked($t->employees_can_apply)> Employees can ask for it</label>
<label class="rq-toggle"><input type="checkbox" name="requires_document" value="1" @checked($t->requires_document)> Needs a document</label>
<div class="pp-field" style="margin:0">
    <label>…when longer than <small>(days, blank = always)</small></label>
    <input class="pp-input" type="number" min="0" max="365" name="document_after_days" value="{{ $t->document_after_days }}">
</div>
<label class="rq-toggle"><input type="checkbox" name="is_active" value="1" @checked($t->is_active)> Switched on</label>
<div class="pp-field" style="margin:0">
    <label>Days per year <small>(blank = no limit)</small></label>
    <input class="pp-input" type="number" step="0.5" min="0" max="366" name="days_per_year" value="{{ $t->days_per_year !== null ? (float) $t->days_per_year : '' }}">
</div>
<div class="pp-field" style="margin:0">
    <label>Most days in one request <small>(blank = no limit)</small></label>
    <input class="pp-input" type="number" step="0.5" min="0.5" max="366" name="max_days_per_request" value="{{ $t->max_days_per_request !== null ? (float) $t->max_days_per_request : '' }}">
</div>
