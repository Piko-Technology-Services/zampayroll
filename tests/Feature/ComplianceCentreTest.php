<?php

namespace Tests\Feature;

use App\Mail\ComplianceMail;
use App\Models\AuditLog;
use App\Models\Compliance\ApprovalRequest;
use App\Models\Compliance\ApprovalWorkflow;
use App\Models\Compliance\CompanyDocument;
use App\Models\Compliance\CompanyPolicy;
use App\Models\Compliance\ContractSignature;
use App\Models\Compliance\DocumentTemplate;
use App\Models\Compliance\EmploymentContract;
use App\Models\Compliance\Letterhead;
use App\Models\Compliance\PolicyAcknowledgement;
use App\Models\Employee;
use App\Models\User;
use App\Services\Compliance\DocxWriter;
use App\Services\Compliance\Vault;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Compliance Centre acceptance tests: the contract, policy and repository workflows end to end,
 * plus the security checks (tenancy, roles, manipulated ids, file validation, integrity).
 */
class ComplianceCentreTest extends TestCase
{
    private const H = 'http://localhost';

    private User $admin;
    private User $hr;
    private User $md;
    private User $ceo;
    private User $manager;
    private User $maryUser;
    private User $johnUser;
    private Employee $mary;
    private Employee $john;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Tenancy::reset();
        DB::setDefaultConnection('mysql');
        $this->admin = User::where('email', 'admin@pikozm.com')->first();
        $cid = $this->admin->company_id;
        Tenancy::setCompanyId($cid);
        \App\Support\Permissions::seedDefaultRoles($cid);
        \App\Models\Company::whereKey($cid)->update(['address' => 'Plot 5, Cairo Road', 'city' => 'Lusaka', 'tpin' => '1002003004', 'pay_day' => 25, 'phone' => '+260 952 992 844']);

        $mk = fn ($email, $name, $role, $emp = null) => User::firstOrCreate(['email' => $email], ['company_id' => $cid, 'name' => $name,
            'password' => bcrypt('x12345678'), 'role' => $role, 'employee_id' => $emp, 'email_verified_at' => now()]);
        $this->hr = $mk('hr.cc@pikozm.com', 'Hilda HR', 'hr');
        $this->md = $mk('md.cc@pikozm.com', 'Moses Director', 'company_admin');
        $this->ceo = $mk('ceo.cc@pikozm.com', 'Chanda CEO', 'company_admin');
        $this->manager = $mk('mgr.cc@pikozm.com', 'Mutale Manager', 'manager');
        $this->mary = Employee::where('employee_id', 'EMP-001')->first();
        $this->john = Employee::where('employee_id', 'EMP-002')->first();
        $this->mary->forceFill(['nrc_no' => '123456/10/1', 'position' => 'Accounts Officer', 'department' => 'Finance', 'contract_start' => '2026-12-01',
            'supervisor' => 'Finance Manager'])->save();
        $this->maryUser = $mk('mary.cc@pikozm.com', 'Mary Banda', 'employee', $this->mary->id);
        $this->johnUser = $mk('john.cc@pikozm.com', 'John Phiri', 'employee', $this->john->id);
    }

    private function signaturePng(): string
    {
        $img = imagecreatetruecolor(400, 120);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagesetthickness($img, 4);
        imageline($img, 20, 90, 380, 30, imagecolorallocate($img, 16, 35, 26));
        imageline($img, 40, 30, 300, 100, imagecolorallocate($img, 16, 35, 26));
        ob_start();
        imagepng($img);

        return 'data:image/png;base64,' . base64_encode(ob_get_clean());
    }

    private function signatureJpeg(): UploadedFile
    {
        $img = imagecreatetruecolor(600, 200);
        imagefill($img, 0, 0, imagecolorallocate($img, 245, 245, 240));
        imagesetthickness($img, 6);
        imageline($img, 30, 150, 560, 50, imagecolorallocate($img, 20, 20, 60));
        $path = tempnam(sys_get_temp_dir(), 'sig') . '.jpg';
        imagejpeg($img, $path, 90);

        return new UploadedFile($path, 'signature.jpg', 'image/jpeg', null, true);
    }

    private function contractWorkflow(): ApprovalWorkflow
    {
        $this->actingAs($this->admin)->post(self::H . '/compliance/workflows', [
            'name' => 'Three-step approval', 'applies_to' => 'contract', 'is_active' => 1,
            'steps' => [
                ['label' => 'HR Manager review', 'approver' => 'role:hr', 'action' => 'review'],
                ['label' => 'Managing Director approval', 'approver' => 'user:' . $this->md->id, 'action' => 'approve'],
                ['label' => 'CEO final signature', 'approver' => 'user:' . $this->ceo->id, 'action' => 'sign'],
            ],
        ])->assertSessionHasNoErrors();

        return ApprovalWorkflow::where('name', 'Three-step approval')->latest('id')->first();
    }

    public function test_contract_workflow_end_to_end(): void
    {
        // 1. Letterhead (generated from the company profile), previewed then saved.
        $lh = ['name' => 'Main letterhead', 'mode' => 'generated', 'margin_top' => 10, 'margin_bottom' => 10, 'margin_left' => 20, 'margin_right' => 20,
            'header_height' => 30, 'footer_height' => 15, 'logo_position' => 'left', 'font' => 'DejaVu Sans', 'font_size' => 10.5, 'accent' => '#0B3D2E',
            'page_numbers' => 1, 'show_registration' => 1, 'is_default' => 1];
        $this->actingAs($this->admin)->post(self::H . '/compliance/letterheads/preview', $lh)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($this->admin)->post(self::H . '/compliance/letterheads', $lh)->assertRedirect(route('compliance.settings'));
        $letterhead = Letterhead::where('name', 'Main letterhead')->firstOrFail();

        // Workflow rules: the last contract step must sign.
        $this->actingAs($this->admin)->post(self::H . '/compliance/workflows', ['name' => 'Bad', 'applies_to' => 'contract',
            'steps' => [['label' => 'Only review', 'approver' => 'role:hr', 'action' => 'review']]])->assertSessionHasErrors('steps');
        $this->contractWorkflow();

        // 2-4. Probationary contract for Mary, filled from her record.
        $tpl = DocumentTemplate::whereNull('company_id')->where('kind', 'contract')->where('key', 'probationary')->firstOrFail();
        $this->actingAs($this->hr)->post(self::H . '/compliance/contracts', ['employee_id' => $this->mary->id, 'contract_type' => 'probationary',
            'template_id' => $tpl->id, 'letterhead_id' => $letterhead->id])->assertRedirect();
        $contract = EmploymentContract::where('employee_id', $this->mary->id)->latest('id')->firstOrFail();
        $this->assertSame('draft', $contract->status);
        $this->actingAs($this->hr)->get(self::H . "/compliance/contracts/{$contract->id}/edit")->assertOk()
            ->assertSee('Employee residential address')->assertSee('Main duties')->assertSee('123456/10/1', false);

        // Missing details block sending.
        $this->actingAs($this->hr)->post(self::H . "/compliance/contracts/{$contract->id}/send")->assertSessionHasErrors('send');

        // 5. Edit: add a clause and complete the missing fields.
        $body = \App\Services\Compliance\Contracts::version($contract)->body . '<h2>Uniform</h2><p>The Employer provides two sets of uniform.</p><script>alert(1)</script>';
        $this->actingAs($this->hr)->put(self::H . "/compliance/contracts/{$contract->id}", ['body' => $body,
            'data' => ['employee_address' => 'Plot 12, Kabulonga, Lusaka', 'job_responsibilities' => "Prepare invoices\nReconcile accounts"]])->assertRedirect();
        $this->assertStringNotContainsString('<script', \App\Services\Compliance\Contracts::version($contract->fresh())->body);
        $this->assertSame([], \App\Services\Compliance\Contracts::missing($contract->fresh()));

        // 6-7. Word and PDF.
        $docx = $this->actingAs($this->hr)->get(self::H . "/compliance/contracts/{$contract->id}/docx");
        $docx->assertOk();
        $tmp = tempnam(sys_get_temp_dir(), 'dx');
        file_put_contents($tmp, $docx->getContent());
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmp) === true);
        $xml = $zip->getFromName('word/document.xml');
        $this->assertStringContainsString('Mary Banda', $xml);
        $this->assertStringContainsString('Uniform', $xml);
        $this->assertNotFalse($zip->getFromName('word/header1.xml'));
        $this->assertTrue((new \DOMDocument())->loadXML($xml), 'document.xml must be well-formed');
        $zip->close();
        $pdf = $this->actingAs($this->hr)->get(self::H . "/compliance/contracts/{$contract->id}/pdf");
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        // 8. Send to Mary.
        $this->actingAs($this->hr)->post(self::H . "/compliance/contracts/{$contract->id}/send")->assertSessionHasNoErrors();
        $contract->refresh();
        $this->assertSame('sent', $contract->status);
        Mail::assertSent(ComplianceMail::class, fn ($m) => $m->event === 'contract.issued' && $m->hasTo($this->mary->fresh()->user->email));

        // 9-10. Mary reviews in the portal. John cannot see it. Drafts edits are blocked once sent.
        $this->actingAs($this->maryUser)->get(self::H . '/portal/documents')->assertOk()->assertSee('Review and sign');
        $this->actingAs($this->maryUser)->get(self::H . "/portal/documents/contracts/{$contract->id}")->assertOk()->assertSee('Plot 12, Kabulonga, Lusaka');
        $this->assertNotNull($contract->fresh()->employee_viewed_at);
        $this->actingAs($this->johnUser)->get(self::H . "/portal/documents/contracts/{$contract->id}")->assertNotFound();
        $this->actingAs($this->johnUser)->post(self::H . "/portal/documents/contracts/{$contract->id}/sign", [])->assertNotFound();
        $this->assertSame('sent', $contract->fresh()->status, 'Opening the contract is not acceptance');

        $v = \App\Services\Compliance\Contracts::version($contract);
        $sign = fn ($hash, $name = 'Mary Banda') => $this->actingAs($this->maryUser)->post(self::H . "/portal/documents/contracts/{$contract->id}/sign", [
            'method' => 'draw', 'signature_data' => $this->signaturePng(), 'typed_name' => $name, 'version' => $contract->current_version, 'hash' => $hash, 'consent' => 1]);
        $sign(str_repeat('0', 64))->assertSessionHasErrors('signature');
        $sign($v->hash, 'Someone Else')->assertSessionHasErrors('typed_name');
        $this->actingAs($this->maryUser)->post(self::H . "/portal/documents/contracts/{$contract->id}/sign", ['method' => 'draw', 'signature_data' => $this->signaturePng(),
            'typed_name' => 'Mary Banda', 'version' => $contract->current_version, 'hash' => $v->hash])->assertSessionHasErrors('consent');
        $sign($v->hash)->assertSessionHasNoErrors();
        $contract->refresh();
        $this->assertSame('in_approval', $contract->status);
        $empSig = ContractSignature::where('contract_id', $contract->id)->where('signer_type', 'employee')->firstOrFail();
        $this->assertSame($v->hash, $empSig->document_hash);
        $this->assertNotEmpty($empSig->ip);
        $this->assertNotNull(Vault::get($empSig->image_path));

        // 11. HR is told; the first step waits for anyone with the HR role. The MD cannot jump ahead.
        Mail::assertSent(ComplianceMail::class, fn ($m) => $m->event === 'contract.employee_signed' && $m->hasTo('hr.cc@pikozm.com'));
        $steps = ApprovalRequest::where('approvable_type', 'contract')->where('approvable_id', $contract->id)->orderBy('position')->get();
        $this->assertSame(['pending', 'waiting', 'waiting'], $steps->pluck('status')->all());
        $this->actingAs($this->md)->post(self::H . "/compliance/approvals/{$steps[1]->id}", ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($this->manager)->post(self::H . "/compliance/approvals/{$steps[0]->id}", ['decision' => 'approve'])->assertForbidden();

        // The MD asks for changes: HR edits (new version), Mary signs again, approvals restart.
        $this->actingAs($this->hr)->post(self::H . "/compliance/approvals/{$steps[0]->id}", ['decision' => 'approve'])->assertRedirect();
        $this->actingAs($this->md)->post(self::H . "/compliance/approvals/{$steps[1]->id}", ['decision' => 'changes'])->assertSessionHasErrors('comment');
        $this->actingAs($this->md)->post(self::H . "/compliance/approvals/{$steps[1]->id}", ['decision' => 'changes', 'comment' => 'Probation should be two months'])->assertRedirect();
        $this->assertSame('changes', $contract->fresh()->status);
        $this->assertSame('cancelled', $steps[2]->fresh()->status);
        Mail::assertSent(ComplianceMail::class, fn ($m) => $m->event === 'contract.changes');

        $v1 = \App\Services\Compliance\Contracts::version($contract->fresh());
        $this->actingAs($this->hr)->put(self::H . "/compliance/contracts/{$contract->id}", ['body' => str_replace('first 3 months', 'first 2 months', $v1->body) . '<p>Amended probation.</p>',
            'change_note' => 'Probation shortened', 'then' => 'send'])->assertSessionHasNoErrors();
        $contract->refresh();
        $this->assertSame(2, $contract->current_version);
        $this->assertSame('sent', $contract->status);
        $this->assertSame($v1->hash, \App\Services\Compliance\Contracts::version($contract, 1)->hash, 'Version 1 is preserved unchanged');
        $v2 = \App\Services\Compliance\Contracts::version($contract);
        $this->assertNotSame($v1->hash, $v2->hash);

        // Uploaded signature this time (background removed).
        $this->actingAs($this->maryUser)->post(self::H . "/portal/documents/contracts/{$contract->id}/sign", ['method' => 'upload', 'signature_file' => $this->signatureJpeg(),
            'typed_name' => 'mary banda', 'version' => 2, 'hash' => $v2->hash, 'consent' => 1])->assertSessionHasNoErrors();
        $png = imagecreatefromstring(Vault::get(ContractSignature::where('contract_id', $contract->id)->where('version', 2)->first()->image_path));
        $this->assertSame(127, imagecolorsforindex($png, imagecolorat($png, 0, 0))['alpha'], 'Paper background removed');

        $round = ApprovalRequest::where('approvable_type', 'contract')->where('approvable_id', $contract->id)->where('round', $contract->fresh()->round)->orderBy('position')->get();
        $this->actingAs($this->hr)->post(self::H . "/compliance/approvals/{$round[0]->id}", ['decision' => 'approve']);
        $this->actingAs($this->md)->post(self::H . "/compliance/approvals/{$round[1]->id}", ['decision' => 'approve', 'comment' => 'Approved']);
        // 13. CEO must sign: without a signature or consent it fails.
        $this->actingAs($this->ceo)->post(self::H . "/compliance/approvals/{$round[2]->id}", ['decision' => 'approve'])->assertSessionHasErrors('consent');
        $this->actingAs($this->ceo)->get(self::H . "/compliance/approvals/{$round[2]->id}")->assertOk()->assertSee('Sign and execute');
        $this->actingAs($this->ceo)->post(self::H . "/compliance/approvals/{$round[2]->id}", ['decision' => 'approve', 'consent' => 1, 'signature_method' => 'draw',
            'signature_data' => $this->signaturePng(), 'signer_title' => 'Chief Executive Officer'])->assertSessionHasNoErrors();

        // 14-16. Executed, fingerprinted, stored, distributed, filed.
        $contract->refresh();
        $this->assertSame('executed', $contract->status);
        $stored = Vault::get($contract->executed_pdf_path);
        $this->assertStringStartsWith('%PDF', $stored);
        $this->assertSame(hash('sha256', $stored), $contract->executed_hash);
        Mail::assertSent(ComplianceMail::class, fn ($m) => $m->event === 'contract.executed' && $m->hasTo($this->mary->fresh()->user->email) && $m->attachmentBytes === $stored);
        Mail::assertSent(ComplianceMail::class, fn ($m) => $m->event === 'contract.distributed' && $m->hasTo('ceo.cc@pikozm.com'));
        $this->assertDatabaseHas('compliance_mail_logs', ['subject_type' => 'contract', 'subject_id' => $contract->id, 'event' => 'contract.executed', 'status' => 'sent']);
        $filed = CompanyDocument::where('contract_id', $contract->id)->firstOrFail();
        $this->assertSame($this->mary->id, $filed->employee_id);
        $this->actingAs($this->maryUser)->get(self::H . '/portal/documents/contracts/' . $contract->id . '/pdf')->assertOk();
        $this->actingAs($this->hr)->get(self::H . "/compliance/contracts/{$contract->id}")->assertOk()->assertSee('Fully executed')->assertSee($contract->executed_hash);

        // 17. Complete history.
        $actions = AuditLog::where('subject_type', 'EmploymentContract')->where('subject_id', $contract->id)->pluck('action')->all();
        foreach (['compliance.contract_created', 'compliance.contract_edited', 'compliance.contract_sent', 'compliance.contract_viewed', 'compliance.contract_signed_employee', 'compliance.contract_executed'] as $a) {
            $this->assertContains($a, $actions);
        }
        $this->assertSame(2, ContractSignature::where('contract_id', $contract->id)->where('version', 2)->count());

        // Locked: no silent edits; tampering with the stored file is detected; amendments make a new contract.
        $this->actingAs($this->hr)->put(self::H . "/compliance/contracts/{$contract->id}", ['body' => '<p>changed</p>'])->assertStatus(422);
        $this->actingAs($this->hr)->get(self::H . "/compliance/contracts/{$contract->id}/edit")->assertForbidden();
        $this->actingAs($this->hr)->post(self::H . "/compliance/contracts/{$contract->id}/amend")->assertRedirect();
        $amend = EmploymentContract::where('supersedes_id', $contract->id)->firstOrFail();
        $this->assertSame('draft', $amend->status);
        $this->assertSame('executed', $contract->fresh()->status);
        Vault::disk()->put($contract->executed_pdf_path, $stored . 'tampered');
        $this->actingAs($this->hr)->get(self::H . "/compliance/contracts/{$contract->id}/pdf")->assertStatus(410);
        Vault::disk()->put($contract->executed_pdf_path, $stored);

        // Another company sees none of it.
        Tenancy::reset();
        $other = User::where('email', 'admin@second.com')->first();
        $this->actingAs($other)->get(self::H . "/compliance/contracts/{$contract->id}")->assertNotFound();
        $this->actingAs($other)->get(self::H . "/compliance/contracts/{$contract->id}/pdf")->assertNotFound();
        $this->actingAs($other)->get(self::H . "/compliance/approvals/{$round[2]->id}")->assertNotFound();
        $this->actingAs($other)->get(self::H . "/compliance/documents/{$filed->id}/download")->assertNotFound();
    }

    public function test_policy_workflow_end_to_end(): void
    {
        $this->actingAs($this->admin)->post(self::H . '/compliance/workflows', ['name' => 'Policy approval', 'applies_to' => 'policy', 'is_active' => 1,
            'steps' => [['label' => 'Managing Director approval', 'approver' => 'user:' . $this->md->id, 'action' => 'approve']]])->assertSessionHasNoErrors();

        // 1-3. Disciplinary policy from the library with company details; HR edits it.
        $this->actingAs($this->hr)->get(self::H . '/compliance/policies/library')->assertOk()->assertSee('Required by law')->assertSee('Employment Code Act No. 3 of 2019, s.95(1)');
        $tpl = DocumentTemplate::whereNull('company_id')->where('kind', 'policy')->where('key', 'disciplinary')->firstOrFail();
        $this->assertSame('recommended', $tpl->legal_status, 'Not presented as a statutory requirement');
        $this->actingAs($this->hr)->post(self::H . '/compliance/policies', ['template_id' => $tpl->id])->assertRedirect();
        $policy = CompanyPolicy::latest('id')->firstOrFail();
        $body = \App\Services\Compliance\Policies::draft($policy)->body . '<h2>Gross misconduct examples</h2><ul><li>Theft</li><li>Fraud</li></ul>';
        $this->actingAs($this->hr)->put(self::H . "/compliance/policies/{$policy->id}", ['title' => 'Disciplinary Policy', 'body' => $body, 'requires_acknowledgement' => 1])->assertRedirect();

        // 4. Word and PDF with the company name filled in.
        $docx = $this->actingAs($this->hr)->get(self::H . "/compliance/policies/{$policy->id}/docx")->assertOk()->getContent();
        $tmp = tempnam(sys_get_temp_dir(), 'dx');
        file_put_contents($tmp, $docx);
        $zip = new \ZipArchive();
        $zip->open($tmp);
        $this->assertStringContainsString('Pikozm Ltd', $zip->getFromName('word/document.xml'));
        $this->assertStringContainsString('Gross misconduct', $zip->getFromName('word/document.xml'));
        $this->assertStringContainsString('w:numId', $zip->getFromName('word/document.xml'));
        $this->assertStringStartsWith('%PDF', $this->actingAs($this->hr)->get(self::H . "/compliance/policies/{$policy->id}/pdf")->getContent());

        // 5-7. Approval then publication.
        $this->actingAs($this->hr)->post(self::H . "/compliance/policies/{$policy->id}/submit")->assertRedirect();
        $this->assertSame('in_approval', $policy->fresh()->status);
        $this->actingAs($this->maryUser)->get(self::H . "/portal/documents/policies/{$policy->id}")->assertNotFound();
        $req = ApprovalRequest::where('approvable_type', 'policy')->where('approvable_id', $policy->id)->firstOrFail();
        $this->actingAs($this->hr)->post(self::H . "/compliance/approvals/{$req->id}", ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($this->md)->post(self::H . "/compliance/approvals/{$req->id}", ['decision' => 'approve'])->assertRedirect();
        $policy->refresh();
        $this->assertSame('active', $policy->status);
        $this->assertSame(1, $policy->active_version);

        // 8-10. Employees are told and acknowledge; HR sees the status.
        Mail::assertSent(ComplianceMail::class, fn ($m) => $m->event === 'policy.published' && $m->hasTo($this->mary->fresh()->user->email));
        $this->actingAs($this->maryUser)->get(self::H . '/portal/documents')->assertSee('Read and acknowledge: Disciplinary Policy');
        $page = $this->actingAs($this->maryUser)->get(self::H . "/portal/documents/policies/{$policy->id}")->assertOk()->assertSee('Gross misconduct');
        $hash = $policy->activeVersionRow()->hash;
        $this->actingAs($this->maryUser)->post(self::H . "/portal/documents/policies/{$policy->id}/acknowledge", ['version' => 1, 'hash' => $hash])->assertSessionHasErrors('confirm');
        $this->actingAs($this->maryUser)->post(self::H . "/portal/documents/policies/{$policy->id}/acknowledge", ['version' => 1, 'hash' => $hash, 'confirm' => 1])->assertSessionHasNoErrors();
        $ack = PolicyAcknowledgement::where('policy_id', $policy->id)->where('employee_id', $this->mary->id)->firstOrFail();
        $this->assertSame($hash, $ack->document_hash);
        $this->assertStringContainsString('does not take away any of my rights', $ack->statement);
        $this->actingAs($this->hr)->get(self::H . '/compliance/acknowledgements')->assertOk()->assertSee('Mary Banda');
        $this->assertTrue(\App\Services\Compliance\Policies::outstanding($policy)->contains('id', $this->john->id));
        $this->assertFalse(\App\Services\Compliance\Policies::outstanding($policy)->contains('id', $this->mary->id));

        // 11-12. Revision: a reason is required, v1 stays active until v2 is approved, and fresh acknowledgement is requested.
        $this->actingAs($this->hr)->post(self::H . "/compliance/policies/{$policy->id}/revise", [])->assertSessionHasErrors('change_reason');
        $this->actingAs($this->hr)->post(self::H . "/compliance/policies/{$policy->id}/revise", ['change_reason' => 'Added appeals timeline'])->assertRedirect();
        $policy->refresh();
        $this->assertSame(2, $policy->current_version);
        $this->assertSame(1, $policy->active_version);
        $this->actingAs($this->maryUser)->get(self::H . "/portal/documents/policies/{$policy->id}")->assertOk()->assertDontSee('Appeals within 10 days');
        $v2 = \App\Services\Compliance\Policies::draft($policy)->body . '<p>Appeals within 10 days.</p>';
        $this->actingAs($this->hr)->put(self::H . "/compliance/policies/{$policy->id}", ['title' => 'Disciplinary Policy', 'body' => $v2, 'requires_acknowledgement' => 1,
            'change_reason' => 'Added appeals timeline', 'then' => 'submit', 'fresh_ack' => 1])->assertSessionHasNoErrors();
        $req2 = ApprovalRequest::where('approvable_type', 'policy')->where('approvable_id', $policy->id)->where('round', 2)->firstOrFail();
        $this->actingAs($this->md)->post(self::H . "/compliance/approvals/{$req2->id}", ['decision' => 'approve']);
        $policy->refresh();
        $this->assertSame(2, $policy->active_version);
        $this->assertSame('superseded', \App\Models\Compliance\PolicyVersion::where('policy_id', $policy->id)->where('version', 1)->value('status'));
        Mail::assertSent(ComplianceMail::class, fn ($m) => $m->event === 'policy.updated');
        $this->assertTrue(\App\Services\Compliance\Policies::outstanding($policy)->contains('id', $this->mary->id), 'Fresh acknowledgement requested');
        $this->assertSame(1, PolicyAcknowledgement::where('policy_id', $policy->id)->count(), 'The v1 acknowledgement is kept');
    }

    public function test_document_repository_and_access_control(): void
    {
        $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[]/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
        $docx = DocxWriter::make('<h1>HR Manual</h1><p>Welcome.</p>', null, Tenancy::company(), 'HR Manual');
        $cat = fn ($name) => \App\Models\Compliance\DocumentCategory::where('name', $name)->value('id');

        $this->actingAs($this->hr)->get(self::H . '/compliance/documents')->assertOk(); // creates default categories
        $this->assertNotNull($cat('Employment contracts'));

        // 1-5. Upload a policy, a contract and an HR manual with metadata.
        $this->actingAs($this->hr)->post(self::H . '/compliance/documents', ['files' => [UploadedFile::fake()->createWithContent('Leave policy 2024.pdf', $pdf)],
            'title' => 'Leave Policy 2024', 'category_id' => $cat('Leave policies'), 'reference' => 'POL-LV-01', 'review_date' => now()->addDays(20)->toDateString(),
            'access_level' => 'company', 'share_with_employees' => 1, 'tags' => 'leave, handbook'])->assertSessionHasNoErrors();
        $this->actingAs($this->hr)->post(self::H . '/compliance/documents', ['files' => [UploadedFile::fake()->createWithContent('Mary contract.pdf', $pdf)],
            'title' => 'Mary Banda signed contract (paper)', 'category_id' => $cat('Employment contracts'), 'employee_id' => $this->mary->id,
            'access_level' => 'employee', 'share_with_employees' => 1, 'expiry_date' => now()->addDays(25)->toDateString()])->assertSessionHasNoErrors();
        $this->actingAs($this->hr)->postJson(self::H . '/compliance/documents', ['files' => [UploadedFile::fake()->createWithContent('HR Manual.docx', $docx)],
            'category_id' => $cat('HR manuals'), 'access_level' => 'hr'])->assertOk()->assertJson(['created' => 1]);
        $policyDoc = CompanyDocument::where('reference', 'POL-LV-01')->firstOrFail();
        $maryDoc = CompanyDocument::where('employee_id', $this->mary->id)->whereNull('contract_id')->firstOrFail();
        $manual = CompanyDocument::where('title', 'HR Manual')->firstOrFail();

        // 6. Search and filters.
        $this->actingAs($this->hr)->get(self::H . '/compliance/documents?q=POL-LV')->assertSee('Leave Policy 2024')->assertDontSee('HR Manual');
        $this->actingAs($this->hr)->get(self::H . '/compliance/documents?tag=handbook')->assertSee('Leave Policy 2024');
        $this->actingAs($this->hr)->get(self::H . '/compliance/documents?employee=' . $this->mary->id . '&view=grid')->assertSee('Mary Banda signed contract');
        $this->actingAs($this->hr)->get(self::H . '/compliance/documents?expiry=soon')->assertSee('Mary Banda signed contract')->assertDontSee('Leave Policy 2024');

        // 7-8. Preview inline (PDF) and download; Word files are download-only.
        $this->actingAs($this->hr)->get(self::H . "/compliance/documents/{$policyDoc->id}/preview")->assertOk()->assertHeader('Content-Disposition', 'inline; filename="leave-policy-2024.pdf"')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->hr)->get(self::H . "/compliance/documents/{$manual->id}/preview")->assertStatus(415);
        $this->assertSame($docx, $this->actingAs($this->hr)->get(self::H . "/compliance/documents/{$manual->id}/download")->getContent());

        // 9. Replace keeps version history.
        $this->actingAs($this->hr)->post(self::H . "/compliance/documents/{$policyDoc->id}/replace", ['file' => UploadedFile::fake()->createWithContent('Leave policy 2025.pdf', $pdf . "\n%v2"), 'note' => '2025 update'])->assertSessionHasNoErrors();
        $this->assertSame(2, $policyDoc->fresh()->current_version);
        $this->assertSame($pdf, $this->actingAs($this->hr)->get(self::H . "/compliance/documents/{$policyDoc->id}/download/1")->getContent());
        $this->assertStringEndsWith('%v2', $this->actingAs($this->hr)->get(self::H . "/compliance/documents/{$policyDoc->id}/download/2")->getContent());

        // Validation: renamed files, oversized types, ZIP path tricks.
        $this->actingAs($this->hr)->post(self::H . '/compliance/documents', ['files' => [UploadedFile::fake()->createWithContent('invoice.pdf', 'MZ this is a program')], 'access_level' => 'hr'])->assertSessionHasErrors('files');
        $this->actingAs($this->hr)->post(self::H . '/compliance/documents', ['files' => [UploadedFile::fake()->createWithContent('run.php', '<?php echo 1;')], 'access_level' => 'hr'])->assertSessionHasErrors('files');
        $zipPath = tempnam(sys_get_temp_dir(), 'z') . '.zip';
        $z = new \ZipArchive();
        $z->open($zipPath, \ZipArchive::CREATE);
        $z->addFromString('../../../evil.pdf', $pdf);
        $z->addFromString('folder/Training record.pdf', $pdf);
        $z->addFromString('shell.php', '<?php system($_GET[1]);');
        $z->close();
        $this->actingAs($this->hr)->post(self::H . '/compliance/documents', ['files' => [new UploadedFile($zipPath, 'bulk.zip', 'application/zip', null, true)], 'access_level' => 'hr'])->assertSessionHasNoErrors();
        $this->assertTrue(CompanyDocument::where('title', 'evil')->exists());
        $this->assertTrue(CompanyDocument::where('title', 'Training record')->exists());
        $this->assertFalse(CompanyDocument::where('title', 'shell')->exists());
        foreach (\App\Models\Compliance\CompanyDocumentVersion::pluck('path') as $p) {
            $this->assertStringStartsWith('compliance/' . $this->admin->company_id . '/', $p);
            $this->assertStringNotContainsString('..', $p);
        }

        // 10. Expiry and review reminders, sent once per interval.
        $this->artisan('compliance:reminders', ['--company' => $this->admin->company_id])->assertSuccessful();
        Mail::assertSent(ComplianceMail::class, fn ($m) => $m->event === 'reminder.expiry' && str_contains($m->mailSubject, 'Mary Banda signed contract'));
        Mail::assertSent(ComplianceMail::class, fn ($m) => $m->event === 'reminder.review' && str_contains($m->mailSubject, 'Leave Policy 2024'));
        $count = DB::table('compliance_reminder_logs')->count();
        $this->artisan('compliance:reminders', ['--company' => $this->admin->company_id])->assertSuccessful();
        $this->assertSame($count, DB::table('compliance_reminder_logs')->count());

        // 11. Access: a manager sees company-wide documents, not HR-only ones; employees see only what is shared with them.
        $this->actingAs($this->manager)->get(self::H . "/compliance/documents/{$policyDoc->id}")->assertOk();
        $this->actingAs($this->manager)->get(self::H . "/compliance/documents/{$manual->id}")->assertNotFound();
        $this->actingAs($this->manager)->get(self::H . "/compliance/documents/{$manual->id}/download")->assertNotFound();
        $this->actingAs($this->manager)->get(self::H . '/compliance/documents')->assertSee('Leave Policy 2024')->assertDontSee('HR Manual');
        $this->actingAs($this->manager)->post(self::H . '/compliance/documents', ['files' => [UploadedFile::fake()->createWithContent('x.pdf', $pdf)], 'access_level' => 'hr'])->assertForbidden();
        $this->actingAs($this->maryUser)->get(self::H . "/portal/documents/files/{$maryDoc->id}")->assertOk();
        $this->actingAs($this->johnUser)->get(self::H . "/portal/documents/files/{$maryDoc->id}")->assertNotFound();
        $this->actingAs($this->johnUser)->get(self::H . "/portal/documents/files/{$manual->id}")->assertNotFound();
        $this->actingAs($this->johnUser)->get(self::H . "/portal/documents/files/{$policyDoc->id}")->assertOk();
        $this->actingAs($this->maryUser)->get(self::H . "/compliance/documents/{$maryDoc->id}")->assertRedirect(route('portal.dashboard'));

        // Archive keeps the file; register export lists everything.
        $this->actingAs($this->hr)->post(self::H . "/compliance/documents/{$manual->id}/archive")->assertRedirect();
        $this->assertSame('archived', $manual->fresh()->status);
        $this->assertNotNull(Vault::get($manual->versions()->first()->path));
        $csv = $this->actingAs($this->hr)->get(self::H . '/compliance/documents/register?format=csv');
        $csv->assertOk();
        $this->assertStringContainsString('Leave Policy 2024', $csv->streamedContent());
        $this->assertSame('PK', substr($this->actingAs($this->hr)->get(self::H . '/compliance/documents/register?format=xlsx')->getFile()->getContent(), 0, 2));

        // 12. Other tenant.
        Tenancy::reset();
        $other = User::where('email', 'admin@second.com')->first();
        $this->actingAs($other)->get(self::H . "/compliance/documents/{$policyDoc->id}")->assertNotFound();
        $this->actingAs($other)->get(self::H . "/compliance/documents/{$policyDoc->id}/download/1")->assertNotFound();
        $this->actingAs($other)->get(self::H . '/compliance/documents')->assertDontSee('Leave Policy 2024');
    }

    public function test_templates_overview_reports_and_permissions(): void
    {
        $master = DocumentTemplate::whereNull('company_id')->where('kind', 'contract')->where('key', 'permanent')->firstOrFail();
        $this->assertSame(6, DocumentTemplate::whereNull('company_id')->where('kind', 'contract')->count());
        $this->assertSame(18, DocumentTemplate::whereNull('company_id')->where('kind', 'policy')->count());
        $this->assertSame(7, DocumentTemplate::whereNull('company_id')->where('legal_status', 'required')->count());

        // Masters are read-only; a company copy is private to the company and versioned.
        $this->actingAs($this->hr)->get(self::H . "/compliance/templates/{$master->id}/edit")->assertForbidden();
        $this->actingAs($this->hr)->post(self::H . "/compliance/templates/{$master->id}/duplicate")->assertRedirect();
        $copy = DocumentTemplate::where('company_id', $this->admin->company_id)->where('source_template_id', $master->id)->latest('id')->firstOrFail();
        $this->actingAs($this->hr)->put(self::H . "/compliance/templates/{$copy->id}", ['name' => 'Our permanent contract', 'key' => 'permanent', 'body' => $copy->body . '<p>Extra clause.</p>', 'change_note' => 'Extra clause'])->assertRedirect();
        $this->assertSame(2, $copy->fresh()->version);
        $this->assertStringNotContainsString('Extra clause', $master->fresh()->body);
        $this->actingAs($this->hr)->get(self::H . "/compliance/templates/{$master->id}/preview")->assertOk();

        // Word import.
        $docx = DocxWriter::make('<h2>Hours</h2><p>We work <strong>8 hours</strong>.</p><ul><li>Mon</li></ul>', null, Tenancy::company(), 'x');
        $this->actingAs($this->hr)->post(self::H . '/compliance/templates', ['kind' => 'policy', 'name' => 'Imported policy', 'key' => 'imported',
            'word_file' => UploadedFile::fake()->createWithContent('policy.docx', $docx)])->assertRedirect();
        $imported = DocumentTemplate::where('name', 'Imported policy')->firstOrFail();
        $this->assertStringContainsString('<h2>Hours</h2>', $imported->body);
        $this->assertStringContainsString('<strong>8 hours</strong>', $imported->body);
        $this->assertStringContainsString('<li>Mon</li>', $imported->body);

        // A master update shows on copies but never changes them.
        DB::table('document_templates')->where('id', $master->id)->update(['version' => 2]);
        $this->assertTrue($copy->fresh()->masterUpdateAvailable());
        DB::table('document_templates')->where('id', $master->id)->update(['version' => 1]);

        // Overview figures are counted from records; drill-down links work; alerts can be hidden but come back when the number changes.
        $this->actingAs($this->admin)->get(self::H . '/compliance')->assertOk()->assertSee('Without a completed contract')->assertSee('not a legal assessment');
        $stats = \App\Services\Compliance\Overview::stats();
        $this->assertSame(Employee::workforce()->count(), $stats['employees']);
        $this->actingAs($this->admin)->post(self::H . '/compliance/alerts/without_contract/dismiss')->assertRedirect();
        $this->assertFalse(collect(\App\Services\Compliance\Overview::alerts($stats, $this->admin))->contains('key', 'without_contract'));
        $stats['without_contract']++;
        $this->assertTrue(collect(\App\Services\Compliance\Overview::alerts($stats, $this->admin))->contains('key', 'without_contract'));
        foreach (array_keys(array_merge(...array_values(\App\Http\Controllers\Compliance\ReportController::REPORTS))) as $key) {
            $this->actingAs($this->admin)->get(self::H . '/compliance/reports?report=' . $key)->assertOk();
        }
        $this->assertStringContainsString('John Phiri', $this->actingAs($this->admin)->get(self::H . '/compliance/reports/export?report=without_contract&format=csv')->streamedContent());
        $this->assertStringStartsWith('%PDF', $this->actingAs($this->admin)->get(self::H . '/compliance/reports/export?report=active_policies&format=pdf')->getContent());

        // Role checks.
        $this->actingAs($this->manager)->get(self::H . '/compliance')->assertForbidden();
        $this->actingAs($this->manager)->get(self::H . '/compliance/contracts/create')->assertForbidden();
        $this->actingAs($this->manager)->get(self::H . '/compliance/settings')->assertForbidden();
        $this->actingAs($this->manager)->get(self::H . '/compliance/templates')->assertForbidden();
        $this->actingAs($this->hr)->get(self::H . '/compliance/settings')->assertForbidden();
        $this->actingAs($this->maryUser)->get(self::H . '/compliance')->assertRedirect(route('portal.dashboard'));
        $this->actingAs($this->admin)->get(self::H . '/compliance/settings')->assertOk()->assertSee('Approval workflows');
        $this->actingAs($this->admin)->get(self::H . '/dashboard')->assertSee('Compliance Centre');
        $this->actingAs($this->maryUser)->get(self::H . '/portal')->assertSee(route('portal.documents.index'), false);

        // Settings guard the legal minimums.
        $this->actingAs($this->admin)->put(self::H . '/compliance/settings', ['retention_years' => 2, 'probation_months' => 6])->assertSessionHasErrors(['retention_years', 'probation_months']);
    }
}
