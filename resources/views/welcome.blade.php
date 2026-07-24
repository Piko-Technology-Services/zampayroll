<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ZamPayroll — AI-Powered HR & Payroll, Made Simple</title>
<meta name="description" content="ZamPayroll is a NAPSA & ZRA recommended, AI-powered HR and Payroll system for Zambia. Our new system is currently under development — get notified at launch.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700;9..144,900&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
  body{
    margin:0;background:var(--cream);color:var(--ink);
    font-family:'Inter',sans-serif;font-weight:400;
    -webkit-font-smoothing:antialiased;
  }
  h1,h2,h3,.disp{font-family:'Fraunces',serif}
  .container{width:100%;max-width:1140px;margin:0 auto;padding:0 26px}
  a{color:inherit}
  img{max-width:100%;display:block}

  .eyebrow{
    display:inline-flex;align-items:center;gap:10px;
    font-family:'Fraunces',serif;font-weight:600;font-size:.78rem;
    letter-spacing:.16em;text-transform:uppercase;color:var(--green-dk);
  }
  .eyebrow .ln{width:26px;height:1px;background:var(--green-dk);display:inline-block}

  /* ---------- NAV ---------- */
  #nbar{
    position:sticky;top:0;z-index:60;
    background:rgba(250,247,240,.88);backdrop-filter:blur(10px);
    border-bottom:1px solid var(--line);
  }
  .nbar-inner{display:flex;align-items:center;justify-content:space-between;padding:14px 0}
  .brand{display:flex;align-items:center;gap:10px;text-decoration:none}
  .brand img{width:38px;height:38px;border-radius:9px}
  .brand-word{font-family:'Fraunces',serif;font-weight:700;font-size:1.18rem;letter-spacing:-.01em}
  .brand-word .g{color:var(--green)}
  .brand-word .k{color:var(--black)}
  .nlinks{display:flex;align-items:center;gap:30px}
  .nlinks a{font-size:.86rem;font-weight:500;color:var(--ink2);text-decoration:none}
  .nlinks a:hover{color:var(--ink)}
  .btn{
    display:inline-flex;align-items:center;justify-content:center;gap:8px;
    font-weight:600;font-size:.86rem;border-radius:9px;text-decoration:none;
    border:1px solid transparent;cursor:pointer;transition:transform .15s, filter .15s, background .15s;
  }
  .btn-primary{background:var(--green);color:#fff;padding:11px 20px}
  .btn-primary:hover{background:var(--green-dk);transform:translateY(-1px)}
  .btn-line{background:transparent;color:var(--ink);border-color:var(--line);padding:10px 19px}
  .btn-line:hover{border-color:var(--ink3);transform:translateY(-1px)}

  /* ---------- HERO ---------- */
  #hero{padding:76px 0 60px;position:relative;overflow:hidden}
  .hero-grid{display:grid;grid-template-columns:1.05fr .95fr;gap:56px;align-items:center}
  .badges{display:flex;gap:10px;flex-wrap:wrap;margin:20px 0 26px}
  .badge{
    display:flex;align-items:center;gap:7px;font-size:.74rem;font-weight:700;
    padding:7px 13px;border-radius:100px;border:1px solid var(--line);background:var(--paper);color:var(--ink2);
    letter-spacing:.02em;
  }
  .badge i{color:var(--green)}
  .h1{
    font-size:clamp(2.1rem,4.4vw,3.4rem);line-height:1.06;font-weight:700;letter-spacing:-.015em;
    margin:0 0 20px;color:var(--ink);
  }
  .h1 em{font-style:normal;color:var(--green)}
  .hero-sub{font-size:1.03rem;line-height:1.7;color:var(--ink2);max-width:490px;margin-bottom:30px}
  .signup{
    display:flex;gap:10px;max-width:440px;margin-bottom:14px;flex-wrap:wrap;
  }
  .signup input{
    flex:1;min-width:200px;padding:13px 16px;border-radius:9px;border:1px solid var(--line);
    background:var(--paper);font-family:'Inter',sans-serif;font-size:.9rem;color:var(--ink);
  }
  .signup input:focus{outline:none;border-color:var(--green)}
  .fine{font-size:.76rem;color:var(--ink3)}

  /* payslip stub visual */
  .stub-wrap{position:relative}
  .stub{
    background:var(--paper);border:1px solid var(--line);border-radius:18px;
    padding:26px 26px 22px;box-shadow:0 30px 60px -30px rgba(20,22,26,.25);
    position:relative;
  }
  .stub-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;padding-bottom:16px;border-bottom:1px dashed var(--line)}
  .stub-top img{width:30px;height:30px;border-radius:7px}
  .stub-id{font-size:.7rem;color:var(--ink3);text-align:right;line-height:1.5}
  .stub-name{font-family:'Fraunces',serif;font-weight:700;font-size:1.05rem}
  .stub-role{font-size:.76rem;color:var(--ink3)}
  .stub-row{display:flex;justify-content:space-between;font-size:.84rem;padding:7px 0;color:var(--ink2)}
  .stub-row.neg{color:var(--red)}
  .stub-row b{color:var(--ink);font-weight:600}
  .stub-div{height:1px;background:var(--line);margin:10px 0}
  .stub-net{display:flex;justify-content:space-between;align-items:baseline;margin-top:4px}
  .stub-net .lbl{font-size:.78rem;color:var(--ink3);text-transform:uppercase;letter-spacing:.08em;font-weight:600}
  .stub-net .amt{font-family:'Fraunces',serif;font-weight:700;font-size:1.5rem;color:var(--green-dk)}
  .stub-bars{display:flex;align-items:flex-end;gap:6px;height:38px;margin-top:16px}
  .stub-bars i{flex:1;border-radius:3px 3px 0 0}
  .ai-chip{
    position:absolute;bottom:-16px;right:22px;background:var(--black);color:#fff;
    padding:9px 15px;border-radius:100px;font-size:.72rem;font-weight:600;
    display:flex;align-items:center;gap:7px;box-shadow:0 12px 24px -10px rgba(0,0,0,.4);
  }
  .ai-chip i{color:var(--orange)}

  /* ---------- PROGRESS / DEV STATUS ---------- */
  #progress{padding:64px 0;border-top:1px solid var(--line)}
  .prog-head{display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:34px}
  .prog-pct{font-family:'Fraunces',serif;font-weight:700;font-size:2.4rem;color:var(--green-dk)}
  .track{height:9px;border-radius:100px;background:var(--line);overflow:hidden;margin-bottom:36px}
  .fill{height:100%;border-radius:100px;background:linear-gradient(90deg,var(--green),var(--green-dk));width:58%}
  .modgrid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
  .modcard{background:var(--paper);border:1px solid var(--line);border-radius:14px;padding:18px}
  .modcard .mtop{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
  .modcard .mname{font-weight:600;font-size:.92rem}
  .mstat{font-size:.68rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;padding:4px 9px;border-radius:100px}
  .mstat.done{background:var(--green-lt);color:var(--green-dk)}
  .mstat.wip{background:#FDF1E3;color:var(--orange)}
  .mdesc{font-size:.78rem;color:var(--ink3);line-height:1.5}

  /* ---------- FEATURES ---------- */
  #features{padding:70px 0;border-top:1px solid var(--line)}
  .sec-head{max-width:600px;margin-bottom:42px}
  .sec-title{font-size:clamp(1.6rem,3vw,2.2rem);font-weight:700;letter-spacing:-.01em;margin:14px 0 12px}
  .sec-sub{color:var(--ink2);line-height:1.7;font-size:.98rem}
  .fgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
  .fcard{background:var(--paper);border:1px solid var(--line);border-radius:16px;padding:26px}
  .fcard .fic{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;margin-bottom:16px;font-size:1.05rem;color:#fff}
  .fcard .ft{font-weight:700;font-size:1rem;margin-bottom:7px}
  .fcard .fd{font-size:.85rem;color:var(--ink3);line-height:1.6}
  .ic-green{background:var(--green)}
  .ic-black{background:var(--black)}
  .ic-red{background:var(--red)}
  .ic-orange{background:var(--orange)}

  .steps-strip{
    display:flex;align-items:center;gap:0;margin-top:26px;background:var(--paper);
    border:1px solid var(--line);border-radius:16px;padding:22px 26px;overflow-x:auto;
  }
  .step{display:flex;align-items:center;gap:12px;white-space:nowrap}
  .step-n{width:30px;height:30px;border-radius:50%;background:var(--green-lt);color:var(--green-dk);font-weight:700;font-size:.82rem;display:flex;align-items:center;justify-content:center;flex-shrink:0}
  .step-t{font-weight:600;font-size:.88rem}
  .step-arrow{color:var(--ink3);margin:0 20px;font-size:.9rem}

  /* ---------- CALCULATORS ---------- */
  #calcs{padding:70px 0;border-top:1px solid var(--line)}
  .cgrid3{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
  .ccard{
    border-radius:16px;padding:26px;color:#fff;position:relative;overflow:hidden;min-height:170px;
    display:flex;flex-direction:column;justify-content:space-between;
  }
  .ccard .ci{font-size:1.3rem;margin-bottom:26px;opacity:.9}
  .ccard .ct{font-family:'Fraunces',serif;font-weight:700;font-size:1.1rem;margin-bottom:6px}
  .ccard .cd{font-size:.8rem;opacity:.85;line-height:1.5}
  .ccard.c1{background:linear-gradient(150deg,var(--green),var(--green-dk))}
  .ccard.c2{background:linear-gradient(150deg,#2b2d31,var(--black))}
  .ccard.c3{background:linear-gradient(150deg,var(--orange),#B7621F)}
  .soon-tag{position:absolute;top:18px;right:18px;font-size:.65rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;background:rgba(255,255,255,.18);padding:5px 10px;border-radius:100px}

  /* ---------- INDUSTRIES ---------- */
  #industries{padding:70px 0;border-top:1px solid var(--line)}
  .pillgrid{display:flex;flex-wrap:wrap;gap:10px;margin-top:30px}
  .pill{
    font-size:.83rem;font-weight:500;padding:10px 16px;border-radius:100px;
    background:var(--paper);border:1px solid var(--line);color:var(--ink2);
  }

  /* ---------- RESELLER ---------- */
  #reseller{padding:70px 0;border-top:1px solid var(--line)}
  .rwrap{
    background:var(--black);color:#fff;border-radius:22px;padding:52px 46px;
    display:grid;grid-template-columns:1.2fr .8fr;gap:40px;align-items:center;position:relative;overflow:hidden;
  }
  .rwrap:before{content:'';position:absolute;width:360px;height:360px;background:var(--green);opacity:.25;filter:blur(90px);top:-140px;right:-100px;border-radius:50%}
  .rwrap .eyebrow{color:var(--orange)}
  .rwrap .eyebrow .ln{background:var(--orange)}
  .rwrap h2{color:#fff;font-size:clamp(1.5rem,2.8vw,2rem);margin:14px 0 14px}
  .rwrap p{color:#C7C9CE;font-size:.94rem;line-height:1.7;margin-bottom:0}
  .rlist{list-style:none;padding:0;margin:22px 0 0;display:flex;flex-direction:column;gap:10px}
  .rlist li{display:flex;align-items:center;gap:10px;font-size:.86rem;color:#E4E5E8}
  .rlist i{color:var(--green);font-size:.8rem}
  .rcard{background:#1B1D21;border:1px solid #2A2C31;border-radius:16px;padding:26px;position:relative;z-index:1}
  .rcard .rt{font-family:'Fraunces',serif;font-weight:700;font-size:1.15rem;margin-bottom:8px}
  .rcard .rd{font-size:.82rem;color:#9A9CA3;line-height:1.6;margin-bottom:20px}

  /* ---------- CONTACT / FOOTER ---------- */
  #contact{padding:70px 0 30px;border-top:1px solid var(--line);text-align:center}
  .cwrap{max-width:560px;margin:0 auto}
  .citems{display:flex;justify-content:center;gap:14px;flex-wrap:wrap;margin-top:28px}
  .citem{display:flex;align-items:center;gap:9px;padding:11px 17px;border-radius:11px;border:1px solid var(--line);background:var(--paper);font-size:.84rem;color:var(--ink2);text-decoration:none}
  .citem i{color:var(--green)}
  .citem:hover{border-color:var(--ink3)}

  footer{padding:26px 0;border-top:1px solid var(--line);margin-top:44px}
  .foot-inner{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px}
  .foot-txt{font-size:.78rem;color:var(--ink3)}
  .soc{display:flex;gap:9px}
  .soc a{width:32px;height:32px;border-radius:9px;border:1px solid var(--line);display:flex;align-items:center;justify-content:center;text-decoration:none;color:var(--ink2);font-size:.8rem}
  .soc a:hover{border-color:var(--ink3);color:var(--ink)}

  @media (max-width:920px){
    .hero-grid{grid-template-columns:1fr}
    .modgrid{grid-template-columns:repeat(2,1fr)}
    .fgrid{grid-template-columns:repeat(2,1fr)}
    .cgrid3{grid-template-columns:1fr}
    .rwrap{grid-template-columns:1fr}
    .nlinks{display:none}
  }
  @media (max-width:560px){
    .modgrid{grid-template-columns:1fr}
    .fgrid{grid-template-columns:1fr}
    .signup{flex-direction:column}
    .rwrap{padding:34px 24px}
    #hero{padding:50px 0 40px}
    .step-arrow{margin:0 10px}
  }
</style>
</head>
<body>

<nav id="nbar">
  <div class="container nbar-inner">
    <a href="#" class="brand">
      <img src="data:image/png;base64,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" alt="ZamPayroll logo">
      <span class="brand-word"><span class="g">zam</span><span class="k">payroll</span></span>
    </a>
    <div class="nlinks">
      <a href="#progress">Progress</a>
      <a href="#features">Features</a>
      <a href="#calcs">Calculators</a>
      <a href="#reseller">Resellers</a>
      <a href="#contact">Contact</a>
    </div>
    <a href="#contact" class="btn btn-primary">Get Notified</a>
  </div>
</nav>

<section id="hero">
  <div class="container hero-grid">
    <div>
      <span class="eyebrow"><span class="ln"></span>System under development</span>
      <h1 class="h1">AI-powered HR &amp; Payroll,<br><em>made simple.</em></h1>
      <div class="badges">
        <span class="badge"><i class="fa-solid fa-circle-check"></i> NAPSA Recommended Payroll</span>
        <span class="badge"><i class="fa-solid fa-circle-check"></i> ZRA Recommended Payroll</span>
      </div>
      <p class="hero-sub">ZamPayroll runs your payroll from generate to finalise in a few clicks, explains to every employee exactly why they were taxed what they were taxed, and catches errors before they reach a payslip. We're putting the finishing touches on it right now.</p>
      <form class="signup" onsubmit="return false;">
        <input type="email" placeholder="you@company.com" aria-label="Email address" required>
        <button class="btn btn-primary" type="submit">Notify Me <i class="fa-solid fa-arrow-right fa-sm"></i></button>
      </form>
      <p class="fine">No spam — just one email when we go live.</p>
    </div>
    <div class="stub-wrap">
      <div class="stub">
        <div class="stub-top">
          <img src="data:image/png;base64,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" alt="">
          <div class="stub-id">Payslip &middot; June 2026<br>EMP-0142</div>
        </div>
        <div class="stub-name">Chanda Mwansa</div>
        <div class="stub-role">Site Accountant &middot; Ndola Branch</div>
        <div class="stub-div"></div>
        <div class="stub-row"><span>Gross Pay</span><b>K 18,500.00</b></div>
        <div class="stub-row neg"><span>PAYE (Tax)</span><span>&minus; K 3,120.40</span></div>
        <div class="stub-row neg"><span>NAPSA</span><span>&minus; K 925.00</span></div>
        <div class="stub-row neg"><span>NHIMA</span><span>&minus; K 185.00</span></div>
        <div class="stub-div"></div>
        <div class="stub-net">
          <span class="lbl">Net Pay</span>
          <span class="amt">K 14,269.60</span>
        </div>
        <div class="stub-bars">
          <i style="height:55%;background:var(--red)"></i>
          <i style="height:80%;background:var(--black)"></i>
          <i style="height:100%;background:var(--orange)"></i>
          <i style="height:70%;background:var(--green)"></i>
        </div>
      </div>
      <div class="ai-chip"><i class="fa-solid fa-wand-magic-sparkles"></i> AI: "Here's why you paid more tax this month"</div>
    </div>
  </div>
</section>

<section id="progress">
  <div class="container">
    <div class="prog-head">
      <div>
        <span class="eyebrow"><span class="ln"></span>Build progress</span>
        <h2 class="sec-title" style="margin-top:10px">What we're building this week</h2>
      </div>
      <div class="prog-pct">58%</div>
    </div>
    <div class="track"><div class="fill"></div></div>
    <div class="modgrid">
      <div class="modcard">
        <div class="mtop"><span class="mname">Employee</span><span class="mstat done">Done</span></div>
        <div class="mdesc">Consolidated profiles, contracts, documents &amp; statutory info.</div>
      </div>
      <div class="modcard">
        <div class="mtop"><span class="mname">Leave</span><span class="mstat wip">In progress</span></div>
        <div class="mdesc">Applications, approvals chain and payroll integration.</div>
      </div>
      <div class="modcard">
        <div class="mtop"><span class="mname">Payroll</span><span class="mstat wip">In progress</span></div>
        <div class="mdesc">Generate, adjust, finalise &mdash; with bulk adjustments &amp; reports.</div>
      </div>
      <div class="modcard">
        <div class="mtop"><span class="mname">Overtime</span><span class="mstat wip">In progress</span></div>
        <div class="mdesc">Overtime calculator, applications &amp; bulk imports.</div>
      </div>
    </div>
  </div>
</section>

<section id="features">
  <div class="container">
    <div class="sec-head">
      <span class="eyebrow"><span class="ln"></span>Why ZamPayroll</span>
      <h2 class="sec-title">Built to remove the guesswork from payroll</h2>
      <p class="sec-sub">From the first draft run to the finalised payslip, every step is designed to be fast, explainable and error-free.</p>
    </div>
    <div class="fgrid">
      <div class="fcard">
        <div class="fic ic-green"><i class="fa-solid fa-flask"></i></div>
        <div class="ft">Simulation Mode</div>
        <div class="fd">Run a "what-if" payroll before you commit &mdash; test raises, bonuses or new hires with zero risk.</div>
      </div>
      <div class="fcard">
        <div class="fic ic-black"><i class="fa-solid fa-magnifying-glass-chart"></i></div>
        <div class="ft">Anomaly Detection</div>
        <div class="fd">Automatically flags unusual pay changes before a payslip ever reaches an employee.</div>
      </div>
      <div class="fcard">
        <div class="fic ic-orange"><i class="fa-solid fa-robot"></i></div>
        <div class="ft">AI Payroll Assistant</div>
        <div class="fd">Explains to any employee, in plain language, exactly why they were taxed what they were taxed.</div>
      </div>
      <div class="fcard">
        <div class="fic ic-red"><i class="fa-solid fa-calculator"></i></div>
        <div class="ft">Inbuilt Calculator</div>
        <div class="fd">Salary, overtime and gratuity calculations built right into the workflow &mdash; no spreadsheets needed.</div>
      </div>
      <div class="fcard">
        <div class="fic ic-green"><i class="fa-solid fa-file-csv"></i></div>
        <div class="ft">One-Time Payroll</div>
        <div class="fd">Download CSVs, output payslips, and export directly to ZRA, NAPSA and NHIMA in a click.</div>
      </div>
      <div class="fcard">
        <div class="fic ic-black"><i class="fa-solid fa-gauge-high"></i></div>
        <div class="ft">One Time Dashboard</div>
        <div class="fd">View, adjust and finalise a one-off pay run without touching your main payroll cycle.</div>
      </div>
    </div>
    <div class="steps-strip">
      <div class="step"><span class="step-n">1</span><span class="step-t">Generate</span></div>
      <span class="step-arrow"><i class="fa-solid fa-arrow-right"></i></span>
      <div class="step"><span class="step-n">2</span><span class="step-t">Adjust</span></div>
      <span class="step-arrow"><i class="fa-solid fa-arrow-right"></i></span>
      <div class="step"><span class="step-n">3</span><span class="step-t">Finalise</span></div>
    </div>
  </div>
</section>

<section id="calcs">
  <div class="container">
    <div class="sec-head">
      <span class="eyebrow"><span class="ln"></span>Free tools</span>
      <h2 class="sec-title">Quick calculators, launching with the platform</h2>
    </div>
    <div class="cgrid3">
      <div class="ccard c1">
        <span class="soon-tag">Coming Soon</span>
        <div class="ci"><i class="fa-solid fa-sack-dollar"></i></div>
        <div>
          <div class="ct">Salary Calculator</div>
          <div class="cd">Instant gross-to-net breakdown with PAYE, NAPSA &amp; NHIMA.</div>
        </div>
      </div>
      <div class="ccard c2">
        <span class="soon-tag">Coming Soon</span>
        <div class="ci"><i class="fa-solid fa-business-time"></i></div>
        <div>
          <div class="ct">Overtime Calculator</div>
          <div class="cd">Work out overtime pay in line with Zambian labour law.</div>
        </div>
      </div>
      <div class="ccard c3">
        <span class="soon-tag">Coming Soon</span>
        <div class="ci"><i class="fa-solid fa-handshake"></i></div>
        <div>
          <div class="ct">Gratuity Calculator</div>
          <div class="cd">Calculate end-of-contract gratuity in seconds.</div>
        </div>
      </div>
    </div>
  </div>
</section>

<section id="industries">
  <div class="container">
    <div class="sec-head">
      <span class="eyebrow"><span class="ln"></span>Who it's for</span>
      <h2 class="sec-title">Built for every kind of employer</h2>
      <p class="sec-sub">From single-site clinics to multi-branch contractors, ZamPayroll adapts to how your organisation actually runs payroll.</p>
    </div>
    <div class="pillgrid">
      <span class="pill">HR Consultancy Firms</span>
      <span class="pill">HR Practitioners</span>
      <span class="pill">Law Firms</span>
      <span class="pill">Accounting Firms</span>
      <span class="pill">Private Schools</span>
      <span class="pill">Mining Contractors</span>
      <span class="pill">Logistics Companies</span>
      <span class="pill">Chinese Construction Companies</span>
      <span class="pill">Private Universities</span>
      <span class="pill">Private Clinics &amp; Hospitals</span>
      <span class="pill">Drugstores &amp; Pharmaceutical Facilities</span>
      <span class="pill">Convenience Stores</span>
      <span class="pill">Cleaning Companies</span>
    </div>
  </div>
</section>

<section id="reseller">
  <div class="container">
    <div class="rwrap">
      <div>
        <span class="eyebrow"><span class="ln"></span>Partner with us</span>
        <h2>Become a ZamPayroll reseller</h2>
        <p>Advertise the software, onboard your clients, and train them to use it &mdash; we handle the platform, you build the relationship.</p>
        <ul class="rlist">
          <li><i class="fa-solid fa-check"></i> Advertise ZamPayroll to your network</li>
          <li><i class="fa-solid fa-check"></i> Onboard and train new clients</li>
          <li><i class="fa-solid fa-check"></i> Earn on every client you bring on</li>
        </ul>
      </div>
      <div class="rcard">
        <div class="rt">Reseller signup</div>
        <div class="rd">Leave your details and we'll reach out as soon as the reseller programme opens.</div>
        <a href="#contact" class="btn btn-primary" style="width:100%">Sign Up as a Reseller</a>
      </div>
    </div>
  </div>
</section>

<section id="contact">
  <div class="container cwrap">
    <span class="eyebrow"><span class="ln"></span>Get in touch</span>
    <h2 class="sec-title">We'd love to hear from you</h2>
    <p class="sec-sub" style="margin:0 auto">Questions, early access requests or reseller enquiries &mdash; reach out any time.</p>
    <div class="citems">
      <a href="mailto:hello@zampayroll.com" class="citem"><i class="fa-solid fa-envelope"></i> hello@zampayroll.com</a>
      <span class="citem"><i class="fa-solid fa-location-dot"></i> Lusaka, Zambia</span>
      <span class="citem"><i class="fa-regular fa-clock"></i> Mon &ndash; Fri, 08:00 &ndash; 17:00</span>
    </div>
  </div>
</section>

<footer>
  <div class="container foot-inner">
    <span class="foot-txt">&copy; <span id="yr"></span> ZamPayroll. Payroll made simple.</span>
    <div class="soc">
      <a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
      <a href="#" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
      <a href="#" aria-label="X"><i class="fa-brands fa-x-twitter"></i></a>
      <a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
    </div>
  </div>
</footer>

<script>document.getElementById('yr').textContent = new Date().getFullYear();</script>
</body>
</html>