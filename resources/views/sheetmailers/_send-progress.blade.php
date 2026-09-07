<div class="mb-6"
     x-data="sheetmailerSendProgress(@js($sendBatch), '{{ route('sheetmailers.send-status', [$sheetmailer, $sendBatch['id']]) }}')"
     x-init="init()">
    <div class="p-4 border rounded-md transition-colors"
         :class="finished ? (failed > 0 ? 'bg-yellow-50 border-yellow-300' : 'bg-green-50 border-green-300') : 'bg-blue-50 border-blue-300'">
        <div class="flex items-center justify-between text-sm mb-2">
            <span class="font-medium" x-text="statusText()"></span>
            <span x-text="progress + '%'"></span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
            <div class="h-2 transition-all duration-500"
                 :class="failed > 0 ? 'bg-yellow-500' : 'bg-green-600'"
                 :style="`width: ${progress}%`"></div>
        </div>
    </div>
</div>

<script>
    function sheetmailerSendProgress(initial, pollUrl) {
        return {
            ...initial,
            finished: false,
            timer: null,

            init() {
                this.timer = setInterval(() => this.poll(), 1500);
            },

            poll() {
                fetch(pollUrl, { headers: { Accept: 'application/json' } })
                    .then((r) => r.json())
                    .then((data) => {
                        Object.assign(this, data);
                        if (this.finished && this.timer) {
                            clearInterval(this.timer);
                            this.timer = null;
                        }
                    })
                    .catch(() => {});
            },

            statusText() {
                if (this.finished) {
                    return this.failed > 0
                        ? `Done - ${this.processed - this.failed} sent, ${this.failed} failed`
                        : `Done - ${this.processed} sent`;
                }
                return `Sending... ${this.processed}/${this.total}`;
            },
        };
    }
</script>
