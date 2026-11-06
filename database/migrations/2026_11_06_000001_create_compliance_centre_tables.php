<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Compliance Centre: letterheads, contract and policy templates, employment contracts with
 * signatures and approvals, company policies with acknowledgements, the document repository,
 * reminders and an email delivery log.
 *
 * Every tenant row carries company_id (the BelongsToCompany scope filters on it). Master templates
 * have company_id = NULL and are shared read-only. Run `php artisan tenancy:migrate` for dedicated tenants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_settings', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->unique();
            $t->text('disclaimer')->nullable();
            $t->json('reminder_days')->nullable();            // e.g. [90, 30, 7, 0]
            $t->unsignedSmallInteger('retention_years')->default(5);
            $t->string('working_hours', 255)->nullable();
            $t->string('notice_period', 120)->nullable();
            $t->unsignedTinyInteger('probation_months')->default(3);
            $t->string('allowances', 255)->nullable();
            $t->string('signatory_name', 120)->nullable();
            $t->string('signatory_title', 120)->nullable();
            $t->timestamps();
        });

        Schema::create('company_letterheads', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->index();
            $t->string('name', 120);
            $t->string('mode', 20)->default('generated');     // generated | custom
            $t->boolean('is_default')->default(false);
            $t->string('header_path')->nullable();             // private disk, image
            $t->string('footer_path')->nullable();
            $t->string('source_path')->nullable();             // original upload (e.g. PDF)
            $t->json('settings')->nullable();                  // margins, positions, font, page numbers
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('document_templates', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->nullable()->index();   // NULL = ZamPayroll master
            $t->string('kind', 20);                                        // contract | policy
            $t->string('key', 60);                                         // permanent, disciplinary, ...
            $t->string('name', 160);
            $t->text('description')->nullable();
            $t->string('legal_status', 20)->nullable();                    // required | conditional | recommended
            $t->text('legal_note')->nullable();
            $t->longText('body');
            $t->unsignedInteger('version')->default(1);
            $t->unsignedBigInteger('source_template_id')->nullable();
            $t->unsignedInteger('source_version')->nullable();
            $t->string('review_status', 20)->default('unreviewed');        // unreviewed | reviewed
            $t->date('reviewed_on')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['kind', 'key']);
        });

        Schema::create('document_template_versions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->nullable()->index();
            $t->unsignedBigInteger('template_id')->index();
            $t->unsignedInteger('version');
            $t->longText('body');
            $t->string('change_note', 255)->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->unique(['template_id', 'version']);
        });

        Schema::create('employment_contracts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->index();
            $t->unsignedBigInteger('employee_id')->index();
            $t->unsignedBigInteger('template_id')->nullable();
            $t->unsignedBigInteger('letterhead_id')->nullable();
            $t->unsignedBigInteger('supersedes_id')->nullable();
            $t->string('reference', 40);
            $t->string('contract_type', 30);
            $t->string('title', 160);
            $t->string('status', 30)->default('draft')->index();
            $t->unsignedInteger('current_version')->default(1);
            $t->unsignedInteger('round')->default(0);
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->json('data')->nullable();                      // placeholder values HR filled in
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('employee_viewed_at')->nullable();
            $t->timestamp('employee_signed_at')->nullable();
            $t->timestamp('declined_at')->nullable();
            $t->text('decline_reason')->nullable();
            $t->timestamp('executed_at')->nullable();
            $t->string('executed_pdf_path')->nullable();
            $t->string('executed_hash', 64)->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['company_id', 'reference']);
        });

        Schema::create('employment_contract_versions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->index();
            $t->unsignedBigInteger('contract_id')->index();
            $t->unsignedInteger('version');
            $t->longText('body');
            $t->string('hash', 64);
            $t->string('change_note', 255)->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->unique(['contract_id', 'version']);
        });

        Schema::create('contract_signatures', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->index();
            $t->unsignedBigInteger('contract_id')->index();
            $t->unsignedInteger('version');
            $t->string('signer_type', 20);                    // employee | company
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->unsignedBigInteger('approval_id')->nullable();
            $t->string('signer_name', 160);
            $t->string('signer_title', 160)->nullable();
            $t->string('method', 20);                         // draw | upload
            $t->string('image_path');
            $t->string('document_hash', 64);
            $t->text('consent_text');
            $t->string('typed_name', 160)->nullable();
            $t->string('ip', 64)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->timestamp('signed_at');
            $t->timestamps();
        });

        Schema::create('approval_workflows', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->index();
            $t->string('applies_to', 20);                     // contract | policy
            $t->string('name', 120);
            $t->json('contract_types')->nullable();           // null = all types
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('approval_workflow_steps', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->index();
            $t->unsignedBigInteger('workflow_id')->index();
            $t->unsignedSmallInteger('position');
            $t->string('label', 120);
            $t->string('approver_type', 10);                  // user | role
            $t->unsignedBigInteger('approver_user_id')->nullable();
            $t->string('approver_role_key', 40)->nullable();
            $t->string('action', 10);                         // review | approve | sign
            $t->unsignedBigInteger('delegate_user_id')->nullable();
            $t->timestamps();
        });

        Schema::create('approval_requests', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->index();
            $t->string('approvable_type', 40);                // contract | policy
            $t->unsignedBigInteger('approvable_id');
            $t->unsignedInteger('version');
            $t->unsignedInteger('round')->default(1);
            $t->unsignedSmallInteger('position');
            $t->string('label', 120);
            $t->string('approver_type', 10);
            $t->unsignedBigInteger('approver_user_id')->nullable();
            $t->string('approver_role_key', 40)->nullable();
            $t->unsignedBigInteger('delegate_user_id')->nullable();
            $t->string('action', 10);
            $t->string('status', 20)->default('waiting')->index(); // waiting | pending | approved | rejected | changes | cancelled
            $t->unsignedBigInteger('acted_by')->nullable();
            $t->string('acted_by_name', 160)->nullable();
            $t->boolean('acted_as_delegate')->default(false);
            $t->timestamp('acted_at')->nullable();
            $t->text('comment')->nullable();
            $t->timestamps();
            $t->index(['approvable_type', 'approvable_id']);
        });

        Schema::create('compliance_comments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->index();
            $t->string('subject_type', 40);
            $t->unsignedBigInteger('subject_id');
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('author_name', 160);
            $t->string('author_role', 20);                    // employee | hr | approver
            $t->text('body');
            $t->timestamps();
            $t->index(['subject_type', 'subject_id']);
        });

        Schema::create('company_policies', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->index();
            $t->unsignedBigInteger('template_id')->nullable();
            $t->unsignedBigInteger('letterhead_id')->nullable();
            $t->string('reference', 40);
            $t->string('title', 160);
            $t->string('category', 60)->nullable();
            $t->string('legal_status', 20)->nullable();
            $t->string('status', 30)->default('draft')->index(); // draft | in_approval | changes | active | archived
            $t->unsignedInteger('current_version')->default(1);
            $t->unsignedInteger('active_version')->nullable();
            $t->unsignedInteger('round')->default(0);
            $t->boolean('requires_acknowledgement')->default(true);
            $t->date('review_date')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['company_id', 'reference']);
        });

        Schema::create('company_policy_versions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->index();
            $t->unsignedBigInteger('policy_id')->index();
            $t->unsignedInteger('version');
            $t->longText('body');
            $t->string('hash', 64);
            $t->string('change_reason', 255)->nullable();
            $t->string('status', 20)->default('draft');       // draft | approved | published | superseded
            $t->boolean('requires_acknowledgement')->default(true);
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->unique(['policy_id', 'version']);
        });

        Schema::create('policy_acknowledgements', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->index();
            $t->unsignedBigInteger('policy_id');
            $t->unsignedInteger('version');
            $t->unsignedBigInteger('employee_id');
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('document_hash', 64);
            $t->text('statement');
            $t->string('ip', 64)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->timestamp('acknowledged_at');
            $t->timestamps();
            $t->unique(['policy_id', 'version', 'employee_id'], 'policy_ack_unique');
        });

        Schema::create('document_categories', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->index();
            $t->unsignedBigInteger('parent_id')->nullable()->index();
            $t->string('name', 120);
            $t->boolean('is_default')->default(false);
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('company_documents', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->index();
            $t->unsignedBigInteger('category_id')->nullable()->index();
            $t->string('title', 200);
            $t->text('description')->nullable();
            $t->string('reference', 80)->nullable()->index();
            $t->date('issue_date')->nullable();
            $t->date('effective_date')->nullable();
            $t->date('expiry_date')->nullable()->index();
            $t->date('review_date')->nullable()->index();
            $t->string('department', 120)->nullable();
            $t->string('branch', 120)->nullable();
            $t->string('access_level', 20)->default('hr');    // company | hr | finance | management | department | employee
            $t->string('access_department', 120)->nullable();
            $t->boolean('share_with_employees')->default(false);
            $t->unsignedBigInteger('employee_id')->nullable()->index();
            $t->unsignedBigInteger('contract_id')->nullable();
            $t->unsignedBigInteger('policy_id')->nullable();
            $t->string('compliance_requirement', 160)->nullable();
            $t->json('tags')->nullable();
            $t->text('notes')->nullable();
            $t->unsignedInteger('current_version')->default(1);
            $t->string('status', 20)->default('active');      // active | archived
            $t->unsignedBigInteger('uploaded_by')->nullable();
            $t->string('uploaded_by_name', 160)->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('company_document_versions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->index();
            $t->unsignedBigInteger('document_id')->index();
            $t->unsignedInteger('version');
            $t->string('path');
            $t->string('original_name', 255);
            $t->string('mime', 120);
            $t->string('extension', 10);
            $t->unsignedBigInteger('size');
            $t->string('sha256', 64);
            $t->string('note', 255)->nullable();
            $t->unsignedBigInteger('uploaded_by')->nullable();
            $t->string('uploaded_by_name', 160)->nullable();
            $t->timestamp('created_at')->nullable();
            $t->unique(['document_id', 'version']);
        });

        Schema::create('compliance_reminder_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->index();
            $t->string('subject_type', 40);
            $t->unsignedBigInteger('subject_id');
            $t->string('kind', 30);                           // expiry | review | contract_end | policy_review
            $t->date('target_date');
            $t->smallInteger('days_before');
            $t->timestamp('sent_at');
            $t->unique(['subject_type', 'subject_id', 'kind', 'target_date', 'days_before'], 'compliance_reminder_unique');
        });

        Schema::create('compliance_mail_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->index();
            $t->string('subject_type', 40)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('event', 60);
            $t->string('recipient', 190);
            $t->string('status', 10);                         // sent | failed
            $t->text('error')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->index(['subject_type', 'subject_id']);
        });

        Schema::create('compliance_alert_dismissals', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->index();
            $t->unsignedBigInteger('user_id');
            $t->string('alert_key', 60);
            $t->unsignedInteger('count_at_dismissal');
            $t->timestamps();
            $t->unique(['user_id', 'alert_key']);
        });

        // Give the standard roles their Compliance Centre permissions (administrators already have '*').
        $grants = [
            'hr'      => ['compliance.view', 'compliance.contracts', 'compliance.policies', 'compliance.documents', 'compliance.documents.view'],
            'payroll' => ['compliance.documents.view'],
            'manager' => ['compliance.documents.view'],
            'viewer'  => ['compliance.view', 'compliance.documents.view'],
        ];
        if (Schema::hasTable('company_roles')) {
            foreach ($grants as $key => $perms) {
                foreach (DB::table('company_roles')->where('key', $key)->where('is_system', true)->get() as $role) {
                    $current = json_decode($role->permissions, true) ?: [];
                    if (in_array('*', $current, true)) {
                        continue;
                    }
                    DB::table('company_roles')->where('id', $role->id)->update([
                        'permissions' => json_encode(array_values(array_unique(array_merge($current, $perms)))),
                        'updated_at'  => now(),
                    ]);
                }
            }
        }

        \App\Services\Compliance\TemplateLibrary::sync();
    }

    public function down(): void
    {
        foreach (['compliance_alert_dismissals', 'compliance_mail_logs', 'compliance_reminder_logs', 'company_document_versions', 'company_documents',
            'document_categories', 'policy_acknowledgements', 'company_policy_versions', 'company_policies', 'compliance_comments', 'approval_requests',
            'approval_workflow_steps', 'approval_workflows', 'contract_signatures', 'employment_contract_versions', 'employment_contracts',
            'document_template_versions', 'document_templates', 'company_letterheads', 'compliance_settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
