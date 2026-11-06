<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\CompanyRole;
use App\Models\Compliance\ApprovalWorkflow;
use App\Models\Compliance\ApprovalWorkflowStep;
use App\Models\User;
use App\Services\Compliance\TemplateLibrary;
use App\Support\Audit;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Company-designed approval chains. No job titles are hard-coded: each step names a person or a role,
 * what they do (review, approve, sign for the company) and, optionally, one explicitly allowed delegate.
 */
class WorkflowController extends Controller
{
    private function options(): array
    {
        return [
            'users' => User::where('company_id', Tenancy::companyId())->orderBy('name')->get()->filter->isStaff()->values(),
            'roles' => CompanyRole::orderBy('name')->get(),
            'types' => TemplateLibrary::CONTRACT_TYPES,
        ];
    }

    public function create(Request $request)
    {
        $wf = new ApprovalWorkflow(['applies_to' => $request->query('for') === 'policy' ? 'policy' : 'contract', 'is_active' => true]);

        return view('compliance.workflow-form', ['workflow' => $wf, 'steps' => collect()] + $this->options());
    }

    public function edit(ApprovalWorkflow $workflow)
    {
        return view('compliance.workflow-form', ['workflow' => $workflow, 'steps' => $workflow->steps] + $this->options());
    }

    public function store(Request $request)
    {
        $wf = new ApprovalWorkflow();
        $this->save($request, $wf);

        return redirect()->route('compliance.settings')->with('success', 'Approval workflow saved.');
    }

    public function update(Request $request, ApprovalWorkflow $workflow)
    {
        $this->save($request, $workflow);

        return redirect()->route('compliance.settings')->with('success', 'Approval workflow updated. Documents already in approval keep the steps they started with.');
    }

    public function destroy(ApprovalWorkflow $workflow)
    {
        $workflow->delete();
        Audit::record('compliance.workflow_removed', $workflow, "Workflow \"{$workflow->name}\" removed");

        return back()->with('success', 'Workflow removed.');
    }

    private function save(Request $request, ApprovalWorkflow $wf): void
    {
        $d = $request->validate([
            'name' => 'required|string|max:120',
            'applies_to' => 'required|in:contract,policy',
            'contract_types' => 'nullable|array', 'contract_types.*' => 'in:' . implode(',', array_keys(TemplateLibrary::CONTRACT_TYPES)),
            'is_active' => 'nullable|boolean',
            'steps' => 'required|array|min:1|max:8',
            'steps.*.label' => 'required|string|max:120',
            'steps.*.approver' => 'required|string|max:60',
            'steps.*.action' => 'required|in:review,approve,sign',
            'steps.*.delegate_user_id' => 'nullable|integer',
        ], ['steps.required' => 'Add at least one step.']);

        $userIds = User::where('company_id', Tenancy::companyId())->pluck('id')->all();
        $roleKeys = CompanyRole::pluck('key')->all();
        $steps = [];
        foreach (array_values($d['steps']) as $i => $s) {
            [$type, $ref] = array_pad(explode(':', $s['approver'], 2), 2, null);
            if (! ($type === 'user' && in_array((int) $ref, $userIds, true)) && ! ($type === 'role' && in_array($ref, $roleKeys, true))) {
                throw ValidationException::withMessages(["steps.{$i}.approver" => 'Choose who does this step.']);
            }
            $delegate = ! empty($s['delegate_user_id']) ? (int) $s['delegate_user_id'] : null;
            if ($delegate && ! in_array($delegate, $userIds, true)) {
                throw ValidationException::withMessages(["steps.{$i}.delegate_user_id" => 'Choose a delegate from your team.']);
            }
            $steps[] = ['position' => $i + 1, 'label' => $s['label'], 'approver_type' => $type, 'approver_user_id' => $type === 'user' ? (int) $ref : null,
                'approver_role_key' => $type === 'role' ? $ref : null, 'action' => $s['action'], 'delegate_user_id' => $delegate];
        }
        if ($d['applies_to'] === 'contract') {
            if (end($steps)['action'] !== 'sign') {
                throw ValidationException::withMessages(['steps' => 'The last step of a contract workflow must be the final company signature ("Signs for the company").']);
            }
            if (collect($steps)->where('action', 'sign')->count() > 1) {
                throw ValidationException::withMessages(['steps' => 'Only the last step should sign for the company. Earlier steps review or approve.']);
            }
        }

        DB::transaction(function () use ($wf, $d, $steps) {
            $wf->fill(['name' => $d['name'], 'applies_to' => $d['applies_to'], 'is_active' => (bool) ($d['is_active'] ?? false),
                'contract_types' => $d['applies_to'] === 'contract' && ! empty($d['contract_types']) ? array_values($d['contract_types']) : null])->save();
            ApprovalWorkflowStep::where('workflow_id', $wf->id)->delete();
            foreach ($steps as $s) {
                ApprovalWorkflowStep::create($s + ['workflow_id' => $wf->id]);
            }
        });
        Audit::record('compliance.workflow_saved', $wf, "Workflow \"{$wf->name}\" saved with " . count($steps) . ' step(s)',
            ['steps' => collect($steps)->map(fn ($s) => $s['label'] . ' (' . $s['action'] . ')')->all()]);
    }
}
