<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicketMessage extends Model
{
    use Concerns\BelongsToCompany, Concerns\UsesLandlordDatabase;

    protected $fillable = ['support_ticket_id', 'company_id', 'user_id', 'author_name', 'from_staff', 'body', 'attachments'];

    protected $casts = ['from_staff' => 'boolean', 'attachments' => 'array'];

    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    protected function inferCompanyIdFromParent(): ?int
    {
        return null;
    }
}
