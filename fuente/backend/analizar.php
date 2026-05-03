<?php

declare(strict_types=1);

use Antlr\Antlr4\Runtime\CommonTokenStream;
use Antlr\Antlr4\Runtime\InputStream;
use Antlr\Antlr4\Runtime\Error\Exceptions\RecognitionException;
use Antlr\Antlr4\Runtime\Error\Listeners\BaseErrorListener;
use Antlr\Antlr4\Runtime\Recognizer;

$projectRoot = dirname(__DIR__, 2);
$generatedDir = $projectRoot . '/fuente/compilador/generado';
$reportsDir = $projectRoot . '/reportes';
$semanticAnalyzerFile = $projectRoot . '/fuente/compilador/semantica/AnalizadorSemantico.php';
$generadorArm64File = $projectRoot . '/fuente/compilador/generacion_codigo/GeneradorARM64.php';

if (file_exists($projectRoot . '/vendor/autoload.php')) {
    require_once $projectRoot . '/vendor/autoload.php';
}

if (!class_exists(InputStream::class) || !class_exists(CommonTokenStream::class)) {
    http_response_code(500);
    if (PHP_SAPI !== 'cli') {
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode([
        'ok' => false,
        'errors' => [[
            'type' => 'setup',
            'description' => 'Falta o esta incompleto el runtime de ANTLR para PHP. Ejecuta composer install.',
            'line' => 0,
            'column' => 0,
        ]],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(1);
}

if (is_dir($generatedDir)) {
    $archivosGenerados = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($generatedDir, FilesystemIterator::SKIP_DOTS)
    );

    /** @var SplFileInfo $fileInfo */
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isFile() && $fileInfo->getExtension() === 'php') {
            $archivosGenerados[] = $fileInfo->getPathname();
        }
    }

    sort($archivosGenerados, SORT_STRING);

    // Carga determinística para evitar errores de dependencias entre Visitor/BaseVisitor.
    $ordenPrioritario = [
        $generatedDir . '/GolampiLexer.php',
        $generatedDir . '/GolampiParser.php',
        $generatedDir . '/GolampiVisitor.php',
        $generatedDir . '/GolampiBaseVisitor.php',
    ];

    foreach ($ordenPrioritario as $archivoPrioritario) {
        if (is_file($archivoPrioritario)) {
            require_once $archivoPrioritario;
        }
    }

    foreach ($archivosGenerados as $archivoGenerado) {
        if (in_array($archivoGenerado, $ordenPrioritario, true)) {
            continue;
        }
        require_once $archivoGenerado;
    }
}

if (!class_exists('GolampiLexer') || !class_exists('GolampiParser')) {
    http_response_code(500);
    $errorPayload = [
        'ok' => false,
        'errors' => [[
            'type' => 'setup',
            'description' => 'No se encontraron GolampiLexer/GolampiParser. Ejecuta guiones/generar_antlr_php.sh primero.',
            'line' => 0,
            'column' => 0,
        ]],
    ];

    if (PHP_SAPI !== 'cli') {
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode($errorPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(1);
}

final class CollectingErrorListener extends BaseErrorListener
{
    /** @var array<int, array{type:string,description:string,line:int,column:int}> */
    private array $errors = [];

    public function __construct(private string $errorType)
    {
    }

    public function syntaxError(
        Recognizer $recognizer,
        ?object $offendingSymbol,
        int $line,
        int $charPositionInLine,
        string $msg,
        ?RecognitionException $e = null
    ): void {
        $this->errors[] = [
            'type' => $this->errorType,
            'description' => $msg,
            'line' => $line,
            'column' => $charPositionInLine,
        ];
    }

    /** @return array<int, array{type:string,description:string,line:int,column:int}> */
    public function all(): array
    {
        return $this->errors;
    }
}

/** @return array<string, mixed> */
function readInputSource(): array
{
    if (PHP_SAPI === 'cli') {
        global $argv;
        $execute = in_array('--ejecutar', $argv, true);

        if (isset($argv[1]) && is_file($argv[1])) {
            $source = file_get_contents($argv[1]);
            if ($source === false) {
                return ['ok' => false, 'error' => 'No se pudo leer el archivo de entrada.'];
            }
            return ['ok' => true, 'source' => $source, 'execute' => $execute];
        }

        $stdin = stream_get_contents(STDIN);
        if ($stdin !== false && trim($stdin) !== '') {
            return ['ok' => true, 'source' => $stdin, 'execute' => $execute];
        }

        return ['ok' => false, 'error' => 'Uso CLI: php fuente/backend/analizar.php <archivo.gol> [--ejecutar] o enviar código por STDIN.'];
    }

    $rawInput = file_get_contents('php://input');
    if ($rawInput === false || trim($rawInput) === '') {
        return ['ok' => false, 'error' => 'Body vacío. Esperado: JSON con campo "source".'];
    }

    $json = json_decode($rawInput, true);
    if (!is_array($json) || !isset($json['source']) || !is_string($json['source'])) {
        return ['ok' => false, 'error' => 'Formato inválido. Esperado: {"source": "..."}.'];
    }

    return [
        'ok' => true,
        'source' => $json['source'],
        'execute' => isset($json['execute']) ? (bool) $json['execute'] : false,
    ];
}

function comandoDisponible(string $comando): bool
{
    $resultado = shell_exec('command -v ' . escapeshellarg($comando) . ' 2>/dev/null');
    return is_string($resultado) && trim($resultado) !== '';
}

/**
 * @return array{exit_code:int,stdout:string,stderr:string}
 */
function ejecutarComando(string $comando, string $cwd): array
{
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $proceso = proc_open($comando, $descriptorSpec, $pipes, $cwd);
    if (!is_resource($proceso)) {
        return [
            'exit_code' => 127,
            'stdout' => '',
            'stderr' => 'No se pudo iniciar el proceso.',
        ];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($proceso);

    return [
        'exit_code' => is_int($exitCode) ? $exitCode : 1,
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
    ];
}

/**
 * @return array<string,mixed>
 */
function ejecutarArm64SiDisponible(string $projectRoot, string $reportsDir): array
{
    if (PHP_OS_FAMILY !== 'Linux') {
        return [
            'intentada' => false,
            'disponible' => false,
            'ok' => false,
            'codigo_salida' => null,
            'stdout' => '',
            'stderr' => '',
            'mensaje' => 'La ejecución ARM64 automática está habilitada solo en Linux.',
        ];
    }

    $faltantes = [];
    foreach (['aarch64-linux-gnu-as', 'aarch64-linux-gnu-ld', 'qemu-aarch64'] as $cmd) {
        if (!comandoDisponible($cmd)) {
            $faltantes[] = $cmd;
        }
    }

    if ($faltantes !== []) {
        return [
            'intentada' => false,
            'disponible' => false,
            'ok' => false,
            'codigo_salida' => null,
            'stdout' => '',
            'stderr' => '',
            'mensaje' => 'Faltan herramientas: ' . implode(', ', $faltantes) . '. Instala: sudo apt install -y binutils-aarch64-linux-gnu qemu-user',
        ];
    }

    $asmPath = $reportsDir . '/programa_fase4.s';
    $linuxDir = $reportsDir . '/linux';
    $objPath = $linuxDir . '/programa_fase4.o';
    $binPath = $linuxDir . '/programa_fase4';
    if (!is_dir($linuxDir)) {
        mkdir($linuxDir, 0777, true);
    }

    $cmdAs = 'aarch64-linux-gnu-as ' . escapeshellarg($asmPath) . ' -o ' . escapeshellarg($objPath);
    $asResult = ejecutarComando($cmdAs, $projectRoot);
    if ($asResult['exit_code'] !== 0) {
        return [
            'intentada' => true,
            'disponible' => true,
            'ok' => false,
            'codigo_salida' => $asResult['exit_code'],
            'stdout' => $asResult['stdout'],
            'stderr' => $asResult['stderr'],
            'mensaje' => 'Falló el ensamblado ARM64.',
        ];
    }

    $cmdLd = 'aarch64-linux-gnu-ld ' . escapeshellarg($objPath) . ' -o ' . escapeshellarg($binPath);
    $ldResult = ejecutarComando($cmdLd, $projectRoot);
    if ($ldResult['exit_code'] !== 0) {
        return [
            'intentada' => true,
            'disponible' => true,
            'ok' => false,
            'codigo_salida' => $ldResult['exit_code'],
            'stdout' => $ldResult['stdout'],
            'stderr' => $ldResult['stderr'],
            'mensaje' => 'Falló el enlazado ARM64.',
        ];
    }

    $cmdRun = 'qemu-aarch64 ' . escapeshellarg($binPath);
    $runResult = ejecutarComando($cmdRun, $projectRoot);

    return [
        'intentada' => true,
        'disponible' => true,
        'ok' => $runResult['exit_code'] === 0,
        'codigo_salida' => $runResult['exit_code'],
        'stdout' => $runResult['stdout'],
        'stderr' => $runResult['stderr'],
        'mensaje' => $runResult['exit_code'] === 0
            ? 'Ejecución ARM64 completada correctamente.'
            : 'Ejecución ARM64 finalizó con error.',
        'binario' => 'reportes/linux/programa_fase4',
    ];
}

$input = readInputSource();
if (($input['ok'] ?? false) !== true) {
    http_response_code(400);
    if (PHP_SAPI !== 'cli') {
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode([
        'ok' => false,
        'errors' => [[
            'type' => 'input',
            'description' => $input['error'] ?? 'Entrada inválida.',
            'line' => 0,
            'column' => 0,
        ]],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(1);
}

$sourceCode = $input['source'];
$lexer = new GolampiLexer(InputStream::fromString($sourceCode));
$lexerListener = new CollectingErrorListener('lexical');
$lexer->removeErrorListeners();
$lexer->addErrorListener($lexerListener);

$tokens = new CommonTokenStream($lexer);
$parser = new GolampiParser($tokens);
$parserListener = new CollectingErrorListener('syntax');
$parser->removeErrorListeners();
$parser->addErrorListener($parserListener);
$tree = $parser->program();

$allErrors = array_merge($lexerListener->all(), $parserListener->all());
$semanticErrors = [];
$symbolTablePayload = [
    'generated_at' => date(DATE_ATOM),
    'total_scopes' => 0,
    'total_symbols' => 0,
    'scopes' => [],
    'symbols' => [],
];
$asmGenerado = null;

if (count($allErrors) === 0 && class_exists('GolampiBaseVisitor') && file_exists($semanticAnalyzerFile)) {
    require_once $semanticAnalyzerFile;
    $semanticAnalyzer = new AnalizadorSemantico();
    $semanticAnalyzer->analyze($tree);
    $semanticErrors = $semanticAnalyzer->allErrors();

    $symbolTableData = $semanticAnalyzer->reporteTablaSimbolos();
    $symbolTablePayload = [
        'generated_at' => date(DATE_ATOM),
        'total_scopes' => count($symbolTableData['scopes']),
        'total_symbols' => count($symbolTableData['symbols']),
        'scopes' => $symbolTableData['scopes'],
        'symbols' => $symbolTableData['symbols'],
    ];
}

$allErrors = array_merge($allErrors, $semanticErrors);

if (count($allErrors) === 0 && file_exists($generadorArm64File)) {
    require_once $generadorArm64File;
    $generador = new GeneradorARM64();
    $asmGenerado = $generador->generarProgramaBase($sourceCode, $symbolTablePayload, $tree);
}

$resultadoEjecucion = [
    'intentada' => false,
    'disponible' => false,
    'ok' => false,
    'codigo_salida' => null,
    'stdout' => '',
    'stderr' => '',
    'mensaje' => 'No se solicitó ejecución del binario ARM64.',
];

if (!is_dir($reportsDir)) {
    mkdir($reportsDir, 0777, true);
}

$reportPayload = [
    'generated_at' => date(DATE_ATOM),
    'total_errors' => count($allErrors),
    'errors' => $allErrors,
];

file_put_contents(
    $reportsDir . '/errores_fase1.json',
    json_encode($reportPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL
);
file_put_contents(
    $reportsDir . '/errores_semanticos_fase2.json',
    json_encode([
        'generated_at' => date(DATE_ATOM),
        'total_errors' => count($semanticErrors),
        'errors' => $semanticErrors,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL
);
file_put_contents(
    $reportsDir . '/tabla_simbolos_fase2.json',
    json_encode($symbolTablePayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL
);
if ($asmGenerado !== null) {
    file_put_contents($reportsDir . '/programa_fase4.s', $asmGenerado);
}

$solicitaEjecucion = ($input['execute'] ?? false) === true;
if ($solicitaEjecucion && count($allErrors) === 0 && $asmGenerado !== null) {
    $resultadoEjecucion = ejecutarArm64SiDisponible($projectRoot, $reportsDir);
}

if (PHP_SAPI !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
}

echo json_encode([
    'ok' => count($allErrors) === 0,
    'errors' => $allErrors,
    'semantic_errors' => $semanticErrors,
    'symbol_table' => $symbolTablePayload,
    'arm64' => [
        'generado' => $asmGenerado !== null,
        'archivo' => $asmGenerado !== null ? 'reportes/programa_fase4.s' : null,
        'contenido' => $asmGenerado,
    ],
    'ejecucion' => $resultadoEjecucion,
    'reportes' => [
        'errores' => 'reportes/errores_fase1.json',
        'errores_semanticos' => 'reportes/errores_semanticos_fase2.json',
        'tabla_simbolos' => 'reportes/tabla_simbolos_fase2.json',
        'asm' => $asmGenerado !== null ? 'reportes/programa_fase4.s' : null,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit(count($allErrors) === 0 ? 0 : 1);
