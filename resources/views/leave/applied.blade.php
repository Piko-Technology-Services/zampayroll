<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Application Submitted</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap');
:root {
  --paper: #ffffff;
  --ink: #00742E;
  --ink-soft: #58687D;
  --ink-faint: #93A0B2;
  --brass: #00742E;
  --brass-soft: #EAF7ED;
  --line: #D9E9DE;
  --accent-orange: #E97B00;
  --accent-red: #D50115;
}
* { box-sizing: border-box; }
body {
  margin: 0;
  font-family: 'IBM Plex Sans', -apple-system, sans-serif;
  background: var(--paper);
  color: var(--ink);
  padding: 3rem 1rem;
}
.wrap { max-width: 440px; margin: 0 auto; text-align: center; }
.check {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: var(--brass-soft);
  color: var(--ink);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1rem;
  font-size: 1.6rem;
  font-weight: 700;
}
h1 {
  font-family: 'IBM Plex Sans', -apple-system, sans-serif;
  font-size: 1.3rem;
  margin: 0 0 .5rem;
  color: var(--ink);
}
p {
  font-size: .88rem;
  color: var(--ink-soft);
  line-height: 1.6;
}
.card {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 8px;
  padding: 1.4rem;
  margin-top: 1.5rem;
  text-align: left;
  font-size: .84rem;
  box-shadow: 0 1px 0 rgba(0, 116, 46, 0.03);
}
.row {
  display: flex;
  justify-content: space-between;
  padding: .4rem 0;
  border-bottom: 1px solid var(--line);
  gap: 1rem;
}
.row:last-child { border-bottom: none; }
.row span:first-child { color: var(--ink-faint); flex-shrink: 0; }
.row span:last-child {
  font-weight: 600;
  font-family: 'IBM Plex Sans', -apple-system, sans-serif;
  text-align: right;
  color: var(--ink);
}
</style>
</head>
<body>
<div class="wrap">
    <div class="check">✓</div>
    <h1>Application Submitted</h1>
    <p>Your leave request has been sent to HR for review. You'll receive an email once a decision has been made.</p>

    <div class="card">
        <div class="row"><span>Leave Type</span><span>{{ \App\Models\LeaveRequest::TYPES[$leaveRequest->leave_type] ?? $leaveRequest->leave_type }}</span></div>
        <div class="row"><span>Dates</span><span>{{ $leaveRequest->start_date->format('d M Y') }} → {{ $leaveRequest->end_date->format('d M Y') }}</span></div>
        @if($leaveRequest->return_date)
        <div class="row"><span>Return Date</span><span>{{ $leaveRequest->return_date->format('d M Y') }}</span></div>
        @endif
        <div class="row"><span>Days Requested</span><span>{{ $leaveRequest->days }}</span></div>
        @if(!empty($leaveRequest->documents))
        <div class="row"><span>Documents</span><span>{{ count($leaveRequest->documents) }} attached</span></div>
        @endif
        <div class="row"><span>Status</span><span>Pending</span></div>
    </div>
</div>
</body>
</html>
