<?php

use App\Support\HumanReadablePayloadSections;

it('splits merged fan-out step context into ordered sections', function () {
    $payload = [
        '0_FetchDummyOrdersStep' => [
            'items' => [['id' => 10, 'total' => 1]],
            'total' => 50,
            'source' => 'dummy-json',
        ],
        '_fan_out_item' => ['id' => 10, 'total' => 1],
        '_fan_out_reference' => '10',
        'source' => 'filament_integration_flows_page',
        '1_TransformOrderStep' => ['dummy_order_id' => 10],
    ];

    $sections = HumanReadablePayloadSections::from($payload);

    $headings = array_column($sections, 'heading');

    expect($headings)->toContain('Context')
        ->and($headings)->toContain('Current fan-out item')
        ->and($headings[array_search('Current fan-out item', $headings, true)])->toBe('Current fan-out item');

    $step0 = collect($sections)->first(fn (array $s): bool => str_contains($s['heading'], 'Step 0'));
    $step1 = collect($sections)->first(fn (array $s): bool => str_contains($s['heading'], 'Step 1'));

    expect($step0['heading'])->toContain('Fetch Dummy Orders Step')
        ->and($step1['heading'])->toContain('Transform Order Step')
        ->and($step0['body'])->toContain('"id": 10');
});

it('detects run-level trigger and context wrapper', function () {
    $payload = [
        'trigger_payload' => ['x' => 1],
        'context' => ['y' => 2],
    ];

    $sections = HumanReadablePayloadSections::from($payload);

    expect($sections)->toHaveCount(2)
        ->and($sections[0]['heading'])->toBe('Trigger payload')
        ->and($sections[1]['heading'])->toBe('Execution context')
        ->and($sections[0]['body'])->toContain('"x": 1');
});
