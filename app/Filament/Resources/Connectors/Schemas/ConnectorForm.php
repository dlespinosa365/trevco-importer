<?php

namespace App\Filament\Resources\Connectors\Schemas;

use App\Connectors\ConnectorFieldSpec;
use App\Connectors\ConnectorTypeRegistry;
use App\Enums\ConnectorType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Livewire\Component as LivewireComponent;

class ConnectorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Connector')
                    ->schema([
                        Select::make('connector_type')
                            ->label('Type')
                            ->options(collect(ConnectorType::cases())->mapWithKeys(
                                fn (ConnectorType $c): array => [$c->value => $c->label()]
                            ))
                            ->required()
                            ->live()
                            ->disabled(fn (LivewireComponent $livewire): bool => $livewire instanceof EditRecord)
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                if ($state !== null && $state !== '') {
                                    $set('credentials', []);
                                }
                            }),
                        TextInput::make('key')
                            ->label('Key')
                            ->required()
                            ->maxLength(255)
                            ->regex('/^[a-z0-9][a-z0-9_-]*$/')
                            ->helperText('Unique identifier for flows (e.g. netsuite_sb1). Lowercase letters, numbers, underscore, hyphen.')
                            ->disabled(fn (LivewireComponent $livewire): bool => $livewire instanceof EditRecord),
                        TextInput::make('name')
                            ->label('Name')
                            ->maxLength(255),
                    ]),
                Section::make('Connection parameters')
                    ->description('Values are stored encrypted.')
                    ->schema(fn (Get $get): array => self::credentialFields($get)),
            ]);
    }

    /**
     * @return array<int, TextInput>
     */
    private static function credentialFields(Get $get): array
    {
        $type = ConnectorType::tryFrom((string) ($get('connector_type') ?? ''));

        if ($type === null) {
            return [];
        }

        $definition = ConnectorTypeRegistry::definition($type);

        return array_map(fn (ConnectorFieldSpec $spec): TextInput => self::textInputForSpec($spec), $definition->fields());
    }

    private static function textInputForSpec(ConnectorFieldSpec $spec): TextInput
    {
        $rules = $spec->rules();
        if ($spec->secret) {
            $rules = array_values(array_filter(
                $rules,
                fn (string $rule): bool => strtolower($rule) !== 'required',
            ));
        }

        $field = TextInput::make('credentials.'.$spec->name)
            ->label($spec->label)
            ->rules($rules);

        if ($spec->helperText !== null) {
            $field->helperText($spec->helperText);
        }

        if ($spec->type === 'password') {
            $field
                ->password()
                ->revealable()
                ->placeholder('Leave blank to keep current value');
        }

        if ($spec->secret) {
            $field
                ->required(fn (LivewireComponent $livewire): bool => ! ($livewire instanceof EditRecord))
                ->dehydrated(fn (?string $state): bool => filled($state));
        }

        return $field;
    }
}
