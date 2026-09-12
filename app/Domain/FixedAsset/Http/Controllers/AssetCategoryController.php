<?php

namespace App\Domain\FixedAsset\Http\Controllers;

use App\Domain\Accounting\Models\Account;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\FixedAsset\Http\Requests\AssetCategoryRequest;
use App\Domain\FixedAsset\Models\AssetCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $companyId = (int) session('active_company_id');

        $categories = AssetCategory::query()
            ->with([
                'assetAccount:id,code,name',
                'depreciationExpenseAccount:id,code,name',
                'accumulatedDepreciationAccount:id,code,name',
            ])
            ->where('company_id', $companyId)
            ->withTrashed()
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('FixedAssets/Categories', [
            'categories' => $categories,
            'postableAccounts' => Account::query()
                ->where('company_id', $companyId)
                ->where('is_postable', true)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
        ]);
    }

    public function store(AssetCategoryRequest $request): RedirectResponse
    {
        $companyId = (int) session('active_company_id');

        $category = AssetCategory::query()->create([
            'company_id' => $companyId,
            'name' => $request->input('name'),
            'asset_account_id' => $request->integer('asset_account_id'),
            'depreciation_expense_account_id' => $request->integer('depreciation_expense_account_id'),
            'accumulated_depreciation_account_id' => $request->integer('accumulated_depreciation_account_id'),
            'default_method' => $request->input('default_method'),
            'default_useful_life_months' => $request->integer('default_useful_life_months'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        AuditLogger::log('fixed_asset', 'create', null, $category->id, [], $category->fresh()->toArray(), $companyId);

        return redirect()->route('asset-categories.index')->with('success', 'Asset category created.');
    }

    public function update(AssetCategory $category, AssetCategoryRequest $request): RedirectResponse
    {
        abort_if((int) $category->company_id !== (int) session('active_company_id'), 404);

        $category->update([
            'name' => $request->input('name'),
            'asset_account_id' => $request->integer('asset_account_id'),
            'depreciation_expense_account_id' => $request->integer('depreciation_expense_account_id'),
            'accumulated_depreciation_account_id' => $request->integer('accumulated_depreciation_account_id'),
            'default_method' => $request->input('default_method'),
            'default_useful_life_months' => $request->integer('default_useful_life_months'),
            'is_active' => $request->boolean('is_active', true),
            'updated_by' => $request->user()->id,
        ]);

        AuditLogger::log('fixed_asset', 'update', null, $category->id, $category->getOriginal(), $category->fresh()->toArray(), session('active_company_id'));

        return redirect()->route('asset-categories.index')->with('success', 'Asset category updated.');
    }

    public function destroy(AssetCategory $category, Request $request): RedirectResponse
    {
        abort_if((int) $category->company_id !== (int) session('active_company_id'), 404);

        $inUse = $category->assets()->withTrashed()->exists();

        if ($inUse) {
            $category->update(['is_active' => false, 'updated_by' => $request->user()->id]);

            AuditLogger::log('fixed_asset', 'delete', null, $category->id, [], ['in_use' => true], session('active_company_id'));

            return back()->with('warning', 'Asset category is used by assets so it was deactivated instead.');
        }

        $category->delete();

        AuditLogger::log('fixed_asset', 'delete', null, $category->id, [], [], session('active_company_id'));

        return back()->with('success', 'Asset category deleted.');
    }
}