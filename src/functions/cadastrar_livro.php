<?php
// src/functions/cadastrar_livro.php
// Acionado quando acao=registrar_livro via POST
// Depende de $diretorio e $arquivo definidos no cadastrar.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['acao'] ?? '') !== 'registrar_livro') return;

$nome       = htmlspecialchars(trim($_POST['nome']       ?? ''), ENT_QUOTES, 'UTF-8');
$registro   = trim($_POST['registro']   ?? '');
$quantidade = trim($_POST['quantidade'] ?? '');

if (!$nome || !$registro || !$quantidade) {
    echo "<script>alert('Preencha todos os campos!'); history.back();</script>";
    exit;
}

if (!filter_var($registro, FILTER_VALIDATE_INT) || !filter_var($quantidade, FILTER_VALIDATE_INT)) {
    echo "<script>alert('Registro ou quantidade inválidos!'); history.back();</script>";
    exit;
}

if (!is_dir($diretorio)) mkdir($diretorio, 0777, true);
if (!file_exists($arquivo)) file_put_contents($arquivo, "---BANCO DE DADOS---\n", LOCK_EX);

$conteudo = file_get_contents($arquivo);

if (str_contains($conteudo, "Nome: $nome |")) {
    echo "<script>alert('Nome já cadastrado!'); history.back();</script>";
    exit;
}

if (str_contains($conteudo, "Registro: $registro |")) {
    echo "<script>alert('Registro já cadastrado!'); history.back();</script>";
    exit;
}

file_put_contents($arquivo, "Nome: $nome | Registro: $registro | Quantidade: $quantidade\n", FILE_APPEND | LOCK_EX);
echo "<script>alert('Livro registrado com sucesso!'); window.location.href = 'cadastrar.php';</script>";
session_write_close();
exit;