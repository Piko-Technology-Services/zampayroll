<?php

namespace App\Http\Controllers;

use App\Mail\OvertimeDecided;
use App\Models\OvertimeRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class OvertimeDashboardController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');

        $requests = OvertimeRequest::with(['employee', 'reviewedBy'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->get();

        $counts = [
            'pending'  => OvertimeRequest::where('status', 'pending')->count(),
            'approved' => OvertimeRequest::where('status', 'approved')->count(),
            'rejected' => OvertimeRequest::where('status', 'rejected')->count(),
        ];

        return view('dashboard.overtime.dashboard', compact('requests', 'status', 'counts'));
    }

    public function approve(Request $request, OvertimeRequest $overtimeRequest)
    {
        $this->decide($request, $overtimeRequest, 'approved');

        return back()->with('success', 'Overtime request approved.');
    }

    public function reject(Request $request, OvertimeRequest $overtimeRequest)
    {
        $this->decide($request, $overtimeRequest, 'rejected');

        return back()->with('success', 'Overtime request rejected.');
    }

    private function decide(Request $request, OvertimeRequest $overtimeRequest, string $status): void
    {
        $request->validate(['comment' => 'nullable|string|max:500']);

        $overtimeRequest->update([
            'status'      => $status,
            'hr_comment'  => $request->input('comment'),
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        try {
            Mail::to($overtimeRequest->employee->personal_email ?? $overtimeRequest->employee->company_email)
                ->send(new OvertimeDecided($overtimeRequest));
        } catch (\Exception $e) {
            report($e);
        }
    }


    public function exportApprovedOvertime(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | VALIDATE EXPORT PERIOD
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'month' => [
                'required',
                'integer',
                'between:1,12',
            ],

            'year' => [
                'required',
                'integer',
                'min:2000',
                'max:2100',
            ],
        ], [
            'month.required' =>
                'Please select the month you want to export.',

            'month.between' =>
                'Please select a valid month.',

            'year.required' =>
                'Please select the year you want to export.',

            'year.min' =>
                'Please select a valid year.',

            'year.max' =>
                'Please select a valid year.',
        ]);

        try {

            /*
            |--------------------------------------------------------------------------
            | SELECTED PERIOD
            |--------------------------------------------------------------------------
            */

            $month = (int) $validated['month'];
            $year  = (int) $validated['year'];

            $startDate = Carbon::createFromDate(
                $year,
                $month,
                1
            )->startOfMonth();

            $endDate = $startDate->copy()->endOfMonth();


            /*
            |--------------------------------------------------------------------------
            | GET APPROVED OVERTIME FOR SELECTED MONTH
            |--------------------------------------------------------------------------
            */

            $overtime = OvertimeRequest::with('employee')
                ->where('status', 'approved')
                ->whereBetween('date', [
                    $startDate->toDateString(),
                    $endDate->toDateString(),
                ])
                ->orderBy('date')
                ->orderBy('employee_id')
                ->get();


            /*
            |--------------------------------------------------------------------------
            | FILE NAME
            |--------------------------------------------------------------------------
            */

            $periodName = $startDate->format('F_Y');

            $filename = "overtime_{$periodName}.csv";


            /*
            |--------------------------------------------------------------------------
            | CREATE CSV
            |--------------------------------------------------------------------------
            */

            $callback = function () use ($overtime) {

                $file = fopen('php://output', 'w');


                /*
                |--------------------------------------------------------------------------
                | CSV HEADER
                |--------------------------------------------------------------------------
                */

                fputcsv($file, [
                    'employee_number',
                    'employee_name',
                    'amount',
                ]);


                /*
                |--------------------------------------------------------------------------
                | CSV DATA
                |--------------------------------------------------------------------------
                */

                foreach ($overtime as $record) {

                    fputcsv($file, [

                        // Employee ID
                        $record->employee?->employee_id
                            ?? $record->employee_id,

                        // Full name
                        trim(($record->employee?->first_name ?? '') . ' ' . ($record->employee?->last_name ?? '')),

                        // Approved overtime amount
                        abs($record->amount ?? 0),
                    ]);
                }

                fclose($file);
            };


            /*
            |--------------------------------------------------------------------------
            | DOWNLOAD
            |--------------------------------------------------------------------------
            */

            return response()->streamDownload(
                $callback,
                $filename,
                [
                    'Content-Type' => 'text/csv',
                ]
            );

        } catch (\Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | LOG TECHNICAL ERROR
            |--------------------------------------------------------------------------
            */

            \Log::error('Overtime export failed', [
                'user_id' => auth()->id(),
                'month' => $validated['month'] ?? null,
                'year' => $validated['year'] ?? null,
                'error' => $e->getMessage(),
            ]);


            /*
            |--------------------------------------------------------------------------
            | SAFE USER RESPONSE
            |--------------------------------------------------------------------------
            */

            return back()
                ->withInput()
                ->with(
                    'error',
                    'We could not export the overtime records. Please try again.'
                );
        }
    }


}
