@php
    $livewireKey = 'flow-error-logs-'.str_replace(['/', '\\'], '-', $this->flowRef);
@endphp

<x-filament-panels::page>
    <div class="@container min-w-0 w-full max-w-full">
        @livewire(\App\Livewire\FlowErrorLogsPanel::class, ['flowRef' => $this->flowRef], key($livewireKey))
    </div>
</x-filament-panels::page>
