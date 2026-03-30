<?php

namespace App\Filament\Resources\FlowExecutions\RelationManagers;

use App\Models\StepExecution;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

class StepExecutionsRelationManager extends RelationManager
{
    protected static string $relationship = 'stepExecutions';

    protected static ?string $title = 'Steps';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('step_class')
            ->columns([
                TextColumn::make('step_index')
                    ->sortable(),
                TextColumn::make('step_class')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('duration_ms')
                    ->suffix(' ms')
                    ->placeholder('—'),
                TextColumn::make('error_message')
                    ->limit(50)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->placeholder('—'),
                TextColumn::make('started_at')
                    ->dateTime()
                    ->placeholder('—'),
                TextColumn::make('finished_at')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('viewIo')
                    ->label(__('Input / output'))
                    ->icon(Heroicon::OutlinedCodeBracket)
                    ->modalHeading(__('Step input & output'))
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close'))
                    ->modalContent(fn (StepExecution $record): View => view(
                        'filament.flow-executions.step-io-modal',
                        ['record' => $record],
                    )),
            ])
            ->defaultSort('step_index');
    }

    protected function canCreate(): bool
    {
        return false;
    }
}
