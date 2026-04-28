<?php
// src/functions/historico.php
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['acao'] ?? '') !== 'buscar_historico') return;

ob_clean();
header('Content-Type: application/json; charset=utf-8');

$arqHistorico = $diretorio . '/historico.txt';
$busca = strtolower(trim($_POST['busca'] ?? ''));
$ano   = trim($_POST['ano'] ?? '');

if (!file_exists($arqHistorico)) {
    echo json_encode(['success' => true, 'registros' => [], 'ranking' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$registros = [];
foreach (file($arqHistorico, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
    if (str_starts_with($linha, '---')) continue;
    if (!preg_match('/ID:\s*(\S+)\s*\|\s*Registro:\s*(\S+)\s*\|\s*Livro:\s*(.+?)\s*\|\s*Aluno:\s*(.+?)\s*\|\s*Sala:\s*(.*?)\s*\|\s*Retirada:\s*(\S+)\s*\|\s*Devolucao:\s*(\S+)\s*\|\s*Ano:\s*(\d+)/', $linha, $m)) continue;

    $r = [
        'id'        => $m[1],
        'registro'  => $m[2],
        'livro'     => $m[3],
        'aluno'     => $m[4],
        'sala'      => $m[5],
        'retirada'  => $m[6],
        'devolucao' => $m[7],
        'ano'       => $m[8],
    ];

    if ($ano && $r['ano'] !== $ano) continue;
    if ($busca && !str_contains(strtolower($r['aluno']), $busca)
               && !str_contains(strtolower($r['livro']),  $busca)
               && !str_contains(strtolower($r['sala']),   $busca)) continue;

    $registros[] = $r;
}

// ranking: soma por aluno no período filtrado
$ranking = [];
foreach ($registros as $r) {
    $ranking[$r['aluno']] = ($ranking[$r['aluno']] ?? 0) + 1;
}
arsort($ranking);
$rankingArr = array_map(fn($aluno, $total) => ['aluno' => $aluno, 'total' => $total],
    array_keys($ranking), array_values($ranking));

echo json_encode([
    'success'   => true,
    'registros' => array_reverse($registros), // mais recentes primeiro
    'ranking'   => array_slice($rankingArr, 0, 10),
], JSON_UNESCAPED_UNICODE);
exit;