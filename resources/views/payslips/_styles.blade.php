{{-- Payslip styles. DomPDF-safe (tables, no flex/grid), greyscale. --}}
<style>
    .ps { position: relative; font-family: 'DejaVu Sans', 'Helvetica Neue', Arial, sans-serif; color: #1c1c1c; font-size: 10.5px; line-height: 1.4; background: #fff; overflow: hidden; }
    .ps table { width: 100%; border-collapse: collapse; }
    .ps .mono { font-family: 'DejaVu Sans Mono', 'Courier New', monospace; }
    .ps-wm { position: absolute; top: 330px; left: -40px; right: -40px; text-align: center; font-family: 'DejaVu Serif', Georgia, serif; font-size: 96px; font-weight: bold; letter-spacing: 14px; color: #000; opacity: .035; transform: rotate(-28deg); z-index: 0; }
    .ps > * { position: relative; z-index: 1; }
    .ps .ps-wm { position: absolute; z-index: 0; }
    .ps-strip td { background: #161616; color: #f2f2f2; font-size: 8.5px; letter-spacing: 2.6px; font-weight: bold; padding: 6px 10px; }
    .ps-strip td.r { text-align: right; }
    .ps-head { margin-top: 14px; }
    .ps-head td { vertical-align: middle; }
    .ps-head td.logo { width: 70px; padding-right: 12px; }
    .ps-head td.logo img { max-width: 64px; max-height: 64px; }
    .ps-head .co-name { font-family: 'DejaVu Serif', Georgia, serif; font-size: 17px; font-weight: bold; color: #111; }
    .ps-head .co-meta { color: #666; font-size: 9px; margin-top: 2px; }
    .ps-head td.title { text-align: right; width: 260px; }
    .ps-head .t { font-family: 'DejaVu Serif', Georgia, serif; font-size: 26px; letter-spacing: 5px; text-transform: uppercase; color: #111; line-height: 1; }
    .ps-head .ref { color: #555; font-size: 9px; margin-top: 4px; letter-spacing: .5px; }
    .ps-who { margin-top: 14px; border: 1px solid #1c1c1c; }
    .ps-who td { padding: 10px 14px; vertical-align: middle; }
    .ps-who td.bar { width: 6px; padding: 0; background: #1c1c1c; }
    .ps-who .lbl { font-size: 7.5px; text-transform: uppercase; letter-spacing: 2px; color: #666; font-weight: bold; }
    .ps-who .name { font-family: 'DejaVu Serif', Georgia, serif; font-size: 18px; font-weight: bold; margin-top: 2px; color: #111; }
    .ps-who .sub { color: #444; font-size: 9.5px; margin-top: 2px; }
    .ps-who td.net { text-align: right; width: 200px; background: #f1f1f1; border-left: 1px solid #1c1c1c; }
    .ps-who .amt { font-size: 21px; font-weight: bold; color: #111; margin-top: 2px; letter-spacing: .3px; }
    .ps-facts { margin-top: 10px; }
    .ps-facts td { width: 25%; padding: 6px 8px 6px 0; border-bottom: 1px solid #ddd; font-size: 10px; color: #222; vertical-align: top; }
    .ps-facts td span { display: block; font-size: 7.5px; text-transform: uppercase; letter-spacing: 1.4px; color: #777; font-weight: bold; margin-bottom: 1px; }
    .ps-lines { margin-top: 14px; }
    .ps-lines th { background: #e4e4e4; color: #222; font-size: 8px; text-transform: uppercase; letter-spacing: 1.6px; font-weight: bold; padding: 6px 8px; text-align: left; border-top: 1px solid #1c1c1c; border-bottom: 1px solid #1c1c1c; }
    .ps-lines th.a, .ps-lines td.a { text-align: right; width: 96px; white-space: nowrap; }
    .ps-lines th.gap, .ps-lines td.gap { width: 14px; background: #fff; border: 0; }
    .ps-lines td { padding: 5px 8px; border-bottom: 1px dotted #c8c8c8; font-size: 10px; height: 13px; }
    .ps-lines td.a { font-family: 'DejaVu Sans Mono', 'Courier New', monospace; }
    .ps-lines .code { display: inline-block; width: 30px; color: #888; font-family: 'DejaVu Sans Mono', 'Courier New', monospace; font-size: 8.5px; }
    .ps-lines tfoot td { font-weight: bold; border-top: 1.5px solid #1c1c1c; border-bottom: 1.5px solid #1c1c1c; padding: 6px 8px; }
    .ps-lines tfoot td.gap { border: 0; }
    .ps-sum { margin-top: 14px; }
    .ps-sum td { width: 33.33%; padding: 9px 12px; border: 1px solid #bbb; font-size: 13px; font-weight: bold; font-family: 'DejaVu Sans Mono', 'Courier New', monospace; }
    .ps-sum td span { display: block; font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 7.5px; text-transform: uppercase; letter-spacing: 1.6px; color: #666; margin-bottom: 2px; }
    .ps-sum td.net { background: #161616; color: #fff; border-color: #161616; font-size: 16px; }
    .ps-sum td.net span { color: #bbb; }
    .ps-words { text-align: right; font-style: italic; color: #555; font-size: 9px; margin-top: 4px; }
    .ps-ytd { margin-top: 14px; }
    .ps-ytd th { text-align: left; font-size: 7.5px; text-transform: uppercase; letter-spacing: 1.6px; color: #555; padding: 0 0 4px; border-bottom: 1px solid #1c1c1c; }
    .ps-ytd td { width: 20%; padding: 6px 8px 6px 0; font-family: 'DejaVu Sans Mono', 'Courier New', monospace; font-size: 10px; }
    .ps-ytd td span { display: block; font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 7.5px; text-transform: uppercase; letter-spacing: 1.3px; color: #777; }
    .ps-note { margin-top: 8px; font-size: 8.5px; color: #666; }
    .ps-foot { margin-top: 18px; padding-top: 10px; border-top: 1px dashed #999; color: #555; font-size: 8.5px; }
    .ps-foot p { margin: 0 0 3px; }
    .ps-foot .small { font-size: 7.5px; color: #888; }
    .ps-foot .seal { float: right; border: 1.5px solid #555; color: #555; padding: 3px 8px; font-size: 8px; letter-spacing: 2.5px; font-weight: bold; margin-left: 12px; }
</style>
