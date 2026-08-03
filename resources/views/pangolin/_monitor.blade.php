<style>
    .pangolin-monitor.pm-root { background:#0d1117; color:#e6edf3; border-radius:.5rem; overflow:hidden; border:1px solid #30363d; }
    .pangolin-monitor .pm-header { background:#161b22; border-bottom:1px solid #30363d; }
    .pangolin-monitor .pm-title { color:#58a6ff; font-weight:700; font-size:.875rem; letter-spacing:.02em; }
    .pangolin-monitor .pm-meta { color:#8b949e; font-size:.75rem; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
    .pangolin-monitor .pm-panel { background:#161b22; border:1px solid #30363d; border-radius:.375rem; }
    .pangolin-monitor .pm-panel-title { color:#58a6ff; font-weight:700; font-size:.75rem; letter-spacing:.05em; text-transform:uppercase; }
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
    .pangolin-monitor .pm-ok-text   { color:#3fb950; }
    .pangolin-monitor .pm-warn-text { color:#d29922; }
    .pangolin-monitor .pm-down-text { color:#f85149; }
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

            rows(service) {
                const r = this.groups[service];
                return Array.isArray(r) ? r : [];
            },

            hasRows(service) {
                return this.rows(service).length > 0;
            },

            hasAnyData() {
                return ['keepalived', 'pangolin', 'patroni', 'etcd', 'haproxy', 'newt']
                    .some((s) => this.hasRows(s));
            },

            fmtLag(bytes) {
                if (bytes === null || bytes === undefined) return '';
                if (bytes < 1024) return bytes + 'B';
                if (bytes < 1024 * 1024) return Math.floor(bytes / 1024) + 'KB';
                return Math.floor(bytes / (1024 * 1024)) + 'MB';
            },

            panelStatus(service, failStatuses = ['down']) {
                const rs = this.rows(service);
                if (!rs.length) return 'dim';
                const bad = rs.filter((r) => failStatuses.includes(r.status)).length;
                if (bad === 0) return 'ok';
                if (bad >= rs.length) return 'down';
                return 'warn';
            },

            // ---- VIP / Keepalived ----
            vipRow() {
                return this.rows('vip')[0] || null;
            },
            keepalivedMaster() {
                const up = this.rows('keepalived').filter((n) => n.status === 'up');
                if (!up.length) return null;
                return up.reduce((best, n) =>
                    (n.metrics?.effective_priority ?? -Infinity) > (best.metrics?.effective_priority ?? -Infinity) ? n : best
                );
            },
            keepalivedRowState(node) {
                const master = this.keepalivedMaster();
                if (master && master.node_ip === node.node_ip) return { label: 'MASTER', cls: 'ok' };
                if (node.status === 'up') return { label: 'BACKUP', cls: 'dim' };
                return { label: 'FAULT', cls: 'down' };
            },

            // ---- Pangolin / Gerbil ----
            pangolinGerbilRows() {
                const gerbilByIp = Object.fromEntries(this.rows('gerbil').map((g) => [g.node_ip, g]));
                return this.rows('pangolin').map((p) => ({
                    name: p.node_name,
                    ip: p.node_ip,
                    api_ok: p.status === 'up',
                    gerbil_ok: (gerbilByIp[p.node_ip]?.status) === 'up',
                }));
            },
            pangolinRowClass(row) {
                if (row.api_ok && row.gerbil_ok) return 'ok';
                if (row.api_ok || row.gerbil_ok) return 'warn';
                return 'down';
            },

            // ---- HAProxy ----
            haproxyBackendSummary(row) {
                const backends = row.metrics?.backends || {};
                return Object.entries(backends).map(([pool, servers]) => ({
                    pool,
                    up: servers.filter((s) => s.status === 'UP').length,
                    total: servers.length,
                }));
            },
            haproxyRowClass(row) {
                if (row.status === 'down') return 'down';
                if (row.status === 'degraded') return 'warn';
                return 'ok';
            },

            // ---- Patroni ----
            patroniRowClass(row) {
                if (row.status !== 'up') return 'down';
                return row.role === 'primary' ? 'ok' : 'dim';
            },

            // ---- etcd ----
            etcdRowClass(row) {
                if (row.status !== 'up') return 'down';
                return row.metrics?.leader ? 'ok' : 'dim';
            },

            // ---- Newt ----
            newtRowClass(row) {
                return row.status === 'up' ? 'ok' : 'down';
            },

            overallStatus() {
                const all = [
                    ...this.rows('keepalived'), ...this.rows('pangolin'), ...this.rows('gerbil'),
                    ...this.rows('patroni'), ...this.rows('etcd'), ...this.rows('haproxy'), ...this.rows('newt'),
                ];
                if (!all.length) return 'dim';
                const bad = all.filter((r) => r.status !== 'up').length;
                if (bad === 0) return 'ok';
                if (bad >= all.length) return 'down';
                return 'warn';
            },
        };
    }
</script>

<div class="pangolin-monitor pm-root" x-data="pangolinMonitor(@js($statuses))">
    <div class="pm-header flex flex-wrap items-center justify-between gap-3 px-4 py-3">
        <div class="flex items-center gap-2">
            <span class="pm-dot" :class="'pm-dot-' + overallStatus()"></span>
            <h3 class="pm-title">{{ __('Pangolin HA Cluster') }}</h3>
        </div>
        <div class="flex items-center gap-4">
            <span class="pm-meta" x-text="'Last refresh: ' + (lastRefresh || '—') + '   Auto-refresh: 15s'"></span>
            <form action="{{ route('pangolin.monitor.refresh') }}" method="POST">
                @csrf
                <x-secondary-button type="submit">{{ __('Refresh now') }}</x-secondary-button>
            </form>
        </div>
    </div>

    <div class="p-3 space-y-3">
        <template x-if="!hasAnyData()">
            <div class="pm-panel p-6 text-center pm-meta">
                {{ __('No data yet — click Refresh now, or wait for the scheduled poll.') }}
            </div>
        </template>

        <!-- VIP / Keepalived -->
        <div class="pm-panel p-3" x-show="hasRows('keepalived') || vipRow()">
            <div class="pm-panel-title mb-2 flex items-center gap-2">
                <span class="pm-dot" :class="'pm-dot-' + panelStatus('keepalived', ['down', 'degraded'])"></span>
                <span>{{ __('VIP / Keepalived') }}</span>
            </div>
            <template x-if="vipRow()">
                <div class="pm-row" :class="'pm-row-' + (vipRow().status === 'up' ? 'ok' : 'down')">
                    <span class="pm-dot" :class="'pm-dot-' + (vipRow().status === 'up' ? 'ok' : 'down')"></span>
                    <span class="pm-name">VIP <span x-text="vipRow().node_ip"></span></span>
                    <template x-if="vipRow().status === 'up'">
                        <span>&rarr; MASTER: <span class="pm-badge" x-text="keepalivedMaster()?.node_name ?? '—'"></span></span>
                    </template>
                    <template x-if="vipRow().status !== 'up'">
                        <span class="pm-badge">UNREACHABLE</span>
                    </template>
                </div>
            </template>
            <template x-for="node in rows('keepalived')" :key="node.node_ip">
                <div class="pm-row" :class="'pm-row-' + keepalivedRowState(node).cls">
                    <span class="pm-dot" :class="'pm-dot-' + keepalivedRowState(node).cls"></span>
                    <span class="pm-name" x-text="node.node_name"></span>
                    <span class="pm-meta" x-text="node.node_ip"></span>
                    <span class="pm-badge" x-text="keepalivedRowState(node).label"></span>
                    <span class="pm-meta">
                        priority=<span x-text="node.metrics?.effective_priority"></span><template x-if="node.status !== 'up'"><span> (base <span x-text="node.metrics?.base_priority"></span>)</span></template>
                    </span>
                </div>
            </template>
        </div>

        <!-- Pangolin / Gerbil -->
        <div class="pm-panel p-3" x-show="hasRows('pangolin')">
            <div class="pm-panel-title mb-2 flex items-center gap-2">
                <span class="pm-dot" :class="'pm-dot-' + panelStatus('pangolin')"></span>
                <span>{{ __('Pangolin / Gerbil backends') }}</span>
            </div>
            <template x-for="row in pangolinGerbilRows()" :key="row.ip">
                <div class="pm-row" :class="'pm-row-' + pangolinRowClass(row)">
                    <span class="pm-dot" :class="'pm-dot-' + pangolinRowClass(row)"></span>
                    <span class="pm-name" x-text="row.name"></span>
                    <span :class="row.api_ok ? 'pm-ok-text' : 'pm-down-text'">API <span x-text="row.api_ok ? 'UP' : 'DOWN'"></span></span>
                    <span :class="row.gerbil_ok ? 'pm-ok-text' : 'pm-down-text'">gerbil <span x-text="row.gerbil_ok ? 'UP' : 'DOWN'"></span></span>
                </div>
            </template>
        </div>

        <!-- HAProxy -->
        <div class="pm-panel p-3" x-show="hasRows('haproxy')">
            <div class="pm-panel-title mb-2 flex items-center gap-2">
                <span class="pm-dot" :class="'pm-dot-' + panelStatus('haproxy')"></span>
                <span>{{ __('HAProxy backends') }}</span>
            </div>
            <template x-for="row in rows('haproxy')" :key="row.node_ip">
                <div class="pm-row" :class="'pm-row-' + haproxyRowClass(row)">
                    <span class="pm-dot" :class="'pm-dot-' + haproxyRowClass(row)"></span>
                    <span class="pm-name" x-text="row.node_name"></span>
                    <template x-if="row.status === 'down'">
                        <span class="pm-badge">STATS UNREACHABLE</span>
                    </template>
                    <template x-if="row.status !== 'down'">
                        <span class="flex flex-wrap gap-3">
                            <template x-for="b in haproxyBackendSummary(row)" :key="b.pool">
                                <span :class="b.up === 0 ? 'pm-down-text' : 'pm-meta'">
                                    <span x-text="b.pool"></span>: <span x-text="b.up + '/' + b.total"></span>
                                </span>
                            </template>
                        </span>
                    </template>
                </div>
            </template>
        </div>

        <!-- Patroni + etcd -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
            <div class="pm-panel p-3" x-show="hasRows('patroni')">
                <div class="pm-panel-title mb-2 flex items-center gap-2">
                    <span class="pm-dot" :class="'pm-dot-' + panelStatus('patroni')"></span>
                    <span>{{ __('PostgreSQL / Patroni') }}</span>
                </div>
                <template x-for="row in rows('patroni')" :key="row.node_ip">
                    <div class="pm-row" :class="'pm-row-' + patroniRowClass(row)">
                        <span class="pm-dot" :class="'pm-dot-' + patroniRowClass(row)"></span>
                        <span class="pm-name" x-text="row.node_name"></span>
                        <template x-if="row.status !== 'up'">
                            <span class="pm-badge">UNREACHABLE</span>
                        </template>
                        <template x-if="row.status === 'up'">
                            <span class="flex flex-wrap items-center gap-3">
                                <span class="pm-badge" x-text="row.role === 'primary' ? 'LEADER' : 'REPLICA'"></span>
                                <span class="pm-meta">state=<span x-text="row.metrics?.state"></span></span>
                                <span class="pm-meta">TL=<span x-text="row.metrics?.timeline"></span></span>
                                <template x-if="row.role !== 'primary' && row.metrics?.lag_bytes !== null && row.metrics?.lag_bytes !== undefined">
                                    <span class="pm-meta">lag=<span x-text="fmtLag(row.metrics.lag_bytes)"></span></span>
                                </template>
                                <template x-if="row.metrics?.pending_restart">
                                    <span class="pm-warn-text">restart pending</span>
                                </template>
                            </span>
                        </template>
                    </div>
                </template>
            </div>

            <div class="pm-panel p-3" x-show="hasRows('etcd')">
                <div class="pm-panel-title mb-2 flex items-center gap-2">
                    <span class="pm-dot" :class="'pm-dot-' + panelStatus('etcd')"></span>
                    <span>{{ __('etcd cluster') }}</span>
                </div>
                <template x-for="row in rows('etcd')" :key="row.node_ip">
                    <div class="pm-row" :class="'pm-row-' + etcdRowClass(row)">
                        <span class="pm-dot" :class="'pm-dot-' + etcdRowClass(row)"></span>
                        <span class="pm-name" x-text="row.node_name"></span>
                        <template x-if="row.status !== 'up'">
                            <span class="pm-badge">UNREACHABLE</span>
                        </template>
                        <template x-if="row.status === 'up'">
                            <span class="flex flex-wrap items-center gap-3">
                                <span class="pm-badge" x-text="row.metrics?.leader ? 'LEADER' : 'FOLLOWER'"></span>
                                <span class="pm-meta">term=<span x-text="row.metrics?.raft_term"></span></span>
                                <span class="pm-meta">db=<span x-text="row.metrics?.db_kb"></span>KB</span>
                            </span>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        <!-- Newt agents -->
        <div class="pm-panel p-3" x-show="hasRows('newt')">
            <div class="pm-panel-title mb-2 flex items-center gap-2">
                <span class="pm-dot" :class="'pm-dot-' + panelStatus('newt')"></span>
                <span>{{ __('Newt agents') }}</span>
            </div>
            <template x-for="row in rows('newt')" :key="row.node_ip">
                <div class="pm-row" :class="'pm-row-' + newtRowClass(row)">
                    <span class="pm-dot" :class="'pm-dot-' + newtRowClass(row)"></span>
                    <span class="pm-name" x-text="row.node_name"></span>
                    <span class="pm-meta" x-text="row.node_ip"></span>
                    <span class="pm-badge" x-text="row.status === 'up' ? 'REACHABLE' : 'UNREACHABLE'"></span>
                    <span class="pm-meta" x-text="row.message || ''"></span>
                </div>
            </template>
        </div>
    </div>
</div>
