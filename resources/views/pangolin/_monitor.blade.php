<style>
    .pangolin-monitor.pm-root { background:#0d1117; color:#e6edf3; border-radius:.5rem; overflow:hidden; border:1px solid #30363d; }
    .pangolin-monitor .pm-header { background:#161b22; border-bottom:1px solid #30363d; }
    .pangolin-monitor .pm-title { color:#58a6ff; font-weight:700; font-size:.875rem; letter-spacing:.02em; }
    .pangolin-monitor .pm-meta { color:#8b949e; font-size:.75rem; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
    .pangolin-monitor .pm-panel { background:#161b22; border:1px solid #30363d; border-radius:.375rem; }
    .pangolin-monitor .pm-row { display:flex; flex-wrap:wrap; align-items:center; gap:.625rem; padding:.375rem .625rem; border-radius:.25rem; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size:.8125rem; }
    .pangolin-monitor .pm-row + .pm-row { margin-top:2px; }
    .pangolin-monitor .pm-row-ok   { background: rgba(46,160,67,.15); color:#3fb950; }
    .pangolin-monitor .pm-row-warn { background: rgba(210,153,34,.15); color:#d29922; }
    .pangolin-monitor .pm-row-down { background: rgba(248,81,73,.15); color:#f85149; }
    .pangolin-monitor .pm-row-dim  { background: rgba(139,148,158,.08); color:#8b949e; }
    .pangolin-monitor .pm-name { min-width:11rem; font-weight:700; }
    .pangolin-monitor .pm-badge { font-weight:700; }
    .pangolin-monitor .pm-dot { width:.5rem; height:.5rem; border-radius:9999px; flex:none; display:inline-block; }
    .pangolin-monitor .pm-dot-ok   { background:#3fb950; }
    .pangolin-monitor .pm-dot-warn { background:#d29922; }
    .pangolin-monitor .pm-dot-down { background:#f85149; }
    .pangolin-monitor .pm-dot-dim  { background:#8b949e; }
</style>

<script>
    function pangolinMonitor(initialGroups) {
        return {
            groups: initialGroups || {},
            lastRefresh: null,
            timer: null,

            init() {
                this.lastRefresh = new Date().toLocaleTimeString();
                this.timer = setInterval(() => this.poll(), 15000);
            },

            poll() {
                fetch('{{ route('pangolin.monitor.data') }}', { headers: { Accept: 'application/json' } })
                    .then((r) => r.json())
                    .then((data) => {
                        this.groups = data;
                        this.lastRefresh = new Date().toLocaleTimeString();
                    })
                    .catch(() => {});
            },

            row(service) {
                const r = this.groups[service];
                return Array.isArray(r) && r.length ? r[0] : null;
            },

            newtRows() {
                const r = this.groups['newt'];
                return Array.isArray(r) ? r : [];
            },

            hasAnyData() {
                return ['pangolin', 'gerbil', 'api'].some((s) => this.row(s)) || this.newtRows().length > 0;
            },

            rowClass(service, okStatuses = ['up']) {
                const r = this.row(service);
                if (!r) return 'dim';
                if (r.status === 'unknown') return 'dim';
                return okStatuses.includes(r.status) ? 'ok' : (r.status === 'degraded' ? 'warn' : 'down');
            },

            newtRowClass(row) {
                return row.status === 'up' ? 'ok' : 'down';
            },

            overallStatus() {
                const rows = ['pangolin', 'gerbil', 'api']
                    .map((s) => this.row(s))
                    .filter((r) => r && r.status !== 'unknown')
                    .concat(this.newtRows());
                if (!rows.length) return 'dim';
                const bad = rows.filter((r) => r.status !== 'up').length;
                if (bad === 0) return 'ok';
                if (bad >= rows.length) return 'down';
                return 'warn';
            },
        };
    }
</script>

<div class="bg-white shadow-sm sm:rounded-lg p-6 mb-4">
    <h3 class="text-lg font-semibold mb-2">{{ __('Monitor settings') }}</h3>
    <p class="text-sm text-gray-500 mb-4">{{ __('Saved until changed — no need to re-enter these on every visit.') }}</p>
    <form action="{{ route('pangolin.monitor.settings.update') }}" method="POST" class="flex flex-wrap items-end gap-4">
        @csrf
        <div>
            <label for="node_ip" class="block text-sm font-medium text-gray-700">{{ __('Node IP') }}</label>
            <input type="text" name="node_ip" id="node_ip" value="{{ old('node_ip', $settings?->node_ip) }}" required
                   placeholder="10.23.2.71" class="mt-1 block w-40 border-gray-300 rounded-md shadow-sm font-mono text-sm">
            @error('node_ip')
                <span class="text-red-500 text-sm">{{ $message }}</span>
            @enderror
        </div>
        <div>
            <label for="pangolin_url" class="block text-sm font-medium text-gray-700">{{ __('Pangolin URL') }}</label>
            <input type="text" name="pangolin_url" id="pangolin_url" value="{{ old('pangolin_url', $settings?->pangolin_url) }}"
                   placeholder="https://pangolin.uop.gr" class="mt-1 block w-56 border-gray-300 rounded-md shadow-sm font-mono text-sm">
            @error('pangolin_url')
                <span class="text-red-500 text-sm">{{ $message }}</span>
            @enderror
        </div>
        <div>
            <label for="api_key" class="block text-sm font-medium text-gray-700">
                {{ __('API key') }}
                <span class="text-gray-400 font-normal">({{ $settings?->api_key ? __('set — leave blank to keep it') : __('not set') }})</span>
            </label>
            <input type="password" name="api_key" id="api_key" autocomplete="new-password"
                   placeholder="{{ $settings?->api_key ? '••••••••••••' : '' }}" class="mt-1 block w-56 border-gray-300 rounded-md shadow-sm font-mono text-sm">
            @error('api_key')
                <span class="text-red-500 text-sm">{{ $message }}</span>
            @enderror
        </div>
        <x-primary-button type="submit">{{ __('Save') }}</x-primary-button>
    </form>
</div>

<div class="pangolin-monitor pm-root" x-data="pangolinMonitor(@js($statuses))">
    <div class="pm-header flex flex-wrap items-center justify-between gap-3 px-4 py-3">
        <div class="flex items-center gap-2">
            <span class="pm-dot" :class="'pm-dot-' + overallStatus()"></span>
            <h3 class="pm-title">{{ __('Pangolin') }}</h3>
        </div>
        <div class="flex items-center gap-4">
            <span class="pm-meta" x-text="'Last refresh: ' + (lastRefresh || '—') + '   Auto-refresh: 15s'"></span>
            <form action="{{ route('pangolin.monitor.refresh') }}" method="POST">
                @csrf
                <x-secondary-button type="submit">{{ __('Refresh now') }}</x-secondary-button>
            </form>
        </div>
    </div>

    <div class="p-3 space-y-2">
        <template x-if="!hasAnyData()">
            <div class="pm-panel p-6 text-center pm-meta">
                {{ __('No data yet — save your Node IP above, then click Refresh now, or wait for the scheduled poll.') }}
            </div>
        </template>

        <template x-if="row('pangolin')">
            <div class="pm-row" :class="'pm-row-' + rowClass('pangolin')">
                <span class="pm-dot" :class="'pm-dot-' + rowClass('pangolin')"></span>
                <span class="pm-name">{{ __('Pangolin') }}</span>
                <span x-text="row('pangolin').status === 'up' ? 'UP' : (row('pangolin').message || 'DOWN')"></span>
            </div>
        </template>

        <template x-if="row('gerbil')">
            <div class="pm-row" :class="'pm-row-' + rowClass('gerbil')">
                <span class="pm-dot" :class="'pm-dot-' + rowClass('gerbil')"></span>
                <span class="pm-name">{{ __('Gerbil') }}</span>
                <span x-text="row('gerbil').status === 'up' ? 'UP' : (row('gerbil').message || 'DOWN')"></span>
            </div>
        </template>

        <template x-if="row('api')">
            <div class="pm-row" :class="'pm-row-' + rowClass('api')">
                <span class="pm-dot" :class="'pm-dot-' + rowClass('api')"></span>
                <span class="pm-name">{{ __('Integration API') }}</span>
                <template x-if="row('api').status === 'unknown'">
                    <span class="pm-meta" x-text="row('api').message"></span>
                </template>
                <template x-if="row('api').status === 'up'">
                    <span class="pm-meta" x-text="(row('api').metrics?.total_resources ?? 0) + ' resources'"></span>
                </template>
                <template x-if="row('api').status !== 'unknown' && row('api').status !== 'up'">
                    <span class="pm-meta" x-text="row('api').message || 'DOWN'"></span>
                </template>
            </div>
        </template>

        <div class="pm-panel p-3 mt-2">
            <div class="pm-panel-title mb-2 text-xs font-bold uppercase tracking-wide" style="color:#58a6ff;">{{ __('Newt agents') }}</div>
            <template x-for="host in newtRows()" :key="host.node_ip">
                <div class="pm-row" :class="'pm-row-' + newtRowClass(host)">
                    <span class="pm-dot" :class="'pm-dot-' + newtRowClass(host)"></span>
                    <span class="pm-name" x-text="host.node_name"></span>
                    <span class="pm-meta" x-text="host.node_ip"></span>
                    <span class="pm-badge" x-text="host.status === 'up' ? 'REACHABLE' : 'UNREACHABLE'"></span>
                    <span class="pm-meta" x-text="host.message || ''"></span>
                </div>
            </template>
            <template x-if="!newtRows().length">
                <div class="pm-row pm-row-dim">{{ __('No agents configured yet — add one below.') }}</div>
            </template>
        </div>
    </div>
</div>

<div class="bg-white shadow-sm sm:rounded-lg p-6 mt-4">
    <h3 class="text-lg font-semibold mb-2">{{ __('Newt agents') }}</h3>
    <p class="text-sm text-gray-500 mb-4">{{ __('Add or remove agents as the number of sites changes — this list drives both the checks above and the Logs tab.') }}</p>

    <div class="space-y-2 mb-4">
        @forelse ($newtAgents as $newtAgent)
            <div class="flex items-center gap-3 text-sm">
                <span class="font-medium">{{ $newtAgent->name }}</span>
                <span class="text-gray-500 font-mono">{{ $newtAgent->ip }}</span>
                <form action="{{ route('pangolin.monitor.newt-agents.destroy', $newtAgent) }}" method="POST"
                      onsubmit="return confirm('{{ __('Remove this Newt agent?') }}');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm">{{ __('Remove') }}</button>
                </form>
            </div>
        @empty
            <p class="text-sm text-gray-400">{{ __('None configured yet.') }}</p>
        @endforelse
    </div>

    <form action="{{ route('pangolin.monitor.newt-agents.store') }}" method="POST" class="flex flex-wrap items-end gap-4">
        @csrf
        <div>
            <label for="newt_name" class="block text-sm font-medium text-gray-700">{{ __('Name') }}</label>
            <input type="text" name="name" id="newt_name" value="{{ old('name') }}" required
                   placeholder="patra" class="mt-1 block w-40 border-gray-300 rounded-md shadow-sm text-sm">
            @error('name')
                <span class="text-red-500 text-sm">{{ $message }}</span>
            @enderror
        </div>
        <div>
            <label for="newt_ip" class="block text-sm font-medium text-gray-700">{{ __('IP') }}</label>
            <input type="text" name="ip" id="newt_ip" value="{{ old('ip') }}" required
                   placeholder="10.23.2.60" class="mt-1 block w-40 border-gray-300 rounded-md shadow-sm font-mono text-sm">
            @error('ip')
                <span class="text-red-500 text-sm">{{ $message }}</span>
            @enderror
        </div>
        <x-primary-button type="submit">{{ __('Add agent') }}</x-primary-button>
    </form>
</div>
