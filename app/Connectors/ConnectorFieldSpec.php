<?php

namespace App\Connectors;

final class ConnectorFieldSpec
{
    /**
     * @param  list<string>  $rules
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $type,
        public array $rules = [],
        public ?string $helperText = null,
        public bool $secret = false,
    ) {}

    /**
     * @return list<string>
     */
    public function rules(): array
    {
        return $this->rules;
    }
}
