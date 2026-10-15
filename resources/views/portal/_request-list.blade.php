{{-- Shared history list. Expects $items (collection of rows: title, detail, status, at, cancelUrl, note) --}}
@if($items->isEmpty())
    <div class="pt-empty"><i class="bi bi-inbox"></i>{{ $empty }}</div>
@else
    <ul class="pt-list">
        @foreach($items as $item)
            <li style="align-items:flex-start">
                <span class="ic"><i class="bi {{ $icon }}"></i></span>
                <span class="tx">
                    <b>{{ $item['title'] }}</b>
                    <span>{{ $item['detail'] }}</span>
                    @if(!empty($item['note']))
                        <div class="small mt-1" style="color:var(--zp-ink-2)"><i class="bi bi-chat-left-text me-1"></i>HR: {{ $item['note'] }}</div>
                    @endif
                </span>
                <span class="d-flex flex-column align-items-end gap-1">
                    <span class="pt-status {{ $item['status'] }}">{{ $item['status'] }}</span>
                    @if($item['status'] === 'pending' && !empty($item['cancelUrl']))
                        <form method="POST" action="{{ $item['cancelUrl'] }}" onsubmit="return confirm('Cancel this request?')">
                            @csrf @method('DELETE')
                            <button class="zp-btn-link small" style="border:0;background:none;font-size:.78rem;color:var(--zp-red)">Cancel</button>
                        </form>
                    @endif
                </span>
            </li>
        @endforeach
    </ul>
@endif
