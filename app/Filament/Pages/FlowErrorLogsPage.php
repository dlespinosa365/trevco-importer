<?php

namespace App\Filament\Pages;

use App\Integrations\FlowDefinitionRegistry;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;

class FlowErrorLogsPage extends Page
{
    protected Width|string|null $maxContentWidth = Width::Full;

    protected static ?string $title = 'Flow error logs';

    protected static ?string $slug = 'integrations/{integrationSlug}/flows/error-logs';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.flow-error-logs-page';

    public string $integrationSlug = '';

    #[Url(as: 'flow_ref')]
    public string $flowRef = '';

    public function mount(string $integrationSlug): void
    {
        $this->integrationSlug = $integrationSlug;

        if ($this->flowRef === '') {
            abort(404);
        }

        if (! str_starts_with($this->flowRef, $this->integrationSlug.'/')) {
            abort(404);
        }

        $registry = app(FlowDefinitionRegistry::class);
        $valid = collect($registry->allFlowRefs())
            ->contains(fn (string $ref): bool => $ref === $this->flowRef);

        if (! $valid) {
            abort(404);
        }
    }

    public function getHeading(): string|Htmlable|null
    {
        $parts = explode('/', $this->flowRef);
        $label = end($parts) ?: $this->flowRef;

        return __('Flow error logs: :flow', ['flow' => $label]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->flowRef;
    }
}
