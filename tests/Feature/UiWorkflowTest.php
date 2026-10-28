<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\SettingRoleController;
use App\Http\Requests\Approval\FilterApprovalRequest;
use App\Models\Cuti;
use App\Services\Approvals\ApprovalListService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class UiWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Schema::create('employees', function (Blueprint $table) {
            $table->string('nik')->primary();
            $table->string('nama_karyawan');
        });
        Schema::create('cuti_izin', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nik_karyawan');
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('permission_role');
        });
        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('nik_karyawan');
            $table->string('name');
            $table->string('email');
            $table->integer('role_id')->nullable();
            $table->timestamps();
        });
        Schema::create('role_user', function (Blueprint $table) {
            $table->string('user_id');
            $table->integer('role_id');
            $table->timestamps();
        });
        for ($i = 1; $i <= 101; $i++) {
            DB::table('employees')->insert(['nik' => 'EMP' . $i, 'nama_karyawan' => $i === 101 ? 'Target Terakhir' : 'Karyawan ' . $i]);
            DB::table('cuti_izin')->insert(['id' => $i, 'nik_karyawan' => 'EMP' . $i]);
            DB::table('users')->insert(['id' => 'user' . $i, 'nik_karyawan' => 'EMP' . $i, 'name' => 'Akun ' . $i, 'email' => 'user' . $i . '@example.test']);
        }
    }

    public function test_approval_search_reaches_records_beyond_first_page_and_preserves_scope(): void
    {
        $service = app(ApprovalListService::class);
        $this->assertSame(101, $service->paginate(Cuti::query(), [])->total());
        $this->assertSame(25, $service->paginate(Cuti::query(), [])->count());
        $result = $service->paginate(Cuti::query(), ['search' => 'Target Terakhir']);
        $this->assertSame(1, $result->total());
        $this->assertSame('EMP101', $result->first()->nik_karyawan);
        $this->assertSame(0, $service->paginate(Cuti::where('id', '<=', 100), ['search' => 'Target Terakhir'])->total());
        $this->assertTrue(Validator::make(['search' => str_repeat('x', 101)], (new FilterApprovalRequest())->rules())->fails());
    }

    public function test_role_list_is_bounded_and_searches_outside_first_page(): void
    {
        $controller = app(SettingRoleController::class);
        $page = $controller->index(Request::create('/admin/setting-role'))->getData()['users'];
        $this->assertSame(101, $page->total());
        $this->assertSame(25, $page->count());
        $filtered = $controller->index(Request::create('/admin/setting-role', 'GET', ['search' => 'Target Terakhir']))->getData()['users'];
        $this->assertSame(1, $filtered->total());
        $this->assertSame('EMP101', $filtered->first()->nik_karyawan);
    }

    public function test_empty_approval_feedback_is_rendered_outside_the_scrollable_table(): void
    {
        $items = app(ApprovalListService::class)->paginate(Cuti::where('id', 9999), []);
        $html = view('approval.partials.list-footer', compact('items'))->render();
        $this->assertStringContainsString('Tidak ada pengajuan yang sesuai dengan pencarian.', $html);
        $this->assertStringNotContainsString('<table', $html);
        $this->assertStringContainsString('0 pengajuan ditemukan.', $html);
    }

    public function test_permission_form_keeps_values_errors_and_accessible_labels(): void
    {
        $this->withSession(['_old_input' => ['tipe' => 'UNPAID', 'tanggal_mulai' => '2026-10-27', 'keterangan' => '<script>unsafe</script>']]);
        app('request')->setLaravelSession(app('session.store'));
        $errors = (new ViewErrorBag())->put('default', new MessageBag(['tanggal_berakhir' => 'Tanggal berakhir wajib diisi.']));
        $html = view('user.izin._fields', compact('errors'))->render();

        $this->assertStringContainsString('value="2026-10-27"', $html);
        $this->assertStringContainsString('&lt;script&gt;unsafe&lt;/script&gt;', $html);
        $this->assertStringContainsString('Tanggal berakhir wajib diisi.', $html);
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($document);
        foreach ($xpath->query('//input | //textarea') as $field) {
            $this->assertNotEmpty($field->getAttribute('id'));
            $this->assertSame(1, $xpath->query('//label[@for="' . $field->getAttribute('id') . '"]')->length);
        }
    }
}
