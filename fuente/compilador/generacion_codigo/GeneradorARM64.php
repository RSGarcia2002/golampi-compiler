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
        $flujoControl = $this->detectarFlujoControl($codigoFuente);

        $lineas = [
            '.section .text',
            '.global _start',
            '',
            '_start:',
            '    // Golampi ARM64 (fase 4)',
            '    // Lineas fuente: ' . $lineasFuente,
            '    // Simbolos: ' . $totalSimbolos . ' | Ambitos: ' . $totalScopes,
            '    // Funciones detectadas: ' . count($funciones),
            '    // if: ' . $flujoControl['if'] . ' | for: ' . $flujoControl['for'] . ' | switch: ' . $flujoControl['switch'],
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

        $lineas[] = '';
        $lineas[] = '/* Plantillas de labels/saltos (fase 4 base) */';
        $lineas = array_merge($lineas, $this->bloquesControlFlujo($flujoControl));

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

    /**
     * @return array{if:int,for:int,switch:int}
     */
    private function detectarFlujoControl(string $codigoFuente): array
    {
        return [
            'if' => preg_match_all('/\bif\b/', $codigoFuente) ?: 0,
            'for' => preg_match_all('/\bfor\b/', $codigoFuente) ?: 0,
            'switch' => preg_match_all('/\bswitch\b/', $codigoFuente) ?: 0,
        ];
    }

    /**
     * @param array{if:int,for:int,switch:int} $flujoControl
     * @return array<int,string>
     */
    private function bloquesControlFlujo(array $flujoControl): array
    {
        $lineas = [];

        for ($i = 0; $i < $flujoControl['if']; $i++) {
            $lineas[] = 'L_if_' . $i . '_cond:';
            $lineas[] = '    b L_if_' . $i . '_end';
            $lineas[] = 'L_if_' . $i . '_body:';
            $lineas[] = '    nop';
            $lineas[] = 'L_if_' . $i . '_end:';
            $lineas[] = '    nop';
            $lineas[] = '';
        }

        for ($i = 0; $i < $flujoControl['for']; $i++) {
            $lineas[] = 'L_for_' . $i . '_cond:';
            $lineas[] = '    b L_for_' . $i . '_end';
            $lineas[] = 'L_for_' . $i . '_body:';
            $lineas[] = '    b L_for_' . $i . '_cond';
            $lineas[] = 'L_for_' . $i . '_end:';
            $lineas[] = '    nop';
            $lineas[] = '';
        }

        for ($i = 0; $i < $flujoControl['switch']; $i++) {
            $lineas[] = 'L_switch_' . $i . '_case_0:';
            $lineas[] = '    b L_switch_' . $i . '_end';
            $lineas[] = 'L_switch_' . $i . '_default:';
            $lineas[] = '    nop';
            $lineas[] = 'L_switch_' . $i . '_end:';
            $lineas[] = '    nop';
            $lineas[] = '';
        }

        if ($lineas === []) {
            $lineas[] = 'L_control_vacio:';
            $lineas[] = '    nop';
        }

        return $lineas;
    }
}
