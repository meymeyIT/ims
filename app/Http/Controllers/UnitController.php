<?php

namespace App\Http\Controllers;

use App\Http\Requests\UnitRequest;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class UnitController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:units.view', only: ['index']),
            new Middleware('can:units.manage', except: ['index']),
        ];
    }

    public function index(Request $request): View
    {
        $units = Unit::query()
            ->withCount('products')
            ->search($request->query('search'))
            ->when($request->query('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->query('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('units.index', compact('units'));
    }

    public function create(): View
    {
        return view('units.create', ['unit' => new Unit(['is_active' => true])]);
    }

    public function store(UnitRequest $request): RedirectResponse
    {
        $unit = Unit::create($request->validated());

        ActivityLogger::log('created', 'units', "Created unit {$unit->name} ({$unit->abbreviation})", $unit);

        return redirect()->route('units.index')->with('success', "Unit \"{$unit->name}\" was created.");
    }

    public function edit(Unit $unit): View
    {
        return view('units.edit', compact('unit'));
    }

    public function update(UnitRequest $request, Unit $unit): RedirectResponse
    {
        $unit->update($request->validated());

        ActivityLogger::log('updated', 'units', "Updated unit {$unit->name}", $unit);

        return redirect()->route('units.index')->with('success', "Unit \"{$unit->name}\" was updated.");
    }

    public function toggleStatus(Unit $unit): RedirectResponse
    {
        $unit->update(['is_active' => ! $unit->is_active]);
        $state = $unit->is_active ? 'activated' : 'deactivated';

        ActivityLogger::log($state, 'units', ucfirst($state) . " unit {$unit->name}", $unit);

        return back()->with('success', "Unit \"{$unit->name}\" was {$state}.");
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        if ($unit->isInUse()) {
            return back()->with('error', "Unit \"{$unit->name}\" is used by products and cannot be deleted. Deactivate it instead.");
        }

        try {
            $unit->delete();
        } catch (QueryException) {
            return back()->with('error', "Unit \"{$unit->name}\" is in use and cannot be deleted.");
        }

        ActivityLogger::log('deleted', 'units', "Deleted unit {$unit->name}", $unit);

        return redirect()->route('units.index')->with('success', "Unit \"{$unit->name}\" was deleted.");
    }
}
