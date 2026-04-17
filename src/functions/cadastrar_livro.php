<?php
// src/functions/cadastrar_livro.php
// Acionado quando acao=registrar_livro via POST

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['acao'] ?? '') !== 'registrar_livro') return;

$nome        = htmlspecialchars(trim($_POST['nome']        ?? ''), ENT_QUOTES, 'UTF-8');
$registro    = trim($_POST['registro']    ?? '');
$quantidade  = trim($_POST['quantidade']  ?? '');
$autor       = htmlspecialchars(trim($_POST['autor']       ?? ''), ENT_QUOTES, 'UTF-8');
$editora     = htmlspecialchars(trim($_POST['editora']     ?? ''), ENT_QUOTES, 'UTF-8');
$ano         = trim($_POST['ano']         ?? '');
$prateleira  = htmlspecialchars(trim($_POST['prateleira']  ?? ''), ENT_QUOTES, 'UTF-8');
$faixaEtaria = htmlspecialchars(trim($_POST['faixa_etaria'] ?? ''), ENT_QUOTES, 'UTF-8');

if (!$nome || !$registro || !$quantidade) {
    echo "<script>alert('Título, registro e quantidade são obrigatórios!'); history.back();</script>";
    exit;
}

if ($ano && (!filter_var($ano, FILTER_VALIDATE_INT) || $ano < 1000 || $ano > 2099)) {
    echo "<script>alert('Ano inválido! Use um valor entre 1000 e 2099.'); history.back();</script>";
    exit;
}

if (!is_dir($diretorio)) mkdir($diretorio, 0777, true);
if (!file_exists($arquivo)) file_put_contents($arquivo, "---BANCO DE DADOS---\n", LOCK_EX);

$conteudo = file_get_contents($arquivo);

// Verifica duplicidade por nome OU por registro
if (str_contains($conteudo, "Nome: $nome |")) {
    echo "<script>alert('Título já cadastrado!'); history.back();</script>";
    exit;
}
if (str_contains($conteudo, "| Registro: $registro |")) {
    echo "<script>alert('Número de registro já existe!'); history.back();</script>";
    exit;
}

// Monta linha — campos opcionais só aparecem se preenchidos
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

echo "<script>alert('Livro registrado com sucesso!'); window.location.href = '/acervo.php';</script>";
session_write_close();
exit;