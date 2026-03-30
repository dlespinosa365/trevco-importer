<?php

namespace App\Console\Commands;

use App\Connectors\Vendors\Amazon\AmazonVendorCentralConnector;
use App\Connectors\Vendors\DummyJson\DummyJsonConnector;
use App\Connectors\Vendors\NetSuite\NetSuiteConnector;
use App\Enums\ConnectorType;
use App\Models\Connector;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Console\Command;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Collection;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Throwable;

class RunConnectorMethodCommand extends Command
{
    protected $signature = 'connectors:run-method';

    protected $description = 'Run a connector method interactively with prompted parameters.';

    public function handle(): int
    {
        $connector = $this->askConnector();
        if ($connector === null) {
            $this->error('No connectors found.');

            return self::FAILURE;
        }

        try {
            $connectorClient = $this->buildConnectorClient($connector);
        } catch (BindingResolutionException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $methods = $this->listPublicMethods($connectorClient);
        if ($methods === []) {
            $this->error('No invokable public methods were found for this connector.');

            return self::FAILURE;
        }

        $methodByLabel = collect($methods)->mapWithKeys(
            fn (ReflectionMethod $method): array => [$this->methodSignature($method) => $method]
        )->all();

        $selected = $this->choice('Connector method', array_keys($methodByLabel), default: 0);
        $method = $methodByLabel[$selected];
        try {
            $args = $this->askMethodArguments($method);
        } catch (Throwable $e) {
            $this->error("Invalid parameter value: {$e->getMessage()}");

            return self::FAILURE;
        }

        try {
            $result = $method->invokeArgs($connectorClient, $args);
        } catch (Throwable $e) {
            $this->error("Method failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info('Method executed successfully.');
        if ($result !== null) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }

    private function askConnector(): ?Connector
    {
        /** @var Collection<int, Connector> $connectors */
        $connectors = Connector::query()
            ->orderBy('key')
            ->get(['id', 'key', 'name', 'connector_type', 'credentials', 'user_id']);

        if ($connectors->isEmpty()) {
            return null;
        }

        $options = $connectors
            ->map(fn (Connector $connector): string => $this->connectorOptionLabel($connector))
            ->values()
            ->all();

        $selected = $this->choice('Connector key', $options, default: 0);

        return $connectors->first(
            fn (Connector $connector): bool => $this->connectorOptionLabel($connector) === $selected
        );
    }

    private function connectorOptionLabel(Connector $connector): string
    {
        return "{$connector->key} ({$connector->connector_type->value})";
    }

    /**
     * @throws BindingResolutionException
     */
    private function buildConnectorClient(Connector $connector): object
    {
        return match ($connector->connector_type) {
            ConnectorType::NetSuite => app()->make(NetSuiteConnector::class, [
                'credentials' => $connector->credentials ?? [],
            ]),
            ConnectorType::DummyJson => app()->make(DummyJsonConnector::class, [
                'credentials' => $connector->credentials ?? [],
            ]),
            ConnectorType::AmazonVendorCentral => app()->make(AmazonVendorCentralConnector::class, [
                'credentials' => $connector->credentials ?? [],
            ]),
        };
    }

    /**
     * @return list<ReflectionMethod>
     */
    private function listPublicMethods(object $connectorClient): array
    {
        $reflection = new \ReflectionClass($connectorClient);
        $methods = collect($reflection->getMethods(ReflectionMethod::IS_PUBLIC))
            ->reject(fn (ReflectionMethod $method): bool => $method->isStatic())
            ->reject(fn (ReflectionMethod $method): bool => str_starts_with($method->name, '__'))
            ->sortBy(fn (ReflectionMethod $method): string => $method->name)
            ->values()
            ->all();

        return $methods;
    }

    private function methodSignature(ReflectionMethod $method): string
    {
        $params = collect($method->getParameters())
            ->map(function (ReflectionParameter $parameter): string {
                $type = $parameter->getType();
                $typeLabel = $type instanceof ReflectionNamedType ? $type->getName().' ' : '';
                $optional = $parameter->isOptional() ? '?' : '';

                return "{$typeLabel}{$parameter->getName()}{$optional}";
            })
            ->implode(', ');

        return "{$method->name}({$params})";
    }

    /**
     * @return list<mixed>
     */
    private function askMethodArguments(ReflectionMethod $method): array
    {
        $args = [];

        foreach ($method->getParameters() as $parameter) {
            $answer = (string) $this->ask($this->parameterQuestion($parameter), '');
            if ($answer === '' && $parameter->isOptional()) {
                continue;
            }

            $args[] = $this->castAnswer($parameter, $answer);
        }

        return $args;
    }

    private function parameterQuestion(ReflectionParameter $parameter): string
    {
        $type = $parameter->getType();
        $typeLabel = $type instanceof ReflectionNamedType ? $type->getName() : 'mixed';
        $requiredLabel = $parameter->isOptional() ? 'optional' : 'required';

        return "Value for {$parameter->getName()} ({$typeLabel}, {$requiredLabel})";
    }

    private function castAnswer(ReflectionParameter $parameter, string $answer): mixed
    {
        $type = $parameter->getType();
        if (! $type instanceof ReflectionNamedType) {
            return $this->castAsJsonOrString($answer);
        }

        return match ($type->getName()) {
            'int' => (int) $answer,
            'float' => (float) $answer,
            'bool' => in_array(strtolower($answer), ['1', 'true', 'yes', 'y'], true),
            'string' => $answer,
            'array' => $this->decodeArray($answer, $parameter->getName()),
            DateTimeInterface::class, DateTimeImmutable::class, '\DateTimeInterface', '\DateTimeImmutable' => new DateTimeImmutable($answer),
            default => $this->castAsJsonOrString($answer),
        };
    }

    private function castAsJsonOrString(string $answer): mixed
    {
        $trimmed = trim($answer);
        if ($trimmed !== '' && in_array($trimmed[0], ['{', '[', '"'], true)) {
            try {
                return json_decode($answer, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                return $answer;
            }
        }

        return $answer;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function decodeArray(string $answer, string $parameterName): array
    {
        try {
            $decoded = json_decode($answer, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new \InvalidArgumentException("Parameter [{$parameterName}] expects a valid JSON array/object.");
        }

        if (! is_array($decoded)) {
            throw new \InvalidArgumentException("Parameter [{$parameterName}] expects a JSON array/object.");
        }

        return $decoded;
    }
}
