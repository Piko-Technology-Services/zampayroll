{{--
    Add / edit employee — a step-by-step journey with a live ID card.
    Expects: $employee, $departments, $branches, $positions, $people, $banks, $natures, $nextNumber, $mode ('create'|'edit')
--}}
@php
    $isEdit = $mode === 'edit';
    $v = fn ($f) => old($f, $employee->{$f});
    $d = fn ($f) => old($f, optional($employee->{$f})->format('Y-m-d'));
    $steps = [
        1 => ['who', 'Who', 'bi-person'],
        2 => ['job', 'Job', 'bi-briefcase'],
        3 => ['pay', 'Pay & bank', 'bi-cash-coin'],
        4 => ['statutory', 'ZRA · NAPSA · NHIMA', 'bi-shield-check'],
        5 => ['contact', 'Contact & portal', 'bi-telephone'],
        6 => ['review', 'Review', 'bi-check2-circle'],
    ];
    $startStep = (int) array_search($startStep ?? null, array_column($steps, 0), true);
    $startStep = $startStep ? $startStep + 1 : 1;
@endphp

<div class="pp" id="ppJourney" data-start="{{ $startStep }}" data-mode="{{ $mode }}">

    <section class="pp-hero" style="padding-bottom:1.25rem">
        <div class="pp-eyebrow">People · {{ $isEdit ? 'Update profile' : 'Add employee' }}</div>
        <h1>{{ $isEdit ? 'Update ' . $employee->first_name . "'s profile" : "Let's welcome someone new" }}</h1>
        <p>{{ $isEdit ? 'Jump to any step, change what you need and save.' : 'Six quick steps. Only the name is required — you can save after step one and finish the rest later.' }}</p>
    </section>

    <div class="pp-steps" id="ppSteps" role="tablist">
        @foreach($steps as $n => [$key, $label, $icon])
            <button type="button" class="pp-step" data-step="{{ $n }}" @disabled(! $isEdit && $n > 1)>
                <span class="n">{{ $n }}</span><span class="t">{{ $label }}</span>
            </button>
        @endforeach
    </div>

    @if($errors->any())
        <div class="pp-error"><i class="bi bi-exclamation-triangle me-1"></i> Please check the highlighted fields: {{ implode(' ', array_slice($errors->all(), 0, 3)) }}</div>
    @endif

    <form method="POST" enctype="multipart/form-data" id="ppForm" novalidate
          action="{{ $isEdit ? route('employees.update', $employee) : route('employees.store') }}">
        @csrf
        @if($isEdit) @method('PUT') @endif
        <input type="hidden" name="intent" value="save" id="ppIntent">

        <div class="pp-journey">
            <div class="pp-card">

                {{-- 1 · WHO --}}
                <div class="pp-panel" data-panel="1">
                    <h2 class="pp-q">Who are we adding?</h2>
                    <p class="pp-qsub">Use the name exactly as it appears on their NRC — it goes on payslips and ZRA returns.</p>

                    <div class="pp-photo" style="margin-bottom:1.2rem">
                        <div class="pp-photo-ring" id="ppPhotoRing">
                            @if($employee->photo_url)<img src="{{ $employee->photo_url }}" alt="">@else<i class="bi bi-camera"></i>@endif
                        </div>
                        <div>
                            <label class="pp-btn pp-btn-ghost pp-btn-sm" style="margin:0">
                                <i class="bi bi-upload"></i> {{ $employee->passport_photo ? 'Change photo' : 'Add a photo' }}
                                <input type="file" name="passport_photo" accept="image/*" id="ppPhoto" hidden>
                            </label>
                            <div class="pp-muted" style="font-size:.76rem;margin-top:.35rem">Optional · passport-style, up to 4 MB</div>
                        </div>
                    </div>

                    <div class="pp-grid pp-grid-3">
                        @foreach(['first_name' => 'First name', 'middle_name' => 'Middle name', 'last_name' => 'Surname'] as $f => $l)
                            <div class="pp-field @error($f) invalid @enderror">
                                <label for="f_{{ $f }}">{{ $l }} @if($f === 'middle_name')<small>optional</small>@endif</label>
                                <input class="pp-input" id="f_{{ $f }}" name="{{ $f }}" value="{{ $v($f) }}" @if($f !== 'middle_name') required @endif autocomplete="off" data-live="{{ $f }}">
                                @error($f)<div class="err">{{ $message }}</div>@enderror
                            </div>
                        @endforeach
                    </div>

                    <div class="pp-grid" style="margin-top:1rem">
                        <div class="pp-field">
                            <label>Gender</label>
                            <div class="pp-pills">
                                @foreach(['Female' => 'bi-gender-female', 'Male' => 'bi-gender-male'] as $g => $ic)
                                    <label class="pp-pill"><input type="radio" name="gender" value="{{ $g }}" @checked($v('gender') === $g)><span><i class="bi {{ $ic }}"></i>{{ $g }}</span></label>
                                @endforeach
                            </div>
                        </div>
                        <div class="pp-field @error('date_of_birth') invalid @enderror">
                            <label for="f_dob">Date of birth</label>
                            <input type="date" class="pp-input" id="f_dob" name="date_of_birth" value="{{ $d('date_of_birth') }}" max="{{ now()->subYears(14)->toDateString() }}">
                            <div class="hint" id="ppAge"></div>
                            @error('date_of_birth')<div class="err">{{ $message }}</div>@enderror
                        </div>
                        <div class="pp-field">
                            <label for="f_nrc">NRC number</label>
                            <input class="pp-input" id="f_nrc" name="nrc_no" value="{{ $v('nrc_no') }}" placeholder="123456/10/1" data-live="nrc_no" inputmode="numeric">
                            <div class="hint">Format: six digits / district / 1</div>
                        </div>
                        <div class="pp-field">
                            <label for="f_nat">Nationality</label>
                            <input class="pp-input" id="f_nat" name="nationality" value="{{ $v('nationality') }}" list="ppNationalities">
                            <datalist id="ppNationalities">@foreach(['Zambian','Zimbabwean','Malawian','Congolese','Tanzanian','South African','Kenyan','Chinese','Indian','Somali','British'] as $n)<option value="{{ $n }}">@endforeach</datalist>
                        </div>
                        <div class="pp-field" id="ppPassportField">
                            <label for="f_pass">Passport number <small>for non-Zambians</small></label>
                            <input class="pp-input" id="f_pass" name="passport_number" value="{{ $v('passport_number') }}">
                        </div>
                    </div>
                </div>

                {{-- 2 · JOB --}}
                <div class="pp-panel" data-panel="2" hidden>
                    <h2 class="pp-q">What will <span data-name>they</span> do?</h2>
                    <p class="pp-qsub">Pick from what you already use, or type something new.</p>
                    <div class="pp-grid">
                        <div class="pp-field">
                            <label for="f_pos">Job title</label>
                            <input class="pp-input" id="f_pos" name="position" value="{{ $v('position') }}" list="ppPositions" data-live="position" placeholder="e.g. Accountant">
                            <datalist id="ppPositions">@foreach($positions as $p)<option value="{{ $p }}">@endforeach</datalist>
                        </div>
                        <div class="pp-field">
                            <label for="f_dep">Department</label>
                            <input class="pp-input" id="f_dep" name="department" value="{{ $v('department') }}" list="ppDepts" data-live="department" placeholder="e.g. Finance">
                            <datalist id="ppDepts">@foreach($departments as $p)<option value="{{ $p }}">@endforeach</datalist>
                        </div>
                        <div class="pp-field">
                            <label for="f_br">Branch / site</label>
                            <input class="pp-input" id="f_br" name="branch" value="{{ $v('branch') }}" list="ppBranches" placeholder="e.g. Lusaka">
                            <datalist id="ppBranches">@foreach($branches as $p)<option value="{{ $p }}">@endforeach</datalist>
                        </div>
                        <div class="pp-field">
                            <label for="f_sup">Reports to</label>
                            <input class="pp-input" id="f_sup" name="supervisor" value="{{ $v('supervisor') }}" list="ppPeople" placeholder="Supervisor's name">
                            <datalist id="ppPeople">@foreach($people as $p)<option value="{{ $p }}">@endforeach</datalist>
                        </div>
                        <div class="pp-field pp-span-2">
                            <label>Type of employment</label>
                            <div class="pp-pills">
                                @foreach($natures as $k => $l)
                                    <label class="pp-pill"><input type="radio" name="employment_nature" value="{{ $k }}" @checked($v('employment_nature') === $k)><span>{{ $l }}</span></label>
                                @endforeach
                            </div>
                        </div>
                        <div class="pp-field">
                            <label for="f_start">Start date</label>
                            <input type="date" class="pp-input" id="f_start" name="contract_start" value="{{ $d('contract_start') }}" data-live="contract_start">
                        </div>
                        <div class="pp-field" data-show-for="PROBATION,PERMANENT">
                            <label for="f_prob">Probation ends <small>optional</small></label>
                            <input type="date" class="pp-input" id="f_prob" name="probation_end" value="{{ $d('probation_end') }}">
                            <div class="hint"><button type="button" class="pp-link" data-plus-months="3">+3 months</button> · <button type="button" class="pp-link" data-plus-months="6">+6 months</button></div>
                        </div>
                        <div class="pp-field" data-show-for="CONTRACT,CASUAL,INTERN">
                            <label for="f_end">Contract ends</label>
                            <input type="date" class="pp-input" id="f_end" name="contract_end" value="{{ $d('contract_end') }}">
                            <div class="hint"><button type="button" class="pp-link" data-plus-months="12" data-target="f_end">1 year</button> · <button type="button" class="pp-link" data-plus-months="24" data-target="f_end">2 years</button></div>
                        </div>
                        <div class="pp-field">
                            <label for="f_ein">Employee number</label>
                            <input class="pp-input" id="f_ein" name="employee_id" value="{{ old('employee_id', $isEdit ? $employee->employee_id : '') }}" placeholder="{{ $nextNumber }}" data-live="employee_id">
                            <div class="hint">{{ $isEdit ? 'Used on payslips and reports.' : 'Leave blank and we\'ll use ' . $nextNumber . ', or use your existing number.' }}</div>
                            @error('employee_id')<div class="err">{{ $message }}</div>@enderror
                        </div>
                        @if($isEdit)
                        <div class="pp-field">
                            <label>Status</label>
                            <div class="pp-pills">
                                @foreach(['Active', 'Suspended', 'Inactive', 'Terminated'] as $s)
                                    <label class="pp-pill"><input type="radio" name="employment_status" value="{{ $s }}" @checked(($v('employment_status') ?: 'Active') === $s)><span>{{ $s }}</span></label>
                                @endforeach
                            </div>
                            <div class="hint">Only Active employees are included in payroll runs.</div>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- 3 · PAY --}}
                <div class="pp-panel" data-panel="3" hidden>
                    <h2 class="pp-q">How much does <span data-name>they</span> take home?</h2>
                    <p class="pp-qsub">Tell us the monthly take-home pay. ZamPayroll works backwards to the gross, PAYE, NAPSA and NHIMA every payroll run.</p>
                    <div class="pp-grid">
                        <div class="pp-field pp-span-2">
                            <label for="f_net">Monthly take-home (net) pay</label>
                            <div class="pp-money"><span>K</span><input class="pp-input" id="f_net" name="net_salary" value="{{ $v('net_salary') }}" inputmode="decimal" placeholder="0.00" data-live="net_salary"></div>
                            <div class="hint" id="ppNetWords"></div>
                            @error('net_salary')<div class="err">{{ $message }}</div>@enderror
                        </div>
                        <div class="pp-field">
                            <label for="f_le">Leave days per year</label>
                            <input class="pp-input" id="f_le" name="leave_days_entitled" value="{{ $v('leave_days_entitled') }}" inputmode="decimal">
                            <div class="hint">The Employment Code minimum is 24 days (2 per month).</div>
                        </div>
                        <div class="pp-field">
                            <label for="f_lb">Leave days left today <small>optional</small></label>
                            <input class="pp-input" id="f_lb" name="leave_days_balance" value="{{ $v('leave_days_balance') }}" inputmode="decimal">
                            <div class="hint">Moving from another system? Bring their balance across.</div>
                        </div>
                    </div>

                    <h3 style="font:700 .95rem var(--zp-font);margin:1.5rem 0 .8rem"><i class="bi bi-bank me-1" style="color:var(--zp-copper)"></i> Where should we pay it?</h3>
                    <div class="pp-grid">
                        <div class="pp-field">
                            <label for="f_bank">Bank</label>
                            <input class="pp-input" id="f_bank" name="bank_name" value="{{ $v('bank_name') }}" list="ppBanks" placeholder="Start typing… e.g. Zanaco">
                            <datalist id="ppBanks">@foreach($banks as $b)<option value="{{ $b->name }}" data-sort="{{ $b->sort_code }}">@endforeach</datalist>
                        </div>
                        <div class="pp-field">
                            <label for="f_bbr">Bank branch</label>
                            <input class="pp-input" id="f_bbr" name="bank_branch" value="{{ $v('bank_branch') }}" placeholder="e.g. Cairo Road">
                        </div>
                        <div class="pp-field">
                            <label for="f_acc">Account number</label>
                            <input class="pp-input" id="f_acc" name="bank_account_no" value="{{ $v('bank_account_no') }}" inputmode="numeric">
                        </div>
                        <div class="pp-field">
                            <label for="f_sort">Sort code <small>filled from the bank when we know it</small></label>
                            <input class="pp-input" id="f_sort" name="bank_sort_code" value="{{ $v('bank_sort_code') }}">
                        </div>
                    </div>
                    <div class="pp-callout"><i class="bi bi-phone"></i><div>Paid by mobile money? Put <b>Airtel Money</b> or <b>MTN MoMo</b> as the bank and the phone number as the account.</div></div>
                </div>

                {{-- 4 · STATUTORY --}}
                <div class="pp-panel" data-panel="4" hidden>
                    <h2 class="pp-q">Statutory numbers</h2>
                    <p class="pp-qsub">These appear on the ZRA PAYE return, NAPSA and NHIMA schedules. Don't have them yet? Skip — we'll remind you before you file.</p>
                    <div class="pp-grid pp-grid-3">
                        <div class="pp-field">
                            <label for="f_tpin">ZRA TPIN</label>
                            <input class="pp-input" id="f_tpin" name="tpin" value="{{ $v('tpin') }}" inputmode="numeric" maxlength="20" placeholder="10 digits" data-len="10">
                            <div class="hint" data-len-hint></div>
                        </div>
                        <div class="pp-field">
                            <label for="f_ssn">NAPSA number</label>
                            <input class="pp-input" id="f_ssn" name="ssn" value="{{ $v('ssn') }}" placeholder="Social security no.">
                        </div>
                        <div class="pp-field">
                            <label for="f_nhima">NHIMA number</label>
                            <input class="pp-input" id="f_nhima" name="nhima_no" value="{{ $v('nhima_no') }}">
                        </div>
                    </div>
                    <div class="pp-callout green"><i class="bi bi-info-circle"></i><div>Deductions are calculated automatically from your <b>payroll rules</b> (PAYE bands, NAPSA 5% up to the ceiling, NHIMA 1%). You don't enter amounts here.</div></div>
                </div>

                {{-- 5 · CONTACT --}}
                <div class="pp-panel" data-panel="5" hidden>
                    <h2 class="pp-q">How do we reach <span data-name>them</span>?</h2>
                    <p class="pp-qsub">An email lets them use the self-service portal for payslips, leave and loans.</p>
                    <div class="pp-grid">
                        <div class="pp-field"><label for="f_ph">Mobile number</label><input type="tel" class="pp-input" id="f_ph" name="primary_phone" value="{{ $v('primary_phone') }}" placeholder="097 / 096 / 077…" data-live="primary_phone"></div>
                        <div class="pp-field"><label for="f_ph2">Other phone <small>optional</small></label><input type="tel" class="pp-input" id="f_ph2" name="secondary_phone" value="{{ $v('secondary_phone') }}"></div>
                        <div class="pp-field @error('company_email') invalid @enderror"><label for="f_em">Work email</label><input type="email" class="pp-input" id="f_em" name="company_email" value="{{ $v('company_email') }}" data-live="company_email">@error('company_email')<div class="err">{{ $message }}</div>@enderror</div>
                        <div class="pp-field @error('personal_email') invalid @enderror"><label for="f_pem">Personal email <small>optional</small></label><input type="email" class="pp-input" id="f_pem" name="personal_email" value="{{ $v('personal_email') }}" data-live="personal_email">@error('personal_email')<div class="err">{{ $message }}</div>@enderror</div>
                    </div>

                    <h3 style="font:700 .95rem var(--zp-font);margin:1.5rem 0 .8rem"><i class="bi bi-heart-pulse me-1" style="color:var(--zp-red)"></i> In case of emergency</h3>
                    <div class="pp-grid pp-grid-3">
                        <div class="pp-field"><label for="f_en">Emergency contact</label><input class="pp-input" id="f_en" name="emergency_name" value="{{ $v('emergency_name') }}"></div>
                        <div class="pp-field"><label for="f_er">Relationship</label><input class="pp-input" id="f_er" name="emergency_relationship" value="{{ $v('emergency_relationship') }}" list="ppRel"><datalist id="ppRel">@foreach(['Spouse','Parent','Sibling','Child','Relative','Friend'] as $r)<option value="{{ $r }}">@endforeach</datalist></div>
                        <div class="pp-field"><label for="f_ep">Their phone</label><input type="tel" class="pp-input" id="f_ep" name="emergency_phone" value="{{ $v('emergency_phone') }}"></div>
                        <div class="pp-field"><label for="f_kn">Next of kin <small>for benefits</small></label><input class="pp-input" id="f_kn" name="next_of_kin_name" value="{{ $v('next_of_kin_name') }}" data-live="next_of_kin_name"></div>
                        <div class="pp-field"><label for="f_kp">Next of kin phone</label><input type="tel" class="pp-input" id="f_kp" name="next_of_kin_phone" value="{{ $v('next_of_kin_phone') }}"></div>
                        <div class="pp-field"><label for="f_ka">Next of kin address</label><input class="pp-input" id="f_ka" name="next_of_kin_address" value="{{ $v('next_of_kin_address') }}"></div>
                    </div>
                    <div style="margin-top:.5rem"><button type="button" class="pp-link" id="ppSameAsEmergency"><i class="bi bi-arrow-repeat"></i> Same person as emergency contact</button></div>

                    <h3 style="font:700 .95rem var(--zp-font);margin:1.5rem 0 .8rem"><i class="bi bi-folder2-open me-1" style="color:var(--zp-copper)"></i> Documents <small class="pp-muted" style="font-weight:500">contract, NRC copy, certificates — optional</small></h3>
                    <input type="file" name="documents[]" multiple class="pp-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                    @if($isEdit && $employee->uploads)
                        <div class="pp-muted" style="margin:.7rem 0 .4rem;font-size:.8rem">Already on file — tick to remove:</div>
                        <div class="pp-pills">
                            @foreach($employee->uploads as $i => $u)
                                <label class="pp-toggle" style="font-weight:500;font-size:.82rem;background:var(--zp-mist);padding:.4rem .7rem;border-radius:10px"><input type="checkbox" name="remove_documents[]" value="{{ $i }}"> {{ $u['name'] ?? 'Document' }}</label>
                            @endforeach
                        </div>
                    @endif

                    @if(auth()->user()?->hasPermission('portal.manage'))
                        @unless($employee->exists && $employee->user)
                        <label class="pp-callout green" style="cursor:pointer;margin-top:1.3rem">
                            <input type="checkbox" name="create_portal" value="1" @checked(old('create_portal')) style="width:20px;height:20px;accent-color:var(--zp-green);margin-top:.1rem">
                            <div><b>Create their self-service portal</b><br>We'll email login details so they can see payslips and apply for leave, overtime and loans. Needs an email above.</div>
                        </label>
                        @endunless
                    @endif
                </div>

                {{-- 6 · REVIEW --}}
                <div class="pp-panel" data-panel="6" hidden>
                    <h2 class="pp-q">Looking good, <span data-name>friend</span>!</h2>
                    <p class="pp-qsub">Check everything once. Click <b>Edit</b> on any card to jump back.</p>
                    <div class="pp-review" id="ppReview"></div>
                </div>

                <div class="pp-nav">
                    <button type="button" class="pp-btn pp-btn-ghost" id="ppBack"><i class="bi bi-arrow-left"></i> Back</button>
                    <div class="pp-nav-right">
                        <button type="button" class="pp-btn pp-btn-soft" id="ppLater" @if(! $isEdit) hidden @endif>
                            <i class="bi bi-bookmark-check"></i> {{ $isEdit ? 'Save changes' : 'Save & finish later' }}
                        </button>
                        <button type="button" class="pp-btn pp-btn-primary" id="ppNext">Next <i class="bi bi-arrow-right"></i></button>
                        <button type="submit" class="pp-btn pp-btn-primary" id="ppSave" hidden><i class="bi bi-check2-circle"></i> <span>{{ $isEdit ? 'Save profile' : 'Add to the team' }}</span></button>
                    </div>
                </div>
            </div>

            {{-- LIVE SIDE --}}
            <aside class="pp-side">
                <div class="pp-idcard" aria-label="Employee card preview">
                    <div class="pp-idcard-top"><span>{{ $company->name ?? auth()->user()->company?->name ?? 'ZamPayroll' }}</span><span>Staff ID</span></div>
                    <div class="pp-idcard-body">
                        <div class="pp-idcard-photo" id="ppCardPhoto">@if($employee->photo_url)<img src="{{ $employee->photo_url }}" alt="">@else<span id="ppCardInitials">{{ $employee->initials ?: '?' }}</span>@endif</div>
                        <div style="min-width:0">
                            <div class="pp-idcard-name" id="ppCardName">{{ $employee->full_name ?: 'New colleague' }}</div>
                            <div class="pp-idcard-role" id="ppCardRole">{{ $employee->position ?: 'Job title' }}</div>
                        </div>
                    </div>
                    <div class="pp-idcard-meta">
                        <div><span>Employee no.</span><b id="ppCardEin">{{ $employee->employee_id ?: $nextNumber }}</b></div>
                        <div><span>Department</span><b id="ppCardDept">{{ $employee->department ?: '—' }}</b></div>
                        <div><span>NRC</span><b id="ppCardNrc">{{ $employee->nrc_no ?: '—' }}</b></div>
                        <div><span>Since</span><b id="ppCardSince">{{ optional($employee->contract_start)->format('M Y') ?: '—' }}</b></div>
                    </div>
                    <div class="zp-flag"><span></span><span></span><span></span><span></span></div>
                </div>

                <div class="pp-meter">
                    <div class="pp-meter-head"><span>Profile complete</span><b id="ppPct">0%</b></div>
                    <div class="pp-bar"><i id="ppBar"></i></div>
                    <ul class="pp-checks" id="ppChecks">
                        @foreach(\App\Models\Employee::PROFILE_CHECKS as $key => $group)
                            <li data-check="{{ implode(',', array_map(fn ($f) => $f === 'portal_email' ? 'company_email|personal_email' : $f, array_keys($group['fields']))) }}"><i class="bi bi-check-circle-fill"></i> {{ $group['label'] }}</li>
                        @endforeach
                    </ul>
                </div>
            </aside>
        </div>
    </form>
</div>

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/zp-people.css') }}">
@endpush

@push('scripts')
<script src="{{ asset('assets/js/zp-people.js') }}"></script>
<script>
(function () {
    const root = document.getElementById('ppJourney');
    const form = document.getElementById('ppForm');
    const isEdit = root.dataset.mode === 'edit';
    const TOTAL = 6;
    const $ = (s) => root.querySelector(s);
    const val = (n) => { const el = form.elements[n]; if (!el) return ''; if (el instanceof RadioNodeList) return el.value; return (el.value || '').trim(); };
    const esc = zpPeople.esc;
    let step = 1, reached = isEdit ? TOTAL : 1;

    const CHECKS = @json(collect(\App\Models\Employee::PROFILE_CHECKS)->map(fn ($g) => array_keys($g['fields'])));

    function show(n) {
        step = Math.max(1, Math.min(TOTAL, n));
        reached = Math.max(reached, step);
        root.querySelectorAll('[data-panel]').forEach(p => p.hidden = +p.dataset.panel !== step);
        root.querySelectorAll('.pp-step').forEach(s => {
            const k = +s.dataset.step;
            s.classList.toggle('active', k === step);
            s.classList.toggle('done', k < step || (isEdit && k !== step));
            s.disabled = k > reached;
        });
        $('#ppBack').style.visibility = step === 1 ? 'hidden' : 'visible';
        $('#ppNext').hidden = step === TOTAL;
        $('#ppSave').hidden = step !== TOTAL && !isEdit;
        if (isEdit) { $('#ppSave').hidden = false; $('#ppLater').hidden = true; }
        else $('#ppLater').hidden = step === 1 || step === TOTAL;
        if (step === TOTAL) renderReview();
        const first = root.querySelector('[data-panel="' + step + '"] input:not([type=hidden]):not([type=radio]):not([type=checkbox]):not([type=file])');
        if (first && window.innerWidth > 900) setTimeout(() => { const a = document.activeElement; if (!a || a === document.body || a.tagName === 'BUTTON') first.focus({ preventScroll: true }); }, 50);
        root.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function validStep(n) {
        const panel = root.querySelector('[data-panel="' + n + '"]');
        for (const el of panel.querySelectorAll('input[required], input[type=email]')) {
            if (el.required && !el.value.trim()) { el.closest('.pp-field')?.classList.add('invalid'); el.focus(); return false; }
            if (!el.checkValidity()) { el.closest('.pp-field')?.classList.add('invalid'); el.reportValidity(); return false; }
            el.closest('.pp-field')?.classList.remove('invalid');
        }
        return true;
    }

    $('#ppNext').addEventListener('click', () => { if (validStep(step)) show(step + 1); });
    $('#ppBack').addEventListener('click', () => show(step - 1));
    root.querySelectorAll('.pp-step').forEach(s => s.addEventListener('click', () => {
        const k = +s.dataset.step;
        if (k > step && !validStep(step)) return;
        show(k);
    }));
    $('#ppLater').addEventListener('click', () => {
        if (!validStep(1)) { show(1); validStep(1); return; }
        $('#ppIntent').value = 'later';
        form.requestSubmit ? form.requestSubmit() : form.submit();
    });
    form.addEventListener('submit', (e) => {
        if (!validStep(1)) { e.preventDefault(); e.stopPropagation(); show(1); validStep(1); return; }
        $('#ppSave').disabled = true;
        $('#ppSave span').textContent = 'Saving…';
    });
    form.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.tagName === 'INPUT' && e.target.type !== 'submit') {
            e.preventDefault();
            if (step < TOTAL) $('#ppNext').click(); else form.requestSubmit();
        }
    });

    /* ----- live card + completeness ----- */
    function initials() { return ((val('first_name')[0] || '') + (val('last_name')[0] || '')).toUpperCase() || '?'; }
    function update() {
        const name = [val('first_name'), val('middle_name'), val('last_name')].filter(Boolean).join(' ');
        $('#ppCardName').textContent = name || 'New colleague';
        $('#ppCardRole').textContent = val('position') || 'Job title';
        $('#ppCardDept').textContent = val('department') || '—';
        $('#ppCardNrc').textContent = val('nrc_no') || '—';
        $('#ppCardEin').textContent = val('employee_id') || form.elements['employee_id'].placeholder || '—';
        const st = val('contract_start');
        $('#ppCardSince').textContent = st ? new Date(st + 'T00:00').toLocaleDateString('en-GB', { month: 'short', year: 'numeric' }) : '—';
        const ini = document.getElementById('ppCardInitials'); if (ini) ini.textContent = initials();
        root.querySelectorAll('[data-name]').forEach(s => s.textContent = val('first_name') || s.dataset.fallback || (s.dataset.fallback = s.textContent));

        let done = 0, total = 0;
        root.querySelectorAll('#ppChecks li').forEach(li => {
            const fields = li.dataset.check.split(',');
            let ok = 0;
            fields.forEach(f => { total++; if (f.split('|').some(x => val(x) && !(x === 'net_salary' && parseFloat(val(x)) <= 0))) { ok++; done++; } });
            li.classList.toggle('ok', ok === fields.length);
        });
        const pct = Math.round(done / total * 100);
        $('#ppPct').textContent = pct + '%';
        $('#ppBar').style.width = pct + '%';

        const dob = val('date_of_birth');
        if (dob) { const a = Math.floor((Date.now() - new Date(dob)) / 31557600000); $('#ppAge').textContent = a > 0 ? a + ' years old' : ''; } else $('#ppAge').textContent = '';
        const net = val('net_salary');
        $('#ppNetWords').textContent = net ? zpPeople.money(net) + ' a month · ' + zpPeople.money(parseFloat(net.replace(/[^\d.]/g, '')) * 12).replace('K ', 'K ') + ' a year' : '';
        const nat = val('nationality').toLowerCase();
        document.getElementById('ppPassportField').style.display = (!nat || nat === 'zambian') && !val('passport_number') ? 'none' : '';
        const nature = val('employment_nature');
        root.querySelectorAll('[data-show-for]').forEach(el => el.style.display = !nature || el.dataset.showFor.split(',').includes(nature) || el.querySelector('input').value ? '' : 'none');
        const tp = form.elements['tpin'], hint = tp.parentElement.querySelector('[data-len-hint]');
        hint.textContent = tp.value && tp.value.replace(/\D/g, '').length !== 10 ? 'TPINs are usually 10 digits (' + tp.value.replace(/\D/g, '').length + ' so far)' : '';
        if (step === 1) $('#ppLater').hidden = true;
        if (!isEdit && step > 1 && step < TOTAL) $('#ppLater').hidden = !(val('first_name') && val('last_name'));
    }
    form.addEventListener('input', update);
    form.addEventListener('change', update);

    // photo preview
    document.getElementById('ppPhoto').addEventListener('change', (e) => {
        const f = e.target.files[0]; if (!f) return;
        const url = URL.createObjectURL(f);
        document.getElementById('ppPhotoRing').innerHTML = '<img src="' + url + '" alt="">';
        document.getElementById('ppCardPhoto').innerHTML = '<img src="' + url + '" alt="">';
    });

    // bank → sort code
    form.elements['bank_name'].addEventListener('change', (e) => {
        const o = [...document.querySelectorAll('#ppBanks option')].find(o => o.value.toLowerCase() === e.target.value.trim().toLowerCase());
        if (o && o.dataset.sort && !form.elements['bank_sort_code'].value) form.elements['bank_sort_code'].value = o.dataset.sort;
    });

    // +months helpers
    root.addEventListener('click', (e) => {
        const b = e.target.closest('[data-plus-months]'); if (!b) return;
        const start = val('contract_start') ? new Date(val('contract_start') + 'T00:00') : new Date();
        start.setMonth(start.getMonth() + +b.dataset.plusMonths);
        const target = document.getElementById(b.dataset.target || 'f_prob');
        target.value = start.toISOString().slice(0, 10); update();
    });
    document.getElementById('ppSameAsEmergency').addEventListener('click', () => {
        form.elements['next_of_kin_name'].value = val('emergency_name');
        form.elements['next_of_kin_phone'].value = val('emergency_phone'); update();
    });

    /* ----- review ----- */
    function renderReview() {
        const label = (n) => (form.querySelector('[name="' + n + '"]')?.closest('.pp-field')?.querySelector('label')?.childNodes[0]?.textContent || n).trim();
        const blocks = [
            [1, 'Who', ['first_name', 'middle_name', 'last_name', 'gender', 'date_of_birth', 'nrc_no', 'nationality']],
            [2, 'Job', ['position', 'department', 'branch', 'supervisor', 'employment_nature', 'contract_start', 'employee_id']],
            [3, 'Pay & bank', ['net_salary', 'leave_days_entitled', 'bank_name', 'bank_account_no']],
            [4, 'Statutory', ['tpin', 'ssn', 'nhima_no']],
            [5, 'Contact', ['primary_phone', 'company_email', 'personal_email', 'emergency_name', 'next_of_kin_name']],
        ];
        const nice = { gender: 'Gender', employment_nature: 'Employment type', employee_id: 'Employee no.' };
        document.getElementById('ppReview').innerHTML = blocks.map(([n, title, fields]) => {
            const rows = fields.map(f => {
                let v = val(f);
                if (f === 'net_salary' && v) v = zpPeople.money(v);
                if (f === 'employee_id' && !v) v = form.elements['employee_id'].placeholder + ' (auto)';
                if (f === 'employment_nature' && v) v = form.querySelector('[name=employment_nature]:checked + span')?.textContent || v;
                return '<dt>' + esc(nice[f] || label(f)) + '</dt><dd class="' + (v ? '' : 'empty') + '">' + (v ? esc(v) : 'later') + '</dd>';
            }).join('');
            return '<div class="pp-review-block"><h4>' + title + ' <button type="button" class="pp-link" data-jump="' + n + '">Edit</button></h4><dl>' + rows + '</dl></div>';
        }).join('');
    }
    root.addEventListener('click', (e) => { const j = e.target.closest('[data-jump]'); if (j) show(+j.dataset.jump); });

    // open on the step with a server-side error, if any
    const bad = root.querySelector('.pp-field.invalid');
    const start = bad ? +bad.closest('[data-panel]').dataset.panel : +root.dataset.start;
    if (bad) reached = TOTAL;
    update();
    show(start || 1);
})();
</script>
@endpush
