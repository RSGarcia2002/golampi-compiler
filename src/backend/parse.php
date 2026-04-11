<?php

declare(strict_types=1);

use Antlr\Antlr4\Runtime\CharStreams;
use Antlr\Antlr4\Runtime\CommonTokenStream;
use Antlr\Antlr4\Runtime\Error\Exceptions\RecognitionException;
use Antlr\Antlr4\Runtime\Error\Listeners\BaseErrorListener;
use Antlr\Antlr4\Runtime\Recognizer;

$projectRoot = dirname(__DIR__, 2);
$generatedDir = $projectRoot . '/src/compiler/generated';
$reportsDir = $projectRoot . '/reports';

if (file_exists($projectRoot . '/vendor/autoload.php')) {
    require_once $projectRoot . '/vendor/autoload.php';
}

if (!class_exists(CharStreams::class) || !class_exists(CommonTokenStream::class)) {
    http_response_code(500);
    if (PHP_SAPI !== 'cli') {
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode([
        'ok' => false,
        'errors' => [[
            'type' => 'setup',
            'description' => 'Falta runtime de ANTLR para PHP. Ejecuta composer install.',
            'line' => 0,
            'column' => 0,
        ]],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(1);
}

if (is_dir($generatedDir)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($generatedDir, FilesystemIterator::SKIP_DOTS)
    );

    /** @var SplFileInfo $fileInfo */
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isFile() && $fileInfo->getExtension() === 'php') {
            require_once $fileInfo->getPathname();
        }
    }
}

if (!class_exists('GolampiLexer') || !class_exists('GolampiParser')) {
    http_response_code(500);
    $errorPayload = [
        'ok' => false,
        'errors' => [[
            'type' => 'setup',
            'description' => 'No se encontraron GolampiLexer/GolampiParser. Ejecuta scripts/generate_antlr_php.sh primero.',
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

        if (isset($argv[1]) && is_file($argv[1])) {
            $source = file_get_contents($argv[1]);
            if ($source === false) {
                return ['ok' => false, 'error' => 'No se pudo leer el archivo de entrada.'];
            }
            return ['ok' => true, 'source' => $source];
        }

        $stdin = stream_get_contents(STDIN);
        if ($stdin !== false && trim($stdin) !== '') {
            return ['ok' => true, 'source' => $stdin];
        }

        return ['ok' => false, 'error' => 'Uso CLI: php src/backend/parse.php <archivo.gol> o enviar código por STDIN.'];
    }

    $rawInput = file_get_contents('php://input');
    if ($rawInput === false || trim($rawInput) === '') {
        return ['ok' => false, 'error' => 'Body vacío. Esperado: JSON con campo "source".'];
    }

    $json = json_decode($rawInput, true);
    if (!is_array($json) || !isset($json['source']) || !is_string($json['source'])) {
        return ['ok' => false, 'error' => 'Formato inválido. Esperado: {"source": "..."}.'];
    }

    return ['ok' => true, 'source' => $json['source']];
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
$lexer = new GolampiLexer(CharStreams::fromString($sourceCode));
$lexerListener = new CollectingErrorListener('lexical');
$lexer->removeErrorListeners();
$lexer->addErrorListener($lexerListener);

$tokens = new CommonTokenStream($lexer);
$parser = new GolampiParser($tokens);
$parserListener = new CollectingErrorListener('syntax');
$parser->removeErrorListeners();
$parser->addErrorListener($parserListener);
$parser->program();

$allErrors = array_merge($lexerListener->all(), $parserListener->all());

if (!is_dir($reportsDir)) {
    mkdir($reportsDir, 0777, true);
}

$reportPayload = [
    'generated_at' => date(DATE_ATOM),
    'total_errors' => count($allErrors),
    'errors' => $allErrors,
];

file_put_contents(
    $reportsDir . '/errors_phase1.json',
    json_encode($reportPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL
);

if (PHP_SAPI !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
}

echo json_encode([
    'ok' => count($allErrors) === 0,
    'errors' => $allErrors,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit(count($allErrors) === 0 ? 0 : 1);
