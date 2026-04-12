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
        $funciones = $this->extraerFuncionesUsuario($tablaSimbolos);

        $lineas = [
            '.section .text',
            '.global _start',
            '',
            '_start:',
            '    // Golampi ARM64 (fase 4)',
            '    // Lineas fuente: ' . $lineasFuente,
            '    // Simbolos: ' . $totalSimbolos . ' | Ambitos: ' . $totalScopes,
            '    // Funciones detectadas: ' . count($funciones),
            '    bl main',
            '    mov x0, #0',
            '    mov x8, #93',
            '    svc #0',
            '',
        ];

        $lineas[] = $this->bloqueFuncion('main');
        foreach ($funciones as $funcion) {
            if ($funcion === 'main') {
                continue;
            }
            $lineas[] = '';
            $lineas[] = $this->bloqueFuncion($funcion);
        }

        return implode("\n", $lineas) . "\n";
    }

    /**
     * @param array<string,mixed> $tablaSimbolos
     * @return array<int,string>
     */
    private function extraerFuncionesUsuario(array $tablaSimbolos): array
    {
        $simbolos = $tablaSimbolos['symbols'] ?? [];
        if (!is_array($simbolos)) {
            return [];
        }

        $funciones = [];
        foreach ($simbolos as $simbolo) {
            if (!is_array($simbolo)) {
                continue;
            }

            $kind = (string) ($simbolo['kind'] ?? '');
            if ($kind !== 'function') {
                continue;
            }

            $nombre = (string) ($simbolo['name'] ?? '');
            if ($nombre === '') {
                continue;
            }

            $funciones[$nombre] = true;
        }

        return array_keys($funciones);
    }

    private function bloqueFuncion(string $nombre): string
    {
        return implode("\n", [
            '.global ' . $nombre,
            $nombre . ':',
            '    stp x29, x30, [sp, #-16]!',
            '    mov x29, sp',
            "    // TODO: cuerpo de funcion '$nombre'",
            '    mov x0, #0',
            '    ldp x29, x30, [sp], #16',
            '    ret',
        ]);
    }
}
