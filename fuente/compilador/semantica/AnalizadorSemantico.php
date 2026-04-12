<?php

declare(strict_types=1);

require_once __DIR__ . '/../simbolos/TablaSimbolos.php';

final class AnalizadorSemantico extends GolampiBaseVisitor
{
    private const TYPE_INT = 'int';
    private const TYPE_FLOAT = 'float';
    private const TYPE_BOOL = 'bool';
    private const TYPE_STRING = 'string';
    private const TYPE_NIL = 'nil';
    private const TYPE_UNKNOWN = 'unknown';
    private const TYPE_ERROR = 'error';

    private TablaSimbolos $tablaSimbolos;

    /** @var array<int, array{type:string,description:string,line:int,column:int}> */
    private array $errors = [];

    private int $blockCounter = 0;
    private int $nivelBucles = 0;

    public function __construct()
    {
        $this->tablaSimbolos = new TablaSimbolos();
        $this->tablaSimbolos->enterScope('global', 'global', 0, 0);
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
    public function reporteTablaSimbolos(): array
    {
        return [
            'scopes' => $this->tablaSimbolos->allScopes(),
            'symbols' => $this->tablaSimbolos->allSymbols(),
        ];
    }

    public function visitFunctionDecl($ctx): mixed
    {
        $identifier = $ctx->IDENTIFIER();
        $name = $identifier->getText();
        $token = $identifier->getSymbol();

        $declared = $this->tablaSimbolos->declare(
            $name,
            'function',
            $token->getLine(),
            $token->getCharPositionInLine(),
            'function'
        );

        if (!$declared) {
            $this->addSemanticError(
                "Redeclaracion de funcion '$name' en el mismo ambito.",
                $token->getLine(),
                $token->getCharPositionInLine()
            );
        }

        return $this->visitChildren($ctx);
    }

    public function visitMainFunction($ctx): mixed
    {
        $token = $ctx->getStart();
        $declared = $this->tablaSimbolos->declare(
            'main',
            'function',
            $token->getLine(),
            $token->getCharPositionInLine(),
            'function'
        );

        if (!$declared) {
            $this->addSemanticError(
                "Redeclaracion de funcion 'main' en el mismo ambito.",
                $token->getLine(),
                $token->getCharPositionInLine()
            );
        }

        return $this->visitChildren($ctx);
    }

    public function visitBlock($ctx): mixed
    {
        $this->blockCounter++;
        $start = $ctx->getStart();
        $scopeName = 'block_' . $this->blockCounter;

        $this->tablaSimbolos->enterScope(
            $scopeName,
            'block',
            $start->getLine(),
            $start->getCharPositionInLine()
        );

        $result = $this->visitChildren($ctx);
        $this->tablaSimbolos->exitScope();

        return $result;
    }

    public function visitVarDecl($ctx): mixed
    {
        $identifier = $ctx->IDENTIFIER();
        $name = $identifier->getText();
        $type = $ctx->typeSpec()->getText();
        $token = $identifier->getSymbol();

        $declared = $this->tablaSimbolos->declare(
            $name,
            $type,
            $token->getLine(),
            $token->getCharPositionInLine()
        );

        if (!$declared) {
            $this->addSemanticError(
                "Redeclaracion de variable '$name' en el mismo ambito.",
                $token->getLine(),
                $token->getCharPositionInLine()
            );
        }

        if ($ctx->expr() !== null) {
            $exprType = $this->visit($ctx->expr());
            $this->assertAssignable(
                $type,
                is_string($exprType) ? $exprType : self::TYPE_UNKNOWN,
                $token->getLine(),
                $token->getCharPositionInLine(),
                "No se puede inicializar '$name' de tipo '$type' con valor de tipo '{$exprType}'."
            );
        }

        return null;
    }

    public function visitAssignment($ctx): mixed
    {
        $identifier = $ctx->IDENTIFIER();
        $name = $identifier->getText();
        $token = $identifier->getSymbol();

        $resolved = $this->tablaSimbolos->resolve($name);
        if ($resolved === null) {
            $this->addSemanticError(
                "Variable '$name' usada sin declaracion previa.",
                $token->getLine(),
                $token->getCharPositionInLine()
            );
            $this->visit($ctx->expr());
            return self::TYPE_ERROR;
        }

        $exprType = $this->visit($ctx->expr());
        $targetType = (string) ($resolved['type'] ?? self::TYPE_UNKNOWN);
        $this->assertAssignable(
            $targetType,
            is_string($exprType) ? $exprType : self::TYPE_UNKNOWN,
            $token->getLine(),
            $token->getCharPositionInLine(),
            "Asignacion invalida: variable '$name' es '$targetType' y la expresion es '{$exprType}'."
        );

        return null;
    }

    public function visitIdentifierExpr($ctx): mixed
    {
        $name = $ctx->getText();
        $token = $ctx->getStart();

        $resolved = $this->tablaSimbolos->resolve($name);
        if ($resolved === null) {
            $this->addSemanticError(
                "Identificador '$name' usado sin declaracion previa.",
                $token->getLine(),
                $token->getCharPositionInLine()
            );
            return self::TYPE_ERROR;
        }

        return (string) ($resolved['type'] ?? self::TYPE_UNKNOWN);
    }

    public function visitLiteralExpr($ctx): mixed
    {
        return $this->visit($ctx->literal());
    }

    public function visitIfStmt($ctx): mixed
    {
        $tipoCondicion = $this->visit($ctx->expr());
        if (!is_string($tipoCondicion)) {
            $tipoCondicion = self::TYPE_UNKNOWN;
        }

        if ($tipoCondicion !== self::TYPE_BOOL && $tipoCondicion !== self::TYPE_ERROR) {
            $this->addSemanticError(
                "Condicion invalida en if: se esperaba 'bool' y se recibio '$tipoCondicion'.",
                $ctx->expr()->getStart()->getLine(),
                $ctx->expr()->getStart()->getCharPositionInLine()
            );
        }

        return $this->visitChildren($ctx);
    }

    public function visitForStmt($ctx): mixed
    {
        if ($ctx->expr() !== null) {
            $tipoCondicion = $this->visit($ctx->expr());
            if (!is_string($tipoCondicion)) {
                $tipoCondicion = self::TYPE_UNKNOWN;
            }

            if ($tipoCondicion !== self::TYPE_BOOL && $tipoCondicion !== self::TYPE_ERROR) {
                $this->addSemanticError(
                    "Condicion invalida en for: se esperaba 'bool' y se recibio '$tipoCondicion'.",
                    $ctx->expr()->getStart()->getLine(),
                    $ctx->expr()->getStart()->getCharPositionInLine()
                );
            }
        }

        $this->nivelBucles++;
        $resultado = $this->visit($ctx->block());
        $this->nivelBucles--;

        return $resultado;
    }

    public function visitBreakStmt($ctx): mixed
    {
        if ($this->nivelBucles <= 0) {
            $token = $ctx->getStart();
            $this->addSemanticError(
                "Uso invalido de 'break': solo se permite dentro de un bucle.",
                $token->getLine(),
                $token->getCharPositionInLine()
            );
        }

        return null;
    }

    public function visitContinueStmt($ctx): mixed
    {
        if ($this->nivelBucles <= 0) {
            $token = $ctx->getStart();
            $this->addSemanticError(
                "Uso invalido de 'continue': solo se permite dentro de un bucle.",
                $token->getLine(),
                $token->getCharPositionInLine()
            );
        }

        return null;
    }

    public function visitGroupedExpr($ctx): mixed
    {
        return $this->visit($ctx->expr());
    }

    public function visitUnaryExpr($ctx): mixed
    {
        $operator = $ctx->children[0]->getText();
        $line = $ctx->getStart()->getLine();
        $column = $ctx->getStart()->getCharPositionInLine();
        $exprType = $this->visit($ctx->expr());
        if (!is_string($exprType)) {
            $exprType = self::TYPE_UNKNOWN;
        }

        if ($operator === '!') {
            if ($exprType !== self::TYPE_BOOL && $exprType !== self::TYPE_ERROR) {
                $this->addSemanticError(
                    "Operacion invalida: '!' requiere bool y se recibio '$exprType'.",
                    $line,
                    $column
                );
                return self::TYPE_ERROR;
            }
            return self::TYPE_BOOL;
        }

        if ($operator === '-') {
            if (!$this->isNumericType($exprType) && $exprType !== self::TYPE_ERROR) {
                $this->addSemanticError(
                    "Operacion invalida: '-' unario requiere tipo numerico y se recibio '$exprType'.",
                    $line,
                    $column
                );
                return self::TYPE_ERROR;
            }
            return $exprType;
        }

        return self::TYPE_UNKNOWN;
    }

    public function visitBinaryExpr($ctx): mixed
    {
        $leftType = $this->visit($ctx->expr(0));
        $rightType = $this->visit($ctx->expr(1));
        $operator = $ctx->children[1]->getText();
        $line = $ctx->getStart()->getLine();
        $column = $ctx->getStart()->getCharPositionInLine();

        if (!is_string($leftType)) {
            $leftType = self::TYPE_UNKNOWN;
        }
        if (!is_string($rightType)) {
            $rightType = self::TYPE_UNKNOWN;
        }

        if ($leftType === self::TYPE_ERROR || $rightType === self::TYPE_ERROR) {
            return self::TYPE_ERROR;
        }

        if (in_array($operator, ['+', '-', '*', '/'], true)) {
            if ($operator === '+' && $leftType === self::TYPE_STRING && $rightType === self::TYPE_STRING) {
                return self::TYPE_STRING;
            }

            if (!$this->isNumericType($leftType) || !$this->isNumericType($rightType)) {
                $this->addSemanticError(
                    "Operacion invalida: '$operator' requiere operandos numericos y se recibio '$leftType' y '$rightType'.",
                    $line,
                    $column
                );
                return self::TYPE_ERROR;
            }

            return $this->promoteNumericType($leftType, $rightType);
        }

        if ($operator === '%') {
            if ($leftType !== self::TYPE_INT || $rightType !== self::TYPE_INT) {
                $this->addSemanticError(
                    "Operacion invalida: '%' requiere operandos int y se recibio '$leftType' y '$rightType'.",
                    $line,
                    $column
                );
                return self::TYPE_ERROR;
            }
            return self::TYPE_INT;
        }

        if (in_array($operator, ['<', '<=', '>', '>='], true)) {
            if (!$this->isNumericType($leftType) || !$this->isNumericType($rightType)) {
                $this->addSemanticError(
                    "Comparacion invalida: '$operator' requiere operandos numericos y se recibio '$leftType' y '$rightType'.",
                    $line,
                    $column
                );
                return self::TYPE_ERROR;
            }
            return self::TYPE_BOOL;
        }

        if (in_array($operator, ['==', '!='], true)) {
            if (
                !$this->isSameType($leftType, $rightType)
                && !$this->isNumericPair($leftType, $rightType)
                && $leftType !== self::TYPE_UNKNOWN
                && $rightType !== self::TYPE_UNKNOWN
            ) {
                $this->addSemanticError(
                    "Comparacion invalida: '$operator' entre tipos '$leftType' y '$rightType'.",
                    $line,
                    $column
                );
                return self::TYPE_ERROR;
            }
            return self::TYPE_BOOL;
        }

        if (in_array($operator, ['&&', '||'], true)) {
            if ($leftType !== self::TYPE_BOOL || $rightType !== self::TYPE_BOOL) {
                $this->addSemanticError(
                    "Operacion logica invalida: '$operator' requiere bool y se recibio '$leftType' y '$rightType'.",
                    $line,
                    $column
                );
                return self::TYPE_ERROR;
            }
            return self::TYPE_BOOL;
        }

        return self::TYPE_UNKNOWN;
    }

    public function visitLiteral($ctx): mixed
    {
        if ($ctx->INT_LITERAL() !== null) {
            return self::TYPE_INT;
        }
        if ($ctx->FLOAT_LITERAL() !== null) {
            return self::TYPE_FLOAT;
        }
        if ($ctx->STRING_LITERAL() !== null) {
            return self::TYPE_STRING;
        }

        $text = $ctx->getText();
        if ($text === 'true' || $text === 'false') {
            return self::TYPE_BOOL;
        }
        if ($text === 'nil') {
            return self::TYPE_NIL;
        }

        return self::TYPE_UNKNOWN;
    }

    private function assertAssignable(
        string $targetType,
        string $exprType,
        int $line,
        int $column,
        string $message
    ): void {
        if ($exprType === self::TYPE_ERROR || $exprType === self::TYPE_UNKNOWN) {
            return;
        }

        if ($this->isSameType($targetType, $exprType)) {
            return;
        }

        if ($targetType === self::TYPE_FLOAT && $exprType === self::TYPE_INT) {
            return;
        }

        $this->addSemanticError($message, $line, $column);
    }

    private function isNumericType(string $type): bool
    {
        return $type === self::TYPE_INT || $type === self::TYPE_FLOAT;
    }

    private function isNumericPair(string $left, string $right): bool
    {
        return $this->isNumericType($left) && $this->isNumericType($right);
    }

    private function isSameType(string $left, string $right): bool
    {
        return $left === $right;
    }

    private function promoteNumericType(string $left, string $right): string
    {
        if ($left === self::TYPE_FLOAT || $right === self::TYPE_FLOAT) {
            return self::TYPE_FLOAT;
        }
        return self::TYPE_INT;
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
