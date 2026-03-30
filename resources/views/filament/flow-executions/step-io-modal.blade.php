@php
    /** @var \App\Models\StepExecution $record */
    $input = $record->input ?? [];
    $output = $record->output ?? [];
    $logs = $record->logs ?? [];
    $inputJson = json_encode($input, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    $outputJson = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    $logsJson = json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
@endphp

<div class="fi-sc fi-sc-has-gap fi-gap-y-6">
    <div>
        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('Error') }}</h3>
        <pre class="mt-2 max-h-40 overflow-auto rounded-lg bg-gray-950/5 p-3 text-xs leading-relaxed text-gray-950 dark:bg-white/5 dark:text-white">{{ $record->error_message ?: '—' }}</pre>
    </div>
    <div>
        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('Input') }}</h3>
        <pre class="mt-2 max-h-72 overflow-auto rounded-lg bg-gray-950/5 p-3 text-xs leading-relaxed text-gray-950 dark:bg-white/5 dark:text-white">{!! $inputJson !!}</pre>
    </div>
    <div>
        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('Output') }}</h3>
        <pre class="mt-2 max-h-72 overflow-auto rounded-lg bg-gray-950/5 p-3 text-xs leading-relaxed text-gray-950 dark:bg-white/5 dark:text-white">{!! $outputJson !!}</pre>
    </div>
    <div>
        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('Logs') }}</h3>
        <pre class="mt-2 max-h-72 overflow-auto rounded-lg bg-gray-950/5 p-3 text-xs leading-relaxed text-gray-950 dark:bg-white/5 dark:text-white">{!! $logsJson !!}</pre>
    </div>
</div>
