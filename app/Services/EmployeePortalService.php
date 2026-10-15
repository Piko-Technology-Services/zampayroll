<?php

namespace App\Services;

use App\Mail\EmployeePortalWelcomeMail;
use App\Models\Employee;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Creates self-service accounts for employees and emails them their
 * credentials + a quick "how to use the portal" guide.
 */
class EmployeePortalService
{
    /**
     * Problems that stop an employee from getting portal access (null = ready).
     */
    public function eligibilityProblem(Employee $employee): ?string
    {
        if ($employee->user()->exists()) {
            return 'already has portal access';
        }

        $email = $employee->portal_email;

        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'has no valid company or personal email on file';
        }

        if (strcasecmp((string) $employee->employment_status, 'Active') !== 0) {
            return 'is not an active employee';
        }

        if (Tenancy::emailTaken($email)) {
            return "uses an email ({$email}) that already belongs to another account";
        }

        return null;
    }

    /**
     * @return array{0: User, 1: string} the account and its temporary password
     */
    public function grant(Employee $employee, User $grantedBy): array
    {
        if ($problem = $this->eligibilityProblem($employee)) {
            throw new RuntimeException("{$employee->full_name} {$problem}.");
        }

        $password = $this->temporaryPassword();

        $user = DB::transaction(fn () => User::create([
            'company_id'           => $employee->company_id,
            'employee_id'          => $employee->id,
            'name'                 => trim($employee->first_name . ' ' . $employee->last_name),
            'email'                => Str::lower($employee->portal_email),
            'phone'                => $employee->primary_phone,
            'position'             => $employee->position,
            'password'             => Hash::make($password),
            'role'                 => User::ROLE_EMPLOYEE,
            'must_change_password' => true,
            // Credentials go to this mailbox; signing in with them proves ownership.
            'email_verified_at'    => now(),
            'portal_invited_at'    => now(),
        ]));

        $this->sendWelcome($user, $password, $grantedBy);

        return [$user, $password];
    }

    /**
     * Issue a fresh temporary password and re-send the welcome email.
     */
    public function resend(Employee $employee, User $grantedBy): User
    {
        $user = $employee->user;

        if (! $user) {
            throw new RuntimeException("{$employee->full_name} doesn't have portal access yet.");
        }

        $password = $this->temporaryPassword();

        $user->forceFill([
            'password'             => Hash::make($password),
            'must_change_password' => true,
            'portal_invited_at'    => now(),
        ])->save();

        $this->sendWelcome($user, $password, $grantedBy);

        return $user;
    }

    public function revoke(Employee $employee): void
    {
        $employee->user?->delete();
    }

    private function sendWelcome(User $user, string $password, User $grantedBy): void
    {
        try {
            Mail::to($user->email)->send(new EmployeePortalWelcomeMail(
                $user,
                $password,
                Tenancy::company()?->name ?? 'your company',
                $grantedBy->name,
                route('login', ['email' => $user->email]),
            ));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Friendly but strong: e.g. "Mango-4827-Kafue"
     */
    private function temporaryPassword(): string
    {
        $words = ['Mango', 'Kafue', 'Zambezi', 'Copper', 'Baobab', 'Lusaka', 'Eagle', 'Mosi', 'Kalahari', 'Lion', 'Sunrise', 'Luangwa'];

        return $words[array_rand($words)] . '-' . random_int(1000, 9999) . '-' . $words[array_rand($words)] . Str::upper(Str::random(1));
    }
}
