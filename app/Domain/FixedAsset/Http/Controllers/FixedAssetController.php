<?php

namespace App\Domain\FixedAsset\Http\Controllers;

use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CashBank\Models\BankAccount;
use App\Domain\CashBank\Models\CashAccount;
use App\Domain\FixedAsset\Exceptions\FixedAssetPostingException;
use App\Domain\FixedAsset\Http\Requests\DepreciationRunRequest;
use App\Domain\FixedAsset\Http\Requests\FixedAssetDisposeRequest;
use App\Domain\FixedAsset\Http\Requests\FixedAssetRequest;
use App\Domain\FixedAsset\Models\AssetCategory;
use App\Domain\FixedAsset\Models\FixedAsset;
use App\Domain\FixedAsset\Services\FixedAssetService;
use App\Domain\Party\Models\Supplier;
use App\Http\Controllers\Controller;
use App\Support\Enums\AssetPaymentMethod;
use App\Support\Enums\DepreciationMethod;
use App\Support\Enums\TransactionStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FixedAssetController extends Controller
{
    public function __construct(protected FixedAssetService $fixedAssetService) {}

    public function index(Request $request): Response
    {
        $companyId = (int) session('active_company_id');

        $query = FixedAsset::query()->with(['category', 'journal'])
            ->where('company_id', $companyId);

        $query = $this->applyStatusFilter($query, $request->input('status'));

        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('asset_code', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        $assets = $query
            ->orderByDesc('acquisition_date')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('FixedAssets/Index', [
            'assets' => $assets,
            'filters' => $request->only(['search', 'status', 'category_id']),
            'categories' => AssetCategory::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
            'periods' => $this->periodOptions($companyId),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('FixedAssets/Create', $this->formProps());
    }

    public function store(FixedAssetRequest $request): RedirectResponse
    {
        $companyId = (int) session('active_company_id');

        try {
            $asset = $this->fixedAssetService->store($request->validated(), $companyId);
        } catch (FixedAssetPostingException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLogger::log('fixed_asset', 'create', null, $asset->id, [], $asset->fresh()->toArray(), $asset->company_id);

        return redirect()->route('fixed-assets.show', $asset)
            ->with('success', 'Fixed asset registered as draft.');
    }

    public function show(FixedAsset $asset): Response
    {
        $this->authorizeCompany($asset);

        return Inertia::render('FixedAssets/Show', [
            'asset' => $asset->load([
                'category',
                'journal',
                'disposal',
                'depreciationEntries.period',
                'cashAccount',
                'bankAccount',
                'supplier',
            ]),
            'schedule' => $this->fixedAssetService->depreciationSchedule($asset),
            'cashAccounts' => CashAccount::query()
                ->where('company_id', $asset->company_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($a) => ['value' => $a->id, 'label' => $a->name])
                ->values()
                ->all(),
        ]);
    }

    public function edit(FixedAsset $asset): Response
    {
        $this->authorizeCompany($asset);

        if (! $asset->isDraft()) {
            return redirect()->route('fixed-assets.show', $asset)
                ->with('error', 'Capitalized or disposed assets cannot be edited.');
        }

        return Inertia::render('FixedAssets/Edit', array_merge($this->formProps(), ['asset' => $asset]));
    }

    public function update(FixedAsset $asset, FixedAssetRequest $request): RedirectResponse
    {
        $this->authorizeCompany($asset);

        try {
            $this->fixedAssetService->update($asset, $request->validated());
        } catch (FixedAssetPostingException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLogger::log('fixed_asset', 'update', null, $asset->id, $asset->getOriginal(), $asset->fresh()->toArray(), $asset->company_id);

        return redirect()->route('fixed-assets.show', $asset)->with('success', 'Fixed asset updated.');
    }

    public function capitalize(FixedAsset $asset): RedirectResponse
    {
        $this->authorizeCompany($asset);

        try {
            $result = $this->fixedAssetService->capitalize($asset);
        } catch (FixedAssetPostingException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLogger::log('fixed_asset', 'capitalize', null, $asset->id, $asset->getOriginal(), $asset->fresh()->toArray(), $asset->company_id);

        return redirect()->route('fixed-assets.show', $asset)
            ->with('success', 'Fixed asset capitalized ('.$result['journal']->journal_no.').');
    }

    public function depreciate(DepreciationRunRequest $request, FixedAssetService $service): RedirectResponse
    {
        $companyId = (int) session('active_company_id');

        try {
            $result = $service->runDepreciation($request->integer('period_id'), $companyId);
        } catch (FixedAssetPostingException|\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        if (! $result['journal']) {
            return back()->with('info', 'No active assets need depreciation for the selected period.');
        }

        AuditLogger::log('fixed_asset', 'depreciate', null, null, [], $result, $companyId);

        return back()->with('success', 'Depreciation posted for '.$result['entries'].' asset(s).');
    }

    public function dispose(FixedAsset $asset, FixedAssetDisposeRequest $request): RedirectResponse
    {
        $this->authorizeCompany($asset);

        try {
            $result = $this->fixedAssetService->dispose($asset, $request->validated());
        } catch (FixedAssetPostingException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLogger::log('fixed_asset', 'dispose', null, $asset->id, $asset->getOriginal(), $asset->fresh()->toArray(), $asset->company_id);

        return redirect()->route('fixed-assets.show', $asset)
            ->with('success', 'Asset disposed ('.$result['journal']->journal_no.').');
    }

    public function destroy(FixedAsset $asset): RedirectResponse
    {
        $this->authorizeCompany($asset);

        try {
            $this->fixedAssetService->destroy($asset);
        } catch (FixedAssetPostingException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLogger::log('fixed_asset', 'delete', null, $asset->id, [], [], $asset->company_id);

        return redirect()->route('fixed-assets.index')->with('success', 'Fixed asset deleted.');
    }

    protected function applyStatusFilter($query, ?string $status)
    {
        return match ($status) {
            'active' => $query->where('status', 'active'),
            'draft' => $query->where('status', 'draft'),
            'disposed' => $query->where('status', 'disposed'),
            default => $query,
        };
    }

    protected function authorizeCompany(FixedAsset $asset): void
    {
        abort_if((int) $asset->company_id !== (int) session('active_company_id'), 404);
    }

    protected function periodOptions(int $companyId): array
    {
        return AccountingPeriod::query()
            ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', $companyId))
            ->where('status', 'open')
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'start_date'])
            ->map(fn ($p) => ['value' => $p->id, 'label' => $p->name])
            ->values()
            ->all();
    }

    protected function formProps(): array
    {
        $companyId = (int) session('active_company_id');

        return [
            'categories' => AssetCategory::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'default_method', 'default_useful_life_months'])
                ->map(fn ($c) => [
                    'value' => $c->id,
                    'label' => $c->name,
                    'default_method' => $c->default_method,
                    'default_useful_life_months' => $c->default_useful_life_months,
                ])
                ->values()
                ->all(),
            'cashAccounts' => CashAccount::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($a) => ['value' => $a->id, 'label' => $a->name])
                ->values()
                ->all(),
            'bankAccounts' => BankAccount::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('account_name')
                ->get(['id', 'account_name', 'bank_name'])
                ->map(fn ($a) => ['value' => $a->id, 'label' => ($a->account_name.' ('.$a->bank_name.')')])
                ->values()
                ->all(),
            'suppliers' => Supplier::query()
                ->where('company_id', $companyId)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])
                ->values()
                ->all(),
            'acquisitionMethods' => collect(AssetPaymentMethod::cases())
                ->map(fn ($m) => ['value' => $m->value, 'label' => $m->label()])
                ->values()
                ->all(),
            'depreciationMethods' => collect(DepreciationMethod::cases())
                ->map(fn ($m) => ['value' => $m->value, 'label' => $m->label()])
                ->values()
                ->all(),
        ];
    }
}