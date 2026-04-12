<?php
// src/functions/emprestar_livro.php
// Ação: emprestar_livro via POST (JSON response)
// Depende de $arquivo e $arqEmprestimos definidos no index.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['acao'] ?? '') !== 'emprestar_livro') return;

header('Content-Type: application/json; charset=utf-8');

$registro  = trim($_POST['registro']  ?? '');
$aluno     = htmlspecialchars(trim($_POST['aluno'] ?? ''), ENT_QUOTES, 'UTF-8');
$sala      = htmlspecialchars(trim($_POST['sala']  ?? ''), ENT_QUOTES, 'UTF-8');
$devolucao = trim($_POST['devolucao'] ?? '');

if (!$registro || !$aluno || !$devolucao) {
    echo json_encode(['success' => false, 'msg' => 'Preencha ao menos o nome e a data.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validar data
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $devolucao) || strtotime($devolucao) < strtotime('today')) {
    echo json_encode(['success' => false, 'msg' => 'Data de devolução inválida ou no passado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Verificar se o livro existe e tem disponibilidade
function _parse_livro_simples(string $arquivo, string $registro): ?array {
    if (!file_exists($arquivo)) return null;
    foreach (file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
        if (preg_match('/Nome:\s*(.+?)\s*\|\s*Registro:\s*' . preg_quote($registro, '/') . '\s*\|\s*Quantidade:\s*(\d+)/', $l, $m)) {
            return ['nome' => $m[1], 'quantidade' => (int)$m[2]];
        }
    }
    return null;
}

function _count_emprestimos(string $arquivo, string $registro): int {
    if (!file_exists($arquivo)) return 0;
    $c = 0;
    foreach (file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
        if (str_contains($l, "Registro: $registro |")) $c++;
    }
    return $c;
}

$livro = _parse_livro_simples($arquivo, $registro);
if (!$livro) {
    echo json_encode(['success' => false, 'msg' => 'Livro não encontrado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$linhasEmp = file_exists($arqEmprestimos)
    ? file($arqEmprestimos, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
    : [];

// Bloqueia o mesmo aluno em QUALQUER livro (1 empréstimo por vez)
foreach ($linhasEmp as $le) {
    if (str_contains($le, "Aluno: $aluno |")) {
        // Descobre qual livro ele já está com
        preg_match('/Registro:\s*(\S+)/', $le, $mReg);
        $regAtual = $mReg[1] ?? '?';
        echo json_encode(['success' => false, 'msg' => "Este aluno já possui um empréstimo ativo (Reg #$regAtual). Devolva antes de emprestar outro."], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$emprestados = _count_emprestimos($arqEmprestimos, $registro);
if ($emprestados >= $livro['quantidade']) {
    echo json_encode(['success' => false, 'msg' => 'Sem exemplares disponíveis para este livro.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_dir($diretorio)) mkdir($diretorio, 0777, true);
if (!file_exists($arqEmprestimos)) file_put_contents($arqEmprestimos, "---EMPRESTIMOS---\n", LOCK_EX);

$id      = uniqid('E', true);
$hoje    = date('Y-m-d');
$linha   = "ID: $id | Registro: $registro | Aluno: $aluno | Sala: $sala | Retirada: $hoje | Devolucao: $devolucao\n";
file_put_contents($arqEmprestimos, $linha, FILE_APPEND | LOCK_EX);

echo json_encode(['success' => true, 'msg' => 'Empréstimo registrado!'], JSON_UNESCAPED_UNICODE);
exit;