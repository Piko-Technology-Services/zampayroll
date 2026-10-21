{{-- Trial / payment countdown shown across the HR area. --}}
@php
    $bu = auth()->user();
    $bs = null;
    if ($bu && $bu->company_id && ! $bu->isEmployee()) {
        try {
            $subsSvc = app(\App\Services\Billing\Subscriptions::class);
            $bc = $subsSvc->company((int) $bu->company_id);
            $bs = $bc ? $subsSvc->state($bc) : null;
        } catch (\Throwable $e) {
            report($e);
        }
    }
    $showBanner = $bs && ($bs['status'] === 'trialing' || in_array($bs['tone'], ['warn', 'bad'], true)) && ! request()->routeIs('billing.*');
    $canBill = $bu?->hasPermission('billing.manage');
@endphp
@if($showBanner)
<div class="zp-subbar {{ $bs['tone'] }}" role="status">
    <i class="bi {{ $bs['limited'] ? 'bi-lock' : ($bs['status'] === 'trialing' ? 'bi-gift' : 'bi-hourglass-split') }}"></i>
    <div class="txt">
        <b>{{ $bs['limited'] ? ($bs['headline'] . ' — read-only for now') : $bs['headline'] }}</b>
        @if($bs['countdown_to'] && ! $bs['limited'])
            <span><span class="zp-countdown" data-to="{{ $bs['countdown_to']->toIso8601String() }}" data-short="1"></span> {{ $bs['countdown_label'] }}</span>
        @elseif($bs['limited'])
            <span>Your data is safe. {{ $canBill ? 'Pay to unlock everything.' : 'Ask your administrator to renew.' }}</span>
        @endif
    </div>
    @if($canBill)
        <a href="{{ route('billing.index') }}" class="go">{{ $bs['status'] === 'trialing' && ! $bs['limited'] ? 'Choose a plan' : 'Pay now' }} <i class="bi bi-arrow-right"></i></a>
    @endif
</div>

@endif
@once
<style>
.zp-subbar{display:flex;align-items:center;gap:.8rem;padding:.7rem 1rem;border-radius:14px;margin-bottom:1rem;font-size:.88rem;background:#E8F3EC;color:#0A3B22;border:1px solid #CFE6D7}
.zp-subbar>i{font-size:1.15rem}
.zp-subbar .txt{flex:1;display:flex;flex-wrap:wrap;gap:.2rem .7rem;align-items:baseline}
.zp-subbar .txt span{color:inherit;opacity:.85}
.zp-subbar .go{font-weight:700;color:#00742D;text-decoration:none;white-space:nowrap}
.zp-subbar.trial{background:linear-gradient(90deg,#E8F3EC,#FBF0E5)}
.zp-subbar.warn{background:#FBF0E5;color:#6d4013;border-color:#f1d6b9}.zp-subbar.warn .go{color:#9a5a17}
.zp-subbar.bad{background:#FBEAEA;color:#7c1d1b;border-color:#f3c2c1}.zp-subbar.bad .go{color:#C42926}
.zp-countdown{font-weight:700;font-variant-numeric:tabular-nums}
</style>
<script>
(function(){function tick(){document.querySelectorAll('.zp-countdown').forEach(function(el){var ms=new Date(el.dataset.to)-new Date();if(ms<0)ms=0;var d=Math.floor(ms/864e5),h=Math.floor(ms%864e5/36e5),m=Math.floor(ms%36e5/6e4),s=Math.floor(ms%6e4/1e3);
if(el.dataset.short){el.textContent=d>0?d+' day'+(d===1?'':'s')+' '+h+'h':h+'h '+m+'m';}else{['d','h','m','s'].forEach(function(k,i){var n=el.querySelector('[data-u="'+k+'"]');if(n)n.textContent=String([d,h,m,s][i]).padStart(2,'0');});}});}
tick();setInterval(tick,1000);})();
</script>
@endonce
