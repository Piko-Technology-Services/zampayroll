<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;

class AttendanceSettingsController extends Controller
{
    public function index(Request $request)
    {
        $company = $request->user()?->company;
        $workDays = $company?->work_days ?: [1, 2, 3, 4, 5];
        $holidays = Holiday::orderBy('date')->get();

        return view('dashboard.attendance.settings', compact('company', 'workDays', 'holidays'));
    }

    public function updateWorkDays(Request $request)
    {

        \Log::info('Attendance workdays debug', [
            'user_id'    => $request->user()?->id,
            'company_id' => $request->user()?->company_id,
            'company'    => $request->user()?->company,
        ]);

        $request->validate([
            'work_days'   => 'array',
            'work_days.*' => 'integer|between:1,7',
        ]);

        $user = $request->user();
        $company = $user?->company;

        abort_unless($company, 404);
        abort_unless($user->isCompanyAdmin(), 403);

        $company->update(['work_days' => $request->input('work_days', [])]);

        return back()->with('success', 'Working days updated.');
    }

    public function storeHoliday(Request $request)
    {
        $request->validate([
            'name'         => 'required|string|max:255',
            'date'         => 'required|date',
            'is_recurring' => 'nullable|boolean',
        ]);

        Holiday::create([
            'name'         => $request->name,
            'date'         => $request->date,
            'is_recurring' => $request->boolean('is_recurring'),
        ]);

        return back()->with('success', 'Holiday added.');
    }

    public function destroyHoliday(Holiday $holiday)
    {
        $holiday->delete();

        return back()->with('success', 'Holiday removed.');
    }
}