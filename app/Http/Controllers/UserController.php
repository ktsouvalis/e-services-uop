<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', User::class);
        $users = User::all();
        return view('users.index', compact('users'));
    }

    public function store()
    {
        Gate::authorize('create', User::class);
        $attributes = request()->validate([
            'name' => 'required',
            'username' => 'required',
        ]);

        $attributes['email'] = request('username') . '@uop.gr';
        if(request('admin')) $attributes['admin'] = true;
        User::create($attributes);

        return redirect()->route('users.index')->with('success', 'User created successfully');
    }

    public function edit(User $user)
    {
        Gate::authorize('update', $user);
        return view('users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        Gate::authorize('update', $user);
        if(request('admin')) $request['admin'] = true; else $request['admin'] = false;
        $user->update($request->all());
        return redirect()->route('users.index')->with('success', 'User updated successfully');
    }

    public function destroy(User $user)
    {
        Gate::authorize('delete', $user);

        // pangolin_runs/authentik_log_runs both cascadeOnDelete() on user_id,
        // but that only removes the DB rows — their per-run directories under
        // storage/app/private/{pangolin,authentik}/runs/{id}/ are otherwise
        // orphaned on disk forever, since nothing else ever cleans them up.
        foreach ($user->pangolinRuns as $run) {
            File::deleteDirectory(storage_path("app/private/pangolin/runs/{$run->id}"));
        }
        foreach ($user->authentikLogRuns as $run) {
            File::deleteDirectory(storage_path("app/private/authentik/runs/{$run->id}"));
        }

        $user->delete();
        return redirect()->route('users.index')->with('success', 'User deleted successfully');
    }
}

?>
