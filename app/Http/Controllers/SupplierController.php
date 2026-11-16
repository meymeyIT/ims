<?php

namespace App\Http\Controllers;

use App\Enums\PurchaseStatus;
use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class SupplierController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:suppliers.view', only: ['index', 'show']),
            new Middleware('can:suppliers.manage', except: ['index', 'show']),
        ];
    }

    public function index(Request $request): View
    {
        $suppliers = Supplier::query()
            ->withCount('purchases')
            ->search($request->query('search'))
            ->when($request->query('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->query('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('suppliers.index', compact('suppliers'));
    }

    public function create(): View
    {
        return view('suppliers.create', ['supplier' => new Supplier(['is_active' => true])]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::create($request->validated());

        ActivityLogger::log('created', 'suppliers', "Created supplier {$supplier->name}", $supplier);

        return redirect()->route('suppliers.show', $supplier)->with('success', "Supplier \"{$supplier->name}\" was created.");
    }

    /** Supplier details + purchase history. */
    public function show(Supplier $supplier): View
    {
        $purchases = $supplier->purchases()
            ->latest('purchase_date')
            ->latest('id')
            ->paginate(10);

        // Totals exclude cancelled purchase orders.
        $totals = $supplier->purchases()
            ->where('status', '!=', PurchaseStatus::Cancelled->value)
            ->selectRaw('COUNT(*) AS orders, COALESCE(SUM(total_amount), 0) AS amount')
            ->first();

        return view('suppliers.show', compact('supplier', 'purchases', 'totals'));
    }

    public function edit(Supplier $supplier): View
    {
        return view('suppliers.edit', compact('supplier'));
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        ActivityLogger::log('updated', 'suppliers', "Updated supplier {$supplier->name}", $supplier);

        return redirect()->route('suppliers.show', $supplier)->with('success', "Supplier \"{$supplier->name}\" was updated.");
    }

    public function toggleStatus(Supplier $supplier): RedirectResponse
    {
        $supplier->update(['is_active' => ! $supplier->is_active]);
        $state = $supplier->is_active ? 'activated' : 'deactivated';

        ActivityLogger::log($state, 'suppliers', ucfirst($state) . " supplier {$supplier->name}", $supplier);

        return back()->with('success', "Supplier \"{$supplier->name}\" was {$state}.");
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->hasHistory()) {
            return back()->with('error', "Supplier \"{$supplier->name}\" has purchase or stock history and cannot be deleted. Deactivate it instead.");
        }

        $supplier->delete();

        ActivityLogger::log('deleted', 'suppliers', "Deleted supplier {$supplier->name}", $supplier);

        return redirect()->route('suppliers.index')->with('success', "Supplier \"{$supplier->name}\" was deleted.");
    }
}
