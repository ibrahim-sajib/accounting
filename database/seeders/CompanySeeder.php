<?php

namespace Database\Seeders;

use App\Domain\Company\Models\Branch;
use App\Domain\Company\Models\Company;
use App\Domain\Rbac\Models\Role;
use App\Domain\Rbac\Models\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->firstOrCreate(
            ['name' => 'Demo Business Ltd'],
            [
                'legal_name' => 'Demo Business Limited',
                'country_code' => 'BD',
                'currency_code' => 'BDT',
                'tax_registration_no' => 'TAX-2026-00001',
                'accounting_basis' => 'accrual',
                'status' => 'active',
                'email' => 'info@demobusiness.local',
                'phone' => '+8801700000000',
                'address' => 'House 12, Road 5, Dhanmondi',
                'city' => 'Dhaka',
                'state' => 'Dhaka',
                'zip_code' => '1205',
            ]
        );

        $branch = Branch::query()->firstOrCreate(
            ['company_id' => $company->id, 'code' => 'HQ'],
            [
                'name' => 'Head Office',
                'address' => 'House 12, Road 5, Dhanmondi',
                'city' => 'Dhaka',
                'status' => 'active',
            ]
        );

        $superAdmin = User::query()->firstOrCreate(
            ['email' => 'admin@demobusiness.local'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'company_id' => $company->id,
                'is_super_admin' => true,
                'status' => 'active',
            ]
        );

        $superAdminRole = Role::query()->where('slug', 'super-admin')->whereNull('company_id')->first();

        if ($superAdminRole) {
            UserRole::query()->firstOrCreate(
                ['user_id' => $superAdmin->id, 'role_id' => $superAdminRole->id, 'company_id' => $company->id],
                ['branch_id' => $branch->id]
            );
        }
    }
}