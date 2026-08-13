<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'work_days',
        'tpin',
        'access_code',
        'access_code_active',
    ];

    protected $casts = [
        'access_code_active' => 'boolean',
        'work_days' => 'array',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function invitations()
    {
        return $this->hasMany(Invitation::class);
    }

    public function pendingInvitations()
    {
        return $this->invitations()->where('status', 'pending')->where('expires_at', '>', now());
    }

    /**
     * Generate a brand new, unique, active access code for this company.
     */
    public function generateAccessCode(): string
    {
        do {
            $code = strtoupper(Str::random(10));
        } while (static::where('access_code', $code)->exists());

        $this->access_code = $code;
        $this->access_code_active = true;
        $this->save();

        return $code;
    }

    public function deactivateAccessCode(): void
    {
        $this->access_code_active = false;
        $this->save();
    }
}
