@push('title')
    <title>Confirm</title>
@endpush
<x-app-layout>
<div class="container mx-auto px-4">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Confirm') }}
        </h2>
    </x-slot>
    @include('sheetmailers._steps', ['step' => 3])

    <div class="flex flex-wrap gap-3 mt-4 mb-2 text-sm">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-green-50 text-green-800 border border-green-200">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
            {{ $emailCount }} {{ __('valid recipient(s)') }}
        </span>
        @if(!empty($nonEmails))
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-red-50 text-red-800 border border-red-200">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
            {{ count($nonEmails) }} {{ __('invalid entr(y/ies) - skipped') }}
        </span>
        @endif
    </div>

    <!-- Email preview card -->
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
            <p class="text-xs text-gray-500">{{ __('From') }}: <span class="text-gray-700">Πανεπιστήμιο Πελοποννήσου &lt;noreply@uop.gr&gt;</span></p>
            <p class="text-sm font-semibold text-gray-900 mt-0.5">{{ $sheetmailer->subject }}</p>
        </div>
        <div class="email-body-preview px-4 py-4 text-sm text-gray-800 leading-relaxed">{!! $sheetmailer->body !!}</div>
        @if($sheetmailer->signature)
            <div class="email-body-preview px-4 pb-4 text-sm text-gray-500 italic">{!! $sheetmailer->signature !!}</div>
        @endif
    </div>
    @if(!empty($placeholderKeys))
    <p class="mt-2 text-xs text-gray-500">
        {{ __('The preview above shows the raw template - each recipient below gets their own') }}
        {{ implode(', ', array_map([\App\Services\Sheetmailers\PlaceholderReplacer::class, 'token'], $placeholderKeys)) }}
        {{ __('substituted in.') }}
    </p>
    @endif

    @if(!empty($nonEmails))
    <div class="mt-4 bg-red-50 border border-red-200 rounded-lg overflow-hidden">
        <div class="px-4 py-2 text-sm font-semibold text-red-800 border-b border-red-200">
            {{ __('Skipped - not valid email addresses') }}
        </div>
        <div class="max-h-40 overflow-y-auto divide-y divide-red-100">
            @foreach($nonEmails as $non_email)
                <div class="px-4 py-1.5 text-sm text-red-700">{{ $non_email }}</div>
            @endforeach
        </div>
    </div>
    @endif

    @if($emailCount > 0)
    <form x-data="sheetmailerRecipientPreview('{{ route('sheetmailers.preview-recipient', [$sheetmailer, 999999999]) }}')"
          action="{{ route('sheetmailers.send', $sheetmailer) }}" method="POST">
        @csrf
        <input type="hidden" name="keep_present" value="1">

        <div class="mt-5 flex items-center justify-between">
            <a href="{{ route('sheetmailers.edit', $sheetmailer) }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500 focus:bg-gray-500 active:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mx-1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                Go Back
            </a>
            <div class="flex items-center gap-3">
                <button type="submit" formaction="{{ route('sheetmailers.dry-run', $sheetmailer) }}"
                        title="Renders the first, middle and last recipient's email and logs it - nothing is sent."
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 focus:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mx-1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                    Dry Run (no emails sent)
                </button>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-500 focus:bg-green-500 active:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mx-1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                      </svg>
                      Send Selected Emails
                </button>
            </div>
        </div>

        <div class="mt-4 bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2.5 bg-gray-50 border-b border-gray-200">
                <span class="text-sm font-semibold text-gray-700">{{ __('Recipients') }} ({{ $emailCount }})</span>
                <label class="inline-flex items-center text-xs text-gray-600 cursor-pointer">
                    <input type="checkbox" checked class="rounded"
                           @change="document.querySelectorAll('.recipient-checkbox').forEach(cb => cb.checked = $event.target.checked)">
                    <span class="ml-1.5">{{ __('Select all') }}</span>
                </label>
            </div>
            <div class="max-h-96 overflow-y-auto overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 sticky top-0">
                        <tr>
                            <th class="w-10 px-4 py-2"></th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Email') }}</th>
                            @foreach($placeholderKeys as $key)
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ \App\Services\Sheetmailers\PlaceholderReplacer::token($key) }}</th>
                            @endforeach
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Preview') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($emails as $correspondent)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2">
                                <input type="checkbox" name="keep[]" value="{{ $loop->index }}" class="recipient-checkbox rounded" checked>
                            </td>
                            <td class="px-2 py-2 text-gray-400">{{ $loop->iteration }}</td>
                            <td class="px-4 py-2 text-gray-800">{{ $correspondent['email'] }}</td>
                            @foreach($placeholderKeys as $key)
                            <td class="px-4 py-2 text-gray-500">{{ $correspondent['placeholders'][$key] ?? '' }}</td>
                            @endforeach
                            <td class="px-4 py-2">
                                <button type="button" class="text-indigo-600 hover:text-indigo-800"
                                        title="{{ __('Preview') }}" aria-label="{{ __('Preview') }}"
                                        @click="openPreview({{ $loop->index }})">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Per-recipient preview panel: shows this row's actual merged subject/body,
             never sent - a spot-check that mail-merge data lined up correctly for this
             specific recipient, since Dry Run only samples first/middle/last. -->
        <div x-show="previewOpen" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-gray-500/75 px-4"
             @keydown.escape.window="previewOpen = false">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[85vh] overflow-y-auto" @click.outside="previewOpen = false">
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-800">{{ __('Preview for') }} <span x-text="previewEmail"></span></h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600 text-xl leading-none" @click="previewOpen = false">&times;</button>
                </div>
                <div class="px-4 py-4">
                    <template x-if="previewLoading">
                        <p class="text-sm text-gray-500">{{ __('Loading...') }}</p>
                    </template>
                    <template x-if="previewError">
                        <p class="text-sm text-red-600" x-text="previewError"></p>
                    </template>
                    <template x-if="!previewLoading && !previewError">
                        <div>
                            <p class="text-xs text-gray-500">{{ __('Subject') }}</p>
                            <p class="text-sm font-semibold text-gray-900 mb-3" x-text="previewSubject"></p>
                            <p class="text-xs text-gray-500 mb-1">{{ __('Body') }}</p>
                            <div class="email-body-preview text-sm text-gray-800 leading-relaxed border border-gray-100 rounded p-3" x-html="previewBody"></div>
                            <template x-if="previewSignature">
                                <div class="email-body-preview text-sm text-gray-500 italic mt-3" x-html="previewSignature"></div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </form>

    <script>
        function sheetmailerRecipientPreview(previewUrlTemplate) {
            return {
                previewOpen: false,
                previewLoading: false,
                previewError: '',
                previewEmail: '',
                previewSubject: '',
                previewBody: '',
                previewSignature: '',

                openPreview(index) {
                    this.previewOpen = true;
                    this.previewLoading = true;
                    this.previewError = '';

                    fetch(previewUrlTemplate.replace(/\d+$/, index), { headers: { Accept: 'application/json' } })
                        .then((r) => {
                            if (!r.ok) throw new Error();
                            return r.json();
                        })
                        .then((data) => {
                            this.previewEmail = data.email;
                            this.previewSubject = data.subject;
                            this.previewBody = data.body;
                            this.previewSignature = data.signature;
                        })
                        .catch(() => {
                            this.previewError = 'Could not load the preview for this recipient.';
                        })
                        .finally(() => {
                            this.previewLoading = false;
                        });
                },
            };
        }
    </script>
    @else
    <div class="mt-4">
        <a href="{{ route('sheetmailers.edit', $sheetmailer) }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500 focus:bg-gray-500 active:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mx-1">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            Go Back
        </a>
    </div>
    @endif
</div>
</x-app-layout>
