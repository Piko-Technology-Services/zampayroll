{{-- 5-stage progress for a pay run. Expects $stage (1–5). --}}
@php
    $labels = [
        1 => ['Calculate', 'Everyone worked out', 'bi-calculator'],
        2 => ['Check', 'Highlights reviewed', 'bi-search'],
        3 => ['Approve', 'Approval chain', 'bi-people'],
        4 => ['Locked', 'No more changes', 'bi-lock'],
        5 => ['Paid', 'Money sent', 'bi-check2-all'],
    ];
@endphp
<ol class="pr-track" aria-label="Payroll progress">
    @foreach($labels as $n => [$t, $s, $ic])
        @php $state = ($stage === 5 || $n < $stage) ? 'done' : ($n === $stage ? 'now' : ''); @endphp
        <li class="{{ $state }}" @if($state === 'now') aria-current="step" @endif>
            <span class="dot"><i class="bi {{ $state === 'done' ? 'bi-check-lg' : $ic }}"></i></span>
            {{ $t }}<small>{{ $s }}</small>
        </li>
    @endforeach
</ol>
