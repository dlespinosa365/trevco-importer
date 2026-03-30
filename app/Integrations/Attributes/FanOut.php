<?php

namespace App\Integrations\Attributes;

use Attribute;

/**
 * Marks a step whose output contains a list of items; each item runs the remainder of the flow in a child execution.
 *
 * Child executions receive context where `_fan_out_item` is the current item, and the fan-out step's stored output
 * is narrowed so `$itemsPath` is a one-element list containing only that item (same shape as the parent output,
 * so the following step's input snapshot reflects a single item, not the full fan-out array).
 *
 * @param  string  $itemsPath  Dot path into the step output array for the list (e.g. "items", "data.orders").
 * @param  string  $itemReferenceKey  Dot path on each item for stable IDs in logs (e.g. "id", "order_id").
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class FanOut
{
    public function __construct(
        public string $itemsPath,
        public string $itemReferenceKey,
        public bool $enabled = true,
    ) {}
}
