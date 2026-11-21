<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;

/**
 * Sign-in accounts for the team. Only administrators manage them, because the role decides what each person can do.
 */
#[Authorize('settings.manage')]
class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::with('roles')->orderBy('name')->paginate($this->perPage()),
        ]);
    }

    public function create(): View
    {
        return view('users.form', ['user' => new User, 'role' => 'staff']);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $user = new User($request->safe()->only(['name', 'email', 'password']));
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole($request->validated('role'));
        });

        return to_route('users.index')->with('status', __('User created.'));
    }

    public function edit(User $user): View
    {
        return view('users.form', ['user' => $user, 'role' => $user->getRoleNames()->first()]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user): void {
            $user->fill($request->safe()->only(['name', 'email']));

            if ($request->filled('password')) {
                $user->password = $request->validated('password');
            }

            $user->save();
            $user->syncRoles([$request->validated('role')]);
        });

        return to_route('users.index')->with('status', __('User updated.'));
    }

    /**
     * Only administrators get here, so refusing self-deletion (and own role changes, see UserRequest) keeps at least one.
     */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->is(auth()->user())) {
            return back()->with('error', __('You cannot delete your own account here.'));
        }

        $user->delete();

        return to_route('users.index')->with('status', __('User deleted.'));
    }
}
