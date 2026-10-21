<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * ZamPayroll AI mode.
 *
 *  - With ANTHROPIC_API_KEY: Claude reads the question, calls AiTools to look
 *    things up or propose actions, and answers in plain language.
 *  - Without a key ("basic mode"): a keyword engine handles the common
 *    questions and actions so the feature still works out of the box.
 *
 * Returns ['reply' => string, 'cards' => array, 'mode' => 'ai'|'basic'].
 */
class AiAgent
{
    private AiTools $tools;

    private array $cards = [];



    public function __construct(private User $user)
    {
        $this->tools = new AiTools($user);
    }

    public static function aiAvailable(): bool
    {
        // External AI is opt-in: only when AI_PROVIDER=anthropic AND a key is set.
        return config('ai.provider') === 'anthropic' && (bool) config('ai.api_key');
    }

    /**
     * @param array $history  [['role'=>'user'|'assistant','content'=>string], ...]
     * @param ?array $attachment  from AttachmentReader
     */
    public function respond(string $message, array $history = [], ?array $attachment = null): array
    {
        if (self::aiAvailable()) {
            try {
                return $this->respondWithClaude($message, $history, $attachment);
            } catch (\Throwable $e) {
                report($e);
                $basic = $this->respondBasic($message, $attachment);
                $basic['reply'] = "I couldn't reach the AI service just now, so here's what I can do in basic mode.\n\n" . $basic['reply'];

                return $basic;
            }
        }

        return $this->respondBasic($message, $attachment);
    }

    /* ================================================================== */
    /*  Claude tool-use loop                                                */
    /* ================================================================== */

    private function respondWithClaude(string $message, array $history, ?array $attachment): array
    {
        $content = $message;

        if ($attachment) {
            $text = Str::limit($attachment['text'], config('ai.attachment_char_limit', 60000), "\n…(truncated)");
            $content .= "\n\n<attached_file name=\"{$attachment['name']}\">\n{$text}\n</attached_file>";
        }

        $messages = array_merge(
            array_map(fn ($m) => ['role' => $m['role'], 'content' => $m['content']], array_slice($history, -12)),
            [['role' => 'user', 'content' => $content]]
        );

        for ($i = 0; $i < 8; $i++) {
            $response = Http::timeout(config('ai.timeout', 60))
                ->withHeaders([
                    'x-api-key' => config('ai.api_key'),
                    'anthropic-version' => '2023-06-01',
                ])
                ->post(config('ai.endpoint'), [
                    'model' => config('ai.model'),
                    'max_tokens' => config('ai.max_tokens', 2048),
                    'system' => $this->systemPrompt(),
                    'tools' => AiTools::definitions(),
                    'messages' => $messages,
                ])
                ->throw()
                ->json();

            $blocks = $response['content'] ?? [];
            $messages[] = ['role' => 'assistant', 'content' => $blocks];

            if (($response['stop_reason'] ?? null) !== 'tool_use') {
                $text = collect($blocks)->where('type', 'text')->pluck('text')->implode("\n");

                return ['reply' => trim($text) ?: 'Done.', 'cards' => $this->cards, 'mode' => 'ai'];
            }

            $results = [];
            foreach ($blocks as $block) {
                if (($block['type'] ?? null) !== 'tool_use') {
                    continue;
                }
                $out = $this->tools->run($block['name'], (array) ($block['input'] ?? []));
                if ($out['card']) {
                    $this->cards[] = $out['card'];
                }
                $results[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $block['id'],
                    'content' => json_encode($out['result'], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
                ];
            }

            $messages[] = ['role' => 'user', 'content' => $results];
        }

        return ['reply' => 'That took more steps than expected — could you narrow the request down?', 'cards' => $this->cards, 'mode' => 'ai'];
    }

    private function systemPrompt(): string
    {
        $company = Tenancy::company();
        $today = now('Africa/Lusaka');

        return <<<PROMPT
You are ZamPayroll AI, the assistant inside ZamPayroll — an HR & payroll system for Zambian businesses.
You are talking to {$this->user->name} ({$this->user->role}) at {$company?->name}. Today is {$today->format('l j F Y')} (Africa/Lusaka). This month is {$today->format('Y-m')}; last month is {$today->copy()->subMonthNoOverflow()->format('Y-m')}.

How to work:
- Always use tools for facts about this company; never invent numbers, names or ids. Data is already limited to this company.
- For anything that changes data (adding employees, approving/rejecting requests, preparing payroll, creating portals) call the matching propose_* tool. The user then sees a confirmation card. Never say an action is done — say it is ready and ask them to press Confirm.
- To act on a person's request, first look up the request id with list_requests (use employee_query), then propose.
- "Prepare this month's payroll" means propose_prepare_payroll for the current month.
- When the user pastes or attaches employee data, extract every person into propose_add_employees, mapping columns sensibly (NAPSA/SSN number -> ssn, NRC -> nrc_no, "basic"/"gross" -> salary, "net"/"take home" -> net_salary).
- Zambian context: money is Zambian Kwacha, written like K12,500.00. Statutory deductions are PAYE (ZRA), NAPSA pension and NHIMA health insurance.
- Be brief and friendly: one or two short sentences or a few bullets. Cards with tables and figures are shown automatically under your reply, so don't repeat every row — summarise.
- If something is ambiguous (e.g. two employees with the same name), ask a short clarifying question.
- You can't change tax rates, salaries of existing staff or delete anything — point the user to the right screen instead.
PROMPT;
    }

    /* ================================================================== */
    /*  Basic mode (no API key)                                             */
    /* ================================================================== */

    private function respondBasic(string $message, ?array $attachment): array
    {
        $m = Str::lower($message);
        $reply = null;

        $use = function (string $tool, array $input = []) {
            $out = $this->tools->run($tool, $input);
            if (str_starts_with($out['result']['error'] ?? '', 'Not allowed')) {
                throw new \DomainException('not_allowed');
            }
            if ($out['card']) {
                $this->cards[] = $out['card'];
            }

            return $out['result'];
        };

        try {
            // ---- employees from a file or pasted CSV
            if ($attachment || (Str::contains($m, ['add', 'import', 'onboard', 'create']) && Str::contains($m, ['employee', 'staff', 'worker']) && substr_count($message, "\n") >= 1)) {
                // Any layout: tables, several sheets, or messy text — see SmartEmployeeParser.
                $employees = $attachment['employees']
                    ?? \App\Services\Import\SmartEmployeeParser::employees(app(\App\Services\Import\SmartEmployeeParser::class)->fromText($message));
                if (! $employees && ! empty($attachment['rows'])) {
                    $employees = array_map(fn ($r) => $this->mapColumns($r), $attachment['rows']);
                }
                $res = $use('propose_add_employees', ['employees' => $employees]);
                $reply = isset($res['error'])
                    ? "I couldn't find anyone in that. Put each person on their own line (or attach a spreadsheet) starting with their name, e.g. “Chanda Mwila, 0977 123 456, accountant, net K8,500”. " . $res['error']
                    : "I've prepared {$res['new']} employee" . ($res['new'] === 1 ? '' : 's') . ' from your list. Check the preview and press Confirm.';
            }
            // ---- approve / reject leave or overtime for someone
            elseif (preg_match('/\b(approve|reject|decline)\b.*\b(leave|overtime|loan)\b(?:\s+(?:request\s+)?(?:for|of|from)\s+(.+))?/i', $message, $mm)) {
                $kind = Str::lower($mm[2]);
                $who = trim($mm[3] ?? '', " .?!");
                $list = $use('list_requests', ['kind' => $kind, 'status' => 'pending', 'employee_query' => $who ?: null]);
                $this->cards = [];
                $ids = collect($list['requests'] ?? [])->pluck('id')->all();
                if (! $ids) {
                    $reply = 'There are no pending ' . $kind . ' requests' . ($who ? " for {$who}" : '') . '.';
                } else {
                    $use('propose_decide_requests', ['kind' => $kind, 'request_ids' => $ids, 'decision' => Str::startsWith(Str::lower($mm[1]), 'appr') ? 'approve' : 'reject']);
                    $reply = 'Here ' . (count($ids) === 1 ? 'is the request' : 'are the ' . count($ids) . ' requests') . '. Press Confirm to go ahead.';
                }
            }
            // ---- prepare payroll
            elseif (Str::contains($m, ['payroll']) && Str::contains($m, ['prepare', 'run ', 'process', 'generate', 'calculate', 'do '])) {
                $period = Str::contains($m, 'last month') ? now()->subMonthNoOverflow()->format('Y-m') : now()->format('Y-m');
                $res = $use('propose_prepare_payroll', ['period' => $period]);
                $reply = isset($res['error']) ? $res['error'] : 'Ready to calculate payroll for ' . $this->tools->periodLabel($period) . '. Press Confirm to run it.';
            }
            // ---- create portals
            elseif (Str::contains($m, 'portal') && Str::contains($m, ['create', 'give', 'set up', 'setup'])) {
                $res = $use('propose_create_portals', ['all_eligible' => true]);
                $reply = isset($res['error']) ? 'Everyone with an email already has a portal, or nobody is eligible yet.' : 'These employees can get portal access. Press Confirm to email their login details.';
            }
            // ---- who is on leave
            elseif (Str::contains($m, ['on leave', 'away', 'off today', 'leave today'])) {
                $date = Str::contains($m, 'tomorrow') ? now()->addDay()->toDateString() : null;
                $res = $use('who_is_on_leave', ['date' => $date]);
                $n = count($res['on_leave']);
                $reply = $n ? "{$n} " . ($n === 1 ? 'person is' : 'people are') . ' on leave ' . ($date ? 'tomorrow' : 'today') . '.' : 'Nobody is on approved leave ' . ($date ? 'tomorrow' : 'today') . '. 🎉';
                if (count($res['pending_covering_date'])) {
                    $reply .= ' ' . count($res['pending_covering_date']) . ' pending request(s) also cover that day.';
                }
            }
            // ---- pending approvals
            elseif (Str::contains($m, ['pending', 'approval', 'waiting', 'to approve', 'requests'])) {
                $kind = Str::contains($m, 'overtime') ? 'overtime' : (Str::contains($m, 'loan') ? 'loan' : 'leave');
                $res = $use('list_requests', ['kind' => $kind, 'status' => 'pending']);
                $counts = $this->tools->pendingCounts();
                $reply = "Waiting for you: {$counts['leave']} leave, {$counts['overtime']} overtime and {$counts['loans']} loan request(s).";
            }
            // ---- payroll figures / statutory
            elseif (Str::contains($m, ['payroll', 'paye', 'napsa', 'nhima', 'salaries', 'wage', 'net pay', 'statutory', 'paid'])) {
                $period = Str::contains($m, ['last month', 'previous']) ? now()->subMonthNoOverflow()->format('Y-m') : 'latest';
                $res = $use('payroll_summary', ['period' => $period]);
                if (isset($res['message'])) {
                    $res = $use('payroll_summary', ['period' => 'latest']);
                }
                $reply = isset($res['net_pay'])
                    ? 'The ' . $this->tools->periodLabel($res['period']) . ' payroll (' . $res['status'] . ') paid ' . $this->tools->k($res['net_pay']) . " net to {$res['employees']} employee" . ($res['employees'] == 1 ? '' : 's') . '.'
                        . (isset($res['net_change_pct']) ? ' That is ' . ($res['net_change_pct'] >= 0 ? 'up ' : 'down ') . abs($res['net_change_pct']) . '% on ' . $this->tools->periodLabel($res['previous']['period']) . '.' : '')
                    : 'There are no payroll runs yet. Say "prepare this month\'s payroll" to start one.';
            }
            // ---- attendance
            elseif (Str::contains($m, ['attendance', 'absent', 'present', 'late'])) {
                $res = $use('attendance_on', []);
                $reply = $res['note'] ?? ("Attendance today: {$res['marked']} of {$res['active_employees']} marked" . (count($res['absent_or_sick']) ? ', ' . count($res['absent_or_sick']) . ' absent or sick.' : '.'));
            }
            // ---- loans
            elseif (Str::contains($m, ['loan', 'advance'])) {
                $res = $use('loans_overview');
                $reply = "{$res['active_loans']} active loans with " . $this->tools->k($res['outstanding_total']) . " outstanding; {$res['pending_requests']} request(s) waiting.";
            }
            // ---- headcount / find someone
            elseif (Str::contains($m, ['how many', 'headcount', 'staff', 'employees', 'workforce', 'team'])) {
                $res = $use('company_overview');
                $reply = "You have {$res['active_employees']} active employees" . ($res['total_employees'] > $res['active_employees'] ? " ({$res['total_employees']} on record)" : '') . " across {$res['departments']} department(s).";
            } elseif (preg_match('/\b(find|show|who is|search|look up)\b\s+(.+)/i', $message, $mm)) {
                $res = $use('search_employees', ['query' => trim($mm[2], " ?.")]);
                $reply = $res['count'] ? "Found {$res['count']}." : 'No employee matches that.';
            }
        } catch (\DomainException $e) {
            return ['reply' => 'Your role doesn\'t include that. Ask your administrator to update your role in Settings → Team & roles.', 'cards' => [], 'mode' => 'basic'];
        }

        $reply ??= "I can help with questions like **how many employees do we have**, **who is on leave today**, **how much was last month's payroll**, and actions like **approve leave for Mary**, **prepare this month's payroll** or attaching an employee list to add people.";

        return ['reply' => $reply, 'cards' => $this->cards, 'mode' => 'basic'];
    }

    /**
     * Map common column headings from other payroll systems to ZamPayroll fields.
     */
    public function mapColumns(array $row): array
    {
        // Same column matching as the bulk importer.
        $map = \App\Services\EmployeeColumnMapper::guessAll(array_keys($row));

        return \App\Services\EmployeeColumnMapper::apply([$row], $map)[0];
    }
}
