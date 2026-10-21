<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Payment {{ $status === 'paid' ? 'received' : 'status' }} · ZamPayroll</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;font-family:Inter,Segoe UI,Arial,sans-serif;background:#F3F6F2;color:#15201A;text-align:center}
.c{background:#fff;border-radius:22px;padding:2rem 1.6rem;max-width:360px;box-shadow:0 24px 60px -32px rgba(10,59,34,.45)}
.i{width:72px;height:72px;border-radius:50%;margin:0 auto 1rem;display:grid;place-items:center;font-size:2rem;color:#fff;background:{{ $status === 'paid' ? '#00742D' : ($status === 'pending' ? '#C8742B' : '#C42926') }}}
a{display:inline-block;margin-top:1rem;background:#00742D;color:#fff;padding:.7rem 1.2rem;border-radius:12px;text-decoration:none;font-weight:700}</style></head>
<body><div class="c">
    <div class="i">{{ $status === 'paid' ? '✓' : ($status === 'pending' ? '…' : '!') }}</div>
    <h2 style="margin:0 0 .4rem">{{ $status === 'paid' ? 'Payment received' : ($status === 'pending' ? 'Payment processing' : 'Payment not completed') }}</h2>
    <p style="color:#6b776f">{{ $status === 'paid' ? 'Thank you! You can close this window.' : ($status === 'pending' ? 'We\'ll update your account as soon as DPO confirms.' : 'No money was taken. You can try again from the billing page.') }}</p>
    <a href="{{ route('billing.index') }}" id="back">Back to ZamPayroll</a>
</div>
<script>
try { if (window.opener && !window.opener.closed) { window.opener.postMessage('zp-dpo-done', '*'); document.getElementById('back').addEventListener('click', function (e) { e.preventDefault(); window.close(); }); @if($status === 'paid') setTimeout(function(){ window.close(); }, 2500); @endif } } catch (e) {}
</script>
</body></html>
