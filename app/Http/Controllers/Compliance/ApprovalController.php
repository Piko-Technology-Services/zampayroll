<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Compliance\ApprovalRequest;
use App\Models\Compliance\CompanyPolicy;
use App\Models\Compliance\EmploymentContract;
use App\Services\Compliance\Approvals;
use App\Services\Compliance\Contracts;
use App\Services\Compliance\DocumentPdf;
use App\Services\Compliance\Policies;
use App\Services\Compliance\SignatureImage;
use App\Support\Tenancy;
use Illuminate\Http\Request;

/** Documents waiting for the signed-in person, and their decisions. */
class ApprovalController extends Controller
{
    public const COMPANY_CONSENT = 'I am authorised to sign this contract on behalf of the company. I have reviewed it and I sign it electronically.';

    public function index(Request $request)
    {
        $mine = Approvals::pendingQueryFor($request->user())->latest('id')->get();
        $done = ApprovalRequest::where('acted_by', $request->user()->id)->latest('acted_at')->limit(25)->get();

        return view('compliance.approvals.index', ['mine' => $mine, 'done' => $done]);
    }

    private function subject(ApprovalRequest $approval)
    {
        $s = $approval->subject();
        abort_unless($s, 404);

        return $s;
    }

    /** Only the assigned approver, their delegate, or someone who already acted may open the step. */
    private function authorizeView(Request $request, ApprovalRequest $approval): void
    {
        $u = $request->user();
        abort_unless($approval->isAssignedTo($u) || $approval->isDelegate($u) || (int) $approval->acted_by === (int) $u->id
            || $u->hasPermission($approval->approvable_type === 'contract' ? 'compliance.contracts' : 'compliance.policies'), 403);
    }

    public function show(Request $request, ApprovalRequest $approval)
    {
        $this->authorizeView($request, $approval);
        $subject = $this->subject($approval);
        $body = $subject instanceof EmploymentContract ? Contracts::renderedBody($subject, false, $approval->version) : Policies::renderedBody($subject, $approval->version);
        $trail = ApprovalRequest::where('approvable_type', $approval->approvable_type)->where('approvable_id', $approval->approvable_id)
            ->where('round', $approval->round)->orderBy('position')->get();

        return view('compliance.approvals.show', [
            'approval' => $approval, 'subject' => $subject, 'body' => $body, 'trail' => $trail,
            'canAct' => $approval->canBeActedOnBy($request->user()) && ! ($subject instanceof EmploymentContract && (int) $request->user()->employee_id === (int) $subject->employee_id),
            'consent' => self::COMPANY_CONSENT,
        ]);
    }

    public function document(Request $request, ApprovalRequest $approval)
    {
        $this->authorizeView($request, $approval);
        $subject = $this->subject($approval);
        $isContract = $subject instanceof EmploymentContract;
        $body = $isContract ? Contracts::renderedBody($subject, false, $approval->version) : Policies::renderedBody($subject, $approval->version);
        $pdf = DocumentPdf::render($body, $subject->letterhead, Tenancy::company(), ['title' => $subject->title, 'watermark' => 'REVIEW COPY',
            'footer_note' => $subject->reference . ' · v' . $approval->version]);

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="' . $subject->reference . '-review.pdf"']);
    }

    public function decide(Request $request, ApprovalRequest $approval)
    {
        $d = $request->validate([
            'decision' => 'required|in:approve,reject,changes',
            'comment' => 'nullable|string|max:2000',
            'signature_method' => 'nullable|in:draw,upload',
            'signature_data' => 'nullable|string|max:2000000',
            'signature_file' => 'nullable|file|max:5120|mimes:png,jpg,jpeg',
            'signer_title' => 'nullable|string|max:160',
        ]);
        $subject = $this->subject($approval);

        if ($subject instanceof EmploymentContract) {
            $signature = null;
            if ($d['decision'] === 'approve' && $approval->action === 'sign') {
                $request->validate(['consent' => 'accepted'], ['consent.accepted' => 'Confirm that you are authorised to sign for the company.']);
                $png = ($d['signature_method'] ?? 'draw') === 'upload' && $request->file('signature_file')
                    ? SignatureImage::fromUpload($request->file('signature_file'))
                    : SignatureImage::fromDataUrl((string) ($d['signature_data'] ?? ''));
                $signature = ['png' => $png, 'method' => ($d['signature_method'] ?? 'draw'), 'title' => $d['signer_title'] ?? null, 'consent' => self::COMPANY_CONSENT];
            }
            $outcome = Contracts::decide($subject, $approval, $request->user(), $d['decision'], $d['comment'] ?? null, $signature);
            $msg = ['complete' => 'Signed. The contract is now fully executed and has been sent to the employee.', 'next' => 'Done. It has moved to the next step.',
                'returned' => 'Sent back to HR with your comment.'][$outcome];

            return redirect()->route('compliance.approvals.index')->with('success', $msg);
        }

        abort_unless($subject instanceof CompanyPolicy, 404);
        $outcome = Policies::decide($subject, $approval, $request->user(), $d['decision'], $d['comment'] ?? null);
        $msg = ['complete' => 'Approved. The policy is now active and employees have been asked to acknowledge it.', 'next' => 'Approved. It has moved to the next step.',
            'returned' => 'Sent back with your comment.'][$outcome];

        return redirect()->route('compliance.approvals.index')->with('success', $msg);
    }
}
