<?php

namespace App\Http\Controllers;

use App\Models\PangolinNewtAgent;
use Illuminate\Http\Request;

/**
 * Plain CRUD for admin-managed Newt agents (name + IP), the SSH pull
 * targets App\Services\Pangolin\NewtConnectionSync fetches from. No
 * index()/show() — the list renders inline in the Pangolin page's "Newt
 * Agents" tab (see PangolinController::index()), same as
 * NetworkDeviceController's devices do in network-lookup/index.blade.php.
 * No Policy/Gate::authorize, same as the rest of this module —
 * PangolinEnabled (menu-gated) covers the whole /pangolin route group these
 * routes live under.
 */
class PangolinNewtAgentController extends Controller
{
    public function create()
    {
        return view('pangolin.newt_agents.create');
    }

    public function store(Request $request)
    {
        PangolinNewtAgent::create($this->validated($request));

        return redirect()->route('pangolin.index', ['tab' => 'newt-agents'])->with('success', 'Newt agent added.');
    }

    public function edit(PangolinNewtAgent $newtAgent)
    {
        return view('pangolin.newt_agents.edit', ['agent' => $newtAgent]);
    }

    public function update(Request $request, PangolinNewtAgent $newtAgent)
    {
        $newtAgent->update($this->validated($request, $newtAgent));

        return redirect()->route('pangolin.index', ['tab' => 'newt-agents'])->with('success', 'Newt agent updated.');
    }

    public function destroy(PangolinNewtAgent $newtAgent)
    {
        $newtAgent->delete();

        return redirect()->route('pangolin.index', ['tab' => 'newt-agents'])->with('success', 'Newt agent removed.');
    }

    private function validated(Request $request, ?PangolinNewtAgent $agent = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'ip' => ['required', 'ip', 'unique:pangolin_newt_agents,ip,'.($agent?->id)],
        ]);
    }
}
