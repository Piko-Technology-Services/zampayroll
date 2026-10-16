<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Knows every employee field ZamPayroll stores and the many ways other
 * payroll systems / spreadsheets label them. Used by the bulk importer and AI mode.
 */
class EmployeeColumnMapper
{
    /** field => [label, group, aliases[]] */
    public const FIELDS = [
        'name'                   => ['Full name (we split it)', 'Name', ['name', 'full name', 'employee name', 'names', 'employee', 'staff name', 'employee full name']],
        'first_name'             => ['First name', 'Name', ['first name', 'firstname', 'forename', 'given name', 'first', 'fname']],
        'middle_name'            => ['Middle name', 'Name', ['middle name', 'middlename', 'other names', 'other name', 'middle']],
        'last_name'              => ['Last name / surname', 'Name', ['last name', 'lastname', 'surname', 'family name', 'last', 'lname']],
        'employee_id'            => ['Employee number', 'Job', ['employee id', 'employee no', 'employee number', 'emp no', 'emp id', 'staff no', 'staff number', 'staff id', 'payroll no', 'payroll number', 'man no', 'code', 'ein']],
        'position'               => ['Job title', 'Job', ['position', 'job title', 'title', 'designation', 'role', 'occupation', 'job', 'grade']],
        'department'             => ['Department', 'Job', ['department', 'dept', 'division', 'section', 'unit', 'cost centre', 'cost center']],
        'branch'                 => ['Branch / site', 'Job', ['branch', 'location', 'site', 'station', 'office', 'town']],
        'supervisor'             => ['Supervisor', 'Job', ['supervisor', 'manager', 'reports to', 'line manager']],
        'employment_nature'      => ['Employment type', 'Job', ['employment type', 'contract type', 'nature', 'employment nature', 'type']],
        'contract_start'         => ['Start date', 'Job', ['start date', 'date joined', 'date engaged', 'engagement date', 'hire date', 'employment date', 'joined', 'date of employment', 'doe']],
        'contract_end'           => ['Contract end date', 'Job', ['end date', 'contract end', 'contract expiry', 'expiry date']],
        'probation_end'          => ['Probation end date', 'Job', ['probation end', 'probation end date', 'confirmation date']],
        'net_salary'             => ['Net pay (take-home)', 'Pay', ['net', 'net salary', 'net pay', 'take home', 'take-home', 'take home pay', 'net amount']],
        'salary'                 => ['Basic / gross salary', 'Pay', ['basic', 'basic pay', 'basic salary', 'gross', 'gross salary', 'gross pay', 'salary', 'monthly salary']],
        'leave_days_entitled'    => ['Leave days per year', 'Pay', ['leave days', 'leave entitlement', 'annual leave', 'leave entitled', 'annual leave days']],
        'leave_days_balance'     => ['Leave days left', 'Pay', ['leave balance', 'leave days balance', 'leave remaining', 'days left']],
        'bank_name'              => ['Bank', 'Bank', ['bank', 'bank name', 'banker']],
        'bank_branch'            => ['Bank branch', 'Bank', ['bank branch', 'branch name']],
        'bank_account_no'        => ['Account number', 'Bank', ['account', 'account no', 'account number', 'bank account', 'acc no', 'a/c no', 'bank account number', 'bank a/c no', 'bank a/c', 'a/c', 'acc number', 'account no.']],
        'nrc_no'                 => ['NRC number', 'Statutory', ['nrc', 'nrc no', 'nrc number', 'national id', 'id number', 'national registration card', 'id no']],
        'tpin'                   => ['TPIN', 'Statutory', ['tpin', 'tax pin', 'tax number', 'zra tpin', 'tin']],
        'ssn'                    => ['NAPSA number', 'Statutory', ['napsa', 'napsa no', 'napsa number', 'ssn', 'social security', 'social security number', 'pension number']],
        'nhima_no'               => ['NHIMA number', 'Statutory', ['nhima', 'nhima no', 'nhima number', 'nhis']],
        'passport_number'        => ['Passport number', 'Statutory', ['passport', 'passport no', 'passport number']],
        'date_of_birth'          => ['Date of birth', 'Personal', ['dob', 'date of birth', 'birth date', 'birthday', 'birthdate']],
        'gender'                 => ['Gender', 'Personal', ['gender', 'sex']],
        'nationality'            => ['Nationality', 'Personal', ['nationality', 'citizenship', 'country']],
        'primary_phone'          => ['Mobile number', 'Contact', ['phone', 'mobile', 'cell', 'phone number', 'contact', 'telephone', 'mobile number', 'cell number', 'phone no', 'contact number']],
        'secondary_phone'        => ['Other phone', 'Contact', ['other phone', 'alternative phone', 'phone 2', 'secondary phone', 'home phone']],
        'company_email'          => ['Work email', 'Contact', ['email', 'work email', 'company email', 'e-mail', 'email address', 'official email']],
        'personal_email'         => ['Personal email', 'Contact', ['personal email', 'private email', 'home email']],
        'emergency_name'         => ['Emergency contact', 'Contact', ['emergency contact', 'emergency name', 'emergency contact name']],
        'emergency_phone'        => ['Emergency phone', 'Contact', ['emergency phone', 'emergency number', 'emergency contact phone']],
        'next_of_kin_name'       => ['Next of kin', 'Contact', ['next of kin', 'nok', 'next of kin name', 'beneficiary']],
        'next_of_kin_phone'      => ['Next of kin phone', 'Contact', ['next of kin phone', 'nok phone', 'next of kin number']],
        'next_of_kin_address'    => ['Next of kin address', 'Contact', ['next of kin address', 'nok address', 'address']],
    ];

    public static function normalise(string $header): string
    {
        return Str::of($header)->lower()->replace(['_', '.', '#', ':', '(', ')', '*'], ' ')->squish()->value();
    }

    /**
     * Best field for a column heading, or null.
     */
    public static function guess(string $header): ?string
    {
        $h = self::normalise($header);
        if ($h === '') {
            return null;
        }

        foreach (self::FIELDS as $field => [, , $aliases]) {
            if (in_array($h, $aliases, true) || $h === str_replace('_', ' ', $field)) {
                return $field;
            }
        }

        // Loose match: heading contains a distinctive alias ("Employee NRC No." → nrc_no)
        foreach (['a/c' => 'bank_account_no', 'acc' => 'bank_account_no', 'nrc' => 'nrc_no', 'napsa' => 'ssn', 'nhima' => 'nhima_no', 'tpin' => 'tpin', 'surname' => 'last_name',
                  'first name' => 'first_name', 'net' => 'net_salary', 'basic' => 'salary', 'gross' => 'salary', 'account' => 'bank_account_no',
                  'bank' => 'bank_name', 'mobile' => 'primary_phone', 'phone' => 'primary_phone', 'email' => 'company_email',
                  'birth' => 'date_of_birth', 'dept' => 'department', 'department' => 'department', 'position' => 'position', 'title' => 'position'] as $needle => $field) {
            if (preg_match('/\b' . preg_quote($needle, '/') . '\b/', $h)) {
                return $field;
            }
        }

        return null;
    }

    /**
     * header => field for a whole header row, never mapping two columns to the same field.
     */
    public static function guessAll(array $headers): array
    {
        $used = [];
        $map = [];
        foreach ($headers as $h) {
            $f = self::guess((string) $h);
            $map[$h] = ($f && ! isset($used[$f])) ? $f : null;
            if ($map[$h]) {
                $used[$f] = true;
            }
        }

        return $map;
    }

    /**
     * Apply a header=>field mapping to raw rows.
     */
    public static function apply(array $rows, array $mapping): array
    {
        return array_map(function ($row) use ($mapping) {
            $out = [];
            foreach ($mapping as $header => $field) {
                if ($field && isset(self::FIELDS[$field]) && array_key_exists($header, $row) && trim((string) $row[$header]) !== '') {
                    $out[$field] = $out[$field] ?? trim((string) $row[$header]);
                }
            }

            return $out;
        }, $rows);
    }

    /** For dropdowns: grouped field options. */
    public static function options(): array
    {
        $groups = [];
        foreach (self::FIELDS as $field => [$label, $group]) {
            $groups[$group][$field] = $label;
        }

        return $groups;
    }
}
