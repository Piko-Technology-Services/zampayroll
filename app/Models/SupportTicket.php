<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    use Concerns\BelongsToCompany, Concerns\UsesLandlordDatabase;

    public const CATEGORIES = ['question' => 'Question', 'problem' => 'Something isn\'t working', 'billing' => 'Billing & payments', 'feature' => 'Feature request'];
    public const STATUSES = ['open' => 'Open', 'waiting' => 'Waiting for you', 'resolved' => 'Resolved', 'closed' => 'Closed'];

    protected $fillable = ['company_id', 'user_id', 'number', 'subject', 'category', 'priority', 'status', 'assigned_to', 'last_reply_at', 'last_reply_by_staff'];

    protected $casts = ['last_reply_at' => 'datetime', 'last_reply_by_staff' => 'boolean'];

    public function messages()
    {
        return $this->hasMany(SupportTicketMessage::class)->orderBy('id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'waiting'], true);
    }

    protected function inferCompanyIdFromParent(): ?int
    {
        return null;
    }
}
