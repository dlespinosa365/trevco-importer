<div class="w-full min-w-0 sm:table sm:table-fixed sm:w-full">
    <div
        class="mb-4 min-w-0 sm:mb-0 sm:table-cell sm:w-[60%] sm:max-h-[calc(100dvh-14rem)] sm:align-top sm:overflow-y-auto sm:pr-3"
    >
        {{ $this->table }}
    </div>

    <div
        class="min-w-0 border-t border-gray-200 pt-4 sm:table-cell sm:w-[40%] sm:border-l sm:border-t-0 sm:pl-4 sm:pt-0 sm:align-top dark:border-white/10"
    >
        <x-filament::section :heading="__('Execution details')">
            @if ($this->selectedRowKey === '')
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Select a row to inspect steps.') }}</p>
            @elseif (count($stepTabs) === 0)
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No step data for this entry.') }}</p>
            @else
                <x-filament::tabs :label="__('Steps')" class="w-full min-w-0 overflow-x-auto pb-1">
                    @foreach ($stepTabs as $tab)
                        <x-filament::tabs.item
                            wire:click="setActiveStepTab('{{ $tab['key'] }}')"
                            wire:key="flow-error-step-tab-{{ $tab['key'] }}"
                            :active="$tab['is_active']"
                        >
                            {{ $tab['label'] }}
                        </x-filament::tabs.item>
                    @endforeach
                </x-filament::tabs>

                <div
                    class="mt-4 max-h-[min(42rem,calc(100dvh-16rem))] space-y-4 overflow-y-auto pr-1"
                    wire:key="flow-error-step-body-{{ $this->activeStepKey }}"
                >
                    {{-- Input --}}
                    <div
                        class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900/40"
                    >
                        <div
                            class="border-b border-gray-200 bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-900 dark:border-white/10 dark:bg-white/5 dark:text-white"
                        >
                            {{ __('Input') }}
                        </div>
                        <div class="space-y-3 p-3">
                            @forelse ($activeStepInputSections as $section)
                                <div class="overflow-hidden rounded-md border border-gray-100 dark:border-white/5">
                                    <div
                                        class="bg-gray-100/80 px-2 py-1 text-xs font-medium text-gray-800 dark:bg-white/10 dark:text-gray-200"
                                    >
                                        {{ $section['heading'] }}
                                    </div>
                                    <pre
                                        class="max-h-64 overflow-auto whitespace-pre-wrap break-words bg-gray-950/5 p-2 font-mono text-xs leading-relaxed text-gray-950 dark:bg-white/5 dark:text-white"
                                    >{{ $section['body'] }}</pre>
                                </div>
                            @empty
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('No input captured.') }}</p>
                            @endforelse
                        </div>
                    </div>

                    {{-- Output --}}
                    <div
                        class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900/40"
                    >
                        <div
                            class="border-b border-gray-200 bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-900 dark:border-white/10 dark:bg-white/5 dark:text-white"
                        >
                            {{ __('Output') }}
                        </div>
                        <div class="space-y-3 p-3">
                            @forelse ($activeStepOutputSections as $section)
                                <div class="overflow-hidden rounded-md border border-gray-100 dark:border-white/5">
                                    <div
                                        class="bg-gray-100/80 px-2 py-1 text-xs font-medium text-gray-800 dark:bg-white/10 dark:text-gray-200"
                                    >
                                        {{ $section['heading'] }}
                                    </div>
                                    <pre
                                        class="max-h-64 overflow-auto whitespace-pre-wrap break-words bg-gray-950/5 p-2 font-mono text-xs leading-relaxed text-gray-950 dark:bg-white/5 dark:text-white"
                                    >{{ $section['body'] }}</pre>
                                </div>
                            @empty
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('No output captured.') }}</p>
                            @endforelse
                        </div>
                    </div>

                    {{-- Error --}}
                    <div
                        class="overflow-hidden rounded-lg border border-red-200 bg-red-50/40 dark:border-red-900/40 dark:bg-red-950/20"
                    >
                        <div
                            class="border-b border-red-200/80 bg-red-100/60 px-3 py-2 text-xs font-semibold text-red-900 dark:border-red-800 dark:bg-red-900/40 dark:text-red-100"
                        >
                            {{ __('Error') }}
                        </div>
                        <pre
                            class="max-h-64 overflow-auto whitespace-pre-wrap break-words p-3 font-mono text-xs leading-relaxed text-red-950 dark:text-red-100"
                        >{{ $activeStepErrorText !== '' ? $activeStepErrorText : __('No error message for this step.') }}</pre>
                    </div>
                </div>
            @endif
        </x-filament::section>
    </div>
</div>
