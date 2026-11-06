<?php

namespace App\Services\Compliance;

use App\Models\Compliance\DocumentTemplate;
use Illuminate\Support\Facades\DB;

/**
 * The ZamPayroll master templates (company_id = NULL): six employment contracts and the policy library.
 *
 * Updating a master: change its text here and raise its VERSION, then run `php artisan compliance:sync-templates`
 * (migrations call it too). Company copies are never touched; they show "a newer master is available".
 *
 * Legal status labels are only "required" where a provision was checked:
 *  - Employment Code Act No. 3 of 2019, s.95(1): harassment policy, performance management policy, grievance
 *    procedure, code of conduct, HIV and AIDS policy, health and wellness policy; s.95(3) bring them to every employee.
 *  - Occupational Health and Safety Act No. 16 of 2025, s.14: a written health and safety policy.
 * Everything here is marked "unreviewed" until a qualified Zambian employment lawyer has reviewed it.
 */
class TemplateLibrary
{
    public const CONTRACT_TYPES = [
        'permanent'    => 'Permanent employment contract',
        'fixed_term'   => 'Fixed-term employment contract',
        'probationary' => 'Probationary employment contract',
        'internship'   => 'Internship agreement',
        'casual'       => 'Casual employment contract',
        'temporary'    => 'Temporary employment contract',
    ];

    public const LEGAL_STATUS = [
        'required'    => 'Required by law',
        'conditional' => 'Required in some cases',
        'recommended' => 'Recommended practice',
    ];

    /** Every placeholder the generator knows, with a friendly label. */
    public const PLACEHOLDERS = [
        'company_name' => 'Company name', 'company_address' => 'Company address', 'company_phone' => 'Company phone',
        'company_email' => 'Company email', 'company_website' => 'Company website', 'company_tpin' => 'Company TPIN',
        'company_registration_number' => 'PACRA registration number', 'company_napsa_number' => 'NAPSA employer number',
        'employee_full_name' => 'Employee full name', 'employee_number' => 'Employee number', 'employee_nrc' => 'NRC number',
        'employee_passport' => 'Passport number', 'employee_nationality' => 'Nationality', 'employee_date_of_birth' => 'Date of birth',
        'employee_address' => 'Employee residential address', 'employee_phone' => 'Employee phone', 'employee_email' => 'Employee email',
        'employee_position' => 'Job title', 'employee_department' => 'Department', 'work_location' => 'Place of work',
        'reporting_manager' => 'Reports to', 'employment_type' => 'Type of employment', 'job_responsibilities' => 'Main duties',
        'start_date' => 'Start date', 'contract_end_date' => 'End date', 'contract_duration' => 'Contract length',
        'probation_months' => 'Probation (months)', 'probation_end_date' => 'Probation end date',
        'basic_salary' => 'Basic salary', 'hourly_rate' => 'Hourly or daily rate', 'pay_frequency' => 'Pay frequency', 'pay_day' => 'Pay day',
        'allowances' => 'Allowances and benefits', 'working_hours' => 'Working hours', 'leave_days_per_month' => 'Annual leave (days per month)',
        'notice_period' => 'Notice period', 'signatory_name' => 'Company signatory', 'signatory_title' => 'Signatory title',
        'today' => 'Date of issue', 'policy_owner' => 'Policy owner', 'effective_date' => 'Effective date',
    ];

    /** Bump a template's version here when its wording changes. */
    public static function definitions(): array
    {
        $out = [];
        foreach (self::CONTRACT_TYPES as $key => $name) {
            $out[] = [
                'kind' => 'contract', 'key' => $key, 'name' => $name, 'version' => 1, 'legal_status' => null,
                'description' => self::contractDescription($key),
                'legal_note' => 'Employment Code Act No. 3 of 2019: a contract of six months or more must be in writing (s.22); probation may not exceed three months, extendable once by up to three months (s.27); the employer must keep a written contract for five years after it ends (s.23(3)).',
                'body' => self::contract($key),
            ];
        }
        foreach (self::policies() as $key => $p) {
            $out[] = ['kind' => 'policy', 'key' => $key, 'name' => $p['name'], 'version' => 1, 'legal_status' => $p['status'],
                'description' => $p['purpose'], 'legal_note' => $p['note'], 'body' => self::policyBody($p)];
        }

        return $out;
    }

    /** Insert new masters and update masters whose version went up. Never touches company copies. */
    public static function sync(): int
    {
        $changed = 0;
        foreach (self::definitions() as $d) {
            $row = DB::table('document_templates')->whereNull('company_id')->where('kind', $d['kind'])->where('key', $d['key'])->first();
            $values = [
                'name' => $d['name'], 'description' => $d['description'], 'legal_status' => $d['legal_status'], 'legal_note' => $d['legal_note'],
                'body' => $d['body'], 'version' => $d['version'], 'is_active' => true, 'updated_at' => now(),
            ];
            if (! $row) {
                $id = DB::table('document_templates')->insertGetId($values + ['company_id' => null, 'kind' => $d['kind'], 'key' => $d['key'],
                    'review_status' => 'unreviewed', 'created_at' => now()]);
                DB::table('document_template_versions')->insert(['template_id' => $id, 'version' => $d['version'], 'body' => $d['body'],
                    'change_note' => 'ZamPayroll master template', 'created_at' => now()]);
                $changed++;
            } elseif ((int) $row->version < $d['version']) {
                DB::table('document_templates')->where('id', $row->id)->update($values);
                DB::table('document_template_versions')->insertOrIgnore(['template_id' => $row->id, 'version' => $d['version'], 'body' => $d['body'],
                    'change_note' => 'Master template updated', 'created_at' => now()]);
                $changed++;
            }
        }

        return $changed;
    }

    /* ------------------------------------------------------------------ */
    /*  Contracts                                                           */
    /* ------------------------------------------------------------------ */

    private static function contractDescription(string $key): string
    {
        return [
            'permanent'    => 'Open-ended employment with no fixed end date. Includes probation, statutory leave and notice.',
            'fixed_term'   => 'Employment for a set period that ends on a stated date, with the option to renew in writing.',
            'probationary' => 'A probation period of up to three months before confirmation, as the Employment Code Act allows.',
            'internship'   => 'A structured learning placement with a supervisor, a stipend and a fixed end date.',
            'casual'       => 'Work as and when needed, paid by the hour or day for the work actually done.',
            'temporary'    => 'Short-term cover for a specific need, such as a busy season or a colleague on leave.',
        ][$key];
    }

    private static function contract(string $key): string
    {
        $title = strtoupper(self::CONTRACT_TYPES[$key]);
        $h = "<h1>{$title}</h1>";
        $h .= '<p>This contract is made on {{today}} between <strong>{{company_name}}</strong> of {{company_address}} (TPIN {{company_tpin}}) ("the Employer") and <strong>{{employee_full_name}}</strong>, NRC {{employee_nrc}}, of {{employee_address}} ("the Employee").</p>';

        $n = 0;
        $s = function (string $heading, string $html) use (&$n, &$h) { $n++; $h .= "<h2>{$n}. {$heading}</h2>{$html}"; };

        $s('Position and duties', '<p>The Employee is employed as <strong>{{employee_position}}</strong> in the {{employee_department}} department and reports to {{reporting_manager}}. The main duties are:</p><p>{{job_responsibilities}}</p><p>The Employer may reasonably assign other duties that match the Employee\'s skills and position.</p>');
        $s('Place of work', '<p>The Employee will normally work at {{work_location}}. The Employer may ask the Employee to work elsewhere for business reasons after reasonable notice.</p>');

        $term = [
            'permanent'    => '<p>Employment starts on {{start_date}} and continues until it is ended under clause on termination below.</p>',
            'fixed_term'   => '<p>Employment starts on {{start_date}} and ends on {{contract_end_date}} ({{contract_duration}}) unless renewed in writing or ended earlier under this contract. Any renewal will be agreed in writing before the end date.</p>',
            'probationary' => '<p>Employment starts on {{start_date}}. The first {{probation_months}} months are a probation period ending on {{probation_end_date}}. Under the Employment Code Act, probation may not exceed three months and may be extended once by up to three further months; the Employee will be told in writing of any extension and the reasons for it. On successful completion the Employer will confirm the appointment in writing.</p>',
            'internship'   => '<p>The internship starts on {{start_date}} and ends on {{contract_end_date}} ({{contract_duration}}). Its purpose is practical learning. {{reporting_manager}} will supervise the Intern, set learning objectives and give regular feedback. This agreement does not promise permanent employment.</p>',
            'casual'       => '<p>The Employee will be engaged as and when work is available, starting on {{start_date}}. Each engagement will be confirmed in advance, and the Employee is paid for the work actually done.</p>',
            'temporary'    => '<p>Employment starts on {{start_date}} and ends on {{contract_end_date}} ({{contract_duration}}), or earlier when the temporary need for which the Employee was engaged ends, with notice as set out below.</p>',
        ][$key];
        $s('Start date and duration', $term);

        if (in_array($key, ['permanent', 'fixed_term', 'temporary'], true)) {
            $s('Probation', '<p>The first {{probation_months}} months are a probation period. Under the Employment Code Act, probation may not exceed three months and may be extended once by up to three further months, with written reasons. During probation either party may end this contract with the notice required by law.</p>');
        }

        $pay = $key === 'casual'
            ? '<p>The Employee will be paid {{hourly_rate}} for each hour or day worked, {{pay_frequency}}. Wages are paid not later than the fifth day after they fall due (Employment Code Act, s.66(2)).</p>'
            : ($key === 'internship'
                ? '<p>The Intern will receive a stipend of {{basic_salary}}, paid {{pay_frequency}} on or before day {{pay_day}}. Wages are paid not later than the fifth day after they fall due (Employment Code Act, s.66(2)).</p>'
                : '<p>The Employee will be paid a basic salary of <strong>{{basic_salary}}</strong>, paid {{pay_frequency}} on or before day {{pay_day}} of each month. Wages are paid not later than the fifth day after they fall due (Employment Code Act, s.66(2)).</p>');
        $s('Remuneration', $pay . '<p>The Employer will deduct only what the law allows (s.68): PAYE, NAPSA and NHIMA contributions, deductions required by a written law, and other deductions the Employee has agreed to in writing. Each pay period the Employee will receive a payslip showing every deduction.</p>');
        $s('Allowances and benefits', '<p>{{allowances}}</p>');
        $s('Working hours and overtime', '<p>Normal working hours are {{working_hours}}. A normal day is eight hours of actual work and, without overtime pay, total hours may not exceed 48 in a week (s.74). Hours over 48 in a week are paid at one and a half times the hourly rate, and work on a public holiday or the weekly rest day at double the hourly rate (s.75), unless the Employee\'s position is excluded from overtime by law. The Employee is entitled to a rest period of at least 24 consecutive hours in every seven days (s.76).</p>');

        if ($key !== 'casual') {
            $s('Leave', '<p>The Employee is entitled to the leave set out in the Employment Code Act No. 3 of 2019 and the Employer\'s leave policy, including:</p><ul><li>Annual leave on full pay of at least {{leave_days_per_month}} days for each month of service, once the Employee has 12 months\' continuous service (s.36).</li><li>Sick leave on the terms set out in the Act (s.38).</li><li>Compassionate leave of at least 12 days a year (s.39) and family responsibility leave (s.40).</li><li>Maternity leave of 14 weeks (s.41) or paternity leave of at least five working days (s.46), where eligible.</li></ul><p>Nothing in this contract reduces any leave the law gives the Employee.</p>');
        } else {
            $s('Leave', '<p>The Employee is entitled to the leave the Employment Code Act gives casual employees for the time actually worked. Nothing in this contract reduces any right the law gives the Employee.</p>');
        }

        $s('Statutory contributions', '<p>The Employer will register the Employee with NAPSA and NHIMA and pay the employer and employee contributions required by law.</p>');
        $s('Confidentiality', '<p>During and after employment the Employee will not disclose or misuse the Employer\'s confidential information, including customer, financial and employee information, except as the job requires or the law demands. All documents and property of the Employer are returned when employment ends.</p>');
        $s('Conduct and discipline', '<p>The Employee will follow the Employer\'s code of conduct and policies, which have been made available to the Employee. Disciplinary matters are handled under the Employer\'s disciplinary procedure, and the Employee will be given an opportunity to be heard before any termination for conduct or performance (s.52(3)). Grievances are raised under the Employer\'s grievance procedure.</p>');

        $end = $key === 'casual'
            ? '<p>Either party may end the engagement by giving the notice required by the Employment Code Act for the length of the engagement.</p>'
            : '<p>Either party may end this contract by giving {{notice_period}} written notice, or pay in place of notice. Where the law requires a longer period, the longer period applies (s.53). The Employer may end the contract summarily only for serious misconduct as the law allows. On termination the Employee will receive a certificate of service (s.59) and all amounts due.</p>';
        $s('Termination', $end);
        $s('Records and personal data', '<p>The Employer keeps the Employee\'s personal information securely and uses it only for employment, payroll and legal purposes. The Employer will keep this contract for at least five years after it ends, as the Employment Code Act requires (s.23(3)).</p>');
        $s('General', '<p>This contract is governed by the laws of Zambia. Where any term is less favourable to the Employee than the law, the law applies. Changes to this contract are valid only if made in writing and accepted by both parties.</p>');
        $s('Declaration', '<p>The Employee confirms that the personal information they have given is true, that they have read and understood this contract, and that they had the opportunity to ask questions about it before signing.</p>');

        return $h;
    }

    /* ------------------------------------------------------------------ */
    /*  Policies                                                            */
    /* ------------------------------------------------------------------ */

    private static function policyBody(array $p): string
    {
        $h = '<h1>' . strtoupper($p['name']) . '</h1>';
        $h .= '<p><strong>Company:</strong> {{company_name}}<br><strong>Effective date:</strong> {{effective_date}}<br><strong>Policy owner:</strong> {{policy_owner}}</p>';
        $n = 0;
        foreach ($p['sections'] as $heading => $html) {
            $n++;
            $h .= "<h2>{$n}. {$heading}</h2>{$html}";
        }
        $n++;
        $h .= "<h2>{$n}. Review</h2><p>{{company_name}} will review this policy at least once a year, or sooner if the law or the business changes. Questions about this policy can be raised with {{policy_owner}}.</p>";

        return $h;
    }

    private static function policies(): array
    {
        $s95 = 'Employment Code Act No. 3 of 2019, s.95(1) requires every employer to have this in place, and s.95(3) requires the employer to bring it to the attention of each employee.';

        return [
            'code_of_conduct' => ['name' => 'Employee Code of Conduct', 'status' => 'required', 'note' => $s95,
                'purpose' => 'Sets the standards of behaviour expected of everyone at work. Required for every employer.',
                'sections' => [
                    'Purpose' => '<p>This code sets out how everyone at {{company_name}} is expected to behave so that the workplace is safe, fair and professional.</p>',
                    'Who it applies to' => '<p>All employees, including those on probation, fixed-term, casual and temporary contracts, and interns.</p>',
                    'Standards we expect' => '<ul><li>Treat colleagues, customers and the public with respect.</li><li>Be honest and act with integrity; never accept bribes or improper gifts.</li><li>Arrive on time and be ready to work.</li><li>Protect company property, money and information.</li><li>Follow health and safety rules.</li><li>Declare any conflict of interest.</li><li>Do not discriminate against or harass anyone.</li></ul>',
                    'Breaches' => '<p>Breaches are handled under the disciplinary policy, which follows a fair procedure and gives the employee an opportunity to be heard.</p>',
                ]],
            'grievance' => ['name' => 'Grievance Handling Policy', 'status' => 'required', 'note' => $s95,
                'purpose' => 'How employees raise work-related concerns and how they are resolved. A grievance procedure is required for every employer.',
                'sections' => [
                    'Purpose' => '<p>Every employee has the right to raise a concern about their work without fear of victimisation.</p>',
                    'Informal resolution' => '<p>Where possible, raise the concern with your supervisor first. Many issues can be settled quickly by talking.</p>',
                    'Formal grievance' => '<ol><li>Put the grievance in writing to your supervisor, or to HR if it concerns your supervisor.</li><li>A meeting will be held within 5 working days. You may bring a colleague or union representative.</li><li>You will receive a written decision within 5 working days of the meeting.</li><li>If you are not satisfied, you may appeal in writing to {{policy_owner}} within 5 working days. The appeal decision is final within the company.</li></ol>',
                    'Your rights' => '<p>Using this procedure does not remove your right to go to a labour officer or the courts under the law.</p>',
                ]],
            'sexual_harassment' => ['name' => 'Sexual Harassment Policy', 'status' => 'required',
                'note' => $s95 . ' The Act requires a harassment policy; "harassment" has the meaning in the Anti-Gender-Based Violence Act, 2011 (s.95(4)).',
                'purpose' => 'Prevents and deals with sexual and other harassment. A harassment policy is required for every employer.',
                'sections' => [
                    'Our commitment' => '<p>{{company_name}} does not tolerate sexual harassment or any other harassment at work, at work events, or through phones and social media.</p>',
                    'What harassment is' => '<p>Unwelcome conduct of a sexual nature, or other unwelcome conduct, that makes a person feel offended, humiliated or intimidated. Examples include unwanted touching, sexual comments or messages, and requests for sexual favours in return for work benefits.</p>',
                    'How to report' => '<p>Report to your supervisor, HR or {{policy_owner}}, in person or in writing. You may report on behalf of someone else. Reports are handled confidentially and promptly.</p>',
                    'Investigation and outcome' => '<p>Every report is investigated fairly, and both sides are heard. Where harassment is found, disciplinary action will follow, which may include dismissal. Victimising someone for reporting is itself misconduct.</p>',
                    'Support' => '<p>The company will take reasonable steps to protect and support the person affected, such as changing reporting lines during an investigation.</p>',
                ]],
            'performance_management' => ['name' => 'Employee Performance Management Policy', 'status' => 'required', 'note' => $s95,
                'purpose' => 'How goals are set, performance is reviewed and poor performance is supported. Required for every employer.',
                'sections' => [
                    'Purpose' => '<p>To help every employee understand what is expected, receive regular feedback and develop.</p>',
                    'Goals and reviews' => '<p>Each employee agrees goals with their supervisor at the start of the year and has at least one formal review each year, with regular check-ins in between.</p>',
                    'Supporting improvement' => '<p>Where performance falls short, the supervisor will explain the gap, agree an improvement plan with reasonable time and support, and record progress.</p>',
                    'Fairness' => '<p>Before any termination for performance, the employee will be given an opportunity to be heard (Employment Code Act, s.52(3)).</p>',
                ]],
            'hiv_aids' => ['name' => 'HIV and AIDS Policy', 'status' => 'required', 'note' => $s95,
                'purpose' => 'Protects employees living with HIV from discrimination and supports prevention. Required for every employer.',
                'sections' => [
                    'Non-discrimination' => '<p>No employee or job applicant will be treated unfairly because of their actual or perceived HIV status. HIV testing is never a condition of employment.</p>',
                    'Confidentiality' => '<p>Information about an employee\'s HIV status is confidential and is shared only with their written consent.</p>',
                    'Support and reasonable accommodation' => '<p>Employees living with HIV may continue working as long as they are able, with reasonable adjustments where needed, and are entitled to sick leave on the same terms as others.</p>',
                    'Prevention and awareness' => '<p>The company will support awareness, voluntary testing and prevention programmes.</p>',
                ]],
            'health_wellness' => ['name' => 'Health and Wellness Policy', 'status' => 'required', 'note' => $s95,
                'purpose' => 'Supports the physical and mental wellbeing of employees. Required for every employer.',
                'sections' => [
                    'Purpose' => '<p>{{company_name}} wants employees to be healthy and well at work.</p>',
                    'What we provide' => '<ul><li>Access to health information and wellness activities.</li><li>Support for employees dealing with stress or illness.</li><li>Reasonable flexibility for medical appointments.</li></ul>',
                    'Your part' => '<p>Look after your health, follow safety rules and tell your supervisor or HR if you need support.</p>',
                ]],
            'health_safety' => ['name' => 'Workplace Health and Safety Policy', 'status' => 'required',
                'note' => 'Occupational Health and Safety Act No. 16 of 2025, s.14 requires every employer to prepare a health and safety policy, display it prominently, have it signed by the chief executive or an equivalent officer, and share it with all employees. Employers with 10 or more employees must also set up a health and safety committee within 30 days (s.9) and carry out a risk assessment every two years (s.23).',
                'purpose' => 'How the company keeps people safe at work. Required for every employer under the 2025 OHS Act.',
                'sections' => [
                    'Policy statement' => '<p>{{company_name}} is committed to providing a safe and healthy workplace for employees, contractors and visitors.</p>',
                    'Organisation' => '<p>{{policy_owner}} is responsible for this policy. Supervisors make sure safe working practices are followed in their areas.</p>',
                    'Arrangements' => '<ul><li>Hazards are identified and risks are assessed and controlled.</li><li>Employees receive safety training and protective equipment where needed.</li><li>Accidents, incidents and ill-health are reported and investigated.</li><li>First aid and emergency procedures are in place and practised.</li></ul>',
                    'Employee duties' => '<p>Work safely, use protective equipment provided, and report hazards and incidents straight away. No one will be penalised for raising a safety concern in good faith.</p>',
                    'Display and review' => '<p>This policy is displayed in a prominent place, signed by the chief executive or an equivalent officer, and shared with all employees.</p>',
                ]],
            'disciplinary' => ['name' => 'Disciplinary Policy', 'status' => 'recommended',
                'note' => 'Strongly recommended. The Employment Code Act refers to the employer\'s disciplinary rules (s.49) and requires that an employee be given an opportunity to be heard before termination for conduct or performance (s.52(3)).',
                'purpose' => 'A fair, consistent process for handling misconduct.',
                'sections' => [
                    'Principles' => '<p>Disciplinary action is fair, consistent and in proportion to the offence. No action is taken until the matter has been investigated and the employee has been heard.</p>',
                    'Procedure' => '<ol><li>Investigation of the facts.</li><li>Written notice of the allegation and of a hearing, with reasonable time to prepare.</li><li>A hearing where the employee may respond and be accompanied.</li><li>A written outcome with reasons.</li><li>A right of appeal within 5 working days.</li></ol>',
                    'Sanctions' => '<p>Depending on seriousness: verbal warning, written warning, final written warning, or dismissal. Serious misconduct may lead to summary dismissal where the law allows.</p>',
                    'Suspension' => '<p>An employee may be suspended while a serious allegation is investigated, in line with the law.</p>',
                ]],
            'leave' => ['name' => 'Leave Management Policy', 'status' => 'recommended',
                'note' => 'Statutory leave applies whether or not you have a policy (Employment Code Act ss.36 to 46). A policy explains how to apply.',
                'purpose' => 'How employees apply for leave and how statutory leave is granted.',
                'sections' => [
                    'Entitlements' => '<p>Employees receive at least the leave set out in the Employment Code Act, including annual leave of at least two days per month of service (s.36), sick leave (s.38), compassionate leave of at least 12 days a year (s.39), family responsibility leave (s.40), maternity leave of 14 weeks (s.41) and paternity leave (s.46).</p>',
                    'Applying' => '<p>Apply through the ZamPayroll employee portal as early as possible. Your supervisor approves or declines with reasons.</p>',
                    'Records' => '<p>Leave balances are kept in ZamPayroll and are visible to each employee.</p>',
                ]],
            'attendance' => ['name' => 'Attendance and Punctuality Policy', 'status' => 'recommended', 'note' => 'Recommended good practice.',
                'purpose' => 'Expectations on working hours, lateness and absence reporting.',
                'sections' => [
                    'Working hours' => '<p>Employees work the hours in their contract and are expected to be on time.</p>',
                    'Reporting absence' => '<p>If you cannot come to work, tell your supervisor before your start time, or as soon as possible.</p>',
                    'Repeated lateness or absence' => '<p>Repeated unexplained lateness or absence is discussed first, and may be handled under the disciplinary policy.</p>',
                ]],
            'overtime' => ['name' => 'Overtime Policy', 'status' => 'recommended',
                'note' => 'Overtime rates are set by law (Employment Code Act s.75); a policy explains approval.',
                'purpose' => 'How overtime is approved, recorded and paid.',
                'sections' => [
                    'Approval' => '<p>Overtime must be approved in advance by a supervisor, except in an emergency.</p>',
                    'Pay' => '<p>Hours over 48 in a week are paid at one and a half times the hourly rate, and work on a public holiday or rest day at double the hourly rate (s.75), for eligible employees.</p>',
                    'Recording' => '<p>Overtime is recorded in ZamPayroll and paid in the next payroll.</p>',
                ]],
            'recruitment' => ['name' => 'Recruitment and Selection Policy', 'status' => 'recommended',
                'note' => 'Recommended. Employment policies must not discriminate (Employment Code Act s.95(2) and s.5).',
                'purpose' => 'A fair, merit-based way to hire.',
                'sections' => [
                    'Principles' => '<p>Appointments are made on merit, without discrimination.</p>',
                    'Process' => '<ol><li>Approved job description.</li><li>Advertisement.</li><li>Shortlisting against set criteria.</li><li>Interviews by at least two people.</li><li>Reference and qualification checks.</li><li>Written offer and contract.</li></ol>',
                    'Records' => '<p>Recruitment records are kept securely.</p>',
                ]],
            'equal_opportunities' => ['name' => 'Equal Opportunities Policy', 'status' => 'recommended',
                'note' => 'Recommended. The Employment Code Act prohibits discrimination (s.5) and requires equal pay for work of equal value (s.5(4)).',
                'purpose' => 'Commitment to fairness and non-discrimination.',
                'sections' => [
                    'Commitment' => '<p>{{company_name}} gives equal opportunities to all employees and applicants, regardless of sex, gender, race, tribe, religion, disability, HIV status, marital status or any other ground the law protects.</p>',
                    'Equal pay' => '<p>Employees are paid equal wages for work of equal value.</p>',
                    'Raising concerns' => '<p>Concerns can be raised under the grievance procedure.</p>',
                ]],
            'termination' => ['name' => 'Employee Termination Procedures', 'status' => 'recommended',
                'note' => 'Recommended. Reflects notice (s.53), the right to be heard (s.52(3)) and the certificate of service (s.59) in the Employment Code Act.',
                'purpose' => 'Steps to follow when employment ends.',
                'sections' => [
                    'Notice' => '<p>Notice is given in writing as set out in the contract or, where the contract is silent, as required by law.</p>',
                    'Fair process' => '<p>Before termination for conduct or performance, the employee is given an opportunity to be heard.</p>',
                    'Final steps' => '<ul><li>Final pay, accrued leave and other dues are calculated and paid.</li><li>Company property is returned.</li><li>A certificate of service is issued.</li><li>Records are kept as the law requires.</li></ul>',
                ]],
            'confidentiality' => ['name' => 'Confidentiality Policy', 'status' => 'recommended', 'note' => 'Recommended good practice.',
                'purpose' => 'Protects company, customer and employee information.',
                'sections' => [
                    'What is confidential' => '<p>Business plans, financial information, customer details, employee records and anything marked confidential.</p>',
                    'Your duties' => '<p>Use confidential information only for your work, keep it secure, and do not share it outside the company unless authorised or required by law.</p>',
                    'After you leave' => '<p>The duty of confidentiality continues after employment ends.</p>',
                ]],
            'substance_abuse' => ['name' => 'Workplace Substance Abuse Policy', 'status' => 'recommended',
                'note' => 'Recommended, especially where safety-critical work is done.',
                'purpose' => 'Keeps the workplace free of alcohol and drug misuse.',
                'sections' => [
                    'Rules' => '<p>No one may work under the influence of alcohol or illegal drugs, or bring them to work.</p>',
                    'Support' => '<p>Employees who seek help for a dependency will be supported and treated confidentially.</p>',
                    'Breaches' => '<p>Breaches are handled under the disciplinary policy.</p>',
                ]],
            'remote_work' => ['name' => 'Remote Work Policy', 'status' => 'recommended', 'note' => 'Recommended where employees work from home or remotely.',
                'purpose' => 'Rules for working away from the office, where the role allows it.',
                'sections' => [
                    'Eligibility' => '<p>Remote work is agreed in writing with your supervisor where the role allows it.</p>',
                    'Expectations' => '<p>Be reachable during working hours, keep company information secure and follow the same policies as in the office.</p>',
                    'Equipment and safety' => '<p>Use company equipment responsibly and keep your workspace safe.</p>',
                ]],
            'complaints_disputes' => ['name' => 'Workplace Complaints and Dispute Resolution Procedures', 'status' => 'recommended',
                'note' => 'Recommended. Complements the grievance procedure that s.95 of the Employment Code Act requires.',
                'purpose' => 'How workplace disputes between colleagues or teams are resolved.',
                'sections' => [
                    'Early resolution' => '<p>Disputes should be raised early and, where possible, resolved by talking.</p>',
                    'Mediation' => '<p>HR may arrange a meeting with a neutral person to help the parties agree.</p>',
                    'Formal steps' => '<p>Unresolved disputes are handled under the grievance procedure. Employees keep their right to approach a labour officer.</p>',
                ]],
        ];
    }
}
