<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $search = trim((string) $request->query('search'));

        $users = User::query()
            ->with('role')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('role'), fn ($q) => $q->where('role_id', $request->integer('role')))
            ->when($request->query('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->query('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'roles' => Role::orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('users.create', [
            'user' => new User(['is_active' => true]),
            'roles' => Role::orderBy('id')->get(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create($request->validated());
        $user->forceFill(['email_verified_at' => now()])->save();

        ActivityLogger::log('created', 'users', "Created user {$user->email}", $user);

        return redirect()->route('users.index')->with('success', "User {$user->name} was created.");
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('users.edit', [
            'user' => $user,
            'roles' => Role::orderBy('id')->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->fill($data);
        $changed = array_keys($user->getDirty());
        $user->save();

        ActivityLogger::log('updated', 'users', "Updated user {$user->email}", $user, [
            'changed' => array_values(array_diff($changed, ['password', 'remember_token'])),
            'password_changed' => in_array('password', $changed, true),
        ]);

        return redirect()->route('users.index')->with('success', "User {$user->name} was updated.");
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        if (Gate::denies('toggleStatus', $user)) {
            return back()->with('error', 'You cannot change the status of this account (your own account or the last active administrator).');
        }

        $user->forceFill(['is_active' => ! $user->is_active])->save();
        $state = $user->is_active ? 'activated' : 'deactivated';

        ActivityLogger::log($state, 'users', ucfirst($state) . " user {$user->email}", $user);

        return back()->with('success', "User {$user->name} was {$state}.");
    }

    public function destroy(User $user): RedirectResponse
    {
        if (Gate::denies('delete', $user)) {
            return back()->with('error', 'You cannot delete this account (your own account or the last active administrator).');
        }

        // Soft delete: the user disappears from lists and cannot log in,
        // but their name stays on historical purchases and stock movements.
        $user->delete();

        ActivityLogger::log('deleted', 'users', "Deleted user {$user->email}", $user);

        return redirect()->route('users.index')->with('success', "User {$user->name} was deleted.");
    }
}
