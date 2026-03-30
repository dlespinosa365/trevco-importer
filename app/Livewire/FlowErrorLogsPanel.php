<?php

namespace App\Livewire;

use App\Models\FlowExecution;
use App\Models\StepExecution;
use App\Support\HumanReadablePayloadSections;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Livewire\Component;

final class FlowErrorLogsPanel extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public string $flowRef = '';

    public string $selectedRowKey = '';

    /**
     * Active detail tab: `step:{id}` for a {@see StepExecution}, or `run` for run-level-only snapshots.
     */
    public string $activeStepKey = '';

    public function mount(string $flowRef): void
    {
        $this->flowRef = $flowRef;
    }

    public function selectRow(string $key): void
    {
        $this->selectedRowKey = $key;
        $this->activeStepKey = $this->defaultActiveStepKeyForSelection($key);
    }

    public function setActiveStepTab(string $key): void
    {
        if ($this->selectedRowKey === '') {
            return;
        }

        if ($key === 'run') {
            if (! str_starts_with($this->selectedRowKey, 'run:')) {
                throw new InvalidArgumentException('Invalid run tab context.');
            }
            $this->activeStepKey = 'run';

            return;
        }

        if (! preg_match('/^step:(\d+)$/', $key, $matches)) {
            throw new InvalidArgumentException('Invalid step tab.');
        }

        $step = StepExecution::query()->find((int) $matches[1]);
        if ($step === null || ! $this->executionBelongsToFlow($step->flow_execution_id)) {
            return;
        }

        $expectedExecutionId = $this->resolvedFlowExecutionIdForSelection();
        if ($expectedExecutionId === null || $step->flow_execution_id !== $expectedExecutionId) {
            return;
        }

        $this->activeStepKey = $key;
    }

    public function deleteRow(string $key): void
    {
        if (str_starts_with($key, 'step:')) {
            $id = (int) substr($key, 5);
            $step = StepExecution::query()->find($id);
            if ($step !== null && $this->executionBelongsToFlow($step->flow_execution_id)) {
                $step->delete();
            }
        } elseif (str_starts_with($key, 'run:')) {
            $id = (int) substr($key, 4);
            $run = FlowExecution::query()->find($id);
            if ($run !== null && $run->flow_ref === $this->flowRef) {
                $run->deleteRecursively();
            }
        }

        if ($this->selectedRowKey === $key) {
            $this->selectedRowKey = '';
            $this->activeStepKey = '';
        }

        $this->flushCachedTableRecords();
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->errorRowsForTable())
            ->columns([
                TextColumn::make('run_label')
                    ->label(__('Run'))
                    ->description(fn (array $record): ?string => $record['kind'] === 'step'
                        ? '('.__('step').')'
                        : '('.__('run').')'),
                TextColumn::make('description')
                    ->label(__('Error'))
                    ->limit(200)
                    ->tooltip(fn (array $record): string => $record['description']),
            ])
            ->recordActions([
                Action::make('delete')
                    ->label(__('Delete'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('Delete error log'))
                    ->modalDescription(__('Remove this error log from the database?'))
                    ->action(function (array $record): void {
                        $this->deleteRow($record['key']);
                    }),
            ])
            ->recordAction('selectRow')
            ->recordClasses(fn (array $record): ?string => $this->selectedRowKey === $record['key']
                ? 'bg-primary-500/10 dark:bg-primary-400/10'
                : null)
            ->paginated(false)
            ->striped()
            ->emptyStateHeading(__('No error logs for this flow yet.'))
            ->recordTitle(fn (Model|array $record): string => is_array($record)
                ? (string) ($record['run_label'] ?? '')
                : (string) ($this->getTableRecordTitle($record) ?? ''));
    }

    /**
     * @return array<string, array{key: string, run_label: string, description: string, kind: string}>
     */
    public function errorRowsForTable(): array
    {
        return $this->errorRows()->keyBy('key')->all();
    }

    /**
     * @return Collection<int, array{key: string, run_label: string, description: string, kind: string}>
     */
    private function errorRows(): Collection
    {
        $executionIds = FlowExecution::query()
            ->where('flow_ref', $this->flowRef)
            ->pluck('id');

        if ($executionIds->isEmpty()) {
            return collect();
        }

        $rows = collect();

        $steps = StepExecution::query()
            ->whereIn('flow_execution_id', $executionIds)
            ->where(function ($q): void {
                $q->where('status', StepExecution::STATUS_FAILED)
                    ->orWhereNotNull('error_message');
            })
            ->with('flowExecution')
            ->orderByDesc('id')
            ->get();

        foreach ($steps as $step) {
            /** @var StepExecution $step */
            $run = $step->flowExecution;
            $runLabel = $run
                ? sprintf('#%d · %s', $run->id, $run->started_at?->timezone(config('app.timezone'))->toDateTimeString() ?? '—')
                : sprintf('#%d', $step->flow_execution_id);

            $rows->push([
                'key' => 'step:'.$step->id,
                'run_label' => $runLabel,
                'description' => (string) ($step->error_message ?? 'Step failed'),
                'kind' => 'step',
            ]);
        }

        $runsWithErrors = FlowExecution::query()
            ->where('flow_ref', $this->flowRef)
            ->whereIn('status', [FlowExecution::STATUS_FAILED, FlowExecution::STATUS_PARTIAL_COMPLETED])
            ->whereNotNull('error_message')
            ->where('error_message', '!=', '')
            ->orderByDesc('id')
            ->get();

        foreach ($runsWithErrors as $run) {
            $hasFailedStep = StepExecution::query()
                ->where('flow_execution_id', $run->id)
                ->where(function ($q): void {
                    $q->where('status', StepExecution::STATUS_FAILED)
                        ->orWhereNotNull('error_message');
                })
                ->exists();

            if (! $hasFailedStep) {
                $rows->push([
                    'key' => 'run:'.$run->id,
                    'run_label' => sprintf('#%d · %s', $run->id, $run->started_at?->timezone(config('app.timezone'))->toDateTimeString() ?? '—'),
                    'description' => (string) $run->error_message,
                    'kind' => 'run',
                ]);
            }
        }

        return $rows->sortByDesc(function (array $row): int {
            if (preg_match('/^(?:step|run):(\d+)$/', $row['key'], $matches)) {
                return (int) $matches[1];
            }

            return 0;
        })->values();
    }

    /**
     * @return list<array{key: string, label: string, is_active: bool}>
     */
    public function stepTabs(): array
    {
        if ($this->selectedRowKey === '') {
            return [];
        }

        $executionId = $this->resolvedFlowExecutionIdForSelection();
        if ($executionId === null) {
            return [];
        }

        $steps = StepExecution::query()
            ->where('flow_execution_id', $executionId)
            ->orderBy('step_index')
            ->get();

        $tabs = [];
        foreach ($steps as $step) {
            $key = 'step:'.$step->id;
            $basename = class_basename((string) ($step->step_class ?? ''));
            $tabs[] = [
                'key' => $key,
                'label' => __('Step :index · :class', [
                    'index' => $step->step_index,
                    'class' => $basename !== '' ? $basename : __('Unknown'),
                ]),
                'is_active' => $this->activeStepKey === $key,
            ];
        }

        if ($tabs === [] && str_starts_with($this->selectedRowKey, 'run:')) {
            $tabs[] = [
                'key' => 'run',
                'label' => __('Run'),
                'is_active' => $this->activeStepKey === 'run',
            ];
        }

        return $tabs;
    }

    /**
     * @return list<array{heading: string, body: string}>
     */
    public function activeStepInputSections(): array
    {
        if ($this->selectedRowKey === '' || $this->activeStepKey === '') {
            return [];
        }

        if ($this->activeStepKey === 'run') {
            return $this->runLevelInputSections();
        }

        $step = $this->activeStepExecution();
        if ($step === null) {
            return [];
        }

        return HumanReadablePayloadSections::from($step->input ?? []);
    }

    /**
     * @return list<array{heading: string, body: string}>
     */
    public function activeStepOutputSections(): array
    {
        if ($this->selectedRowKey === '' || $this->activeStepKey === '') {
            return [];
        }

        if ($this->activeStepKey === 'run') {
            return $this->runLevelOutputSections();
        }

        $step = $this->activeStepExecution();
        if ($step === null) {
            return [];
        }

        return HumanReadablePayloadSections::from($step->output ?? []);
    }

    public function activeStepErrorText(): string
    {
        if ($this->selectedRowKey === '' || $this->activeStepKey === '') {
            return '';
        }

        if ($this->activeStepKey === 'run') {
            if (! str_starts_with($this->selectedRowKey, 'run:')) {
                return '';
            }
            $run = FlowExecution::query()->find((int) substr($this->selectedRowKey, 4));
            if ($run === null || $run->flow_ref !== $this->flowRef) {
                return '';
            }

            return (string) ($run->error_message ?? '');
        }

        $step = $this->activeStepExecution();
        if ($step === null) {
            return '';
        }

        return (string) ($step->error_message ?? '');
    }

    public function render(): View
    {
        $this->ensureActiveStepTabIsValid();

        return view('livewire.flow-error-logs-panel', [
            'stepTabs' => $this->stepTabs(),
            'activeStepInputSections' => $this->activeStepInputSections(),
            'activeStepOutputSections' => $this->activeStepOutputSections(),
            'activeStepErrorText' => $this->activeStepErrorText(),
        ]);
    }

    private function ensureActiveStepTabIsValid(): void
    {
        $tabs = $this->stepTabs();
        if ($tabs === []) {
            return;
        }

        $keys = array_column($tabs, 'key');
        if (! in_array($this->activeStepKey, $keys, true)) {
            $this->activeStepKey = $keys[0];
        }
    }

    private function defaultActiveStepKeyForSelection(string $rowKey): string
    {
        if (str_starts_with($rowKey, 'step:')) {
            return 'step:'.(int) substr($rowKey, 5);
        }

        if (str_starts_with($rowKey, 'run:')) {
            $runId = (int) substr($rowKey, 4);
            $steps = StepExecution::query()
                ->where('flow_execution_id', $runId)
                ->orderBy('step_index')
                ->get();

            if ($steps->isEmpty()) {
                return 'run';
            }

            $failed = $steps->first(
                fn (StepExecution $s): bool => $s->status === StepExecution::STATUS_FAILED
                    || filled($s->error_message)
            );

            return 'step:'.($failed ?? $steps->first())->id;
        }

        return '';
    }

    private function resolvedFlowExecutionIdForSelection(): ?int
    {
        if (str_starts_with($this->selectedRowKey, 'step:')) {
            $step = StepExecution::query()->find((int) substr($this->selectedRowKey, 5));

            return $step?->flow_execution_id;
        }

        if (str_starts_with($this->selectedRowKey, 'run:')) {
            return (int) substr($this->selectedRowKey, 4);
        }

        return null;
    }

    private function activeStepExecution(): ?StepExecution
    {
        if (! preg_match('/^step:(\d+)$/', $this->activeStepKey, $matches)) {
            return null;
        }

        $step = StepExecution::query()->find((int) $matches[1]);
        if ($step === null || ! $this->executionBelongsToFlow($step->flow_execution_id)) {
            return null;
        }

        $expected = $this->resolvedFlowExecutionIdForSelection();
        if ($expected === null || $step->flow_execution_id !== $expected) {
            return null;
        }

        return $step;
    }

    /**
     * @return list<array{heading: string, body: string}>
     */
    private function runLevelInputSections(): array
    {
        if (! str_starts_with($this->selectedRowKey, 'run:')) {
            return [];
        }

        $run = FlowExecution::query()->find((int) substr($this->selectedRowKey, 4));
        if ($run === null || $run->flow_ref !== $this->flowRef) {
            return [];
        }

        return HumanReadablePayloadSections::from([
            'trigger_payload' => $run->trigger_payload,
            'context' => $run->context,
        ]);
    }

    /**
     * @return list<array{heading: string, body: string}>
     */
    private function runLevelOutputSections(): array
    {
        if (! str_starts_with($this->selectedRowKey, 'run:')) {
            return [];
        }

        $run = FlowExecution::query()->find((int) substr($this->selectedRowKey, 4));
        if ($run === null || $run->flow_ref !== $this->flowRef) {
            return [];
        }

        return HumanReadablePayloadSections::from([
            'status' => $run->status,
            'error_message' => $run->error_message,
            'finished_at' => $run->finished_at?->toIso8601String(),
        ]);
    }

    private function executionBelongsToFlow(int $flowExecutionId): bool
    {
        return FlowExecution::query()
            ->whereKey($flowExecutionId)
            ->where('flow_ref', $this->flowRef)
            ->exists();
    }
}
