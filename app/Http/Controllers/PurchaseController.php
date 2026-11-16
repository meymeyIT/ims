<?php

namespace App\Http\Controllers;

use App\Enums\PurchaseStatus;
use App\Http\Requests\PurchaseRequest;
use App\Http\Requests\ReceivePurchaseRequest;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReceipt;
use App\Models\Setting;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PurchaseController extends Controller implements HasMiddleware
{
    public function __construct(private readonly PurchaseService $purchases)
    {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('can:purchases.view', only: ['index', 'show', 'print']),
            new Middleware('can:purchases.create', only: ['create', 'store', 'edit', 'update', 'destroy', 'submit']),
            new Middleware('can:purchases.approve', only: ['approve']),
            new Middleware('can:purchases.cancel', only: ['cancel']),
            new Middleware('can:purchases.receive', only: ['receiveForm', 'receive']),
        ];
    }

    public function index(Request $request): View
    {
        $purchases = Purchase::query()
            ->with(['supplier', 'creator'])
            ->withCount('items')
            ->search($request->query('search'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('supplier'), fn ($q) => $q->where('supplier_id', $request->integer('supplier')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('purchase_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('purchase_date', '<=', $request->date('date_to')))
            ->latest('purchase_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('purchases.index', [
            'purchases' => $purchases,
            'suppliers' => Supplier::withTrashed()->orderBy('name')->get(['id', 'name']),
            'statuses' => PurchaseStatus::cases(),
        ]);
    }

    /** ?products[]=1&products[]=2 pre-fills lines with the suggested restock quantities. */
    public function create(Request $request): View
    {
        $ids = array_filter(array_map('intval', (array) $request->query('products', [])));

        $lines = $ids === [] ? [] : Product::active()->whereIn('id', $ids)->orderBy('name')->get()
            ->map(fn (Product $product) => [
                'product_id' => $product->id,
                'quantity' => $product->suggestedRestockQuantity() ?? '1',
                'unit_cost' => $product->purchase_price,
            ])->all();

        return view('purchases.create', [
            'purchase' => new Purchase(['purchase_date' => now()->toDateString(), 'discount' => '0.00', 'tax' => '0.00']),
            'lines' => $lines,
            ...$this->formOptions(),
        ]);
    }

    public function store(PurchaseRequest $request): RedirectResponse
    {
        $purchase = $this->purchases->create($request->validated(), $request->user(), $request->submitForApproval());

        return redirect()->route('purchases.show', $purchase)
            ->with('success', "Purchase {$purchase->purchase_number} was " . ($request->submitForApproval() ? 'submitted for approval.' : 'saved as a draft.'));
    }

    public function show(Purchase $purchase): View
    {
        $purchase->load([
            'supplier', 'creator', 'approver', 'canceller',
            'items.product.unit',
            'receipts' => fn ($q) => $q->latest('received_at')->latest('id'),
            'receipts.receiver',
            'receipts.items.product.unit',
        ]);

        return view('purchases.show', compact('purchase'));
    }

    public function edit(Purchase $purchase): View|RedirectResponse
    {
        if (! $purchase->status->isEditable()) {
            return redirect()->route('purchases.show', $purchase)->with('error', 'Only draft purchases can be edited.');
        }

        $purchase->load('items');

        return view('purchases.edit', [
            'purchase' => $purchase,
            'lines' => $purchase->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity_ordered,
                'unit_cost' => $item->unit_cost,
            ])->all(),
            ...$this->formOptions(),
        ]);
    }

    public function update(PurchaseRequest $request, Purchase $purchase): RedirectResponse
    {
        $purchase = $this->purchases->update($purchase, $request->validated(), $request->user(), $request->submitForApproval());

        return redirect()->route('purchases.show', $purchase)
            ->with('success', "Purchase {$purchase->purchase_number} was " . ($request->submitForApproval() ? 'updated and submitted for approval.' : 'updated.'));
    }

    public function destroy(Purchase $purchase): RedirectResponse
    {
        $number = $purchase->purchase_number;
        $this->purchases->delete($purchase);

        return redirect()->route('purchases.index')->with('success', "Draft purchase {$number} was deleted.");
    }

    public function submit(Purchase $purchase): RedirectResponse
    {
        $this->purchases->submit($purchase);

        return back()->with('success', "Purchase {$purchase->purchase_number} was submitted for approval.");
    }

    public function approve(Request $request, Purchase $purchase): RedirectResponse
    {
        $this->purchases->approve($purchase, $request->user());

        return back()->with('success', "Purchase {$purchase->purchase_number} was approved. It can now be received.");
    }

    public function cancel(Request $request, Purchase $purchase): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $this->purchases->cancel($purchase, $request->user(), $data['reason']);

        return back()->with('success', "Purchase {$purchase->purchase_number} was cancelled.");
    }

    public function receiveForm(Purchase $purchase): View|RedirectResponse
    {
        if (! $purchase->status->canBeReceived()) {
            return redirect()->route('purchases.show', $purchase)
                ->with('error', "A {$purchase->status->label()} purchase cannot be received. It must be approved first.");
        }

        $purchase->load(['supplier', 'items.product.unit']);

        return view('purchases.receive', [
            'purchase' => $purchase,
            'token' => (string) Str::uuid(),
        ]);
    }

    public function receive(ReceivePurchaseRequest $request, Purchase $purchase): RedirectResponse
    {
        // Same form submitted twice (double-click / refresh / back button)?
        if (PurchaseReceipt::where('receipt_token', $request->validated('receipt_token'))->exists()) {
            return redirect()->route('purchases.show', $purchase)->with('info', 'This delivery was already recorded. Nothing was received twice.');
        }

        try {
            $receipt = $this->purchases->receive(
                $purchase,
                $request->validated('items'),
                $request->validated('receipt_token'),
                Carbon::parse($request->validated('received_at')),
                $request->validated('notes'),
                $request->user(),
            );
        } catch (UniqueConstraintViolationException) {
            // Two identical submissions arrived at the same moment; the database kept only one.
            return redirect()->route('purchases.show', $purchase)->with('info', 'This delivery was already recorded. Nothing was received twice.');
        }

        return redirect()->route('purchases.show', $purchase)
            ->with('success', "Goods received ({$receipt->receipt_number}). Stock was updated.");
    }

    /** Printable purchase order / invoice. */
    public function print(Purchase $purchase): View
    {
        $purchase->load(['supplier', 'creator', 'approver', 'items.product.unit']);

        return view('purchases.print', [
            'purchase' => $purchase,
            'business' => [
                'name' => Setting::get('business_name', config('app.name')),
                'address' => Setting::get('business_address'),
                'phone' => Setting::get('business_phone'),
                'email' => Setting::get('business_email'),
            ],
        ]);
    }

    private function formOptions(): array
    {
        return [
            'suppliers' => Supplier::active()->orderBy('name')->get(['id', 'name', 'company_name']),
            'products' => Product::active()->with('unit')->orderBy('name')->get(['id', 'name', 'sku', 'unit_id', 'purchase_price']),
        ];
    }
}
