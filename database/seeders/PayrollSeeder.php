<?php

namespace Database\Seeders;

use App\Domain\Company\Models\Company;
use App\Domain\Payroll\Models\Department;
use App\Domain\Payroll\Models\Designation;
use Illuminate\Database\Seeder;

/**
 * Seeds default payroll setup rows (departments + designations) for every
 * company. Idempotent: departments/designations are unique per (company, name)
 * and employees may create their own on top.
 */
class PayrollSeeder extends Seeder
{
    protected array $departments = [
        'Human Resources',
        'Finance & Accounts',
        'Sales & Marketing',
        'Operations',
        'Information Technology',
    ];

    protected array $designations = [
        'Managing Director',
        'Department Head',
        'Manager',
        'Senior Officer',
        'Officer',
    ];

    public function run(): void
    {
        foreach (Company::all() as $company) {
            foreach ($this->departments as $name) {
                Department::query()->firstOrCreate(
                    ['company_id' => $company->id, 'name' => $name],
                    ['is_active' => true]
                );
            }

            foreach ($this->designations as $name) {
                Designation::query()->firstOrCreate(
                    ['company_id' => $company->id, 'name' => $name],
                    ['is_active' => true]
                );
            }
        }
    }
}