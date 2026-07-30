<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Invitation extends Model
{
    protected $fillable = [
        'company_id',
        'invited_by',
        'email',
        'token_hash',
        'role',
        'position',
        'status',
        'expires_at',
        'accepted_at',
    ];

    protected $casts = [
        'expires_at'  => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && ! $this->isExpired();
    }

    /**
     * Create a new invitation and return [Invitation $invitation, string $rawToken].
     * The raw token is only ever returned here (to be emailed) — never persisted.
     */
    public static function issue(Company $company, User $inviter, string $email, string $role, ?string $position): array
    {
        $rawToken = Str::random(64);

        $invitation = static::create([
            'company_id'  => $company->id,
            'invited_by'  => $inviter->id,
            'email'       => $email,
            'token_hash'  => hash('sha256', $rawToken),
            'role'        => $role,
            'position'    => $position,
            'status'      => 'pending',
            'expires_at'  => Carbon::now()->addDays(7),
        ]);

        return [$invitation, $rawToken];
    }

    public static function findValidByRawToken(string $rawToken): ?self
    {
        return static::where('token_hash', hash('sha256', $rawToken))
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();
    }
}
