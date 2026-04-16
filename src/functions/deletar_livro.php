<?php
function deletarLivro($registro) {
    global $arquivo, $arqEmprestimos;
    
    if (!file_exists($arquivo)) return ['success' => false, 'msg' => 'Banco de dados não encontrado.'];
    
    $livros = [];
    $achou = false;
    
    // Lê o banco e remove o livro
    $linhas = file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($linhas as $linha) {
        $dados = explode('|', trim($linha));
        if (count($dados) >= 3 && $dados[0] === $registro) {
            $achou = true;
            continue; // Pula esta linha (deleta o livro)
        }
        $livros[] = $linha;
    }
    
    // Remove empréstimos relacionados
    $emprestimos = [];
    if (file_exists($arqEmprestimos)) {
        $linhasEmp = file($arqEmprestimos, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($linhasEmp as $linhaEmp) {
            $dadosEmp = explode('|', trim($linhaEmp));
            if (count($dadosEmp) >= 6 && $dadosEmp[0] === $registro) {
                continue; // Remove empréstimos deste livro
            }
            $emprestimos[] = $linhaEmp;
        }
    }
    
    // Salva os arquivos atualizados
    file_put_contents($arquivo, implode(PHP_EOL, $livros) . PHP_EOL);
    file_put_contents($arqEmprestimos, implode(PHP_EOL, $emprestimos) . PHP_EOL);
    
    return [
        'success' => $achou,
        'msg' => $achou ? "Livro REG #$registro apagado com sucesso!" : "Livro REG #$registro não encontrado."
    ];
}
?>