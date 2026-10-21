<?php

namespace App\Http\Controllers;

use App\Mail\SupportTicketMail;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/** Help: a company's conversations with ZamPayroll support. */
class SupportController extends Controller
{
    public function index()
    {
        return view('support.index', [
            'tickets' => SupportTicket::query()->withCount('messages')->latest('updated_at')->get(),
            'categories' => SupportTicket::CATEGORIES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject'  => 'required|string|max:160',
            'category' => 'required|in:' . implode(',', array_keys(SupportTicket::CATEGORIES)),
            'priority' => 'nullable|in:low,normal,urgent',
            'body'     => 'required|string|max:5000',
            'attachments' => 'nullable|array|max:3',
            'attachments.*' => 'file|max:5120|mimes:jpg,jpeg,png,pdf,xlsx,xls,csv,docx',
        ]);

        $ticket = DB::connection(\App\Support\Tenancy::landlordConnection())->transaction(function () use ($data, $request) {
            $ticket = SupportTicket::create([
                'company_id' => $request->user()->company_id,
                'user_id'    => $request->user()->id,
                'number'     => 'T-' . strtoupper(substr(base_convert((string) (microtime(true) * 1000), 10, 36), -7)),
                'subject'    => $data['subject'],
                'category'   => $data['category'],
                'priority'   => $data['priority'] ?? 'normal',
                'status'     => 'open',
                'last_reply_at' => now(),
            ]);
            $this->addMessage($ticket, $request, $data['body']);

            return $ticket;
        });

        Audit::record('support.ticket_opened', $ticket, "Ticket {$ticket->number}: {$ticket->subject}");
        $this->notifySupport($ticket, 'new');

        return redirect()->route('support.show', $ticket)->with('success', "Ticket {$ticket->number} sent. We usually reply within one working day.");
    }

    public function show(SupportTicket $ticket)
    {
        return view('support.show', ['ticket' => $ticket->load('messages')]);
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $data = $request->validate(['body' => 'required|string|max:5000', 'attachments' => 'nullable|array|max:3',
            'attachments.*' => 'file|max:5120|mimes:jpg,jpeg,png,pdf,xlsx,xls,csv,docx']);

        $this->addMessage($ticket, $request, $data['body']);
        $ticket->update(['status' => 'open', 'last_reply_at' => now(), 'last_reply_by_staff' => false]);
        $this->notifySupport($ticket, 'reply');

        return back()->with('success', 'Reply sent.');
    }

    public function close(SupportTicket $ticket)
    {
        $ticket->update(['status' => 'closed']);
        Audit::record('support.ticket_closed', $ticket, "Ticket {$ticket->number} closed by the customer");

        return back()->with('success', 'Ticket closed. Thanks!');
    }

    private function addMessage(SupportTicket $ticket, Request $request, string $body): SupportTicketMessage
    {
        $files = collect($request->file('attachments', []))->map(fn ($f) => [
            'name' => $f->getClientOriginalName(),
            'path' => $f->store('support/' . $ticket->company_id, 'local'),
        ])->all();

        return SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'company_id'        => $ticket->company_id,
            'user_id'           => $request->user()->id,
            'author_name'       => $request->user()->name,
            'from_staff'        => false,
            'body'              => $body,
            'attachments'       => $files ?: null,
        ]);
    }

    private function notifySupport(SupportTicket $ticket, string $kind): void
    {
        try {
            Mail::to(config('billing.support_email'))->send(new SupportTicketMail($ticket->fresh('messages'), $kind));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
