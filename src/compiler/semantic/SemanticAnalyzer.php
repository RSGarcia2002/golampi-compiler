<?php

declare(strict_types=1);

require_once __DIR__ . '/../symbols/SymbolTable.php';

final class SemanticAnalyzer extends GolampiBaseVisitor
{
    private SymbolTable $symbolTable;

    /** @var array<int, array{type:string,description:string,line:int,column:int}> */
    private array $errors = [];

    private int $blockCounter = 0;

    public function __construct()
    {
        $this->symbolTable = new SymbolTable();
        $this->symbolTable->enterScope('global', 'global', 0, 0);
    }

    public function analyze(object $tree): void
    {
        $this->visit($tree);
    }

    /** @return array<int, array{type:string,description:string,line:int,column:int}> */
    public function allErrors(): array
    {
        return $this->errors;
    }

    /** @return array<string, mixed> */
    public function symbolTableReport(): array
    {
        return [
            'scopes' => $this->symbolTable->allScopes(),
            'symbols' => $this->symbolTable->allSymbols(),
        ];
    }

    public function visitFunctionDecl($ctx): mixed
    {
        $identifier = $ctx->IDENTIFIER();
        $name = $identifier->getText();
        $token = $identifier->getSymbol();

        $declared = $this->symbolTable->declare(
            $name,
            'function',
            $token->line,
            $token->charPositionInLine,
            'function'
        );

        if (!$declared) {
            $this->addSemanticError(
                "Redeclaracion de funcion '$name' en el mismo ambito.",
                $token->line,
                $token->charPositionInLine
            );
        }

        return $this->visitChildren($ctx);
    }

    public function visitMainFunction($ctx): mixed
    {
        $token = $ctx->getStart();
        $declared = $this->symbolTable->declare(
            'main',
            'function',
            $token->line,
            $token->charPositionInLine,
            'function'
        );

        if (!$declared) {
            $this->addSemanticError(
                "Redeclaracion de funcion 'main' en el mismo ambito.",
                $token->line,
                $token->charPositionInLine
            );
        }

        return $this->visitChildren($ctx);
    }

    public function visitBlock($ctx): mixed
    {
        $this->blockCounter++;
        $start = $ctx->getStart();
        $scopeName = 'block_' . $this->blockCounter;

        $this->symbolTable->enterScope(
            $scopeName,
            'block',
            $start->line,
            $start->charPositionInLine
        );

        $result = $this->visitChildren($ctx);
        $this->symbolTable->exitScope();

        return $result;
    }

    public function visitVarDecl($ctx): mixed
    {
        $identifier = $ctx->IDENTIFIER();
        $name = $identifier->getText();
        $type = $ctx->typeSpec()->getText();
        $token = $identifier->getSymbol();

        $declared = $this->symbolTable->declare(
            $name,
            $type,
            $token->line,
            $token->charPositionInLine
        );

        if (!$declared) {
            $this->addSemanticError(
                "Redeclaracion de variable '$name' en el mismo ambito.",
                $token->line,
                $token->charPositionInLine
            );
        }

        return $this->visitChildren($ctx);
    }

    public function visitAssignment($ctx): mixed
    {
        $identifier = $ctx->IDENTIFIER();
        $name = $identifier->getText();
        $token = $identifier->getSymbol();

        $resolved = $this->symbolTable->resolve($name);
        if ($resolved === null) {
            $this->addSemanticError(
                "Variable '$name' usada sin declaracion previa.",
                $token->line,
                $token->charPositionInLine
            );
        }

        return $this->visitChildren($ctx);
    }

    public function visitIdentifierExpr($ctx): mixed
    {
        $name = $ctx->getText();
        $token = $ctx->getStart();

        $resolved = $this->symbolTable->resolve($name);
        if ($resolved === null) {
            $this->addSemanticError(
                "Identificador '$name' usado sin declaracion previa.",
                $token->line,
                $token->charPositionInLine
            );
        }

        return $this->visitChildren($ctx);
    }

    private function addSemanticError(string $description, int $line, int $column): void
    {
        $this->errors[] = [
            'type' => 'semantic',
            'description' => $description,
            'line' => $line,
            'column' => $column,
        ];
    }
}
