<?php
// src/functions/cadastrar_livro.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['acao'] ?? '') !== 'registrar_livro') return;

// ── gera registro automático ──────────────────────────────────────────────────
function proximo_registro(string $arquivo): string {
    if (!file_exists($arquivo)) return '1';
    $ultimo = 0;
    foreach (file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
        if (str_starts_with($linha, '---')) continue;
        if (preg_match('/Registro:\s*([^\|]+)/', $linha, $m)) {
            $num = (int) trim($m[1]);
            if ($num > $ultimo) $ultimo = $num;
        }
    }
    return (string)($ultimo + 1);
}
// ─────────────────────────────────────────────────────────────────────────────

$nome        = htmlspecialchars(trim($_POST['nome']         ?? ''), ENT_QUOTES, 'UTF-8');
$quantidade  = trim($_POST['quantidade']  ?? '');
$autor       = htmlspecialchars(trim($_POST['autor']        ?? ''), ENT_QUOTES, 'UTF-8');
$editora     = htmlspecialchars(trim($_POST['editora']      ?? ''), ENT_QUOTES, 'UTF-8');
$ano         = trim($_POST['ano']         ?? '');
$prateleira  = htmlspecialchars(trim($_POST['prateleira']   ?? ''), ENT_QUOTES, 'UTF-8');
$faixaEtaria = htmlspecialchars(trim($_POST['faixa_etaria'] ?? ''), ENT_QUOTES, 'UTF-8');

if (!$nome || !$quantidade) {
    echo "<script>alert('Título e quantidade são obrigatórios!'); history.back();</script>";
    exit;
}

if ($ano && (!filter_var($ano, FILTER_VALIDATE_INT) || $ano < 1000 || $ano > 2099)) {
    echo "<script>alert('Ano inválido! Use um valor entre 1000 e 2099.'); history.back();</script>";
    exit;
}

if (!is_dir($diretorio)) mkdir($diretorio, 0777, true);
if (!file_exists($arquivo)) file_put_contents($arquivo, "---BANCO DE DADOS---\n", LOCK_EX);

$conteudo = file_get_contents($arquivo);

if (str_contains($conteudo, "Nome: $nome |")) {
    echo "<script>alert('Título já cadastrado!'); history.back();</script>";
    exit;
}

$registro = proximo_registro($arquivo); // ← gerado aqui, após checar duplicidade

$linha = "Nome: $nome | Registro: $registro | Quantidade: $quantidade";
if ($autor)       $linha .= " | Autor: $autor";
if ($editora)     $linha .= " | Editora: $editora";
if ($ano)         $linha .= " | Ano: $ano";
if ($prateleira)  $linha .= " | Prateleira: $prateleira";
if ($faixaEtaria) $linha .= " | FaixaEtaria: $faixaEtaria";

file_put_contents($arquivo, PHP_EOL . $linha, FILE_APPEND | LOCK_EX);

$limpeza = file_get_contents($arquivo);
$limpeza = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $limpeza);
file_put_contents($arquivo, trim($limpeza) . "\n");

echo "<script>alert('Livro registrado com sucesso! Registro: #$registro'); window.location.href = '/acervo.php';</script>";
session_write_close();
exit;