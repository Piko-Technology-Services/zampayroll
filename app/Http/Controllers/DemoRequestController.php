<?php

namespace App\Http\Controllers;

use App\Mail\DemoRequestAdminNotification;
use App\Mail\DemoRequestConfirmation;
use App\Models\DemoRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DemoRequestController extends Controller
{
    /**
     * Show the landing page (with the demo request form on it).
     */
    public function index()
    {
        return view('landing');
    }

    /**
     * Handle a new demo request submission.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'full_name'    => ['required', 'string', 'max:120'],
            'email'        => ['required', 'email', 'max:180'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'company_name' => ['required', 'string', 'max:150'],
            'company_size' => ['nullable', 'string', 'max:50'],
            'industry'     => ['nullable', 'string', 'max:100'],
            'message'      => ['nullable', 'string', 'max:2000'],
            // Honeypot field — must stay empty. Bots tend to fill every input.
            'website'      => ['prohibited'],
        ], [
            'full_name.required'    => 'Please tell us your name.',
            'email.required'        => 'Please enter your email address.',
            'email.email'           => 'Please enter a valid email address.',
            'company_name.required' => 'Please tell us your company name.',
        ]);

        $demoRequest = DemoRequest::create([
            'full_name'    => $validated['full_name'],
            'email'        => $validated['email'],
            'phone'        => $validated['phone'] ?? null,
            'company_name' => $validated['company_name'],
            'company_size' => $validated['company_size'] ?? null,
            'industry'     => $validated['industry'] ?? null,
            'message'      => $validated['message'] ?? null,
        ]);

        // Notify the admin team that a new demo request has come in.
        try {
            Mail::to(config('mail.admin_address', 'hello@zampayroll.com'))
                ->cc(['admin@zampayroll.com', 'billing@zampayroll.com', 'info@zampayroll.com', 'margie@zampayroll.com'])
                ->bcc(['katongobupe444@gmail.com'])
                ->send(new DemoRequestAdminNotification($demoRequest));

            $demoRequest->forceFill(['admin_notified_at' => now()])->save();
        } catch (\Throwable $e) {
            Log::error('Failed to send demo request admin notification: ' . $e->getMessage());
        }

        // Confirm to the requester that we've received their request.
        try {
            Mail::to($demoRequest->email)
                ->send(new DemoRequestConfirmation($demoRequest));

            $demoRequest->forceFill(['user_notified_at' => now()])->save();
        } catch (\Throwable $e) {
            Log::error('Failed to send demo request confirmation: ' . $e->getMessage());
        }

        return redirect()
            ->route('home')
            ->with('demo_request_success', true);
    }
}
