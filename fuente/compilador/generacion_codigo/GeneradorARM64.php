<?php

declare(strict_types=1);

final class GeneradorARM64 extends GolampiBaseVisitor
{
    private const STACK_FRAME_SIZE = 4096;

    /** @var array<int,string> */
    private array $lineas = [];

    /** @var array<string,int> */
    private array $variablesLocales = [];

    private int $contadorEtiquetas = 0;
    private int $contadorSlotsLocales = 0;
    private string $funcionActual = '';

    /** @var array<int, array{cond:string,end:string}> */
    private array $pilaBucles = [];

    /** @var array<int, string> */
    private array $pilaSwitchFin = [];

    /** @var array<int, array{tipo:string,end:string,cond:string}> */
    private array $pilaControl = [];

    /** @var array<string,int> */
    private array $longitudesConocidas = [];

    /** @var array<string,string> */
    private array $tiposVariables = [];

    private bool $usaHeap = false;

    /** @var array<string,string> */
    private array $etiquetaPorString = [];

    /** @var array<string,string> */
    private array $stringPorEtiqueta = [];

    /** @var array<string,string> */
    private array $valoresStringConocidos = [];
    /** @var array<string,string> */
    private array $valoresFloatConocidos = [];
    /** @var array<string,mixed> */
    private array $valoresArregloConocidos = [];
    /** @var array<string,mixed> */
    private array $valoresIndiceConocidos = [];

    private int $contadorStrings = 0;
    private bool $usaRuntimePrint = false;

    /**
     * @param array<string,mixed> $tablaSimbolos
     */
    public function generarProgramaBase(string $codigoFuente, array $tablaSimbolos, ?object $tree = null): string
    {
        $this->lineas = [];
        $this->usaHeap = false;
        $this->etiquetaPorString = [];
        $this->stringPorEtiqueta = [];
        $this->valoresStringConocidos = [];
        $this->valoresFloatConocidos = [];
        $this->valoresArregloConocidos = [];
        $this->valoresIndiceConocidos = [];
        $this->contadorStrings = 0;
        $this->usaRuntimePrint = false;

        $totalSimbolos = count($tablaSimbolos['symbols'] ?? []);
        $totalScopes = count($tablaSimbolos['scopes'] ?? []);
        $lineasFuente = substr_count($codigoFuente, "\n") + 1;

        $this->emit('.section .text');
        $this->emit('.global _start');
        $this->emit('');
        $this->emit('_start:');
        $this->emit('    // Golampi ARM64 (fase 4 avanzada)');
        $this->emit('    // Lineas fuente: ' . $lineasFuente);
        $this->emit('    // Simbolos: ' . $totalSimbolos . ' | Ambitos: ' . $totalScopes);
        $this->emit('    ldr x20, =heap_base');
        $this->emit('    ldr x21, =heap_end');
        $this->emit('    bl main');
        $this->emit('    mov x0, #0');
        $this->emit('    mov x8, #93');
        $this->emit('    svc #0');

        if ($tree !== null) {
            $this->visit($tree);
            $this->emitRuntimeHelpers();
            return implode("\n", $this->lineas) . "\n";
        }

        $this->emit('');
        $this->emit('.global main');
        $this->emit('main:');
        $this->emit('    stp x29, x30, [sp, #-16]!');
        $this->emit('    mov x29, sp');
        $this->emit('    sub sp, sp, #' . self::STACK_FRAME_SIZE);
        $this->emit('    // TODO: generar desde AST');
        $this->emit('    add sp, sp, #' . self::STACK_FRAME_SIZE);
        $this->emit('    ldp x29, x30, [sp], #16');
        $this->emit('    ret');
        $this->emitRuntimeHelpers();

        return implode("\n", $this->lineas) . "\n";
    }

    public function visitProgram($ctx): mixed
    {
        $this->visit($ctx->mainFunction());

        foreach ($ctx->functionDecl() as $funcionCtx) {
            $this->visit($funcionCtx);
        }

        return null;
    }

    public function visitMainFunction($ctx): mixed
    {
        $this->emit('');
        $this->iniciarFuncion('main', null);
        $this->compilarBloque($ctx->block());
        $this->cerrarFuncion();

        return null;
    }

    public function visitFunctionDecl($ctx): mixed
    {
        $nombre = $ctx->IDENTIFIER()?->getText() ?? 'funcion_sin_nombre';

        $this->emit('');
        $this->iniciarFuncion($nombre, $ctx->paramList());
        $this->compilarBloque($ctx->block());
        $this->cerrarFuncion();

        return null;
    }

    private function iniciarFuncion(string $nombre, $paramListCtx): void
    {
        $this->funcionActual = $nombre;
        $this->variablesLocales = [];
        $this->contadorSlotsLocales = 0;
        $this->pilaBucles = [];
        $this->pilaSwitchFin = [];
        $this->pilaControl = [];
        $this->longitudesConocidas = [];
        $this->tiposVariables = [];
        $this->valoresStringConocidos = [];
        $this->valoresFloatConocidos = [];
        $this->valoresArregloConocidos = [];
        $this->valoresIndiceConocidos = [];

        $this->emit('.global ' . $nombre);
        $this->emit($nombre . ':');
        $this->emit('    stp x29, x30, [sp, #-16]!');
        $this->emit('    mov x29, sp');
        $this->emit('    sub sp, sp, #' . self::STACK_FRAME_SIZE);

        if ($paramListCtx === null) {
            return;
        }

        $parametros = $paramListCtx->param();
        foreach ($parametros as $indice => $param) {
            if ($indice > 7) {
                break;
            }

            $nombreParam = $param->IDENTIFIER()?->getText() ?? ('param' . $indice);
            $offset = $this->reservarSlot($nombreParam);
            $this->emit('    str x' . $indice . ', [x29, #' . $offset . ']');
        }
    }

    private function cerrarFuncion(): void
    {
        $etiquetaSalida = $this->etiquetaSalidaFuncion($this->funcionActual);

        $this->emit($etiquetaSalida . ':');
        $this->emit('    add sp, sp, #' . self::STACK_FRAME_SIZE);
        $this->emit('    ldp x29, x30, [sp], #16');
        $this->emit('    ret');
    }

    private function compilarBloque($blockCtx): void
    {
        if ($blockCtx === null) {
            return;
        }

        foreach ($blockCtx->statement() as $statement) {
            $this->compilarStatement($statement);
        }
    }

    private function compilarStatement($statement): void
    {
        if ($statement->varDecl() !== null) {
            $this->visitVarDecl($statement->varDecl());
            return;
        }

        if ($statement->constDecl() !== null) {
            $this->visitConstDecl($statement->constDecl());
            return;
        }

        if ($statement->shortVarDecl() !== null) {
            $this->visitShortVarDecl($statement->shortVarDecl());
            return;
        }

        if ($statement->assignment() !== null) {
            $this->visitAssignment($statement->assignment());
            return;
        }

        if ($statement->postStmt() !== null) {
            $this->visitPostStmt($statement->postStmt());
            return;
        }

        if ($statement->returnStmt() !== null) {
            $this->visitReturnStmt($statement->returnStmt());
            return;
        }

        if ($statement->printStmt() !== null) {
            $this->visitPrintStmt($statement->printStmt());
            return;
        }

        if ($statement->ifStmt() !== null) {
            $this->visitIfStmt($statement->ifStmt());
            return;
        }

        if ($statement->forStmt() !== null) {
            $this->visitForStmt($statement->forStmt());
            return;
        }

        if ($statement->switchStmt() !== null) {
            $this->visitSwitchStmt($statement->switchStmt());
            return;
        }

        if ($statement->breakStmt() !== null) {
            $this->visitBreakStmt($statement->breakStmt());
            return;
        }

        if ($statement->continueStmt() !== null) {
            $this->visitContinueStmt($statement->continueStmt());
            return;
        }

        if ($statement->expr() !== null) {
            $this->compilarExprEnX0($statement->expr());
            return;
        }

        if ($statement->block() !== null) {
            $this->compilarBloque($statement->block());
        }
    }

    public function visitVarDecl($ctx): mixed
    {
        $identificadores = $ctx->identifierList()?->IDENTIFIER() ?? [];
        $expresiones = $ctx->exprList()?->expr() ?? [];
        $tipoDeclaradoRaw = $ctx->typeSpec()?->getText();
        $limite = count($identificadores);

        for ($i = 0; $i < $limite; $i++) {
            $nombre = $identificadores[$i]->getText();
            $offset = $this->reservarSlot($nombre);
            $tipoActualRaw = $tipoDeclaradoRaw ?? (isset($expresiones[$i]) ? $this->tipoAproximadoExpr($expresiones[$i]) : 'unknown');
            $tipoDeclarado = $this->normalizarTipo($tipoActualRaw);
            $this->tiposVariables[$nombre] = $tipoDeclarado;
            $this->emit("    // declaracion: var {$nombre} {$tipoDeclarado}");

            if (isset($expresiones[$i])) {
                $this->emit("    // inicializacion de '{$nombre}'");
                $this->compilarExprEnX0($expresiones[$i]);
                $this->emit('    str x0, [x29, #' . $offset . ']');

                $len = $this->resolverLenEnCompilacion($expresiones[$i]);
                if ($len !== null) {
                    $this->longitudesConocidas[$nombre] = $len;
                }

                $stringConst = $this->resolverStringConstante($expresiones[$i]);
                if ($stringConst !== null) {
                    $this->valoresStringConocidos[$nombre] = $stringConst;
                }

                $floatConst = $this->resolverFloatConstante($expresiones[$i]);
                if ($floatConst !== null) {
                    $this->valoresFloatConocidos[$nombre] = $floatConst;
                }

                $valorArreglo = $this->resolverValorConstanteExpr($expresiones[$i]);
                if (is_array($valorArreglo)) {
                    $this->valoresArregloConocidos[$nombre] = $valorArreglo;
                    $this->longitudesConocidas[$nombre] = count($valorArreglo);
                }

                continue;
            }

            $this->emit("    // valor por defecto para '{$nombre}'");
            if ($tipoActual === 'string') {
                $etiquetaVacia = $this->registrarLiteralString('');
                $this->emit('    ldr x0, =' . $etiquetaVacia);
            } else {
                $this->emit('    mov x0, #0');
            }
            $this->emit('    str x0, [x29, #' . $offset . ']');

            if ($tipoActualRaw !== null && str_starts_with($tipoActualRaw, '[')) {
                $inicial = $this->crearArregloCeroDesdeTipo($tipoActualRaw);
                if ($inicial !== null) {
                    $this->valoresArregloConocidos[$nombre] = $inicial;
                    $this->longitudesConocidas[$nombre] = count($inicial);
                }
            }
        }

        return null;
    }

    public function visitConstDecl($ctx): mixed
    {
        $identificadores = $ctx->identifierList()?->IDENTIFIER() ?? [];
        $expresiones = $ctx->exprList()?->expr() ?? [];
        $tipoDeclarado = $this->normalizarTipo($ctx->typeSpec()?->getText() ?? 'unknown');
        $limite = min(count($identificadores), count($expresiones));

        for ($i = 0; $i < $limite; $i++) {
            $nombre = $identificadores[$i]->getText();
            $offset = $this->reservarSlot($nombre);
            $this->tiposVariables[$nombre] = $tipoDeclarado;
            $this->emit("    // declaracion: const {$nombre} {$tipoDeclarado}");
            $this->compilarExprEnX0($expresiones[$i]);
            $this->emit('    str x0, [x29, #' . $offset . ']');
        }

        return null;
    }

    public function visitShortVarDecl($ctx): mixed
    {
        $identificadores = $ctx->identifierList()?->IDENTIFIER() ?? [];
        $expresiones = $ctx->exprList()?->expr() ?? [];
        $limite = min(count($identificadores), count($expresiones));

        for ($i = 0; $i < $limite; $i++) {
            $nombre = $identificadores[$i]->getText();
            $offset = $this->reservarSlot($nombre);
            $tipo = $this->normalizarTipo($this->tipoAproximadoExpr($expresiones[$i]));
            $this->tiposVariables[$nombre] = $tipo;
            $this->emit("    // declaracion corta: {$nombre} := <expr>");
            $this->compilarExprEnX0($expresiones[$i]);
            $this->emit('    str x0, [x29, #' . $offset . ']');

            $valorArreglo = $this->resolverValorConstanteExpr($expresiones[$i]);
            if (is_array($valorArreglo)) {
                $this->valoresArregloConocidos[$nombre] = $valorArreglo;
                $this->longitudesConocidas[$nombre] = count($valorArreglo);
            }
        }

        return null;
    }

    public function visitAssignment($ctx): mixed
    {
        $targetText = $ctx->assignTarget()?->getText() ?? '';
        if (preg_match('/^\*?([a-zA-Z_][a-zA-Z_0-9]*)/', $targetText, $matches) !== 1) {
            $this->emit('    // asignacion compleja no soportada en backend base');
            $this->compilarExprEnX0($ctx->expr());
            return null;
        }

        $nombre = $matches[1];
        if ($nombre === '') {
            return null;
        }

        if (!isset($this->variablesLocales[$nombre])) {
            $this->variablesLocales[$nombre] = $this->reservarSlot($nombre);
        }

        $operador = $ctx->assignOp()?->getText() ?? '=';
        $this->emit("    // asignacion: {$nombre} {$operador} <expr>");

        if (str_contains($targetText, '[') && $operador === '=') {
            $this->compilarExprEnX0($ctx->expr());
            $indices = $this->extraerIndicesConstantesDesdeTarget($targetText);
            $valorConst = $this->resolverValorConstanteExpr($ctx->expr());
            if ($indices !== null && $valorConst !== null) {
                $this->asignarElementoArregloConocido($nombre, $indices, $valorConst);
                $this->valoresIndiceConocidos[$targetText] = $valorConst;
            }
            return null;
        }

        if ($operador === '=') {
            $this->compilarExprEnX0($ctx->expr());
        } else {
            $this->emit('    ldr x1, [x29, #' . $this->variablesLocales[$nombre] . ']');
            $this->compilarExprEnX0($ctx->expr());
            if ($operador === '+=') {
                $this->emit('    add x0, x1, x0');
            } elseif ($operador === '-=') {
                $this->emit('    sub x0, x1, x0');
            } elseif ($operador === '*=') {
                $this->emit('    mul x0, x1, x0');
            } elseif ($operador === '/=') {
                $this->emit('    sdiv x0, x1, x0');
            } elseif ($operador === '%=') {
                $this->emit('    sdiv x2, x1, x0');
                $this->emit('    msub x0, x2, x0, x1');
            }
        }

        $this->emit('    str x0, [x29, #' . $this->variablesLocales[$nombre] . ']');

        $len = $this->resolverLenEnCompilacion($ctx->expr());
        if ($len !== null) {
            $this->longitudesConocidas[$nombre] = $len;
        } else {
            unset($this->longitudesConocidas[$nombre]);
        }

        $stringConst = $this->resolverStringConstante($ctx->expr());
        if ($stringConst !== null) {
            $this->valoresStringConocidos[$nombre] = $stringConst;
        } else {
            unset($this->valoresStringConocidos[$nombre]);
        }

        $floatConst = $this->resolverFloatConstante($ctx->expr());
        if ($floatConst !== null) {
            $this->valoresFloatConocidos[$nombre] = $floatConst;
        } else {
            unset($this->valoresFloatConocidos[$nombre]);
        }

        return null;
    }

    public function visitReturnStmt($ctx): mixed
    {
        $this->emit('    // return de funcion');
        $exprs = $ctx->exprList()?->expr() ?? [];
        if (isset($exprs[0])) {
            $this->compilarExprEnX0($exprs[0]);
        }

        $this->emit('    b ' . $this->etiquetaSalidaFuncion($this->funcionActual));
        return null;
    }

    public function visitPrintStmt($ctx): mixed
    {
        $this->emit('    // salida por consola: fmt.Println(...)');
        if ($ctx->argList() === null) {
            $this->usaRuntimePrint = true;
            $this->emit('    bl _golampi_print_nl');
            return null;
        }

        $argumentos = $ctx->argList()->expr();
        if ($argumentos === []) {
            $this->usaRuntimePrint = true;
            $this->emit('    bl _golampi_print_nl');
            return null;
        }

        $this->usaRuntimePrint = true;
        $totalArgs = count($argumentos);
        foreach ($argumentos as $indice => $arg) {
            $this->compilarExprEnX0($arg);

            $tipo = $this->tipoAproximadoExpr($arg);
            $this->emit("    // argumento println tipo: {$tipo}");
            if ($tipo === 'string') {
                $this->emit('    bl _golampi_print_cstr');
            } elseif ($tipo === 'bool') {
                $this->emit('    cmp x0, #0');
                $etiquetaTrue = $this->nuevaEtiqueta('print_bool_true');
                $etiquetaFin = $this->nuevaEtiqueta('print_bool_fin');
                $this->emit('    b.ne ' . $etiquetaTrue);
                $this->emit('    ldr x0, =L_bool_false');
                $this->emit('    bl _golampi_print_cstr');
                $this->emit('    b ' . $etiquetaFin);
                $this->emit($etiquetaTrue . ':');
                $this->emit('    ldr x0, =L_bool_true');
                $this->emit('    bl _golampi_print_cstr');
                $this->emit($etiquetaFin . ':');
            } elseif ($tipo === 'float') {
                $valorFloat = $this->resolverFloatConstante($arg);
                if ($valorFloat !== null) {
                    $this->emit('    // float constante materializado como string');
                    $etiquetaFloat = $this->registrarLiteralString($valorFloat);
                    $this->emit('    ldr x0, =' . $etiquetaFloat);
                    $this->emit('    bl _golampi_print_cstr');
                } else {
                    $this->emit('    // float no constante: salida aproximada como entero');
                    $this->emit('    bl _golampi_print_int');
                }
            } else {
                $this->emit('    bl _golampi_print_int');
            }

            if ($indice < ($totalArgs - 1)) {
                $this->emit('    // separador entre argumentos println');
                $this->emit('    ldr x0, =L_espacio');
                $this->emit('    bl _golampi_print_cstr');
            }
        }
        $this->emit('    bl _golampi_print_nl');

        return null;
    }

    public function visitIfStmt($ctx): mixed
    {
        $this->emit('    // estructura de control: if');
        $etiquetaElse = $this->nuevaEtiqueta('if_else');
        $etiquetaFin = $this->nuevaEtiqueta('if_fin');

        $this->emit('    // evaluar condicion del if');
        $this->compilarExprEnX0($ctx->expr());
        $this->emit('    cmp x0, #0');
        $this->emit('    beq ' . $etiquetaElse);

        $bloques = $ctx->block();
        $this->compilarBloque($bloques[0] ?? null);
        $this->emit('    b ' . $etiquetaFin);

        $this->emit($etiquetaElse . ':');
        if (isset($bloques[1])) {
            $this->compilarBloque($bloques[1]);
        } elseif ($ctx->ifStmt() !== null) {
            $this->visitIfStmt($ctx->ifStmt());
        }

        $this->emit($etiquetaFin . ':');
        return null;
    }

    public function visitForStmt($ctx): mixed
    {
        $this->emit('    // estructura de control: for');
        $etiquetaCond = $this->nuevaEtiqueta('for_cond');
        $etiquetaFin = $this->nuevaEtiqueta('for_fin');
        $this->pilaBucles[] = ['cond' => $etiquetaCond, 'end' => $etiquetaFin];
        $this->pilaControl[] = ['tipo' => 'for', 'end' => $etiquetaFin, 'cond' => $etiquetaCond];

        if ($ctx->forClause() !== null && $ctx->forClause()->forInit() !== null) {
            $this->emit('    // inicializacion del for clasico');
            $this->compilarForComponente($ctx->forClause()->forInit());
        }

        $this->emit($etiquetaCond . ':');
        if ($ctx->forClause() !== null && $ctx->forClause()->expr() !== null) {
            $this->emit('    // evaluar condicion del for clasico');
            $this->compilarExprEnX0($ctx->forClause()->expr());
            $this->emit('    cmp x0, #0');
            $this->emit('    beq ' . $etiquetaFin);
        } elseif ($ctx->expr() !== null) {
            $this->emit('    // evaluar condicion del for');
            $this->compilarExprEnX0($ctx->expr());
            $this->emit('    cmp x0, #0');
            $this->emit('    beq ' . $etiquetaFin);
        }

        $this->compilarBloque($ctx->block());
        if ($ctx->forClause() !== null && $ctx->forClause()->forPost() !== null) {
            $this->emit('    // actualizacion del for clasico');
            $this->compilarForComponente($ctx->forClause()->forPost());
        }
        $this->emit('    b ' . $etiquetaCond);
        $this->emit($etiquetaFin . ':');
        array_pop($this->pilaBucles);
        array_pop($this->pilaControl);

        return null;
    }

    public function visitPostStmt($ctx): mixed
    {
        $nombre = $ctx->IDENTIFIER()?->getText() ?? '';
        if ($nombre === '') {
            return null;
        }

        if (!isset($this->variablesLocales[$nombre])) {
            $this->variablesLocales[$nombre] = $this->reservarSlot($nombre);
        }

        $operador = $ctx->children[1]?->getText() ?? '++';
        $this->emit("    // post-operador: {$nombre}{$operador}");
        $this->emit('    ldr x0, [x29, #' . $this->variablesLocales[$nombre] . ']');
        if ($operador === '--') {
            $this->emit('    sub x0, x0, #1');
        } else {
            $this->emit('    add x0, x0, #1');
        }
        $this->emit('    str x0, [x29, #' . $this->variablesLocales[$nombre] . ']');

        return null;
    }

    public function visitSwitchStmt($ctx): mixed
    {
        $this->emit('    // estructura de control: switch');
        $etiquetaFin = $this->nuevaEtiqueta('switch_fin');
        $etiquetaDefault = $this->nuevaEtiqueta('switch_default');
        $casos = $ctx->switchCase();
        $labelsCasos = [];

        foreach ($casos as $index => $_caso) {
            $labelsCasos[$index] = $this->nuevaEtiqueta('switch_case_' . $index);
        }

        $this->pilaSwitchFin[] = $etiquetaFin;
        $this->pilaControl[] = ['tipo' => 'switch', 'end' => $etiquetaFin, 'cond' => ''];

        $this->emit('    // evaluar expresion principal del switch');
        $this->compilarExprEnX0($ctx->expr());
        $this->emit('    mov x19, x0');

        foreach ($casos as $index => $caso) {
            $this->emit('    // comparar con case #' . $index);
            $this->compilarExprEnX0($caso->expr());
            $this->emit('    cmp x19, x0');
            $this->emit('    beq ' . $labelsCasos[$index]);
        }

        if ($ctx->defaultCase() !== null) {
            $this->emit('    b ' . $etiquetaDefault);
        } else {
            $this->emit('    b ' . $etiquetaFin);
        }

        foreach ($casos as $index => $caso) {
            $this->emit($labelsCasos[$index] . ':');
            foreach ($caso->statement() as $statement) {
                $this->compilarStatement($statement);
            }
            if (!$this->terminaConSalto($caso->statement())) {
                $this->emit('    b ' . $etiquetaFin);
            }
        }

        if ($ctx->defaultCase() !== null) {
            $this->emit($etiquetaDefault . ':');
            foreach ($ctx->defaultCase()->statement() as $statement) {
                $this->compilarStatement($statement);
            }
            if (!$this->terminaConSalto($ctx->defaultCase()->statement())) {
                $this->emit('    b ' . $etiquetaFin);
            }
        }

        $this->emit($etiquetaFin . ':');
        array_pop($this->pilaSwitchFin);
        array_pop($this->pilaControl);

        return null;
    }

    public function visitBreakStmt($ctx): mixed
    {
        $actual = $this->pilaControl[count($this->pilaControl) - 1] ?? null;
        if ($actual !== null) {
            $this->emit('    // break: salir de estructura actual');
            $this->emit('    b ' . $actual['end']);
        }

        return null;
    }

    public function visitContinueStmt($ctx): mixed
    {
        for ($i = count($this->pilaControl) - 1; $i >= 0; $i--) {
            $actual = $this->pilaControl[$i];
            if ($actual['tipo'] === 'for') {
                $this->emit('    // continue: volver a condicion del for');
                $this->emit('    b ' . $actual['cond']);
                break;
            }
        }

        return null;
    }

    private function compilarExprEnX0($exprCtx): void
    {
        if ($exprCtx === null) {
            $this->emit('    mov x0, #0');
            return;
        }

        $clase = (new ReflectionClass($exprCtx))->getShortName();

        if ($clase === 'LiteralExprContext') {
            $texto = $exprCtx->literal()?->getText() ?? '0';
            $this->emit("    // literal detectado: {$texto}");
            $this->emitLiteralEnX0($texto);
            return;
        }

        if ($clase === 'IndexExprContext') {
            $valor = $this->resolverValorConstanteExpr($exprCtx);
            if ($valor !== null) {
                $this->emit('    // acceso por indice (valor conocido en compilacion)');
                $this->emitValorConstanteEnX0($valor);
                return;
            }

            $this->emit('    // acceso por indice (stub backend base)');
            $this->emit('    mov x0, #0');
            return;
        }

        if ($clase === 'IdentifierExprContext') {
            $nombre = $exprCtx->IDENTIFIER()?->getText() ?? '';
            if ($nombre !== '' && isset($this->variablesLocales[$nombre])) {
                $this->emit("    // cargar variable '{$nombre}'");
                $this->emit('    ldr x0, [x29, #' . $this->variablesLocales[$nombre] . ']');
            } else {
                $this->emit('    mov x0, #0');
            }
            return;
        }

        if ($clase === 'GroupedExprContext') {
            $this->compilarExprEnX0($exprCtx->expr());
            return;
        }

        if ($clase === 'CastExprContext') {
            $this->emit('    // conversion de tipo');
            $this->compilarExprEnX0($exprCtx->expr());
            return;
        }

        if ($clase === 'UnaryExprContext') {
            $operador = $exprCtx->children[0]?->getText() ?? '';
            $this->emit("    // expresion unaria '{$operador}'");
            $this->compilarExprEnX0($exprCtx->expr());

            if ($operador === '-') {
                $this->emit('    neg x0, x0');
                return;
            }

            if ($operador === '!') {
                $this->emit('    cmp x0, #0');
                $this->emit('    cset x0, eq');
                return;
            }

            return;
        }

        if ($clase === 'BinaryExprContext') {
            $izquierda = $exprCtx->expr(0);
            $derecha = $exprCtx->expr(1);
            $operador = $exprCtx->children[1]?->getText() ?? '';
            $this->emit("    // expresion binaria '{$operador}'");

            if ($operador === '&&') {
                $falseLabel = $this->nuevaEtiqueta('and_false');
                $endLabel = $this->nuevaEtiqueta('and_end');
                $this->compilarExprEnX0($izquierda);
                $this->emit('    cmp x0, #0');
                $this->emit('    beq ' . $falseLabel);
                $this->compilarExprEnX0($derecha);
                $this->emit('    cmp x0, #0');
                $this->emit('    cset x0, ne');
                $this->emit('    b ' . $endLabel);
                $this->emit($falseLabel . ':');
                $this->emit('    mov x0, #0');
                $this->emit($endLabel . ':');
                return;
            }

            if ($operador === '||') {
                $trueLabel = $this->nuevaEtiqueta('or_true');
                $endLabel = $this->nuevaEtiqueta('or_end');
                $this->compilarExprEnX0($izquierda);
                $this->emit('    cmp x0, #0');
                $this->emit('    bne ' . $trueLabel);
                $this->compilarExprEnX0($derecha);
                $this->emit('    cmp x0, #0');
                $this->emit('    cset x0, ne');
                $this->emit('    b ' . $endLabel);
                $this->emit($trueLabel . ':');
                $this->emit('    mov x0, #1');
                $this->emit($endLabel . ':');
                return;
            }

            $this->compilarExprEnX0($izquierda);
            $this->emit('    sub sp, sp, #16');
            $this->emit('    str x0, [sp, #8]');

            $this->compilarExprEnX0($derecha);
            $this->emit('    ldr x1, [sp, #8]');
            $this->emit('    add sp, sp, #16');

            $this->emitOperacionBinaria($operador);
            return;
        }

        if ($clase === 'CallExprContext') {
            $nombre = $exprCtx->IDENTIFIER()?->getText() ?? '';
            $args = $exprCtx->argList()?->expr() ?? [];
            if ($nombre !== '') {
                $this->emit("    // llamada a funcion '{$nombre}'");
            }

            foreach ($args as $i => $arg) {
                if ($i > 7) {
                    break;
                }
                $this->compilarExprEnX0($arg);
                if ($i > 0) {
                    $this->emit('    mov x' . $i . ', x0');
                }
            }

            if ($nombre !== '') {
                if ($nombre === 'len') {
                    $this->emit('    // builtin len(...)');
                    if (isset($args[0])) {
                        $tipoArg = $this->tipoAproximadoExpr($args[0]);
                        $lenConstante = $this->resolverLenEnCompilacion($args[0]);
                        if ($lenConstante !== null) {
                            $this->emit('    ldr x0, =' . $lenConstante);
                            return;
                        }

                        if ($this->esTipoArreglo($tipoArg)) {
                            $this->compilarExprEnX0($args[0]);
                            $this->emit('    cmp x0, #0');
                            $etiquetaLenNull = $this->nuevaEtiqueta('len_null');
                            $etiquetaLenFin = $this->nuevaEtiqueta('len_fin');
                            $this->emit('    beq ' . $etiquetaLenNull);
                            $this->emit('    ldr x0, [x0, #0]');
                            $this->emit('    b ' . $etiquetaLenFin);
                            $this->emit($etiquetaLenNull . ':');
                            $this->emit('    mov x0, #0');
                            $this->emit($etiquetaLenFin . ':');
                            return;
                        }
                    }

                    $this->emit('    // builtin len (stub)');
                    $this->emit('    mov x0, #0');
                    return;
                }

                if ($nombre === 'now') {
                    $this->emit('    // builtin now(): fecha/hora en string');
                    $valor = date(DATE_ATOM);
                    $etiqueta = $this->registrarLiteralString($valor);
                    $this->emit('    ldr x0, =' . $etiqueta);
                    return;
                }

                if ($nombre === 'typeOf') {
                    $this->emit('    // builtin typeOf(...)');
                    $tipo = isset($args[0]) ? $this->tipoAproximadoExpr($args[0]) : 'unknown';
                    $etiqueta = $this->registrarLiteralString($tipo);
                    $this->emit('    ldr x0, =' . $etiqueta);
                    return;
                }

                if ($nombre === 'substr') {
                    $this->emit('    // builtin substr(...)');
                    if (count($args) === 3) {
                        $base = $this->resolverStringConstante($args[0]);
                        $inicio = $this->resolverEnteroConstante($args[1]);
                        $cantidad = $this->resolverEnteroConstante($args[2]);

                        if ($base !== null && $inicio !== null && $cantidad !== null) {
                            $recorte = $this->recortarString($base, $inicio, $cantidad);
                            $etiqueta = $this->registrarLiteralString($recorte);
                            $this->emit('    ldr x0, =' . $etiqueta);
                            return;
                        }
                    }

                    $this->emit('    // builtin substr (stub)');
                    $this->emit('    mov x0, #0');
                    return;
                }

                $this->emit('    bl ' . $nombre);
                return;
            }

            $this->emit('    mov x0, #0');
            return;
        }

        if ($clase === 'ArrayLiteralExprContext') {
            $this->usaHeap = true;
            $elementos = $exprCtx->argList()?->expr() ?? [];
            $cantidad = count($elementos);
            $bytes = ($cantidad + 1) * 8;

            $this->emit("    // literal de arreglo: {$cantidad} elementos");
            $this->emit('    // reservar arreglo en heap: header(len) + data');
            $this->emit('    ldr x9, =' . $bytes);
            $this->emit('    mov x10, x20');
            $this->emit('    add x11, x20, x9');
            $this->emit('    cmp x11, x21');
            $this->emit('    b.hi L_panic_oom');
            $this->emit('    mov x20, x11');
            $this->emit('    ldr x0, =' . $cantidad);
            $this->emit('    str x0, [x10, #0]');

            foreach ($elementos as $i => $elemExpr) {
                $offset = ($i + 1) * 8;
                $this->compilarExprEnX0($elemExpr);
                $this->emit('    str x0, [x10, #' . $offset . ']');
            }

            $this->emit('    mov x0, x10');
            return;
        }

        if ($clase === 'TypedArrayLiteralExprContext' || $clase === 'BraceArrayLiteralExprContext') {
            $valor = $this->resolverValorConstanteExpr($exprCtx);
            if (is_array($valor)) {
                $this->emit('    // literal de arreglo tipado (referencia simbolica)');
                $this->emit('    mov x0, #0');
                return;
            }
        }

        $this->emit('    mov x0, #0');
    }

    private function emitOperacionBinaria(string $operador): void
    {
        if ($operador === '+') {
            $this->emit('    // operacion aritmetica: suma');
            $this->emit('    add x0, x1, x0');
            return;
        }

        if ($operador === '-') {
            $this->emit('    // operacion aritmetica: resta');
            $this->emit('    sub x0, x1, x0');
            return;
        }

        if ($operador === '*') {
            $this->emit('    // operacion aritmetica: multiplicacion');
            $this->emit('    mul x0, x1, x0');
            return;
        }

        if ($operador === '/') {
            $this->emit('    // operacion aritmetica: division entera');
            $this->emit('    sdiv x0, x1, x0');
            return;
        }

        if ($operador === '%') {
            $this->emit('    // operacion aritmetica: modulo');
            $this->emit('    sdiv x2, x1, x0');
            $this->emit('    msub x0, x2, x0, x1');
            return;
        }

        if ($operador === '==') {
            $this->emit('    // operacion relacional: igualdad');
            $this->emit('    cmp x1, x0');
            $this->emit('    cset x0, eq');
            return;
        }

        if ($operador === '!=') {
            $this->emit('    // operacion relacional: diferencia');
            $this->emit('    cmp x1, x0');
            $this->emit('    cset x0, ne');
            return;
        }

        if ($operador === '<') {
            $this->emit('    // operacion relacional: menor que');
            $this->emit('    cmp x1, x0');
            $this->emit('    cset x0, lt');
            return;
        }

        if ($operador === '<=') {
            $this->emit('    // operacion relacional: menor o igual');
            $this->emit('    cmp x1, x0');
            $this->emit('    cset x0, le');
            return;
        }

        if ($operador === '>') {
            $this->emit('    // operacion relacional: mayor que');
            $this->emit('    cmp x1, x0');
            $this->emit('    cset x0, gt');
            return;
        }

        if ($operador === '>=') {
            $this->emit('    // operacion relacional: mayor o igual');
            $this->emit('    cmp x1, x0');
            $this->emit('    cset x0, ge');
            return;
        }

        if ($operador === '&&') {
            $this->emit('    // operacion logica: AND');
            $this->emit('    cmp x1, #0');
            $this->emit('    cset x1, ne');
            $this->emit('    cmp x0, #0');
            $this->emit('    cset x0, ne');
            $this->emit('    and x0, x1, x0');
            return;
        }

        if ($operador === '||') {
            $this->emit('    // operacion logica: OR');
            $this->emit('    cmp x1, #0');
            $this->emit('    cset x1, ne');
            $this->emit('    cmp x0, #0');
            $this->emit('    cset x0, ne');
            $this->emit('    orr x0, x1, x0');
            return;
        }

        $this->emit('    // operador no soportado: ' . $operador);
        $this->emit('    mov x0, #0');
    }

    private function emitLiteralEnX0(string $texto): void
    {
        if (preg_match('/^-?\d+$/', $texto) === 1) {
            $this->emit('    // literal entero');
            $this->emit('    ldr x0, =' . $texto);
            return;
        }

        if (preg_match('/^-?\d+\.\d+$/', $texto) === 1) {
            $this->emit('    // literal float (aprox. entero para ejecucion base)');
            $this->emit('    ldr x0, =' . (int) ((float) $texto));
            return;
        }

        if ($texto === 'true') {
            $this->emit('    // literal booleano true');
            $this->emit('    mov x0, #1');
            return;
        }

        if ($texto === 'false' || $texto === 'nil') {
            $this->emit($texto === 'nil' ? '    // literal nil (se representa como 0)' : '    // literal booleano false');
            $this->emit('    mov x0, #0');
            return;
        }

        if ($texto !== '' && str_starts_with($texto, '"') && str_ends_with($texto, '"')) {
            $sinComillas = substr($texto, 1, -1);
            $etiqueta = $this->registrarLiteralString(stripcslashes($sinComillas));
            $this->emit('    // literal string');
            $this->emit('    ldr x0, =' . $etiqueta);
            return;
        }

        if ($texto !== '' && str_starts_with($texto, "'") && str_ends_with($texto, "'")) {
            $sinComillas = substr($texto, 1, -1);
            $valor = stripcslashes($sinComillas);
            $codigo = $valor === '' ? 0 : ord($valor[0]);
            $this->emit('    // literal rune/char');
            $this->emit('    ldr x0, =' . $codigo);
            return;
        }

        // float por ahora como stub para mantener compilación base ARM64.
        $this->emit('    // literal float (stub actual -> 0)');
        $this->emit('    mov x0, #0');
    }

    private function resolverLenEnCompilacion($exprCtx): ?int
    {
        if ($exprCtx === null) {
            return null;
        }

        $clase = (new ReflectionClass($exprCtx))->getShortName();
        if ($clase === 'ArrayLiteralExprContext') {
            return count($exprCtx->argList()?->expr() ?? []);
        }

        if ($clase === 'LiteralExprContext') {
            $texto = $exprCtx->literal()?->getText() ?? '';
            if ($texto !== '' && str_starts_with($texto, '"') && str_ends_with($texto, '"')) {
                $sinComillas = substr($texto, 1, -1);
                return strlen(stripcslashes($sinComillas));
            }
        }

        if ($clase === 'IdentifierExprContext') {
            $nombre = $exprCtx->IDENTIFIER()?->getText() ?? '';
            if ($nombre !== '' && isset($this->longitudesConocidas[$nombre])) {
                return $this->longitudesConocidas[$nombre];
            }
        }

        if ($clase === 'CallExprContext') {
            $nombreFuncion = $exprCtx->IDENTIFIER()?->getText() ?? '';
            $args = $exprCtx->argList()?->expr() ?? [];

            if ($nombreFuncion === 'len' && isset($args[0])) {
                return $this->resolverLenEnCompilacion($args[0]);
            }

            if ($nombreFuncion === 'substr' && count($args) === 3) {
                $lenBase = $this->resolverLenEnCompilacion($args[0]);
                $inicio = $this->resolverEnteroConstante($args[1]);
                $cantidad = $this->resolverEnteroConstante($args[2]);

                if ($lenBase !== null && $inicio !== null && $cantidad !== null) {
                    if ($inicio < 0 || $cantidad <= 0 || $inicio >= $lenBase) {
                        return 0;
                    }

                    $restante = $lenBase - $inicio;
                    return min($cantidad, $restante);
                }
            }
        }

        return null;
    }

    private function resolverEnteroConstante($exprCtx): ?int
    {
        $valor = $this->resolverValorConstanteExpr($exprCtx);
        if (is_int($valor)) {
            return $valor;
        }
        if (is_bool($valor)) {
            return $valor ? 1 : 0;
        }

        if ($exprCtx === null) {
            return null;
        }

        $clase = (new ReflectionClass($exprCtx))->getShortName();
        if ($clase === 'LiteralExprContext') {
            $texto = $exprCtx->literal()?->getText() ?? '';
            if (preg_match('/^-?\d+$/', $texto) === 1) {
                return (int) $texto;
            }
        }

        return null;
    }

    private function resolverStringConstante($exprCtx): ?string
    {
        $valor = $this->resolverValorConstanteExpr($exprCtx);
        if (is_string($valor)) {
            return $valor;
        }

        if ($exprCtx === null) {
            return null;
        }

        $clase = (new ReflectionClass($exprCtx))->getShortName();
        if ($clase === 'LiteralExprContext') {
            $texto = $exprCtx->literal()?->getText() ?? '';
            if ($texto !== '' && str_starts_with($texto, '"') && str_ends_with($texto, '"')) {
                return stripcslashes(substr($texto, 1, -1));
            }
            return null;
        }

        if ($clase === 'IdentifierExprContext') {
            $nombre = $exprCtx->IDENTIFIER()?->getText() ?? '';
            if ($nombre !== '' && isset($this->valoresStringConocidos[$nombre])) {
                return $this->valoresStringConocidos[$nombre];
            }
            return null;
        }

        if ($clase === 'CallExprContext') {
            $nombre = $exprCtx->IDENTIFIER()?->getText() ?? '';
            $args = $exprCtx->argList()?->expr() ?? [];

            if ($nombre === 'substr' && count($args) === 3) {
                $base = $this->resolverStringConstante($args[0]);
                $inicio = $this->resolverEnteroConstante($args[1]);
                $cantidad = $this->resolverEnteroConstante($args[2]);
                if ($base !== null && $inicio !== null && $cantidad !== null) {
                    return $this->recortarString($base, $inicio, $cantidad);
                }
            }

            if ($nombre === 'typeOf' && isset($args[0])) {
                return $this->tipoAproximadoExpr($args[0]);
            }

            if ($nombre === 'now') {
                return date(DATE_ATOM);
            }
        }

        return null;
    }

    private function resolverFloatConstante($exprCtx): ?string
    {
        $valor = $this->resolverValorConstanteExpr($exprCtx);
        if (is_float($valor)) {
            $texto = (string) $valor;
            return str_contains($texto, '.') ? $texto : ($texto . '.0');
        }

        if ($exprCtx === null) {
            return null;
        }

        $clase = (new ReflectionClass($exprCtx))->getShortName();
        if ($clase === 'LiteralExprContext') {
            $texto = $exprCtx->literal()?->getText() ?? '';
            if (preg_match('/^-?\d+\.\d+$/', $texto) === 1) {
                return $texto;
            }
            return null;
        }

        if ($clase === 'IdentifierExprContext') {
            $nombre = $exprCtx->IDENTIFIER()?->getText() ?? '';
            if ($nombre !== '' && isset($this->valoresFloatConocidos[$nombre])) {
                return $this->valoresFloatConocidos[$nombre];
            }
        }

        return null;
    }

    private function resolverValorConstanteExpr($exprCtx): mixed
    {
        if ($exprCtx === null) {
            return null;
        }

        $clase = (new ReflectionClass($exprCtx))->getShortName();
        if ($clase === 'LiteralExprContext') {
            $texto = $exprCtx->literal()?->getText() ?? '';
            if (preg_match('/^-?\d+$/', $texto) === 1) {
                return (int) $texto;
            }
            if (preg_match('/^-?\d+\.\d+$/', $texto) === 1) {
                return (float) $texto;
            }
            if ($texto === 'true') {
                return true;
            }
            if ($texto === 'false') {
                return false;
            }
            if ($texto === 'nil') {
                return null;
            }
            if ($texto !== '' && str_starts_with($texto, '"') && str_ends_with($texto, '"')) {
                return stripcslashes(substr($texto, 1, -1));
            }
            if ($texto !== '' && str_starts_with($texto, "'") && str_ends_with($texto, "'")) {
                $sinComillas = stripcslashes(substr($texto, 1, -1));
                return $sinComillas === '' ? 0 : ord($sinComillas[0]);
            }
            return null;
        }

        if ($clase === 'IdentifierExprContext') {
            $nombre = $exprCtx->IDENTIFIER()?->getText() ?? '';
            if ($nombre !== '' && array_key_exists($nombre, $this->valoresArregloConocidos)) {
                return $this->valoresArregloConocidos[$nombre];
            }
            if ($nombre !== '' && isset($this->valoresStringConocidos[$nombre])) {
                return $this->valoresStringConocidos[$nombre];
            }
            if ($nombre !== '' && isset($this->valoresFloatConocidos[$nombre])) {
                return (float) $this->valoresFloatConocidos[$nombre];
            }
            return null;
        }

        if ($clase === 'GroupedExprContext') {
            return $this->resolverValorConstanteExpr($exprCtx->expr());
        }

        if ($clase === 'CastExprContext') {
            $valor = $this->resolverValorConstanteExpr($exprCtx->expr());
            $tipoDestino = $this->normalizarTipo($exprCtx->baseType()?->getText() ?? 'unknown');
            if ($valor === null) {
                return null;
            }
            if ($tipoDestino === 'int') {
                return (int) $valor;
            }
            if ($tipoDestino === 'float') {
                return (float) $valor;
            }
            if ($tipoDestino === 'bool') {
                return (bool) $valor;
            }
            if ($tipoDestino === 'string') {
                return (string) $valor;
            }
            return $valor;
        }

        if ($clase === 'ArrayLiteralExprContext') {
            $exprs = $exprCtx->argList()?->expr() ?? [];
            $resultado = [];
            foreach ($exprs as $expr) {
                $resultado[] = $this->resolverValorConstanteExpr($expr);
            }
            return $resultado;
        }

        if ($clase === 'BraceArrayLiteralExprContext') {
            $exprs = $exprCtx->exprList()?->expr() ?? [];
            $resultado = [];
            foreach ($exprs as $expr) {
                $resultado[] = $this->resolverValorConstanteExpr($expr);
            }
            return $resultado;
        }

        if ($clase === 'TypedArrayLiteralExprContext') {
            $exprs = $exprCtx->exprList()?->expr() ?? [];
            $resultado = [];
            foreach ($exprs as $expr) {
                $resultado[] = $this->resolverValorConstanteExpr($expr);
            }
            return $resultado;
        }

        if ($clase === 'IndexExprContext') {
            $base = $this->resolverValorConstanteExpr($exprCtx->expr(0));
            $indice = $this->resolverEnteroConstante($exprCtx->expr(1));
            if (is_array($base) && $indice !== null && array_key_exists($indice, $base)) {
                return $base[$indice];
            }

            $porTexto = $this->resolverIndiceDesdeTexto($exprCtx->getText());
            if ($porTexto !== null) {
                return $porTexto;
            }
            return null;
        }

        if ($clase === 'CallExprContext') {
            $nombre = $exprCtx->IDENTIFIER()?->getText() ?? '';
            $args = $exprCtx->argList()?->expr() ?? [];
            if ($nombre === 'len' && isset($args[0])) {
                $arg = $this->resolverValorConstanteExpr($args[0]);
                if (is_string($arg)) {
                    return strlen($arg);
                }
                if (is_array($arg)) {
                    return count($arg);
                }
            }
        }

        return null;
    }

    private function tipoAproximadoExpr($exprCtx): string
    {
        if ($exprCtx === null) {
            return 'unknown';
        }

        $clase = (new ReflectionClass($exprCtx))->getShortName();

        if ($clase === 'ArrayLiteralExprContext') {
            return '[]unknown';
        }

        if ($clase === 'TypedArrayLiteralExprContext' || $clase === 'BraceArrayLiteralExprContext') {
            $exprs = $clase === 'TypedArrayLiteralExprContext'
                ? ($exprCtx->exprList()?->expr() ?? [])
                : ($exprCtx->exprList()?->expr() ?? []);
            if ($exprs === []) {
                return '[]unknown';
            }
            $tipoElem = $this->tipoAproximadoExpr($exprs[0]);
            return '[]' . $tipoElem;
        }

        if ($clase === 'IdentifierExprContext') {
            $nombre = $exprCtx->IDENTIFIER()?->getText() ?? '';
            if ($nombre !== '' && isset($this->tiposVariables[$nombre])) {
                return $this->normalizarTipo($this->tiposVariables[$nombre]);
            }
        }

        if ($clase === 'LiteralExprContext') {
            $texto = $exprCtx->literal()?->getText() ?? '';
            if (preg_match('/^-?\d+$/', $texto) === 1) {
                return 'int';
            }
            if (preg_match('/^-?\d+\.\d+$/', $texto) === 1) {
                return 'float';
            }
            if ($texto === 'true' || $texto === 'false') {
                return 'bool';
            }
            if ($texto !== '' && str_starts_with($texto, '"') && str_ends_with($texto, '"')) {
                return 'string';
            }
        }

        if ($clase === 'CallExprContext') {
            $nombre = $exprCtx->IDENTIFIER()?->getText() ?? '';
            if ($nombre === 'len') {
                return 'int';
            }
            if (in_array($nombre, ['now', 'substr', 'typeOf'], true)) {
                return 'string';
            }
        }

        if ($clase === 'BinaryExprContext') {
            $operador = $exprCtx->children[1]?->getText() ?? '';
            if (in_array($operador, ['==', '!=', '<', '<=', '>', '>=', '&&', '||'], true)) {
                return 'bool';
            }
            $tipoIzq = $this->tipoAproximadoExpr($exprCtx->expr(0));
            $tipoDer = $this->tipoAproximadoExpr($exprCtx->expr(1));
            if ($tipoIzq === 'float' || $tipoDer === 'float') {
                return 'float';
            }
            return 'int';
        }

        if ($clase === 'IndexExprContext') {
            $tipoBase = $this->tipoAproximadoExpr($exprCtx->expr(0));
            if (str_starts_with($tipoBase, '*')) {
                $tipoBase = substr($tipoBase, 1);
            }
            if (str_starts_with($tipoBase, '[]')) {
                return substr($tipoBase, 2);
            }
            return 'unknown';
        }

        if ($clase === 'CastExprContext') {
            return $this->normalizarTipo($exprCtx->baseType()?->getText() ?? 'unknown');
        }

        if ($clase === 'UnaryExprContext') {
            $operador = $exprCtx->children[0]?->getText() ?? '';
            return $operador === '!' ? 'bool' : 'int';
        }

        return 'unknown';
    }

    private function normalizarTipo(string $tipo): string
    {
        if (str_starts_with($tipo, '[]')) {
            return '[]' . $this->normalizarTipo(substr($tipo, 2));
        }

        if ($tipo === 'int32' || $tipo === 'rune') {
            return 'int';
        }
        if ($tipo === 'float32') {
            return 'float';
        }
        return $tipo;
    }

    private function emitValorConstanteEnX0(mixed $valor): void
    {
        if (is_int($valor)) {
            $this->emit('    ldr x0, =' . $valor);
            return;
        }
        if (is_bool($valor)) {
            $this->emit('    mov x0, #' . ($valor ? '1' : '0'));
            return;
        }
        if (is_string($valor)) {
            $etiqueta = $this->registrarLiteralString($valor);
            $this->emit('    ldr x0, =' . $etiqueta);
            return;
        }
        if (is_float($valor)) {
            $this->emit('    ldr x0, =' . (int) $valor);
            return;
        }
        $this->emit('    mov x0, #0');
    }

    private function extraerIndicesConstantesDesdeTarget(string $targetText): ?array
    {
        if (!preg_match_all('/\[([^\]]+)\]/', $targetText, $matches)) {
            return null;
        }
        $indices = [];
        foreach ($matches[1] as $raw) {
            if (preg_match('/^-?\d+$/', trim($raw)) !== 1) {
                return null;
            }
            $indices[] = (int) trim($raw);
        }
        return $indices;
    }

    private function asignarElementoArregloConocido(string $nombre, array $indices, mixed $valor): void
    {
        if (!isset($this->valoresArregloConocidos[$nombre])) {
            return;
        }
        $ref = &$this->valoresArregloConocidos[$nombre];
        $ultimo = array_pop($indices);
        foreach ($indices as $idx) {
            if (!is_array($ref) || !array_key_exists($idx, $ref)) {
                return;
            }
            $ref = &$ref[$idx];
        }
        if (!is_array($ref) || $ultimo === null || !array_key_exists($ultimo, $ref)) {
            return;
        }
        $ref[$ultimo] = $valor;
    }

    private function crearArregloCeroDesdeTipo(string $tipo): ?array
    {
        if (preg_match_all('/\[([0-9]+)\]/', $tipo, $matches) < 1 || $matches[1] === []) {
            return null;
        }
        $dims = array_map(static fn($x) => (int) $x, $matches[1]);
        return $this->crearArregloCeroRec($dims, 0);
    }

    private function crearArregloCeroRec(array $dims, int $nivel): array
    {
        $tam = $dims[$nivel] ?? 0;
        $out = [];
        for ($i = 0; $i < $tam; $i++) {
            $out[] = isset($dims[$nivel + 1]) ? $this->crearArregloCeroRec($dims, $nivel + 1) : 0;
        }
        return $out;
    }

    private function resolverIndiceDesdeTexto(string $texto): mixed
    {
        if (array_key_exists($texto, $this->valoresIndiceConocidos)) {
            return $this->valoresIndiceConocidos[$texto];
        }

        if (preg_match('/^([a-zA-Z_][a-zA-Z_0-9]*)(\[[0-9]+\])+$/', $texto, $m) !== 1) {
            return null;
        }
        $nombre = $m[1];
        if (!isset($this->valoresArregloConocidos[$nombre])) {
            return null;
        }
        if (!preg_match_all('/\[([0-9]+)\]/', $texto, $indicesMatch)) {
            return null;
        }

        $actual = $this->valoresArregloConocidos[$nombre];
        foreach ($indicesMatch[1] as $raw) {
            $idx = (int) $raw;
            if (!is_array($actual) || !array_key_exists($idx, $actual)) {
                return null;
            }
            $actual = $actual[$idx];
        }

        return $actual;
    }

    private function compilarForComponente($ctx): void
    {
        if ($ctx === null) {
            return;
        }

        if (method_exists($ctx, 'shortVarDecl') && $ctx->shortVarDecl() !== null) {
            $this->visitShortVarDecl($ctx->shortVarDecl());
            return;
        }

        if (method_exists($ctx, 'assignment') && $ctx->assignment() !== null) {
            $this->visitAssignment($ctx->assignment());
            return;
        }

        if (method_exists($ctx, 'postStmt') && $ctx->postStmt() !== null) {
            $this->visitPostStmt($ctx->postStmt());
            return;
        }

        if (method_exists($ctx, 'expr') && $ctx->expr() !== null) {
            $this->compilarExprEnX0($ctx->expr());
            return;
        }

        // fallback para contexts directos
        $clase = (new ReflectionClass($ctx))->getShortName();
        if (str_contains($clase, 'ShortVarDecl')) {
            $this->visitShortVarDecl($ctx);
            return;
        }
        if (str_contains($clase, 'Assignment')) {
            $this->visitAssignment($ctx);
            return;
        }
        if (str_contains($clase, 'PostStmt')) {
            $this->visitPostStmt($ctx);
            return;
        }
        if (str_contains($clase, 'Expr')) {
            $this->compilarExprEnX0($ctx);
        }
    }

    private function esTipoArreglo(string $tipo): bool
    {
        return str_starts_with($tipo, '[]');
    }

    /**
     * @param array<int,mixed> $statements
     */
    private function terminaConSalto(array $statements): bool
    {
        if ($statements === []) {
            return false;
        }

        $ultima = $statements[count($statements) - 1];
        return $ultima->breakStmt() !== null
            || $ultima->continueStmt() !== null
            || $ultima->returnStmt() !== null;
    }

    private function reservarSlot(string $nombre): int
    {
        if (isset($this->variablesLocales[$nombre])) {
            return $this->variablesLocales[$nombre];
        }

        $this->contadorSlotsLocales++;
        $offset = -8 * $this->contadorSlotsLocales;
        $this->variablesLocales[$nombre] = $offset;

        return $offset;
    }

    private function etiquetaSalidaFuncion(string $nombreFuncion): string
    {
        return 'L_' . $nombreFuncion . '_salida';
    }

    private function nuevaEtiqueta(string $prefijo): string
    {
        $etiqueta = 'L_' . $prefijo . '_' . $this->contadorEtiquetas;
        $this->contadorEtiquetas++;
        return $etiqueta;
    }

    private function emit(string $linea): void
    {
        $this->lineas[] = $linea;
    }

    private function emitRuntimeHelpers(): void
    {
        if ($this->usaRuntimePrint) {
            $this->emit('');
            $this->emit('_golampi_print_nl:');
            $this->emit('    ldr x0, =L_nueva_linea');
            $this->emit('    b _golampi_print_cstr');
            $this->emit('');
            $this->emit('_golampi_print_cstr:');
            $this->emit('    mov x1, x0');
            $this->emit('    mov x2, #0');
            $this->emit('L_print_cstr_len:');
            $this->emit('    ldrb w3, [x1, x2]');
            $this->emit('    cbz w3, L_print_cstr_write');
            $this->emit('    add x2, x2, #1');
            $this->emit('    b L_print_cstr_len');
            $this->emit('L_print_cstr_write:');
            $this->emit('    mov x0, #1');
            $this->emit('    mov x8, #64');
            $this->emit('    svc #0');
            $this->emit('    ret');
            $this->emit('');
            $this->emit('_golampi_print_int:');
            $this->emit('    sub sp, sp, #64');
            $this->emit('    mov x9, x0');
            $this->emit('    mov w10, #0');
            $this->emit('    cmp x9, #0');
            $this->emit('    b.ge L_print_int_abs_listo');
            $this->emit('    mov w10, #1');
            $this->emit('    neg x9, x9');
            $this->emit('L_print_int_abs_listo:');
            $this->emit('    mov x11, sp');
            $this->emit('    add x11, x11, #63');
            $this->emit('    mov w12, #0');
            $this->emit('    cmp x9, #0');
            $this->emit('    b.ne L_print_int_loop');
            $this->emit('    mov w13, #48');
            $this->emit('    strb w13, [x11]');
            $this->emit('    mov w12, #1');
            $this->emit('    b L_print_int_signo');
            $this->emit('L_print_int_loop:');
            $this->emit('    mov x14, #10');
            $this->emit('L_print_int_digit:');
            $this->emit('    udiv x15, x9, x14');
            $this->emit('    msub x16, x15, x14, x9');
            $this->emit('    add w16, w16, #48');
            $this->emit('    strb w16, [x11]');
            $this->emit('    sub x11, x11, #1');
            $this->emit('    add w12, w12, #1');
            $this->emit('    mov x9, x15');
            $this->emit('    cbnz x9, L_print_int_digit');
            $this->emit('    add x11, x11, #1');
            $this->emit('L_print_int_signo:');
            $this->emit('    cmp w10, #0');
            $this->emit('    beq L_print_int_emitir');
            $this->emit('    sub x11, x11, #1');
            $this->emit('    mov w13, #45');
            $this->emit('    strb w13, [x11]');
            $this->emit('    add w12, w12, #1');
            $this->emit('L_print_int_emitir:');
            $this->emit('    mov x0, #1');
            $this->emit('    mov x1, x11');
            $this->emit('    uxtw x2, w12');
            $this->emit('    mov x8, #64');
            $this->emit('    svc #0');
            $this->emit('    add sp, sp, #64');
            $this->emit('    ret');
        }

        if ($this->usaHeap) {
            $this->emit('');
            $this->emit('L_panic_oom:');
            $this->emit('    mov x0, #7');
            $this->emit('    mov x8, #93');
            $this->emit('    svc #0');
        }

        if ($this->stringPorEtiqueta !== []) {
            $this->emit('');
            $this->emit('.section .rodata');
            foreach ($this->stringPorEtiqueta as $etiqueta => $valor) {
                $this->emit($etiqueta . ':');
                $this->emit('    .asciz "' . $this->escaparAsmString($valor) . '"');
            }
        }

        if ($this->usaRuntimePrint) {
            if ($this->stringPorEtiqueta === []) {
                $this->emit('');
                $this->emit('.section .rodata');
            }
            $this->emit('L_espacio:');
            $this->emit('    .asciz " "');
            $this->emit('L_nueva_linea:');
            $this->emit('    .asciz "\\n"');
            $this->emit('L_bool_true:');
            $this->emit('    .asciz "true"');
            $this->emit('L_bool_false:');
            $this->emit('    .asciz "false"');
        }

        $this->emit('');
        $this->emit('.section .bss');
        $this->emit('    .align 3');
        $this->emit('heap_base:');
        $this->emit('    .skip 1048576');
        $this->emit('heap_end:');
    }

    private function registrarLiteralString(string $valor): string
    {
        if (isset($this->etiquetaPorString[$valor])) {
            return $this->etiquetaPorString[$valor];
        }

        $etiqueta = 'L_str_' . $this->contadorStrings;
        $this->contadorStrings++;
        $this->etiquetaPorString[$valor] = $etiqueta;
        $this->stringPorEtiqueta[$etiqueta] = $valor;
        return $etiqueta;
    }

    private function escaparAsmString(string $texto): string
    {
        return addcslashes($texto, "\\\"\n\r\t");
    }

    private function recortarString(string $base, int $inicio, int $cantidad): string
    {
        if ($inicio < 0 || $cantidad <= 0 || $inicio >= strlen($base)) {
            return '';
        }

        return substr($base, $inicio, $cantidad);
    }
}
