<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Services\DashboardStats;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardStats $stats): View
    {
        $user = $request->user();

        return view('dashboard', [
            'cards' => $stats->cards(),
            'charts' => $stats->charts(),

            // Widgets are only loaded for users allowed to open the linked pages.
            'recentMovements' => $user->can('stock.view')
                ? StockMovement::with(['product.unit', 'user', 'reversalOf'])->latest('movement_date')->latest('id')->limit(8)->get()
                : collect(),
            'recentPurchases' => $user->can('purchases.view')
                ? Purchase::with('supplier')->latest('purchase_date')->latest('id')->limit(6)->get()
                : collect(),
            'recentActivities' => $user->can('activity_logs.view')
                ? ActivityLog::with('user')->latest('created_at')->latest('id')->limit(8)->get()
                : collect(),
            'lowStockProducts' => Product::active()->lowStock()->with('unit')
                ->orderByRaw('CASE WHEN minimum_stock > 0 THEN current_stock / minimum_stock ELSE 0 END')
                ->limit(6)->get(),
        ]);
    }
}
