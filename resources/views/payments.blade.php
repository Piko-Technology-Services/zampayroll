<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

```
<!-- Primary SEO -->
<title>Make a Payment | ZamPayroll</title>

<meta
    name="description"
    content="Make a payment to ZamPayroll using Mobile Money and securely submit your proof of payment for verification."
>

<meta name="author" content="ZamPayroll">
<meta name="application-name" content="ZamPayroll">

<!-- Payment pages generally do not need to appear in search results -->
<meta
    name="robots"
    content="noindex, follow"
>

<meta name="referrer" content="strict-origin-when-cross-origin">

<!-- Canonical -->
<link
    rel="canonical"
    href="https://zampayroll.com/payments"
>

<!-- Language / Region -->
<meta http-equiv="content-language" content="en-ZM">

<!-- Browser / Theme -->
<meta name="theme-color" content="#0B3D2E">

<!-- ========================================================= -->
<!-- Open Graph / Facebook / LinkedIn                          -->
<!-- ========================================================= -->

<meta property="og:type" content="website">
<meta property="og:site_name" content="ZamPayroll">
<meta property="og:locale" content="en_ZM">

<meta
    property="og:title"
    content="Make a Payment | ZamPayroll"
>

<meta
    property="og:description"
    content="Make a payment to ZamPayroll using Mobile Money and securely submit your proof of payment for verification."
>

<meta
    property="og:url"
    content="https://zampayroll.com/payments"
>

<meta
    property="og:image"
    content="https://misc.zampayroll.com/images/zampayroll-og-image.png"
>

<meta
    property="og:image:secure_url"
    content="https://misc.zampayroll.com/images/zampayroll-og-image.png"
>

<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">

<meta
    property="og:image:alt"
    content="ZamPayroll — Payroll and HR Software for Zambian Businesses"
>

<!-- ========================================================= -->
<!-- Twitter / X                                                -->
<!-- ========================================================= -->

<meta name="twitter:card" content="summary_large_image">

<meta
    name="twitter:title"
    content="Make a Payment | ZamPayroll"
>

<meta
    name="twitter:description"
    content="Make a payment to ZamPayroll using Mobile Money and securely submit your proof of payment for verification."
>

<meta
    name="twitter:image"
    content="https://misc.zampayroll.com/images/zampayroll-og-image.png"
>

<meta
    name="twitter:image:alt"
    content="ZamPayroll — Payroll and HR Software for Zambian Businesses"
>

<!-- ========================================================= -->
<!-- Mobile / App Appearance                                    -->
<!-- ========================================================= -->

<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="ZamPayroll">

<!-- Icons -->
<link rel="icon" type="image/png" href="/favicon.png">
<link rel="apple-touch-icon" href="/favicon.png">

<!-- ========================================================= -->
<!-- Fonts                                                       -->
<!-- ========================================================= -->

<link rel="preconnect" href="https://fonts.googleapis.com">

<link
    href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700;9..144,900&family=Inter:wght@400;500;600;700;800&display=swap"
    rel="stylesheet"
>

<!-- Font Awesome -->
<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
>
```

</head>
<style>
  :root{
    --cream:#FAF7F0;
    --paper:#FFFFFF;
    --ink:#14161A;
    --ink2:#4B4F58;
    --ink3:#8A8D96;
    --line:#E7E2D6;
    --green:#2F7D40;
    --green-dk:#1F5A2C;
    --green-lt:#EAF3EA;
    --red:#C42926;
    --black:#0E1011;
    --orange:#DB7F2F;
  }
  *{box-sizing:border-box}
  html{scroll-behavior:smooth}
  body{margin:0;background:var(--cream);color:var(--ink);font-family:'Inter',sans-serif;font-weight:400;-webkit-font-smoothing:antialiased;}
  h1,h2,h3,.disp{font-family:'Fraunces',serif}
  .container{width:100%;max-width:900px;margin:0 auto;padding:0 26px}
  a{color:inherit}
  img{max-width:100%;display:block}

  .eyebrow{display:inline-flex;align-items:center;gap:10px;font-family:'Fraunces',serif;font-weight:600;font-size:.78rem;letter-spacing:.16em;text-transform:uppercase;color:var(--green-dk);}
  .eyebrow .ln{width:26px;height:1px;background:var(--green-dk);display:inline-block}

  #nbar{position:sticky;top:0;z-index:60;background:rgba(255, 255, 255, 0.88);backdrop-filter:blur(10px);border-bottom:1px solid var(--line);}
  .nbar-inner{display:flex;align-items:center;justify-content:space-between;padding:14px 0;max-width:1140px;margin:0 auto;padding-left:26px;padding-right:26px;}
  .brand{display:flex;align-items:center;gap:10px;text-decoration:none}
  .brand-mark{width: 100%;height:38px;border-radius:9px;background:var(--black);color:#fff;display:flex;align-items:center;justify-content:center;font-family:'Fraunces',serif;font-weight:700}
  .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;font-weight:600;font-size:.86rem;border-radius:9px;text-decoration:none;border:1px solid transparent;cursor:pointer;transition:transform .15s, filter .15s, background .15s;}
  .btn-primary{background:var(--green);color:#fff;padding:11px 20px}
  .btn-primary:hover{background:var(--green-dk);transform:translateY(-1px)}
  .btn-line{background:transparent;color:var(--ink);border-color:var(--line);padding:10px 19px}

  #pay-hero{padding:56px 0 20px;text-align:center}
  .sec-title{font-size:clamp(1.6rem,3vw,2.2rem);font-weight:700;letter-spacing:-.01em;margin:14px 0 12px}
  .sec-sub{color:var(--ink2);line-height:1.7;font-size:.98rem;max-width:560px;margin:0 auto}

  /* ---------- METHOD TABS ---------- */
  #methods{padding:30px 0 70px}
  .tabbar{display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin-bottom:30px}
  .tab{
    display:flex;align-items:center;gap:8px;padding:11px 18px;border-radius:100px;
    border:1px solid var(--line);background:var(--paper);font-size:.85rem;font-weight:600;color:var(--ink2);
    cursor:pointer;user-select:none;transition:border-color .15s, color .15s, background .15s;
  }
  .tab i{font-size:.95rem}
  .tab.active{background:var(--black);border-color:var(--black);color:#fff}
  .tab.disabled{opacity:.55;cursor:not-allowed}
  .tab .soon{font-size:.63rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;background:var(--green-lt);color:var(--green-dk);padding:2px 7px;border-radius:100px;margin-left:2px}
  .tab.active .soon{background:rgba(255,255,255,.18);color:#fff}

  .panel{display:none}
  .panel.active{display:block}

  .soon-card{background:var(--paper);border:1px solid var(--line);border-radius:18px;padding:44px 32px;text-align:center}
  .soon-card i{font-size:1.6rem;color:var(--ink3);margin-bottom:14px}
  .soon-card h3{margin:0 0 8px;font-size:1.1rem}
  .soon-card p{margin:0;font-size:.88rem;color:var(--ink3);max-width:380px;margin:0 auto}

  /* ---------- MOBILE MONEY ---------- */
  .mm-grid{display:grid;grid-template-columns:.9fr 1.1fr;gap:32px;align-items:flex-start}
  .mm-card{
    background:linear-gradient(150deg,#FFCB05,#F2A900);border-radius:18px;padding:30px;color:var(--black);
    position:relative;overflow:hidden;
  }
  .mm-card .mm-brand{display:flex;align-items:center;gap:10px;margin-bottom:26px}
  .mm-card .mm-brand .badge-mtn{
    background:var(--black);color:#FFCB05;font-weight:800;font-size:.78rem;letter-spacing:.02em;
    padding:6px 12px;border-radius:8px;
  }
  .mm-label{font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;opacity:.7;margin-bottom:4px}
  .mm-number{font-family:'Fraunces',serif;font-weight:700;font-size:1.9rem;letter-spacing:.02em;margin-bottom:22px}
  .mm-name{font-size:1.02rem;font-weight:700;margin-bottom:2px}
  .mm-name-lbl{font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;opacity:.7}
  .mm-steps{list-style:none;padding:0;margin:26px 0 0;display:flex;flex-direction:column;gap:10px}
  .mm-steps li{display:flex;gap:10px;font-size:.85rem;line-height:1.5}
  .mm-steps .n{width:20px;height:20px;border-radius:50%;background:var(--black);color:#FFCB05;font-size:.72rem;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px}

  .pay-card{background:var(--paper);border:1px solid var(--line);border-radius:18px;padding:32px}
  .pay-card h3{margin:0 0 6px;font-size:1.2rem}
  .pay-card .sub{font-size:.85rem;color:var(--ink3);margin-bottom:22px}
  .field{margin-bottom:16px}
  .field label{display:block;font-size:.8rem;font-weight:600;color:var(--ink2);margin-bottom:6px}
  .field input,.field select,.field textarea{
    width:100%;padding:12px 14px;border-radius:9px;border:1px solid var(--line);
    background:var(--cream);font-family:'Inter',sans-serif;font-size:.9rem;color:var(--ink);
  }
  .field input:focus,.field select:focus,.field textarea:focus{outline:none;border-color:var(--green)}
  .field textarea{resize:vertical;min-height:80px}
  .field-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  .field-err{font-size:.76rem;color:var(--red);margin-top:6px}
  .field-hint{font-size:.76rem;color:var(--ink3);margin-top:6px}
  .hp-field{position:absolute;left:-9999px;top:-9999px;opacity:0}

  .file-drop{
    border:1.5px dashed var(--line);border-radius:12px;padding:22px 16px;text-align:center;
    background:var(--cream);cursor:pointer;transition:border-color .15s;
  }
  .file-drop:hover, .file-drop.dragover{border-color:var(--green)}
  .file-drop i{font-size:1.2rem;color:var(--ink3);margin-bottom:8px}
  .file-drop .fd-txt{font-size:.85rem;color:var(--ink2);font-weight:600}
  .file-drop .fd-sub{font-size:.75rem;color:var(--ink3);margin-top:3px}
  .file-drop input[type="file"]{display:none}
  .file-name{font-size:.8rem;color:var(--green-dk);font-weight:600;margin-top:10px;display:none}

  .check-row{display:flex;align-items:flex-start;gap:10px;margin-bottom:18px}
  .check-row input[type="checkbox"]{width:18px;height:18px;margin-top:2px;accent-color:var(--green);flex-shrink:0}
  .check-row label{font-size:.85rem;color:var(--ink2);line-height:1.5}

  .alert{display:flex;align-items:flex-start;gap:10px;border-radius:12px;padding:14px 16px;font-size:.86rem;line-height:1.5;margin-bottom:20px;}
  .alert-success{background:var(--green-lt);color:var(--green-dk);border:1px solid #CFE6CF}
  .alert-error{background:#FBEAEA;color:var(--red);border:1px solid #F2CFCE}
  .alert i{margin-top:2px}

  .pay-submit{position:relative}
  .pay-submit .spinner{display:none;width:16px;height:16px;border-radius:50%;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;animation:spin .7s linear infinite;}
  .pay-submit.is-loading{opacity:.85;cursor:not-allowed;pointer-events:none}
  .pay-submit.is-loading .spinner{display:inline-block}
  @keyframes spin{to{transform:rotate(360deg)}}

  footer{padding:30px 0;border-top:1px solid var(--line);margin-top:20px;text-align:center}
  .foot-txt{font-size:.78rem;color:var(--ink3)}

  @media (max-width:760px){
    .mm-grid{grid-template-columns:1fr}
    .field-row{grid-template-columns:1fr}
  }
</style>
</head>
<body>

<nav id="nbar">
  <div class="nbar-inner">
    <a href="{{ route('home') }}" class="brand">
      <img src="https://misc.zampayroll.com/logo-word.png" alt="Zampayroll" class="brand-mark" />
    </a>
    <a href="{{ route('home') }}" class="btn btn-line"><i class="fa-solid fa-arrow-left fa-sm"></i> Back to site</a>
  </div>
</nav>

<section id="pay-hero">
  <div class="container">
    <span class="eyebrow"><span class="ln"></span>Payments</span>
    <h1 class="sec-title">Make a payment</h1>
    <p class="sec-sub">Choose how you'd like to pay. Mobile Money is available right now — the other methods are on their way.</p>
  </div>
</section>

<section id="methods">
  <div class="container">

    @if (session('payment_success'))
      <div class="alert alert-success">
        <i class="fa-solid fa-circle-check"></i>
        <span>Thanks &mdash; we've received your proof of payment. Check your email for confirmation; our team will verify it and follow up shortly.</span>
      </div>
    @endif

    @if ($errors->any())
      <div class="alert alert-error">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span>Please check the highlighted fields below and try again.</span>
      </div>
    @endif

    <div class="tabbar" role="tablist">
      <div class="tab active" data-panel="panel-mobile-money" role="tab"><i class="fa-solid fa-mobile-screen-button"></i> Mobile Money</div>
      <div class="tab disabled" data-panel="panel-visa" role="tab"><i class="fa-brands fa-cc-visa"></i> VISA <span class="soon">Soon</span></div>
      <div class="tab disabled" data-panel="panel-mastercard" role="tab"><i class="fa-brands fa-cc-mastercard"></i> Mastercard <span class="soon">Soon</span></div>
      <div class="tab disabled" data-panel="panel-paypal" role="tab"><i class="fa-brands fa-cc-paypal"></i> PayPal <span class="soon">Soon</span></div>
      <div class="tab disabled" data-panel="panel-bank" role="tab"><i class="fa-solid fa-building-columns"></i> Bank Transfer <span class="soon">Soon</span></div>
    </div>

    {{-- ============ MOBILE MONEY (LIVE) ============ --}}
    <div class="panel active" id="panel-mobile-money">
      <div class="mm-grid">
        <div>
          <div class="mm-card">
            <div class="mm-brand">
              <span class="badge-mtn">MTN MoMo</span>
            </div>
            <div class="mm-label">Send payment to</div>
            <div class="mm-number">+260 776 136 965</div>
            <div class="mm-name-lbl">Account name</div>
            <div class="mm-name">Margie Chilikima</div>

            <ul class="mm-steps">
              <li><span class="n">1</span> Open your MTN Mobile Money menu and send the exact amount to the number above.</li>
              <li><span class="n">2</span> Save or screenshot the confirmation message you receive from MTN.</li>
              <li><span class="n">3</span> Fill in the form and attach that confirmation as proof of payment.</li>
              <li><span class="n">4</span> We'll verify it and email you once it's confirmed.</li>
            </ul>
          </div>
        </div>

        <div class="pay-card">
          <h3>Confirm your payment</h3>
          <p class="sub">Fields marked with * are required.</p>

          <form action="{{ route('payments.store') }}" method="POST" enctype="multipart/form-data" novalidate id="paymentForm">
            @csrf

            {{-- Honeypot field — hidden from real users, bots tend to fill it in --}}
            <div class="hp-field" aria-hidden="true">
              <label for="website">Leave this field empty</label>
              <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="field">
              <label for="company_name">Company name *</label>
              <input type="text" id="company_name" name="company_name" value="{{ old('company_name') }}" placeholder="Your company" required>
              @error('company_name') <div class="field-err">{{ $message }}</div> @enderror
            </div>

            <div class="field-row">
              <div class="field">
                <label for="contact_email">Contact email *</label>
                <input type="email" id="contact_email" name="contact_email" value="{{ old('contact_email') }}" placeholder="you@company.com" required>
                @error('contact_email') <div class="field-err">{{ $message }}</div> @enderror
              </div>
              <div class="field">
                <label for="contact_phone">Phone payment was sent from *</label>
                <input type="tel" id="contact_phone" name="contact_phone" value="{{ old('contact_phone') }}" placeholder="+260 9XX XXX XXX" required>
                @error('contact_phone') <div class="field-err">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="field-row">
              <div class="field">
                <label for="service">Service paid for *</label>
                <select id="service" name="service" required>
                  <option value="" {{ old('service') === null ? 'selected' : '' }}>Select&hellip;</option>
                  @foreach ($services as $option)
                    <option value="{{ $option }}" {{ old('service') === $option ? 'selected' : '' }}>{{ $option }}</option>
                  @endforeach
                </select>
                @error('service') <div class="field-err">{{ $message }}</div> @enderror
              </div>
              <div class="field">
                <label for="amount">Amount paid (ZMW)</label>
                <input type="number" step="0.01" min="0" id="amount" name="amount" value="{{ old('amount') }}" placeholder="e.g. 7500.00">
                @error('amount') <div class="field-err">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="field">
              <label for="proof_of_payment_input">Proof of payment *</label>
              <label class="file-drop" id="fileDrop" for="proof_of_payment_input">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                <div class="fd-txt">Click to upload or drag a file here</div>
                <div class="fd-sub">JPG, PNG or PDF &middot; up to 5MB</div>
                <input type="file" id="proof_of_payment_input" name="proof_of_payment" accept=".jpg,.jpeg,.png,.pdf" required>
              </label>
              <div class="file-name" id="fileName"></div>
              @error('proof_of_payment') <div class="field-err">{{ $message }}</div> @enderror
            </div>

            <div class="field">
              <label for="comment">Comment (optional)</label>
              <textarea id="comment" name="comment" placeholder="Anything else we should know about this payment?">{{ old('comment') }}</textarea>
              @error('comment') <div class="field-err">{{ $message }}</div> @enderror
            </div>

            <div class="check-row">
              <input type="checkbox" id="confirmed_sent" name="confirmed_sent" value="1" {{ old('confirmed_sent') ? 'checked' : '' }} required>
              <label for="confirmed_sent">I confirm I have sent this payment to the MTN Mobile Money number shown above. *</label>
            </div>
            @error('confirmed_sent') <div class="field-err" style="margin-top:-10px;margin-bottom:16px;">{{ $message }}</div> @enderror

            <button type="submit" id="paySubmitBtn" class="btn btn-primary pay-submit" style="width:100%">
              <span class="spinner"></span>
              <span class="btn-label">Submit payment confirmation <i class="fa-solid fa-arrow-right fa-sm"></i></span>
            </button>
          </form>
        </div>
      </div>
    </div>

    {{-- ============ COMING SOON PANELS ============ --}}
    <div class="panel" id="panel-visa">
      <div class="soon-card">
        <i class="fa-brands fa-cc-visa"></i>
        <h3>VISA — coming soon</h3>
        <p>Card payments are on the way. In the meantime, pay via Mobile Money or reach out to us directly.</p>
      </div>
    </div>
    <div class="panel" id="panel-mastercard">
      <div class="soon-card">
        <i class="fa-brands fa-cc-mastercard"></i>
        <h3>Mastercard — coming soon</h3>
        <p>Card payments are on the way. In the meantime, pay via Mobile Money or reach out to us directly.</p>
      </div>
    </div>
    <div class="panel" id="panel-paypal">
      <div class="soon-card">
        <i class="fa-brands fa-cc-paypal"></i>
        <h3>PayPal — coming soon</h3>
        <p>PayPal is on the way for international clients. In the meantime, pay via Mobile Money or reach out to us directly.</p>
      </div>
    </div>
    <div class="panel" id="panel-bank">
      <div class="soon-card">
        <i class="fa-solid fa-building-columns"></i>
        <h3>Bank Transfer — coming soon</h3>
        <p>Bank transfer details are on the way. In the meantime, pay via Mobile Money or reach out to us directly.</p>
      </div>
    </div>

  </div>
</section>

<footer>
  <span class="foot-txt">&copy; {{ date('Y') }} ZamPayroll. Payroll made simple.</span>
</footer>

<script>
  (function () {
    var tabs = document.querySelectorAll('.tab');
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        if (tab.classList.contains('disabled')) return;
        tabs.forEach(function (t) { t.classList.remove('active'); });
        document.querySelectorAll('.panel').forEach(function (p) { p.classList.remove('active'); });
        tab.classList.add('active');
        document.getElementById(tab.dataset.panel).classList.add('active');
      });
    });

    var fileInput = document.getElementById('proof_of_payment_input');
    var fileDrop = document.getElementById('fileDrop');
    var fileName = document.getElementById('fileName');
    if (fileInput && fileDrop && fileName) {
      fileInput.addEventListener('change', function () {
        if (fileInput.files && fileInput.files[0]) {
          fileName.textContent = 'Selected: ' + fileInput.files[0].name;
          fileName.style.display = 'block';
        }
      });
      ['dragover', 'dragenter'].forEach(function (evt) {
        fileDrop.addEventListener(evt, function (e) { e.preventDefault(); fileDrop.classList.add('dragover'); });
      });
      ['dragleave', 'drop'].forEach(function (evt) {
        fileDrop.addEventListener(evt, function (e) { e.preventDefault(); fileDrop.classList.remove('dragover'); });
      });
      fileDrop.addEventListener('drop', function (e) {
        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
          fileInput.files = e.dataTransfer.files;
          fileName.textContent = 'Selected: ' + e.dataTransfer.files[0].name;
          fileName.style.display = 'block';
        }
      });
    }

    var form = document.getElementById('paymentForm');
    var btn = document.getElementById('paySubmitBtn');
    if (form && btn) {
      form.addEventListener('submit', function (e) {
        if (!form.checkValidity()) return;
        if (btn.classList.contains('is-loading')) { e.preventDefault(); return; }
        btn.classList.add('is-loading');
        btn.querySelector('.btn-label').textContent = 'Submitting…';
      });
    }
  })();
</script>

</body>
</html>
