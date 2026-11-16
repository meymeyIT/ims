<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockInController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\StockOutController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {

    Route::get('dashboard', DashboardController::class)
        ->middleware('can:dashboard.view')
        ->name('dashboard');

    // Every logged-in user can manage their own profile and password.
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])
        ->middleware('throttle:6,1')
        ->name('profile.password');

    // ---------------------------------------------------------------- Products
    // Permissions are enforced inside each controller (HasMiddleware).
    Route::resource('categories', CategoryController::class)->except('show');
    Route::patch('categories/{category}/toggle-status', [CategoryController::class, 'toggleStatus'])
        ->name('categories.toggle-status');

    Route::resource('units', UnitController::class)->except('show');
    Route::patch('units/{unit}/toggle-status', [UnitController::class, 'toggleStatus'])
        ->name('units.toggle-status');

    Route::resource('products', ProductController::class);
    Route::patch('products/{product}/toggle-status', [ProductController::class, 'toggleStatus'])
        ->name('products.toggle-status');
    Route::patch('products/{product}/restore', [ProductController::class, 'restore'])
        ->withTrashed()->name('products.restore');
    Route::delete('products/{product}/force', [ProductController::class, 'forceDestroy'])
        ->withTrashed()->name('products.force-destroy');

    // ---------------------------------------------------------------- Suppliers
    Route::resource('suppliers', SupplierController::class);
    Route::patch('suppliers/{supplier}/toggle-status', [SupplierController::class, 'toggleStatus'])
        ->name('suppliers.toggle-status');

    // ---------------------------------------------------------------- Purchases
    Route::resource('purchases', PurchaseController::class);
    Route::prefix('purchases/{purchase}')->name('purchases.')->controller(PurchaseController::class)->group(function () {
        Route::patch('submit', 'submit')->name('submit');
        Route::patch('approve', 'approve')->name('approve');
        Route::patch('cancel', 'cancel')->name('cancel');
        Route::get('receive', 'receiveForm')->name('receive');
        Route::post('receive', 'receive')->middleware('throttle:30,1')->name('receive.store');
        Route::get('print', 'print')->name('print');
    });

    // ---------------------------------------------------------------- Inventory
    Route::get('stock-in', [StockInController::class, 'index'])->name('stock-in.index');
    Route::get('stock-in/create', [StockInController::class, 'create'])->name('stock-in.create');
    Route::post('stock-in', [StockInController::class, 'store'])->middleware('throttle:30,1')->name('stock-in.store');

    Route::get('stock-out', [StockOutController::class, 'index'])->name('stock-out.index');
    Route::get('stock-out/create', [StockOutController::class, 'create'])->name('stock-out.create');
    Route::post('stock-out', [StockOutController::class, 'store'])->middleware('throttle:30,1')->name('stock-out.store');

    Route::get('stock-adjustments/create', [StockAdjustmentController::class, 'create'])->name('stock-adjustments.create');
    Route::post('stock-adjustments', [StockAdjustmentController::class, 'store'])->middleware('throttle:30,1')->name('stock-adjustments.store');

    // Export / print are registered before {movement} so they are not mistaken for an ID.
    Route::get('stock-movements', [StockMovementController::class, 'index'])->name('stock-movements.index');
    Route::get('stock-movements/export', [StockMovementController::class, 'export'])->middleware('throttle:10,1')->name('stock-movements.export');
    Route::get('stock-movements/print', [StockMovementController::class, 'print'])->name('stock-movements.print');
    Route::get('stock-movements/{movement}', [StockMovementController::class, 'show'])->whereNumber('movement')->name('stock-movements.show');
    Route::post('stock-movements/{movement}/reverse', [StockMovementController::class, 'reverse'])
        ->whereNumber('movement')->middleware('throttle:30,1')->name('stock-movements.reverse');

    // ---------------------------------------------------------------- Reports
    Route::prefix('reports')->name('reports.')->controller(ReportController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{report}', 'show')->where('report', '[a-z-]+')->name('show');
        Route::get('{report}/excel', 'excel')->where('report', '[a-z-]+')->middleware('throttle:10,1')->name('excel');
        Route::get('{report}/pdf', 'pdf')->where('report', '[a-z-]+')->middleware('throttle:10,1')->name('pdf');
        Route::get('{report}/print', 'print')->where('report', '[a-z-]+')->name('print');
    });

    // ---------------------------------------------------------------- Notifications
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('notifications/{notification}', [NotificationController::class, 'read'])
        ->whereUuid('notification')->name('notifications.read');

    // ---------------------------------------------------------------- System
    // UserController checks UserPolicy in every action.
    Route::resource('users', UserController::class)->except('show');
    Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])
        ->name('users.toggle-status');

    Route::get('roles', [RoleController::class, 'index'])
        ->middleware('can:roles.view')->name('roles.index');
    Route::get('roles/{role}/edit', [RoleController::class, 'edit'])
        ->middleware('can:roles.manage')->name('roles.edit');
    Route::put('roles/{role}', [RoleController::class, 'update'])
        ->middleware('can:roles.manage')->name('roles.update');

    Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');

    Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingController::class, 'update'])->middleware('throttle:20,1')->name('settings.update');
});

require __DIR__.'/auth.php';
