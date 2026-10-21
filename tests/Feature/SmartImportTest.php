<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Services\Import\SmartEmployeeParser;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * "Paste anything" employee import: messy text and spreadsheets of any layout.
 */
class SmartImportTest extends TestCase
{
    private function admin(): User
    {
        Tenancy::reset();
        DB::setDefaultConnection('mysql');

        return User::where('email', 'admin@pikozm.com')->first();
    }

    /** Rows as field => value for easy asserts. */
    private function people(array $result): array
    {
        return SmartEmployeeParser::employees($result);
    }

    public function test_messy_text_paragraphs_are_understood(): void
    {
        $this->admin();
        $text = "Hi HR, here are the new guys:\n\n"
            . "Chanda Mwila, NRC 123456/10/1, cell 0977 123 456, accountant in Finance, net K8,500, Zanaco acc 0123456789012, started 1 Feb 2024\n\n"
            . "Bwalya Phiri - driver, 0966-555-111, born 12/03/1990, basic 4500, bwalya.phiri@gmail.com, NRC 223344/11/1\n\n"
            . "MRS NATASHA ZULU\nSales Lead\nPhone: +260 955 123 456\nEmail: natasha@acme.co.zm\nSalary: ZMW 12,000\nBank: Stanbic, A/C 9130001234567\nTPIN: 1001234567\n\n"
            . "Regards,\nBupe";

        $people = $this->people(app(SmartEmployeeParser::class)->fromText($text));
        $this->assertCount(3, $people);

        [$chanda, $bwalya, $natasha] = $people;
        $this->assertSame(['Chanda', 'Mwila'], [$chanda['first_name'], $chanda['last_name']]);
        $this->assertSame('123456/10/1', $chanda['nrc_no']);
        $this->assertSame('0977123456', $chanda['primary_phone']);
        $this->assertSame('Accountant', $chanda['position']);
        $this->assertSame('Finance', $chanda['department']);
        $this->assertSame('8500', $chanda['net_salary']);
        $this->assertSame('Zanaco', $chanda['bank_name']);
        $this->assertSame('0123456789012', $chanda['bank_account_no']);
        $this->assertSame('2024-02-01', $chanda['contract_start']);

        $this->assertSame('0966555111', $bwalya['primary_phone']);
        $this->assertSame('1990-03-12', $bwalya['date_of_birth']);
        $this->assertSame('4500', $bwalya['salary']);
        $this->assertSame('bwalya.phiri@gmail.com', $bwalya['company_email']);

        $this->assertSame(['Natasha', 'Zulu', 'Female'], [$natasha['first_name'], $natasha['last_name'], $natasha['gender']]);
        $this->assertSame('0955123456', $natasha['primary_phone']);
        $this->assertSame('Stanbic', $natasha['bank_name']);
        $this->assertSame('9130001234567', $natasha['bank_account_no']);
        $this->assertSame('1001234567', $natasha['tpin']);
        $this->assertSame('12000', $natasha['salary']);
    }

    public function test_numbered_lines_and_labelled_lines(): void
    {
        $this->admin();
        $parser = app(SmartEmployeeParser::class);

        $list = $this->people($parser->fromText("1. John Banda 0977111222 Cashier K3,500\n2. Mary Tembo 0966333444 Cleaner K2,000 female\n3. Peter Mulenga, 0955777888, Security Guard, 2800 kwacha"));
        $this->assertSame(['John', 'Mary', 'Peter'], array_column($list, 'first_name'));
        $this->assertSame(['3500', '2000', '2800'], array_column($list, 'salary'));
        $this->assertSame('Security Guard', $list[2]['position']);

        $labelled = $this->people($parser->fromText("Name: Joseph Lungu; NRC: 445566/61/1; Phone: 0977000111; Position: Store keeper; Net pay: 5600\nName: Grace Mbewe; NRC: 778899/10/1; Phone: 0977000222; Position: Receptionist; Net pay: 4800"));
        $this->assertCount(2, $labelled);
        $this->assertSame('445566/61/1', $labelled[0]['nrc_no']);
        $this->assertSame('4800', $labelled[1]['net_salary']);
    }

    public function test_excel_with_titles_several_sheets_and_no_headings(): void
    {
        $this->admin();
        $book = new Spreadsheet();
        $a = $book->getActiveSheet()->setTitle('Lusaka');
        $a->fromArray([
            ['ACME TRADING LTD'], ['Staff list as at September 2026'], [],
            ['S/N', 'Staff Names', 'Man No.', 'NRC #', 'Designation', 'Cell', 'D.O.E', 'Basic (ZMW)', 'Bankers', 'A/C Number'],
            [1, 'MWILA Chanda', 'A001', '123456/10/1', 'Accountant', 977123456, null, 8500, 'Zanaco', '0123456789012'],
            [2, 'Phiri Bwalya', 'A002', '223344/11/1', 'Driver', 966555111, null, 4500, 'Stanbic', '9130001234567'],
            ['', 'TOTAL', '', '', '', '', '', 13000, '', ''],
        ]);
        $a->setCellValue('G5', ExcelDate::PHPToExcel(new \DateTime('2024-02-01')));
        $a->getStyle('G5')->getNumberFormat()->setFormatCode('m/d/yyyy'); // US format in the file must not confuse us
        $b = $book->createSheet()->setTitle('Kitwe');
        $b->fromArray([
            ['Natasha Zulu', '334455/66/1', '0955123456', 'natasha@acme.co.zm', 'Sales Lead', '12,000', 'F'],
            ['Kondwani Tembo', '556677/10/1', '0977888999', 'kondwani@acme.co.zm', 'Supervisor', '9,500', 'M'],
        ]);
        $book->createSheet()->setTitle('Notes')->setCellValue('A1', 'Remember to update NAPSA');
        $path = tempnam(sys_get_temp_dir(), 'zp') . '.xlsx';
        (new Xlsx($book))->save($path);

        $result = app(SmartEmployeeParser::class)->fromFile(new UploadedFile($path, 'staff.xlsx', null, null, true));
        $people = $this->people($result);

        $this->assertCount(4, $people);
        $this->assertSame('Chanda Mwila', $people[0]['name']);              // surname-first caps fixed
        $this->assertSame('0977123456', $people[0]['primary_phone']);       // number lost its 0
        $this->assertSame('2024-02-01', $people[0]['contract_start']);      // real Excel date
        $this->assertSame('Lusaka', $people[0]['branch']);                  // sheet name = branch
        $this->assertSame('8500', $people[0]['salary']);
        $this->assertSame('Kitwe', $people[2]['branch']);
        $this->assertSame('334455/66/1', $people[2]['nrc_no']);             // headerless columns recognised
        $this->assertSame('natasha@acme.co.zm', $people[2]['company_email']);
        $this->assertSame('Sales Lead', $people[2]['position']);
        $this->assertSame('F', $people[2]['gender']);
        $this->assertStringContainsString('headings found on row 4', implode(' ', $result['notes']));
        @unlink($path);
    }

    public function test_wizard_end_to_end_with_messy_text(): void
    {
        $admin = $this->admin();
        $text = "Please add:\nLweendo Hamoonga, 0977 456 789, cashier, NRC 998877/10/1, net 3,200\nMutinta Siame, 0966 222 333, cleaner, NRC 887766/10/1, net 2,100";

        $parsed = $this->actingAs($admin)->postJson('http://localhost/employees/import/parse', ['paste' => $text])->assertOk()->json();
        $this->assertSame('text', $parsed['mode']);
        $this->assertCount(2, $parsed['rows']);
        $this->assertNotEmpty($parsed['notes']);

        $rows = SmartEmployeeParser::employees($parsed);
        $preview = $this->actingAs($admin)->postJson('http://localhost/employees/import/preview', ['rows' => $rows])->assertOk()->json();
        $this->assertSame(2, $preview['summary']['ready']);

        $done = $this->actingAs($admin)->postJson('http://localhost/employees/import/commit', ['rows' => $rows])->assertOk()->json();
        $this->assertSame(2, $done['created']);
        $this->assertSame(2, Employee::withoutGlobalScopes()->whereIn('nrc_no', ['998877/10/1', '887766/10/1'])->count());
        $emp = Employee::withoutGlobalScopes()->where('nrc_no', '998877/10/1')->first();
        $this->assertSame('Hamoonga', $emp->last_name);
        $this->assertEquals(3200, (float) $emp->net_salary);

        // Nothing recognisable → friendly error, not a crash.
        $this->actingAs($admin)->postJson('http://localhost/employees/import/parse', ['paste' => '1234 5678'])->assertStatus(422);
    }

    public function test_ai_basic_mode_understands_messy_paste(): void
    {
        $admin = $this->admin();
        config(['ai.enabled' => true, 'ai.provider' => 'basic']);
        $res = $this->actingAs($admin)->postJson('http://localhost/ai/message', [
            'message' => "Add these employees\nChisomo Banda 0977 654 321 driver net K4,000\nTiyamike Phiri 0966 765 432 clerk net K3,600",
        ])->assertOk()->json();
        $this->assertStringContainsString('prepared 2 employee', $res['reply']);
    }
}
