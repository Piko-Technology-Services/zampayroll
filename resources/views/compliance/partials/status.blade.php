@php
    $map = [
        'draft' => 'dark', 'sent' => 'blue', 'declined' => 'red', 'in_approval' => 'amber', 'changes' => 'amber', 'rejected' => 'red',
        'executed' => 'green', 'superseded' => '', 'cancelled' => '', 'active' => 'green', 'archived' => '',
        'pending' => 'amber', 'waiting' => '', 'approved' => 'green',
    ];
@endphp
<span class="cc-pill {{ $map[$status] ?? '' }}">{{ $label }}</span>
