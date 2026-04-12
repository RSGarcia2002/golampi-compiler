<?php

declare(strict_types=1);

final class GeneradorARM64
{
    /**
     * @param array<string,mixed> $tablaSimbolos
     */
    public function generarProgramaBase(string $codigoFuente, array $tablaSimbolos): string
    {
        $totalSimbolos = count($tablaSimbolos['symbols'] ?? []);
        $totalScopes = count($tablaSimbolos['scopes'] ?? []);
        $lineasFuente = substr_count($codigoFuente, "\n") + 1;

        return implode("\n", [
            '.section .text',
            '.global _start',
            '',
            '_start:',
            '    // Golampi ARM64 (fase 4 inicial)',
            '    // Lineas fuente: ' . $lineasFuente,
            '    // Simbolos: ' . $totalSimbolos . ' | Ambitos: ' . $totalScopes,
            '    mov x0, #0',
            '    mov x8, #93',
            '    svc #0',
            '',
        ]);
    }
}
