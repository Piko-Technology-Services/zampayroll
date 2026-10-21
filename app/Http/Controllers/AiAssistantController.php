<?php

namespace App\Http\Controllers;

use App\Services\Ai\AiAgent;
use App\Services\Ai\AiTools;
use App\Services\Ai\AttachmentReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Endpoints behind the floating "AI mode" panel.
 */
class AiAssistantController extends Controller
{
    private const HISTORY_KEY = 'ai_history';

    public function message(Request $request, AttachmentReader $reader)
    {
        abort_unless(config('ai.enabled'), 404);

        $request->validate([
            'message'    => 'nullable|string|max:20000',
            'attachment' => 'nullable|file|max:5120|mimes:' . implode(',', AttachmentReader::ACCEPT),
        ]);

        $message = trim((string) $request->input('message'));
        $attachment = null;

        if ($request->hasFile('attachment')) {
            try {
                $attachment = $reader->read($request->file('attachment'));
                try {
                    $parsed = app(\App\Services\Import\SmartEmployeeParser::class)->fromFile($request->file('attachment'));
                    $attachment['employees'] = \App\Services\Import\SmartEmployeeParser::employees($parsed);
                } catch (\Throwable $e) {
                    report($e);
                }
            } catch (\Throwable $e) {
                report($e);

                return response()->json(['reply' => "I couldn't read that file. Try saving it as CSV or Excel (.xlsx).", 'cards' => [], 'mode' => 'error']);
            }
            if ($message === '') {
                $message = 'Add the employees in this file.';
            }
        }

        if ($message === '') {
            return response()->json(['reply' => 'Ask me anything about your people or payroll.', 'cards' => []]);
        }

        $history = $request->session()->get(self::HISTORY_KEY, []);

        $result = (new AiAgent($request->user()))->respond($message, $history, $attachment);

        $history[] = ['role' => 'user', 'content' => $message . ($attachment ? "\n[attached {$attachment['name']}]" : '')];
        $history[] = ['role' => 'assistant', 'content' => $result['reply']];
        $request->session()->put(self::HISTORY_KEY, array_slice($history, -16));

        return response()->json($result);
    }

    public function confirm(Request $request, string $token)
    {
        $tools = new AiTools($request->user());
        $key = $tools->actionKey($token);
        $action = Cache::pull($key);

        if (! $action || (int) $action['company_id'] !== (int) $request->user()->company_id) {
            return response()->json(['ok' => false, 'message' => 'This action has expired. Ask me again and I\'ll prepare it fresh.'], 410);
        }

        try {
            $result = $tools->execute($action);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'message' => 'Something went wrong: ' . $e->getMessage()], 422);
        }

        $history = $request->session()->get(self::HISTORY_KEY, []);
        $history[] = ['role' => 'assistant', 'content' => '[Confirmed: ' . $action['title'] . '] ' . $result['message']];
        $request->session()->put(self::HISTORY_KEY, array_slice($history, -16));

        return response()->json(['ok' => true] + $result);
    }

    public function cancel(Request $request, string $token)
    {
        Cache::forget((new AiTools($request->user()))->actionKey($token));

        return response()->json(['ok' => true]);
    }

    public function reset(Request $request)
    {
        $request->session()->forget(self::HISTORY_KEY);

        return response()->json(['ok' => true]);
    }
}
