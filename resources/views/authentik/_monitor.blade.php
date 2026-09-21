<style>
    .authentik-monitor.am-root { background:#0d1117; color:#e6edf3; border-radius:.5rem; overflow:hidden; border:1px solid #30363d; }
    .authentik-monitor .am-header { background:#161b22; border-bottom:1px solid #30363d; }
    .authentik-monitor .am-title { color:#58a6ff; font-weight:700; font-size:.875rem; letter-spacing:.02em; }
    .authentik-monitor .am-meta { color:#8b949e; font-size:.75rem; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
    .authentik-monitor .am-panel { background:#161b22; border:1px solid #30363d; border-radius:.375rem; }
    .authentik-monitor .am-panel-title { color:#58a6ff; font-weight:700; font-size:.75rem; letter-spacing:.05em; text-transform:uppercase; }
    .authentik-monitor .am-row { display:flex; flex-wrap:wrap; align-items:center; gap:.625rem; padding:.375rem .625rem; border-radius:.25rem; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size:.8125rem; }
    .authentik-monitor .am-row + .am-row { margin-top:2px; }
    .authentik-monitor .am-row-ok   { background: rgba(46,160,67,.15); color:#3fb950; }
    .authentik-monitor .am-row-warn { background: rgba(210,153,34,.15); color:#d29922; }
    .authentik-monitor .am-row-down { background: rgba(248,81,73,.15); color:#f85149; }
    .authentik-monitor .am-row-dim  { background: rgba(139,148,158,.08); color:#8b949e; }
    .authentik-monitor .am-name { min-width:11rem; font-weight:700; }
    .authentik-monitor .am-badge { font-weight:700; }
    .authentik-monitor .am-dot { width:.5rem; height:.5rem; border-radius:9999px; flex:none; display:inline-block; }
    .authentik-monitor .am-dot-ok   { background:#3fb950; }
    .authentik-monitor .am-dot-warn { background:#d29922; }
    .authentik-monitor .am-dot-down { background:#f85149; }
    .authentik-monitor .am-dot-dim  { background:#8b949e; }
</style>

<script>
    function authentikMonitor(initialGroups) {
        return {
            groups: initialGroups || {},
            lastRefresh: null,
            timer: null,

            init() {
                this.lastRefresh = new Date().toLocaleTimeString();
                this.timer = setInterval(() => this.poll(), 15000);
            },

            poll() {
                fetch('{{ route('authentik.monitor.data') }}', { headers: { Accept: 'application/json' } })
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

            hasAnyData() {
                return ['authentik', 'nginx', 'workers', 'worker_queue']
                    .some((s) => this.row(s));
            },

            rowClass(service, okStatuses = ['up']) {
                const r = this.row(service);
                if (!r) return 'dim';
                if (r.status === 'unknown') return 'dim';
                return okStatuses.includes(r.status) ? 'ok' : (r.status === 'degraded' ? 'warn' : 'down');
            },

            overallStatus() {
                const rows = ['authentik', 'nginx', 'workers', 'worker_queue']
                    .map((s) => this.row(s))
                    .filter((r) => r && r.status !== 'unknown');
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
    <form action="{{ route('authentik.monitor.settings.update') }}" method="POST" class="flex flex-wrap items-end gap-4">
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
            <label for="authentik_url" class="block text-sm font-medium text-gray-700">{{ __('Authentik URL') }}</label>
            <input type="text" name="authentik_url" id="authentik_url" value="{{ old('authentik_url', $settings?->authentik_url) }}"
                   placeholder="https://auth.uop.gr" class="mt-1 block w-56 border-gray-300 rounded-md shadow-sm font-mono text-sm">
            @error('authentik_url')
                <span class="text-red-500 text-sm">{{ $message }}</span>
            @enderror
        </div>
        <div>
            <label for="api_token" class="block text-sm font-medium text-gray-700">
                {{ __('API token') }}
                <span class="text-gray-400 font-normal">({{ $settings?->api_token ? __('set — leave blank to keep it') : __('not set') }})</span>
            </label>
            <input type="password" name="api_token" id="api_token" autocomplete="new-password"
                   placeholder="{{ $settings?->api_token ? '••••••••••••' : '' }}" class="mt-1 block w-56 border-gray-300 rounded-md shadow-sm font-mono text-sm">
            @error('api_token')
                <span class="text-red-500 text-sm">{{ $message }}</span>
            @enderror
        </div>
        <x-primary-button type="submit">{{ __('Save') }}</x-primary-button>
    </form>
</div>

<div class="authentik-monitor am-root" x-data="authentikMonitor(@js($statuses))">
    <div class="am-header flex flex-wrap items-center justify-between gap-3 px-4 py-3">
        <div class="flex items-center gap-2">
            <span class="am-dot" :class="'am-dot-' + overallStatus()"></span>
            <h3 class="am-title">{{ __('Authentik') }}</h3>
        </div>
        <div class="flex items-center gap-4">
            <span class="am-meta" x-text="'Last refresh: ' + (lastRefresh || '—') + '   Auto-refresh: 15s'"></span>
            <form action="{{ route('authentik.monitor.refresh') }}" method="POST">
                @csrf
                <x-secondary-button type="submit">{{ __('Refresh now') }}</x-secondary-button>
            </form>
        </div>
    </div>

    <div class="p-3 space-y-2">
        <template x-if="!hasAnyData()">
            <div class="am-panel p-6 text-center am-meta">
                {{ __('No data yet — save your Node IP above, then click Refresh now, or wait for the scheduled poll.') }}
            </div>
        </template>

        <template x-if="row('authentik')">
            <div class="am-row" :class="'am-row-' + rowClass('authentik')">
                <span class="am-dot" :class="'am-dot-' + rowClass('authentik')"></span>
                <span class="am-name">{{ __('Authentik') }}</span>
                <span x-text="row('authentik').status === 'up' ? 'UP' : (row('authentik').message || 'DOWN')"></span>
            </div>
        </template>

        <template x-if="row('nginx')">
            <div class="am-row" :class="'am-row-' + rowClass('nginx')">
                <span class="am-dot" :class="'am-dot-' + rowClass('nginx')"></span>
                <span class="am-name">{{ __('Nginx') }}</span>
                <template x-if="row('nginx').status === 'up'">
                    <span class="am-meta">
                        active=<span x-text="row('nginx').metrics?.active"></span>
                        (R=<span x-text="row('nginx').metrics?.reading"></span>
                        W=<span x-text="row('nginx').metrics?.writing"></span>
                        Wait=<span x-text="row('nginx').metrics?.waiting"></span>)
                    </span>
                </template>
                <template x-if="row('nginx').status !== 'up'">
                    <span class="am-badge">DOWN</span>
                </template>
            </div>
        </template>

        <template x-if="row('workers')">
            <div class="am-row" :class="'am-row-' + rowClass('workers')">
                <span class="am-dot" :class="'am-dot-' + rowClass('workers')"></span>
                <span class="am-name">{{ __('Workers') }}</span>
                <template x-if="row('workers').status === 'unknown'">
                    <span class="am-meta" x-text="row('workers').message"></span>
                </template>
                <template x-if="row('workers').status !== 'unknown'">
                    <span class="am-meta" x-text="(row('workers').metrics?.count ?? 0) + ' connected'"></span>
                </template>
            </div>
        </template>

        <template x-if="row('worker_queue')">
            <div class="am-row" :class="'am-row-' + rowClass('worker_queue')">
                <span class="am-dot" :class="'am-dot-' + rowClass('worker_queue')"></span>
                <span class="am-name">{{ __('Worker queue') }}</span>
                <template x-if="row('worker_queue').status === 'unknown'">
                    <span class="am-meta" x-text="row('worker_queue').message"></span>
                </template>
                <template x-if="row('worker_queue').status !== 'unknown'">
                    <span class="flex flex-wrap items-center gap-3 am-meta">
                        <span>running=<span x-text="row('worker_queue').metrics?.running"></span></span>
                        <span>queued=<span x-text="row('worker_queue').metrics?.queued"></span></span>
                        <span>rejected=<span x-text="row('worker_queue').metrics?.rejected"></span></span>
                        <span>error=<span x-text="row('worker_queue').metrics?.error"></span></span>
                    </span>
                </template>
            </div>
        </template>
    </div>
</div>
