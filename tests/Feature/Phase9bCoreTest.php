<?php

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Rbac\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase9bCoreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::query()->where('is_super_admin', true)->firstOrFail();
        $this->actingAs($this->admin);

        session(['active_company_id' => 1]);
    }

    private function seedLogs(): void
    {
        AuditLogger::log('currency', 'create', null, 1, [], ['code' => 'USD', 'name' => 'US Dollar'], 1);
        AuditLogger::log('customer', 'update', null, 7, ['name' => 'Old'], ['name' => 'New'], 1);
        AuditLogger::log('journal', 'post', null, 3, [], ['journal_no' => 'GJ-2026-0001'], 1);
        AuditLogger::log('expense', 'delete', null, 9, ['amount' => 100], [], 1);
    }

    private function viewer(): User
    {
        $user = User::factory()->create(['company_id' => 1, 'status' => 'active']);
        $user->companyAccess()->create(['company_id' => 1, 'branch_id' => 1, 'is_default' => true]);
        $user->roles()->attach(Role::query()->where('slug', 'viewer')->value('id'), ['company_id' => 1]);

        return $user;
    }

    public function test_audit_page_renders_for_admin_with_filter_options(): void
    {
        $this->seedLogs();

        $this->get(route('audit.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Audit/Index')
                ->has('logs.data', 4)
                ->has('logs.links')
                ->has('filters')
                ->has('modules', 4)
                ->has('actions', 4));
    }

    public function test_module_filter_limits_results(): void
    {
        $this->seedLogs();

        $this->get(route('audit.index', ['module' => 'currency']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Audit/Index')
                ->where('logs.total', 1)
                ->where('logs.data.0.module', 'currency'));
    }

    public function test_action_filter_limits_results(): void
    {
        $this->seedLogs();

        $this->get(route('audit.index', ['action' => 'delete']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('logs.data', 1));
    }

    public function test_changed_fields_diff_is_exposed(): void
    {
        $this->seedLogs();

        $this->get(route('audit.index'))
            ->assertInertia(fn ($page) => $page->where('logs.data.2.diff.0.field', 'name')
                ->where('logs.data.2.diff.0.old', 'Old')
                ->where('logs.data.2.diff.0.new', 'New'));
    }

    public function test_viewer_can_read_but_plain_user_is_blocked(): void
    {
        $this->seedLogs();

        $this->actingAs($this->viewer())
            ->get(route('audit.index'))
            ->assertOk();

        $user = User::factory()->create(['company_id' => 1, 'status' => 'active']);

        $this->actingAs($user)
            ->get(route('audit.index'))
            ->assertForbidden();
    }
}