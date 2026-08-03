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
    .authentik-monitor .am-ok-text   { color:#3fb950; }
    .authentik-monitor .am-warn-text { color:#d29922; }
    .authentik-monitor .am-down-text { color:#f85149; }
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

            rows(service) {
                const r = this.groups[service];
                return Array.isArray(r) ? r : [];
            },

            row(service) {
                return this.rows(service)[0] || null;
            },

            hasRows(service) {
                return this.rows(service).length > 0;
            },

            hasAnyData() {
                return ['keepalived', 'authentik', 'patroni', 'etcd', 'haproxy', 'nginx']
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

            singleStatus(service) {
                const r = this.row(service);
                if (!r || r.status === 'unknown') return 'dim';
                if (r.status === 'down') return 'down';
                if (r.status === 'degraded') return 'warn';
                return 'ok';
            },

            // ---- VIP / Keepalived ----
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

            // ---- Authentik ----
            authentikRowClass(row) {
                return row.status === 'up' ? 'ok' : 'down';
            },

            // ---- Nginx ----
            nginxMaxActive() {
                const ok = this.rows('nginx').filter((n) => n.status === 'up');
                return ok.length ? Math.max(...ok.map((n) => n.metrics?.active ?? 0)) : 0;
            },
            nginxBusiest(row) {
                const max = this.nginxMaxActive();
                return max > 1 && row.status === 'up' && (row.metrics?.active ?? 0) === max;
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
                if (row.status === 'down') return 'down';
                if (row.metrics?.healthy === false) return 'warn';
                return row.role === 'primary' ? 'ok' : 'dim';
            },
            patroniHistoryText() {
                const h = this.row('patroni_history');
                if (!h || !h.metrics) return null;
                const ts = h.metrics.timestamp ? new Date(h.metrics.timestamp).toLocaleString() : 'unknown time';
                return `last failover: TL ${h.metrics.timeline} — ${ts} → ${h.metrics.reason}`;
            },

            // ---- etcd ----
            etcdRowClass(row) {
                if (row.status !== 'up') return 'down';
                return row.metrics?.leader ? 'ok' : 'dim';
            },

            overallStatus() {
                const singles = ['workers', 'worker_queue']
                    .map((s) => this.row(s))
                    .filter((r) => r && r.status !== 'unknown');
                const all = [
                    ...this.rows('keepalived'), ...this.rows('authentik'),
                    ...this.rows('patroni'), ...this.rows('etcd'),
                    ...this.rows('haproxy'), ...this.rows('nginx'),
                    ...singles,
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

<div class="authentik-monitor am-root" x-data="authentikMonitor(@js($statuses))">
    <div class="am-header flex flex-wrap items-center justify-between gap-3 px-4 py-3">
        <div class="flex items-center gap-2">
            <span class="am-dot" :class="'am-dot-' + overallStatus()"></span>
            <h3 class="am-title">{{ __('Authentik HA Cluster') }}</h3>
        </div>
        <div class="flex items-center gap-4">
            <span class="am-meta" x-text="'Last refresh: ' + (lastRefresh || '—') + '   Auto-refresh: 15s'"></span>
            <form action="{{ route('authentik.monitor.refresh') }}" method="POST">
                @csrf
                <x-secondary-button type="submit">{{ __('Refresh now') }}</x-secondary-button>
            </form>
        </div>
    </div>

    <div class="p-3 space-y-3">
        <template x-if="!hasAnyData()">
            <div class="am-panel p-6 text-center am-meta">
                {{ __('No data yet — click Refresh now, or wait for the scheduled poll.') }}
            </div>
        </template>

        <!-- VIP / Keepalived -->
        <div class="am-panel p-3" x-show="hasRows('keepalived') || row('vip')">
            <div class="am-panel-title mb-2 flex items-center gap-2">
                <span class="am-dot" :class="'am-dot-' + panelStatus('keepalived', ['down', 'degraded'])"></span>
                <span>{{ __('VIP / Keepalived') }}</span>
            </div>
            <template x-if="row('vip')">
                <div class="am-row" :class="'am-row-' + (row('vip').status === 'up' ? 'ok' : 'down')">
                    <span class="am-dot" :class="'am-dot-' + (row('vip').status === 'up' ? 'ok' : 'down')"></span>
                    <span class="am-name">VIP <span x-text="row('vip').node_ip"></span></span>
                    <template x-if="row('vip').status === 'up'">
                        <span>&rarr; MASTER: <span class="am-badge" x-text="row('vip').metrics?.holder_name ?? '—'"></span></span>
                    </template>
                    <template x-if="row('vip').status !== 'up'">
                        <span class="am-badge">UNREACHABLE</span>
                    </template>
                </div>
            </template>
            <template x-for="node in rows('keepalived')" :key="node.node_ip">
                <div class="am-row" :class="'am-row-' + keepalivedRowState(node).cls">
                    <span class="am-dot" :class="'am-dot-' + keepalivedRowState(node).cls"></span>
                    <span class="am-name" x-text="node.node_name"></span>
                    <span class="am-meta" x-text="node.node_ip"></span>
                    <span class="am-badge" x-text="keepalivedRowState(node).label"></span>
                    <span class="am-meta">
                        priority=<span x-text="node.metrics?.effective_priority"></span><template x-if="node.status !== 'up'"><span> (base <span x-text="node.metrics?.base_priority"></span>)</span></template>
                    </span>
                </div>
            </template>
        </div>

        <!-- Nginx connections -->
        <div class="am-panel p-3" x-show="hasRows('nginx')">
            <div class="am-panel-title mb-2 flex items-center gap-2">
                <span class="am-dot" :class="'am-dot-' + panelStatus('nginx')"></span>
                <span>{{ __('Nginx connections') }}</span>
            </div>
            <template x-for="node in rows('nginx')" :key="node.node_ip">
                <div class="am-row" :class="node.status !== 'up' ? 'am-row-down' : (nginxBusiest(node) ? 'am-row-ok' : 'am-row-dim')">
                    <span class="am-dot" :class="node.status !== 'up' ? 'am-dot-down' : (nginxBusiest(node) ? 'am-dot-ok' : 'am-dot-dim')"></span>
                    <span class="am-name" x-text="node.node_name"></span>
                    <template x-if="node.status !== 'up'">
                        <span class="am-badge">UNREACHABLE</span>
                    </template>
                    <template x-if="node.status === 'up'">
                        <span class="flex flex-wrap items-center gap-3">
                            <span class="am-meta">active=<span class="am-badge" x-text="node.metrics?.active"></span></span>
                            <span class="am-meta">R=<span x-text="node.metrics?.reading"></span></span>
                            <span class="am-meta">W=<span x-text="node.metrics?.writing"></span></span>
                            <span class="am-meta">Wait=<span x-text="node.metrics?.waiting"></span></span>
                        </span>
                    </template>
                </div>
            </template>
        </div>

        <!-- Authentik backends -->
        <div class="am-panel p-3" x-show="hasRows('authentik')">
            <div class="am-panel-title mb-2 flex items-center gap-2">
                <span class="am-dot" :class="'am-dot-' + panelStatus('authentik')"></span>
                <span>{{ __('Authentik backends') }}</span>
            </div>
            <template x-for="row in rows('authentik')" :key="row.node_ip">
                <div class="am-row" :class="'am-row-' + authentikRowClass(row)">
                    <span class="am-dot" :class="'am-dot-' + authentikRowClass(row)"></span>
                    <span class="am-name" x-text="row.node_name"></span>
                    <span :class="row.status === 'up' ? 'am-ok-text' : 'am-down-text'" x-text="row.status === 'up' ? 'server UP' : 'server DOWN'"></span>
                </div>
            </template>
        </div>

        <!-- Workers / Worker Queue -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
            <div class="am-panel p-3" x-show="row('workers')">
                <div class="am-panel-title mb-2 flex items-center gap-2">
                    <span class="am-dot" :class="'am-dot-' + singleStatus('workers')"></span>
                    <span>{{ __('Authentik workers') }}</span>
                </div>
                <template x-if="row('workers')">
                    <div class="am-row" :class="'am-row-' + singleStatus('workers')">
                        <span class="am-dot" :class="'am-dot-' + singleStatus('workers')"></span>
                        <template x-if="row('workers').status === 'unknown'">
                            <span class="am-meta" x-text="row('workers').message"></span>
                        </template>
                        <template x-if="row('workers').status !== 'unknown'">
                            <span class="flex flex-wrap items-center gap-3">
                                <span class="am-badge" x-text="(row('workers').metrics?.count ?? 0) + '/' + (row('workers').metrics?.expected ?? 0) + ' connected'"></span>
                                <span class="am-meta" x-text="'present: ' + ((row('workers').metrics?.present || []).join(', ') || 'none')"></span>
                            </span>
                        </template>
                    </div>
                </template>
            </div>

            <div class="am-panel p-3" x-show="row('worker_queue')">
                <div class="am-panel-title mb-2 flex items-center gap-2">
                    <span class="am-dot" :class="'am-dot-' + singleStatus('worker_queue')"></span>
                    <span>{{ __('Authentik worker queue') }}</span>
                </div>
                <template x-if="row('worker_queue')">
                    <div class="am-row" :class="'am-row-' + singleStatus('worker_queue')">
                        <span class="am-dot" :class="'am-dot-' + singleStatus('worker_queue')"></span>
                        <template x-if="row('worker_queue').status === 'unknown'">
                            <span class="am-meta" x-text="row('worker_queue').message"></span>
                        </template>
                        <template x-if="row('worker_queue').status !== 'unknown'">
                            <span class="flex flex-wrap items-center gap-3 am-meta">
                                <span>running=<span x-text="row('worker_queue').metrics?.running"></span></span>
                                <span>queued=<span x-text="row('worker_queue').metrics?.queued"></span></span>
                                <span>rejected=<span x-text="row('worker_queue').metrics?.rejected"></span></span>
                                <span>error=<span x-text="row('worker_queue').metrics?.error"></span></span>
                                <span>done=<span x-text="row('worker_queue').metrics?.done"></span></span>
                            </span>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        <!-- HAProxy -->
        <div class="am-panel p-3" x-show="hasRows('haproxy')">
            <div class="am-panel-title mb-2 flex items-center gap-2">
                <span class="am-dot" :class="'am-dot-' + panelStatus('haproxy')"></span>
                <span>{{ __('HAProxy backends') }}</span>
            </div>
            <template x-for="row in rows('haproxy')" :key="row.node_ip">
                <div class="am-row" :class="'am-row-' + haproxyRowClass(row)">
                    <span class="am-dot" :class="'am-dot-' + haproxyRowClass(row)"></span>
                    <span class="am-name" x-text="row.node_name"></span>
                    <template x-if="row.status === 'down'">
                        <span class="am-badge">STATS UNREACHABLE</span>
                    </template>
                    <template x-if="row.status !== 'down'">
                        <span class="flex flex-wrap gap-3">
                            <template x-for="b in haproxyBackendSummary(row)" :key="b.pool">
                                <span :class="b.up === 0 ? 'am-down-text' : 'am-meta'">
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
            <div class="am-panel p-3" x-show="hasRows('patroni')">
                <div class="am-panel-title mb-2 flex items-center gap-2">
                    <span class="am-dot" :class="'am-dot-' + panelStatus('patroni', ['down', 'degraded'])"></span>
                    <span>{{ __('PostgreSQL / Patroni') }}</span>
                </div>
                <template x-for="row in rows('patroni')" :key="row.node_ip">
                    <div class="am-row" :class="'am-row-' + patroniRowClass(row)">
                        <span class="am-dot" :class="'am-dot-' + patroniRowClass(row)"></span>
                        <span class="am-name" x-text="row.node_name"></span>
                        <template x-if="row.status === 'down'">
                            <span class="am-badge">UNREACHABLE</span>
                        </template>
                        <template x-if="row.status !== 'down'">
                            <span class="flex flex-wrap items-center gap-3">
                                <span class="am-badge" x-text="row.role === 'primary' ? 'LEADER' : 'REPLICA'"></span>
                                <span class="am-meta">state=<span x-text="row.metrics?.state"></span></span>
                                <span class="am-meta">TL=<span x-text="row.metrics?.timeline"></span></span>
                                <template x-if="row.role !== 'primary' && row.metrics?.lag_bytes !== null && row.metrics?.lag_bytes !== undefined">
                                    <span class="am-meta">lag=<span x-text="fmtLag(row.metrics.lag_bytes)"></span></span>
                                </template>
                                <template x-if="row.metrics?.pending_restart">
                                    <span class="am-warn-text">restart pending</span>
                                </template>
                                <template x-if="row.metrics?.healthy === false">
                                    <span class="am-warn-text">⚠ stuck (not streaming)</span>
                                </template>
                            </span>
                        </template>
                    </div>
                </template>
                <template x-if="patroniHistoryText()">
                    <div class="am-meta mt-2 px-1" x-text="patroniHistoryText()"></div>
                </template>
            </div>

            <div class="am-panel p-3" x-show="hasRows('etcd')">
                <div class="am-panel-title mb-2 flex items-center gap-2">
                    <span class="am-dot" :class="'am-dot-' + panelStatus('etcd')"></span>
                    <span>{{ __('etcd cluster') }}</span>
                </div>
                <template x-for="row in rows('etcd')" :key="row.node_ip">
                    <div class="am-row" :class="'am-row-' + etcdRowClass(row)">
                        <span class="am-dot" :class="'am-dot-' + etcdRowClass(row)"></span>
                        <span class="am-name" x-text="row.node_name"></span>
                        <template x-if="row.status !== 'up'">
                            <span class="am-badge">UNREACHABLE</span>
                        </template>
                        <template x-if="row.status === 'up'">
                            <span class="flex flex-wrap items-center gap-3">
                                <span class="am-badge" x-text="row.metrics?.leader ? 'LEADER' : 'FOLLOWER'"></span>
                                <span class="am-meta">term=<span x-text="row.metrics?.raft_term"></span></span>
                                <span class="am-meta">db=<span x-text="row.metrics?.db_kb"></span>KB</span>
                            </span>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
