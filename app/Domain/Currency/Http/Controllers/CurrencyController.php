<?php

namespace App\Domain\Currency\Http\Controllers;

use App\Domain\Currency\Http\Requests\CurrencyRequest;
use App\Domain\Currency\Http\Requests\ExchangeRateRequest;
use App\Domain\Currency\Models\Currency;
use App\Domain\Currency\Models\ExchangeRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CurrencyController
{
    public function index(Request $request): Response
    {
        $currencies = Currency::query()
            ->where('company_id', current_company_id())
            ->withCount('exchangeRates')
            ->with(['exchangeRates' => fn ($q) => $q->orderByDesc('effective_date')->limit(1)])
            ->orderByDesc('is_base')
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Currencies/Index', [
            'currencies' => $currencies,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(CurrencyRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $isBase = (bool) ($data['is_base'] ?? false);

        DB::transaction(function () use ($data, $isBase) {
            if ($isBase) {
                Currency::query()->where('company_id', current_company_id())->update(['is_base' => false]);
            }

            $currency = Currency::query()->create($data + ['company_id' => current_company_id()]);

            \App\Domain\Audit\Services\AuditLogger::log('currency', 'create', null, $currency->id, [], $currency->toArray(), current_company_id());
        });

        return redirect()
            ->route('currencies.index')
            ->with('success', 'Currency created.');
    }

    public function update(CurrencyRequest $request, Currency $currency): RedirectResponse
    {
        $old = $currency->toArray();
        $data = $request->validated();

        DB::transaction(function () use ($currency, $data, $old) {
            if (! empty($data['is_base'])) {
                Currency::query()->where('company_id', $currency->company_id)->update(['is_base' => false]);
            }

            $currency->update($data);

            \App\Domain\Audit\Services\AuditLogger::log('currency', 'update', null, $currency->id, $old, $currency->toArray(), $currency->company_id);
        });

        return back()->with('success', 'Currency updated.');
    }

    public function destroy(Request $request, Currency $currency): RedirectResponse
    {
        if ($currency->is_base) {
            return back()->with('error', 'The base currency cannot be deleted.');
        }

        $currency->delete();

        \App\Domain\Audit\Services\AuditLogger::log('currency', 'delete', null, $currency->id, $currency->toArray(), [], $currency->company_id);

        return back()->with('success', 'Currency deleted.');
    }

    public function storeRate(ExchangeRateRequest $request): RedirectResponse
    {
        $rate = ExchangeRate::query()->create($request->validated());

        \App\Domain\Audit\Services\AuditLogger::log('exchange_rate', 'create', null, $rate->id, [], $rate->toArray(), current_company_id());

        return back()->with('success', 'Exchange rate saved.');
    }

    public function destroyRate(Request $request, ExchangeRate $rate): RedirectResponse
    {
        $rate->delete();

        \App\Domain\Audit\Services\AuditLogger::log('exchange_rate', 'delete', null, $rate->id, $rate->toArray(), [], current_company_id());

        return back()->with('success', 'Exchange rate deleted.');
    }
}