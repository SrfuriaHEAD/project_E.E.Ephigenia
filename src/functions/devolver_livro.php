<?php
// src/functions/devolver_livro.php
// Ação: devolver_livro via POST (JSON response)
// Remove a linha do empréstimo pelo ID
// Depende de $arqEmprestimos definido no index.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['acao'] ?? '') !== 'devolver_livro') return;

header('Content-Type: application/json; charset=utf-8');

$id = trim($_POST['id'] ?? '');
if (!$id) {
    echo json_encode(['success' => false, 'msg' => 'ID inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!file_exists($arqEmprestimos)) {
    echo json_encode(['success' => false, 'msg' => 'Nenhum empréstimo registrado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$linhas  = file($arqEmprestimos, FILE_IGNORE_NEW_LINES);
$novas   = [];
$achou   = false;

foreach ($linhas as $l) {
    if (str_contains($l, "ID: $id |")) {
        $achou = true;
        continue; // remove a linha
    }
    $novas[] = $l;
}

if (!$achou) {
    echo json_encode(['success' => false, 'msg' => 'Empréstimo não encontrado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

file_put_contents($arqEmprestimos, implode("\n", $novas) . "\n", LOCK_EX);
echo json_encode(['success' => true, 'msg' => 'Devolução registrada!'], JSON_UNESCAPED_UNICODE);
exit;