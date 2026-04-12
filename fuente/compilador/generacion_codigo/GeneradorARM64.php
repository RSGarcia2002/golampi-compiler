<?php

declare(strict_types=1);

final class GeneradorARM64 extends GolampiBaseVisitor
{
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

    /**
     * @param array<string,mixed> $tablaSimbolos
     */
    public function generarProgramaBase(string $codigoFuente, array $tablaSimbolos, ?object $tree = null): string
    {
        $this->lineas = [];

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
        $this->emit('    bl main');
        $this->emit('    mov x0, #0');
        $this->emit('    mov x8, #93');
        $this->emit('    svc #0');

        if ($tree !== null) {
            $this->visit($tree);
            return implode("\n", $this->lineas) . "\n";
        }

        $this->emit('');
        $this->emit('.global main');
        $this->emit('main:');
        $this->emit('    stp x29, x30, [sp, #-16]!');
        $this->emit('    mov x29, sp');
        $this->emit('    sub sp, sp, #256');
        $this->emit('    // TODO: generar desde AST');
        $this->emit('    add sp, sp, #256');
        $this->emit('    ldp x29, x30, [sp], #16');
        $this->emit('    ret');

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

        $this->emit('.global ' . $nombre);
        $this->emit($nombre . ':');
        $this->emit('    stp x29, x30, [sp, #-16]!');
        $this->emit('    mov x29, sp');
        $this->emit('    sub sp, sp, #256');

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
        $this->emit('    add sp, sp, #256');
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

        if ($statement->assignment() !== null) {
            $this->visitAssignment($statement->assignment());
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
        $nombre = $ctx->IDENTIFIER()?->getText() ?? 'tmp';
        $offset = $this->reservarSlot($nombre);

        if ($ctx->expr() !== null) {
            $this->compilarExprEnX0($ctx->expr());
            $this->emit('    str x0, [x29, #' . $offset . ']');

            $len = $this->resolverLenEnCompilacion($ctx->expr());
            if ($len !== null) {
                $this->longitudesConocidas[$nombre] = $len;
            }
            return null;
        }

        $this->emit('    mov x0, #0');
        $this->emit('    str x0, [x29, #' . $offset . ']');
        return null;
    }

    public function visitAssignment($ctx): mixed
    {
        $nombre = $ctx->IDENTIFIER()?->getText() ?? '';
        if ($nombre === '') {
            return null;
        }

        if (!isset($this->variablesLocales[$nombre])) {
            $this->variablesLocales[$nombre] = $this->reservarSlot($nombre);
        }

        $this->compilarExprEnX0($ctx->expr());
        $this->emit('    str x0, [x29, #' . $this->variablesLocales[$nombre] . ']');

        $len = $this->resolverLenEnCompilacion($ctx->expr());
        if ($len !== null) {
            $this->longitudesConocidas[$nombre] = $len;
        } else {
            unset($this->longitudesConocidas[$nombre]);
        }

        return null;
    }

    public function visitReturnStmt($ctx): mixed
    {
        if ($ctx->expr() !== null) {
            $this->compilarExprEnX0($ctx->expr());
        }

        $this->emit('    b ' . $this->etiquetaSalidaFuncion($this->funcionActual));
        return null;
    }

    public function visitPrintStmt($ctx): mixed
    {
        if ($ctx->argList() === null) {
            return null;
        }

        $argumentos = $ctx->argList()->expr();
        if ($argumentos === []) {
            return null;
        }

        // Por ahora dejamos el último valor evaluado en x0 como salida de depuración.
        $ultimo = end($argumentos);
        if ($ultimo !== false) {
            $this->compilarExprEnX0($ultimo);
        }

        return null;
    }

    public function visitIfStmt($ctx): mixed
    {
        $etiquetaElse = $this->nuevaEtiqueta('if_else');
        $etiquetaFin = $this->nuevaEtiqueta('if_fin');

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
        $etiquetaCond = $this->nuevaEtiqueta('for_cond');
        $etiquetaFin = $this->nuevaEtiqueta('for_fin');
        $this->pilaBucles[] = ['cond' => $etiquetaCond, 'end' => $etiquetaFin];
        $this->pilaControl[] = ['tipo' => 'for', 'end' => $etiquetaFin, 'cond' => $etiquetaCond];

        $this->emit($etiquetaCond . ':');
        if ($ctx->expr() !== null) {
            $this->compilarExprEnX0($ctx->expr());
            $this->emit('    cmp x0, #0');
            $this->emit('    beq ' . $etiquetaFin);
        }

        $this->compilarBloque($ctx->block());
        $this->emit('    b ' . $etiquetaCond);
        $this->emit($etiquetaFin . ':');
        array_pop($this->pilaBucles);
        array_pop($this->pilaControl);

        return null;
    }

    public function visitSwitchStmt($ctx): mixed
    {
        $etiquetaFin = $this->nuevaEtiqueta('switch_fin');
        $etiquetaDefault = $this->nuevaEtiqueta('switch_default');
        $casos = $ctx->switchCase();
        $labelsCasos = [];

        foreach ($casos as $index => $_caso) {
            $labelsCasos[$index] = $this->nuevaEtiqueta('switch_case_' . $index);
        }

        $this->pilaSwitchFin[] = $etiquetaFin;
        $this->pilaControl[] = ['tipo' => 'switch', 'end' => $etiquetaFin, 'cond' => ''];

        $this->compilarExprEnX0($ctx->expr());
        $this->emit('    mov x19, x0');

        foreach ($casos as $index => $caso) {
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
            $this->emit('    b ' . $actual['end']);
        }

        return null;
    }

    public function visitContinueStmt($ctx): mixed
    {
        for ($i = count($this->pilaControl) - 1; $i >= 0; $i--) {
            $actual = $this->pilaControl[$i];
            if ($actual['tipo'] === 'for') {
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
            $this->emitLiteralEnX0($texto);
            return;
        }

        if ($clase === 'IdentifierExprContext') {
            $nombre = $exprCtx->IDENTIFIER()?->getText() ?? '';
            if ($nombre !== '' && isset($this->variablesLocales[$nombre])) {
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

        if ($clase === 'UnaryExprContext') {
            $operador = $exprCtx->children[0]?->getText() ?? '';
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
                    if (isset($args[0])) {
                        $lenConstante = $this->resolverLenEnCompilacion($args[0]);
                        if ($lenConstante !== null) {
                            $this->emit('    ldr x0, =' . $lenConstante);
                            return;
                        }
                    }

                    $this->emit('    // builtin len (stub)');
                    $this->emit('    mov x0, #0');
                    return;
                }

                if (in_array($nombre, ['now', 'substr', 'typeOf'], true)) {
                    $this->emit('    // builtin ' . $nombre . ' (stub)');
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
            $cantidad = count($exprCtx->argList()?->expr() ?? []);
            $this->emit('    mov x0, #' . $cantidad);
            return;
        }

        $this->emit('    mov x0, #0');
    }

    private function emitOperacionBinaria(string $operador): void
    {
        if ($operador === '+') {
            $this->emit('    add x0, x1, x0');
            return;
        }

        if ($operador === '-') {
            $this->emit('    sub x0, x1, x0');
            return;
        }

        if ($operador === '*') {
            $this->emit('    mul x0, x1, x0');
            return;
        }

        if ($operador === '/') {
            $this->emit('    sdiv x0, x1, x0');
            return;
        }

        if ($operador === '%') {
            $this->emit('    sdiv x2, x1, x0');
            $this->emit('    msub x0, x2, x0, x1');
            return;
        }

        if ($operador === '==') {
            $this->emit('    cmp x1, x0');
            $this->emit('    cset x0, eq');
            return;
        }

        if ($operador === '!=') {
            $this->emit('    cmp x1, x0');
            $this->emit('    cset x0, ne');
            return;
        }

        if ($operador === '<') {
            $this->emit('    cmp x1, x0');
            $this->emit('    cset x0, lt');
            return;
        }

        if ($operador === '<=') {
            $this->emit('    cmp x1, x0');
            $this->emit('    cset x0, le');
            return;
        }

        if ($operador === '>') {
            $this->emit('    cmp x1, x0');
            $this->emit('    cset x0, gt');
            return;
        }

        if ($operador === '>=') {
            $this->emit('    cmp x1, x0');
            $this->emit('    cset x0, ge');
            return;
        }

        if ($operador === '&&') {
            $this->emit('    cmp x1, #0');
            $this->emit('    cset x1, ne');
            $this->emit('    cmp x0, #0');
            $this->emit('    cset x0, ne');
            $this->emit('    and x0, x1, x0');
            return;
        }

        if ($operador === '||') {
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
            $this->emit('    ldr x0, =' . $texto);
            return;
        }

        if ($texto === 'true') {
            $this->emit('    mov x0, #1');
            return;
        }

        if ($texto === 'false' || $texto === 'nil') {
            $this->emit('    mov x0, #0');
            return;
        }

        // string/float por ahora como stub para mantener compilación base ARM64.
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
            if ($texto !== '' && str_starts_with($texto, '\"') && str_ends_with($texto, '\"')) {
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

        return null;
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
}
