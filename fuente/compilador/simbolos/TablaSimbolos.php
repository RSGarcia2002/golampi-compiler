<?php

declare(strict_types=1);

final class TablaSimbolos
{
    /** @var array<int, array<string, mixed>> */
    private array $scopes = [];

    /** @var array<int, int> */
    private array $scopeStack = [];

    private int $nextScopeId = 1;

    public function enterScope(string $name, string $kind, int $line, int $column): int
    {
        $scopeId = $this->nextScopeId++;
        $parentId = $this->scopeStack[count($this->scopeStack) - 1] ?? null;

        $this->scopes[$scopeId] = [
            'id' => $scopeId,
            'name' => $name,
            'kind' => $kind,
            'line' => $line,
            'column' => $column,
            'depth' => count($this->scopeStack),
            'parent_scope_id' => $parentId,
            'symbols' => [],
        ];

        $this->scopeStack[] = $scopeId;
        return $scopeId;
    }

    public function exitScope(): void
    {
        if ($this->scopeStack !== []) {
            array_pop($this->scopeStack);
        }
    }

    /** @return array<string, mixed>|null */
    public function currentScope(): ?array
    {
        $scopeId = $this->scopeStack[count($this->scopeStack) - 1] ?? null;
        if ($scopeId === null) {
            return null;
        }

        return $this->scopes[$scopeId] ?? null;
    }

    public function declare(string $name, string $type, int $line, int $column, string $kind = 'variable'): bool
    {
        $scopeId = $this->scopeStack[count($this->scopeStack) - 1] ?? null;
        if ($scopeId === null) {
            return false;
        }

        if (isset($this->scopes[$scopeId]['symbols'][$name])) {
            return false;
        }

        $this->scopes[$scopeId]['symbols'][$name] = [
            'name' => $name,
            'kind' => $kind,
            'type' => $type,
            'line' => $line,
            'column' => $column,
            'scope_id' => $scopeId,
            'scope_name' => $this->scopes[$scopeId]['name'],
            'scope_depth' => $this->scopes[$scopeId]['depth'],
        ];

        return true;
    }

    /** @return array<string, mixed>|null */
    public function resolve(string $name): ?array
    {
        for ($i = count($this->scopeStack) - 1; $i >= 0; $i--) {
            $scopeId = $this->scopeStack[$i];
            if (isset($this->scopes[$scopeId]['symbols'][$name])) {
                return $this->scopes[$scopeId]['symbols'][$name];
            }
        }

        return null;
    }

    /** @return array<int, array<string, mixed>> */
    public function allScopes(): array
    {
        return array_values(array_map(
            static function (array $scope): array {
                return [
                    'id' => $scope['id'],
                    'name' => $scope['name'],
                    'kind' => $scope['kind'],
                    'line' => $scope['line'],
                    'column' => $scope['column'],
                    'depth' => $scope['depth'],
                    'parent_scope_id' => $scope['parent_scope_id'],
                ];
            },
            $this->scopes
        ));
    }

    /** @return array<int, array<string, mixed>> */
    public function allSymbols(): array
    {
        $symbols = [];
        foreach ($this->scopes as $scope) {
            foreach ($scope['symbols'] as $symbol) {
                $symbols[] = $symbol;
            }
        }
        return $symbols;
    }
}
