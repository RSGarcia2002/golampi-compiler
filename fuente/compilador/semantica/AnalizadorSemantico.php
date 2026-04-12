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
    private const TYPE_ARRAY_PREFIX = '[]';

    private TablaSimbolos $tablaSimbolos;

    /** @var array<int, array{type:string,description:string,line:int,column:int}> */
    private array $errors = [];

    private int $blockCounter = 0;
    private int $nivelBucles = 0;
    private int $nivelSwitch = 0;

    /** @var array<string, array{parametros:array<int,string>,retorno:array<int,string>,builtin:bool}> */
    private array $firmasFunciones = [];

    /** @var array<int, array{nombre:string,retorno:array<int,string>}> */
    private array $pilaFunciones = [];

    public function __construct()
    {
        $this->tablaSimbolos = new TablaSimbolos();
        $this->tablaSimbolos->enterScope('global', 'global', 0, 0);

        $this->registrarFuncionBuiltin('len', [self::TYPE_UNKNOWN], [self::TYPE_INT]);
        $this->registrarFuncionBuiltin('now', [], [self::TYPE_STRING]);
        $this->registrarFuncionBuiltin('substr', [self::TYPE_STRING, self::TYPE_INT, self::TYPE_INT], [self::TYPE_STRING]);
        $this->registrarFuncionBuiltin('typeOf', [self::TYPE_UNKNOWN], [self::TYPE_STRING]);
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

    public function visitProgram($ctx): mixed
    {
        foreach ($ctx->functionDecl() as $funcionCtx) {
            $nombre = $funcionCtx->IDENTIFIER()->getText();
            $token = $funcionCtx->IDENTIFIER()->getSymbol();
            $tiposParametros = $this->tiposParametrosDe($funcionCtx->paramList());
            $tiposRetorno = $this->tiposRetornoDe($funcionCtx->returnType());

            $this->registrarFuncion(
                $nombre,
                $tiposParametros,
                $tiposRetorno,
                $token->getLine(),
                $token->getCharPositionInLine()
            );
        }

        $mainToken = $ctx->mainFunction()->getStart();
        $this->registrarFuncion(
            'main',
            [],
            [],
            $mainToken->getLine(),
            $mainToken->getCharPositionInLine()
        );

        return $this->visitChildren($ctx);
    }

    public function visitFunctionDecl($ctx): mixed
    {
        $identifier = $ctx->IDENTIFIER();
        $name = $identifier->getText();
        $token = $identifier->getSymbol();
        $firma = $this->firmasFunciones[$name] ?? ['parametros' => [], 'retorno' => [], 'builtin' => false];
        $tiposParametros = $firma['parametros'];
        $tiposRetorno = $firma['retorno'];

        $this->tablaSimbolos->enterScope(
            'funcion_' . $name,
            'funcion',
            $token->getLine(),
            $token->getCharPositionInLine()
        );

        if ($ctx->paramList() !== null) {
            $parametrosCtx = $ctx->paramList()->param();
            foreach ($parametrosCtx as $index => $paramCtx) {
                $paramId = $paramCtx->IDENTIFIER();
                $paramName = $paramId->getText();
                $paramToken = $paramId->getSymbol();
                $paramType = $tiposParametros[$index] ?? self::TYPE_UNKNOWN;

                $ok = $this->tablaSimbolos->declare(
                    $paramName,
                    $paramType,
                    $paramToken->getLine(),
                    $paramToken->getCharPositionInLine(),
                    'parametro'
                );

                if (!$ok) {
                    $this->addSemanticError(
                        "Parametro duplicado '$paramName' en funcion '$name'.",
                        $paramToken->getLine(),
                        $paramToken->getCharPositionInLine()
                    );
                }
            }
        }

        $this->pilaFunciones[] = ['nombre' => $name, 'retorno' => $tiposRetorno];
        foreach ($ctx->block()->statement() as $statement) {
            $this->visit($statement);
        }
        array_pop($this->pilaFunciones);
        $this->tablaSimbolos->exitScope();

        return null;
    }

    public function visitMainFunction($ctx): mixed
    {
        $token = $ctx->getStart();
        $this->tablaSimbolos->enterScope(
            'funcion_main',
            'funcion',
            $token->getLine(),
            $token->getCharPositionInLine()
        );

        $this->pilaFunciones[] = ['nombre' => 'main', 'retorno' => []];
        foreach ($ctx->block()->statement() as $statement) {
            $this->visit($statement);
        }
        array_pop($this->pilaFunciones);
        $this->tablaSimbolos->exitScope();

        return null;
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

    public function visitConstDecl($ctx): mixed
    {
        $identifier = $ctx->IDENTIFIER();
        $name = $identifier->getText();
        $type = $ctx->typeSpec()->getText();
        $token = $identifier->getSymbol();

        $declared = $this->tablaSimbolos->declare(
            $name,
            $type,
            $token->getLine(),
            $token->getCharPositionInLine(),
            'constante'
        );

        if (!$declared) {
            $this->addSemanticError(
                "Redeclaracion de constante '$name' en el mismo ambito.",
                $token->getLine(),
                $token->getCharPositionInLine()
            );
            return null;
        }

        $exprType = $this->visit($ctx->expr());
        $this->assertAssignable(
            $type,
            is_string($exprType) ? $exprType : self::TYPE_UNKNOWN,
            $token->getLine(),
            $token->getCharPositionInLine(),
            "No se puede inicializar constante '$name' de tipo '$type' con valor de tipo '{$exprType}'."
        );

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

        if (($resolved['kind'] ?? '') === 'constante') {
            $this->addSemanticError(
                "Asignacion invalida: '$name' es constante y no puede modificarse.",
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

    public function visitCallExpr($ctx): mixed
    {
        $nombre = $ctx->IDENTIFIER()->getText();
        $token = $ctx->IDENTIFIER()->getSymbol();

        if (!isset($this->firmasFunciones[$nombre])) {
            $this->addSemanticError(
                "Llamada a funcion '$nombre' no declarada.",
                $token->getLine(),
                $token->getCharPositionInLine()
            );
            if ($ctx->argList() !== null) {
                foreach ($ctx->argList()->expr() as $argExpr) {
                    $this->visit($argExpr);
                }
            }
            return self::TYPE_ERROR;
        }

        $firma = $this->firmasFunciones[$nombre];
        $tiposEsperados = $firma['parametros'];
        $tiposRecibidos = [];

        if ($ctx->argList() !== null) {
            foreach ($ctx->argList()->expr() as $argExpr) {
                $tipoArg = $this->visit($argExpr);
                $tiposRecibidos[] = is_string($tipoArg) ? $tipoArg : self::TYPE_UNKNOWN;
            }
        }

        if ($firma['builtin']) {
            return $this->validarLlamadaBuiltin(
                $nombre,
                $tiposRecibidos,
                $token->getLine(),
                $token->getCharPositionInLine()
            );
        }

        if (count($tiposEsperados) !== count($tiposRecibidos)) {
            $this->addSemanticError(
                "Llamada invalida a '$nombre': se esperaban " . count($tiposEsperados)
                . " parametro(s) y se recibieron " . count($tiposRecibidos) . ".",
                $token->getLine(),
                $token->getCharPositionInLine()
            );
        }

        $comparables = min(count($tiposEsperados), count($tiposRecibidos));
        for ($i = 0; $i < $comparables; $i++) {
            $esperado = $tiposEsperados[$i];
            $recibido = $tiposRecibidos[$i];
            if ($esperado === self::TYPE_UNKNOWN) {
                continue;
            }
            if (!$this->tiposCompatiblesAsignacion($esperado, $recibido)) {
                $this->addSemanticError(
                    "Llamada invalida a '$nombre': parametro " . ($i + 1)
                    . " requiere '$esperado' y se recibio '$recibido'.",
                    $token->getLine(),
                    $token->getCharPositionInLine()
                );
            }
        }

        if ($firma['retorno'] === []) {
            return self::TYPE_UNKNOWN;
        }

        return $firma['retorno'][0];
    }

    public function visitArrayLiteralExpr($ctx): mixed
    {
        if ($ctx->argList() === null) {
            return self::TYPE_ARRAY_PREFIX . self::TYPE_UNKNOWN;
        }

        $tipoElemento = self::TYPE_UNKNOWN;
        foreach ($ctx->argList()->expr() as $elementoExpr) {
            $tipoElementoActual = $this->visit($elementoExpr);
            $tipoElementoActual = is_string($tipoElementoActual) ? $tipoElementoActual : self::TYPE_UNKNOWN;

            if ($tipoElementoActual === self::TYPE_ERROR || $tipoElementoActual === self::TYPE_NIL) {
                $token = $elementoExpr->getStart();
                $this->addSemanticError(
                    "Literal de arreglo invalido: no se permite elemento de tipo '$tipoElementoActual'.",
                    $token->getLine(),
                    $token->getCharPositionInLine()
                );
                return self::TYPE_ERROR;
            }

            if ($tipoElemento === self::TYPE_UNKNOWN) {
                $tipoElemento = $tipoElementoActual;
                continue;
            }

            if ($this->isNumericPair($tipoElemento, $tipoElementoActual)) {
                $tipoElemento = $this->promoteNumericType($tipoElemento, $tipoElementoActual);
                continue;
            }

            if ($tipoElemento !== $tipoElementoActual) {
                $token = $elementoExpr->getStart();
                $this->addSemanticError(
                    "Literal de arreglo invalido: mezcla de tipos '$tipoElemento' y '$tipoElementoActual'.",
                    $token->getLine(),
                    $token->getCharPositionInLine()
                );
                return self::TYPE_ERROR;
            }
        }

        return self::TYPE_ARRAY_PREFIX . $tipoElemento;
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

    public function visitSwitchStmt($ctx): mixed
    {
        $tipoControl = $this->visit($ctx->expr());
        if (!is_string($tipoControl)) {
            $tipoControl = self::TYPE_UNKNOWN;
        }

        $this->nivelSwitch++;

        foreach ($ctx->switchCase() as $caso) {
            $tipoCaso = $this->visit($caso->expr());
            if (!is_string($tipoCaso)) {
                $tipoCaso = self::TYPE_UNKNOWN;
            }

            if (
                $tipoControl !== self::TYPE_ERROR
                && $tipoCaso !== self::TYPE_ERROR
                && !$this->tiposComparablesEnSwitch($tipoControl, $tipoCaso)
            ) {
                $this->addSemanticError(
                    "Caso invalido en switch: tipo '$tipoCaso' no es compatible con '$tipoControl'.",
                    $caso->expr()->getStart()->getLine(),
                    $caso->expr()->getStart()->getCharPositionInLine()
                );
            }

            foreach ($caso->statement() as $statement) {
                $this->visit($statement);
            }
        }

        if ($ctx->defaultCase() !== null) {
            foreach ($ctx->defaultCase()->statement() as $statement) {
                $this->visit($statement);
            }
        }

        $this->nivelSwitch--;
        return null;
    }

    public function visitBreakStmt($ctx): mixed
    {
        if ($this->nivelBucles <= 0 && $this->nivelSwitch <= 0) {
            $token = $ctx->getStart();
            $this->addSemanticError(
                "Uso invalido de 'break': solo se permite dentro de un bucle o switch.",
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

    public function visitReturnStmt($ctx): mixed
    {
        $token = $ctx->getStart();
        $funcionActual = $this->pilaFunciones[count($this->pilaFunciones) - 1] ?? null;
        $retornosEsperados = $funcionActual['retorno'] ?? [];

        if ($retornosEsperados === []) {
            if ($ctx->expr() !== null) {
                $this->addSemanticError(
                    "Return invalido: la funcion '{$funcionActual['nombre']}' no retorna valor.",
                    $token->getLine(),
                    $token->getCharPositionInLine()
                );
                $this->visit($ctx->expr());
            }
            return null;
        }

        if ($ctx->expr() === null) {
            $this->addSemanticError(
                "Return invalido: la funcion '{$funcionActual['nombre']}' debe retornar '{$retornosEsperados[0]}'.",
                $token->getLine(),
                $token->getCharPositionInLine()
            );
            return null;
        }

        $tipoRetorno = $this->visit($ctx->expr());
        $tipoRetorno = is_string($tipoRetorno) ? $tipoRetorno : self::TYPE_UNKNOWN;
        $this->assertAssignable(
            $retornosEsperados[0],
            $tipoRetorno,
            $token->getLine(),
            $token->getCharPositionInLine(),
            "Return invalido: se esperaba '{$retornosEsperados[0]}' y se recibio '$tipoRetorno'."
        );

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

        if ($this->tiposCompatiblesAsignacion($targetType, $exprType)) {
            return;
        }

        $this->addSemanticError($message, $line, $column);
    }

    private function tiposCompatiblesAsignacion(string $destino, string $origen): bool
    {
        if ($this->isArrayType($destino) && $origen === self::TYPE_NIL) {
            return true;
        }

        if ($this->isArrayType($destino) || $this->isArrayType($origen)) {
            return $destino === $origen;
        }

        if ($this->isSameType($destino, $origen)) {
            return true;
        }

        return $destino === self::TYPE_FLOAT && $origen === self::TYPE_INT;
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

    private function isArrayType(string $type): bool
    {
        return str_starts_with($type, self::TYPE_ARRAY_PREFIX);
    }

    private function tiposComparablesEnSwitch(string $tipoControl, string $tipoCaso): bool
    {
        if ($tipoControl === self::TYPE_UNKNOWN || $tipoCaso === self::TYPE_UNKNOWN) {
            return true;
        }

        if ($this->isSameType($tipoControl, $tipoCaso)) {
            return true;
        }

        return $this->isNumericPair($tipoControl, $tipoCaso);
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

    /** @return array<int,string> */
    private function tiposParametrosDe($paramListCtx): array
    {
        if ($paramListCtx === null) {
            return [];
        }

        $tipos = [];
        foreach ($paramListCtx->param() as $paramCtx) {
            $tipos[] = $paramCtx->typeSpec()->getText();
        }
        return $tipos;
    }

    /** @return array<int,string> */
    private function tiposRetornoDe($returnTypeCtx): array
    {
        if ($returnTypeCtx === null) {
            return [];
        }
        return [$returnTypeCtx->typeSpec()->getText()];
    }

    /** @param array<int,string> $parametros @param array<int,string> $retorno */
    private function registrarFuncion(
        string $nombre,
        array $parametros,
        array $retorno,
        int $line,
        int $column,
        bool $builtin = false
    ): void {
        if (isset($this->firmasFunciones[$nombre])) {
            $this->addSemanticError(
                "Redeclaracion de funcion '$nombre'.",
                $line,
                $column
            );
            return;
        }

        $this->firmasFunciones[$nombre] = [
            'parametros' => $parametros,
            'retorno' => $retorno,
            'builtin' => $builtin,
        ];

        $ok = $this->tablaSimbolos->declare(
            $nombre,
            'function',
            $line,
            $column,
            $builtin ? 'builtin' : 'function',
            ['parametros' => $parametros, 'retorno' => $retorno]
        );

        if (!$ok) {
            $this->addSemanticError(
                "Redeclaracion de simbolo '$nombre' en ambito global.",
                $line,
                $column
            );
        }
    }

    /** @param array<int,string> $parametros @param array<int,string> $retorno */
    private function registrarFuncionBuiltin(string $nombre, array $parametros, array $retorno): void
    {
        $this->registrarFuncion($nombre, $parametros, $retorno, 0, 0, true);
    }

    /** @param array<int,string> $tiposRecibidos */
    private function validarLlamadaBuiltin(string $nombre, array $tiposRecibidos, int $line, int $column): string
    {
        if ($nombre === 'len') {
            if (count($tiposRecibidos) !== 1) {
                $this->addSemanticError(
                    "Llamada invalida a 'len': se esperaba 1 parametro y se recibieron " . count($tiposRecibidos) . ".",
                    $line,
                    $column
                );
                return self::TYPE_ERROR;
            }

            $tipo = $tiposRecibidos[0];
            if ($tipo !== self::TYPE_STRING && !$this->isArrayType($tipo) && $tipo !== self::TYPE_ERROR) {
                $this->addSemanticError(
                    "Llamada invalida a 'len': se esperaba string o arreglo y se recibio '$tipo'.",
                    $line,
                    $column
                );
                return self::TYPE_ERROR;
            }

            return self::TYPE_INT;
        }

        if ($nombre === 'now') {
            if (count($tiposRecibidos) !== 0) {
                $this->addSemanticError(
                    "Llamada invalida a 'now': no recibe parametros.",
                    $line,
                    $column
                );
                return self::TYPE_ERROR;
            }

            return self::TYPE_STRING;
        }

        if ($nombre === 'substr') {
            if (count($tiposRecibidos) !== 3) {
                $this->addSemanticError(
                    "Llamada invalida a 'substr': se esperaban 3 parametros y se recibieron " . count($tiposRecibidos) . ".",
                    $line,
                    $column
                );
                return self::TYPE_ERROR;
            }

            if (
                $tiposRecibidos[0] !== self::TYPE_STRING
                || $tiposRecibidos[1] !== self::TYPE_INT
                || $tiposRecibidos[2] !== self::TYPE_INT
            ) {
                $this->addSemanticError(
                    "Llamada invalida a 'substr': firma esperada (string, int, int).",
                    $line,
                    $column
                );
                return self::TYPE_ERROR;
            }

            return self::TYPE_STRING;
        }

        if ($nombre === 'typeOf') {
            if (count($tiposRecibidos) !== 1) {
                $this->addSemanticError(
                    "Llamada invalida a 'typeOf': se esperaba 1 parametro y se recibieron " . count($tiposRecibidos) . ".",
                    $line,
                    $column
                );
                return self::TYPE_ERROR;
            }

            return self::TYPE_STRING;
        }

        return self::TYPE_UNKNOWN;
    }
}
