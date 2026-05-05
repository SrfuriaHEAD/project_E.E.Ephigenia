<?php
session_start();

$diretorio      = __DIR__ . '/db';
$arquivo        = $diretorio . '/banco.txt';
$arqEmprestimos = $diretorio . '/emprestimos.txt';
$arqHistorico   = $diretorio . '/historico.txt';

// ── Parsers ────────────────────────────────────────────────────────────────
function parse_livros_dash(string $arquivo): array {
    if (!file_exists($arquivo)) return [];
    $livros = [];
    foreach (file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
        if (str_starts_with($linha, '---')) continue;
        if (!preg_match('/Nome:\s*(.+?)\s*\|\s*Registro:\s*([^\|]+?)\s*\|\s*Quantidade:\s*(\d+)/', $linha, $m)) continue;
        $l = ['nome' => trim($m[1]), 'registro' => trim($m[2]), 'quantidade' => (int)$m[3],
              'autor' => '', 'prateleira' => '', 'faixaEtaria' => ''];
        if (preg_match('/Autor:\s*(.+?)(?:\s*\||$)/', $linha, $ma))      $l['autor']       = trim($ma[1]);
        if (preg_match('/Prateleira:\s*(.+?)(?:\s*\||$)/', $linha, $mp)) $l['prateleira']  = trim($mp[1]);
        if (preg_match('/FaixaEtaria:\s*(.+?)(?:\s*\||$)/', $linha, $mf))$l['faixaEtaria'] = trim($mf[1]);
        $livros[] = $l;
    }
    return $livros;
}

function parse_emprestimos_dash(string $arquivo): array {
    if (!file_exists($arquivo)) return [];
    $lista = [];
    foreach (file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
        if (str_starts_with($linha, '---')) continue;
        if (preg_match('/ID:\s*(\S+)\s*\|\s*Registro:\s*(\S+)\s*\|\s*Aluno:\s*(.+?)\s*\|\s*Sala:\s*(.*?)\s*\|\s*Retirada:\s*(\S+)\s*\|\s*Devolucao:\s*(\S+)/', $linha, $m)) {
            $lista[] = ['id'=>$m[1],'registro'=>$m[2],'aluno'=>$m[3],'sala'=>$m[4],'retirada'=>$m[5],'devolucao'=>$m[6]];
        }
    }
    return $lista;
}

function parse_historico_dash(string $arquivo): array {
    if (!file_exists($arquivo)) return [];
    $lista = [];
    foreach (file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
        if (str_starts_with($linha, '---')) continue;
        if (preg_match('/ID:\s*(\S+)\s*\|\s*Registro:\s*(\S+)\s*\|\s*Livro:\s*(.+?)\s*\|\s*Aluno:\s*(.+?)\s*\|\s*Sala:\s*(.*?)\s*\|\s*Retirada:\s*(\S+)\s*\|\s*Devolucao:\s*(\S+)\s*\|\s*Ano:\s*(\d+)/', $linha, $m)) {
            $lista[] = ['id'=>$m[1],'registro'=>$m[2],'livro'=>$m[3],'aluno'=>$m[4],'sala'=>$m[5],'retirada'=>$m[6],'devolucao'=>$m[7],'ano'=>$m[8]];
        }
    }
    return $lista;
}

$livros      = parse_livros_dash($arquivo);
$emprestimos = parse_emprestimos_dash($arqEmprestimos);
$historico   = parse_historico_dash($arqHistorico);
$hoje        = date('Y-m-d');

// ── Métricas ───────────────────────────────────────────────────────────────
$totalTitulos   = count($livros);
$totalExemplares= array_sum(array_column($livros, 'quantidade'));
$totalEmprestados = count($emprestimos);
$totalDisponiveis = $totalExemplares - $totalEmprestados;

$alunosAtivos = count(array_unique(array_column($emprestimos, 'aluno')));

$emAtraso = array_filter($emprestimos, fn($e) => $e['devolucao'] < $hoje);
$vencendo  = array_filter($emprestimos, fn($e) => $e['devolucao'] >= $hoje && $e['devolucao'] <= date('Y-m-d', strtotime('+7 days')));

// ── Empréstimos por registro (contagem) ────────────────────────────────────
$empCount = [];
foreach ($emprestimos as $e) $empCount[$e['registro']] = ($empCount[$e['registro']] ?? 0) + 1;

// ── Gráfico 1: Faixa Etária ────────────────────────────────────────────────
$faixas = [];
foreach ($livros as $l) {
    $f = $l['faixaEtaria'] ?: 'Sem categoria';
    $faixas[$f] = ($faixas[$f] ?? 0) + 1;
}
arsort($faixas);

// ── Gráfico 2: Por prateleira ──────────────────────────────────────────────
$prateleiras_count = [];
foreach ($livros as $l) {
    $p = $l['prateleira'] ?: 'Sem prateleira';
    $prateleiras_count[$p] = ($prateleiras_count[$p] ?? 0) + 1;
}
ksort($prateleiras_count);

// ── Gráfico 3: Disponíveis vs emprestados (top 12) ─────────────────────────
$livrosDisp = [];
foreach ($livros as $l) {
    $emp = $empCount[$l['registro']] ?? 0;
    $livrosDisp[] = ['nome'=>substr($l['nome'],0,28), 'total'=>$l['quantidade'], 'emp'=>$emp, 'disp'=>max(0,$l['quantidade']-$emp)];
}
usort($livrosDisp, fn($a,$b) => $b['emp'] - $a['emp']);
$top12 = array_slice($livrosDisp, 0, 12);

// ── Gráfico 4: Por sala ─────────────────────────────────────────────────────
$salas = [];
foreach ($emprestimos as $e) {
    $s = $e['sala'] ?: 'Sem sala';
    $salas[$s] = ($salas[$s] ?? 0) + 1;
}
ksort($salas);

// ── Ranking mais emprestados (histórico) ────────────────────────────────────
$rankLivros = [];
foreach ($historico as $h) $rankLivros[$h['livro']] = ($rankLivros[$h['livro']] ?? 0) + 1;
foreach ($emprestimos as $e) {
    $nome = '';
    foreach ($livros as $l) { if ($l['registro'] === $e['registro']) { $nome = $l['nome']; break; } }
    if ($nome) $rankLivros[$nome] = ($rankLivros[$nome] ?? 0) + 0; // já conta no histórico
}
arsort($rankLivros);
$topLivros = array_slice($rankLivros, 0, 8, true);
$maxRank   = max(array_values($topLivros) ?: [1]);

// ── Prateleiras para mapa visual ──────────────────────────────────────────
// Organiza: colunas A-E, andares 1-5
$colunas = ['A','B','C','D','E'];
$andares  = [1,2,3,4,5];
$mapaPrateleiras = [];
foreach ($livros as $l) {
    $p = strtoupper($l['prateleira']);
    if (preg_match('/^([A-E])(\d)$/', $p, $pm)) {
        $col   = $pm[1];
        $andar = (int)$pm[2];
        if (!isset($mapaPrateleiras[$col][$andar])) $mapaPrateleiras[$col][$andar] = [];
        $mapaPrateleiras[$col][$andar][] = $l['nome'];
    }
}

// ── Dados para JS ──────────────────────────────────────────────────────────
$jsLivros      = json_encode($livros,      JSON_UNESCAPED_UNICODE);
$jsEmprestimos = json_encode($emprestimos, JSON_UNESCAPED_UNICODE);
$jsHistorico   = json_encode($historico,   JSON_UNESCAPED_UNICODE);
$jsFaixas      = json_encode($faixas,      JSON_UNESCAPED_UNICODE);
$jsPrateleiras = json_encode($prateleiras_count, JSON_UNESCAPED_UNICODE);
$jsTop12       = json_encode($top12,       JSON_UNESCAPED_UNICODE);
$jsSalas       = json_encode($salas,       JSON_UNESCAPED_UNICODE);
$jsEmAtraso    = json_encode(array_values($emAtraso), JSON_UNESCAPED_UNICODE);
$jsVencendo    = json_encode(array_values($vencendo),  JSON_UNESCAPED_UNICODE);
$jsTopLivros   = json_encode($topLivros,   JSON_UNESCAPED_UNICODE);
$jsMapaPrat    = json_encode($mapaPrateleiras, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — Biblioteca E.E. Ephigenia</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=DM+Mono:wght@400;500&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<link rel="stylesheet" href="src/static/style.css">
<style>
:root {
  --bg:       #0e0e0e;
  --surface:  #161616;
  --surface2: #1e1e1e;
  --border:   #2a2a2a;
  --rust:     #b5451b;
  --rust2:    #d4572a;
  --rust-dim: rgba(181,69,27,.15);
  --gold:     #c9a84c;
  --text:     #e8e2d9;
  --muted:    #7a7268;
  --ok:       #3d9970;
  --warn:     #c9a84c;
  --danger:   #c0392b;
  --font-serif: 'Playfair Display', Georgia, serif;
  --font-sans:  'Instrument Sans', sans-serif;
  --font-mono:  'DM Mono', monospace;
  --sidebar-w: 260px;
  --header-h:  56px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{background: #fff;color:var(--text);font-family:var(--font-sans);min-height:100vh;overflow-x:hidden}

/* ── Noise overlay ─────────────────────── */
body::before{content:'';position:fixed;inset:0;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");pointer-events:none;z-index:0;opacity:.4}

/* ── Sidebar ──────────────────────────── */
.sidebar{position:fixed;left:0;top:0;bottom:0;width:var(--sidebar-w);background:var(--surface);border-right:1px solid var(--border);z-index:200;display:flex;flex-direction:column;transform:translateX(-100%);transition:transform .3s cubic-bezier(.4,0,.2,1)}
.sidebar.open{transform:translateX(0)}
.sidebar-logo{padding:24px 20px 16px;border-bottom:1px solid var(--border)}
.sidebar-logo .pre{font-family:var(--font-mono);font-size:.7rem;color:var(--muted);letter-spacing:.1em;text-transform:uppercase}
.sidebar-logo .name{font-family:var(--font-serif);font-size:1.25rem;color:var(--text);display:block;margin-top:2px}
.sidebar-logo .name em{color:var(--rust2);font-style:italic}
.sidebar-nav{flex:1;overflow-y:auto;padding:8px 0}
.nav-section{padding:16px 20px 6px;font-family:var(--font-mono);font-size:.65rem;color:var(--muted);letter-spacing:.12em;text-transform:uppercase}
.nav-item{display:flex;align-items:center;gap:10px;padding:10px 20px;cursor:pointer;border-left:2px solid transparent;transition:all .2s;font-size:.88rem;color:var(--muted)}
.nav-item:hover,.nav-item.active{color:var(--text);border-left-color:var(--rust);background:var(--rust-dim)}
.nav-item svg{flex-shrink:0;opacity:.7}
.nav-item.active svg{opacity:1}
.sidebar-footer{padding:16px 20px;border-top:1px solid var(--border);font-family:var(--font-mono);font-size:.68rem;color:var(--muted)}

/* ── Header ───────────────────────────── */
.header{position:fixed;top:0;left:0;right:0;height:var(--header-h);background:rgba(14,14,14,.92);backdrop-filter:blur(12px);border-bottom:1px solid var(--border);z-index:100;display:flex;align-items:center;gap:16px;padding:0 20px}
.hamburger{background:none;border:none;cursor:pointer;color:var(--text);padding:6px;display:flex;flex-direction:column;gap:5px;border-radius:4px;transition:background .2s}
.hamburger:hover{background:var(--rust-dim)}
.hamburger span{display:block;width:20px;height:1.5px;background:currentColor;transition:all .3s}
.hamburger.open span:nth-child(1){transform:translateY(6.5px) rotate(45deg)}
.hamburger.open span:nth-child(2){opacity:0}
.hamburger.open span:nth-child(3){transform:translateY(-6.5px) rotate(-45deg)}
.header-title{font-family:var(--font-serif);font-size:1.1rem}
.header-title em{color:var(--rust2);font-style:italic}
.header-right{margin-left:auto;display:flex;align-items:center;gap:8px}
.header-date{font-family:var(--font-mono);font-size:.72rem;color:var(--muted)}
.btn-export-top{background:var(--rust);border:none;color:#fff;font-family:var(--font-mono);font-size:.72rem;padding:6px 14px;border-radius:4px;cursor:pointer;transition:background .2s}
.btn-export-top:hover{background:var(--rust2)}

/* ── Overlay ──────────────────────────── */
.overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:150;opacity:0;pointer-events:none;transition:opacity .3s}
.overlay.visible{opacity:1;pointer-events:all}

/* ── Main content ─────────────────────── */
.main{margin-top:var(--header-h);padding:28px 24px;min-height:calc(100vh - var(--header-h));position:relative;z-index:1}
.section{display:none}
.section.active{display:block}

/* ── Section titles ──────────────────── */
.section-header{margin-bottom:20px}
.section-label{font-family:var(--font-mono);font-size:.68rem;color:var(--rust);letter-spacing:.12em;text-transform:uppercase}
.section-title{font-family:var(--font-serif);font-size:1.8rem;margin-top:4px;line-height:1.2}
.section-title em{color:var(--rust2);font-style:italic}
.section-sub{font-size:.82rem;color:var(--muted);margin-top:4px;font-family:var(--font-mono)}

/* ── Cards de métricas ───────────────── */
.metrics-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:28px}
.metric-card{background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:16px;position:relative;overflow:hidden}
.metric-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:var(--rust)}
.metric-card.ok::before{background:var(--ok)}
.metric-card.warn::before{background:var(--warn)}
.metric-card.danger::before{background:var(--danger)}
.metric-label{font-family:var(--font-mono);font-size:.65rem;color:var(--muted);letter-spacing:.08em;text-transform:uppercase;margin-bottom:8px}
.metric-value{font-family:var(--font-serif);font-size:2rem;font-weight:700;line-height:1}
.metric-sub{font-family:var(--font-mono);font-size:.68rem;color:var(--muted);margin-top:6px}

/* ── Chart grid ──────────────────────── */
.charts-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:28px}
.chart-card{background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:20px}
.chart-card.full{grid-column:1/-1}
.chart-title{font-family:var(--font-mono);font-size:.72rem;color:var(--rust);letter-spacing:.08em;text-transform:uppercase;margin-bottom:4px}
.chart-sub{font-size:.75rem;color:var(--muted);margin-bottom:16px;font-family:var(--font-mono)}
.chart-wrap{position:relative;height:240px}
.chart-wrap.tall{height:340px}

/* ── Atraso / vencendo ───────────────── */
.alerts-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:28px}
.alert-card{background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:16px}
.alert-title{font-family:var(--font-mono);font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;margin-bottom:12px}
.alert-title.danger{color:var(--danger)}
.alert-title.warn{color:var(--warn)}
.alert-list{display:flex;flex-direction:column;gap:8px;max-height:240px;overflow-y:auto}
.alert-item{background:var(--surface2);border-radius:4px;padding:10px 12px;border-left:2px solid var(--danger)}
.alert-item.warn{border-left-color:var(--warn)}
.alert-aluno{font-size:.85rem;font-weight:600}
.alert-meta{font-family:var(--font-mono);font-size:.68rem;color:var(--muted);margin-top:3px}
.alert-badge{font-family:var(--font-mono);font-size:.65rem;padding:2px 7px;border-radius:2px;background:rgba(192,57,43,.2);color:var(--danger);margin-top:5px;display:inline-block}
.alert-badge.warn{background:rgba(201,168,76,.2);color:var(--warn)}
.empty-alert{font-family:var(--font-mono);font-size:.75rem;color:var(--muted);padding:16px;text-align:center}

/* ── Ranking livros ──────────────────── */
.ranking-card{background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:20px;margin-bottom:28px}
.ranking-item{display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid var(--border)}
.ranking-item:last-child{border-bottom:none}
.rank-num{font-family:var(--font-mono);font-size:.75rem;color:var(--muted);width:20px;text-align:right;flex-shrink:0}
.rank-name{font-size:.85rem;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rank-bar-wrap{width:120px;height:6px;background:var(--surface2);border-radius:3px;flex-shrink:0}
.rank-bar{height:100%;border-radius:3px;background:var(--rust);transition:width .6s}
.rank-count{font-family:var(--font-mono);font-size:.72rem;color:var(--rust);width:24px;text-align:right;flex-shrink:0}

/* ── Prateleiras ─────────────────────── */
.shelves-section{margin-bottom:28px}
.shelves-instruction{font-family:var(--font-mono);font-size:.72rem;color:var(--muted);margin-bottom:16px}
.shelves-container{display:flex;gap:20px;overflow-x:auto;padding-bottom:8px}
.shelf-column{display:flex;flex-direction:column;gap:0;flex-shrink:0}
.shelf-col-label{font-family:var(--font-serif);font-size:1.1rem;color:var(--rust2);text-align:center;margin-bottom:8px;font-style:italic}
.shelf-slots{display:flex;flex-direction:column;gap:6px}
.shelf-slot{width:72px;height:52px;background:var(--surface);border:1px solid var(--border);border-radius:4px;display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:default;position:relative;transition:all .2s}
.shelf-slot.has-books{border-color:var(--rust);cursor:pointer}
.shelf-slot.has-books:hover{background:var(--rust-dim);border-color:var(--rust2);transform:scale(1.06)}
.shelf-slot-label{font-family:var(--font-mono);font-size:.62rem;color:var(--muted)}
.shelf-slot.has-books .shelf-slot-label{color:var(--rust2)}
.shelf-slot-count{font-family:var(--font-serif);font-size:1rem;font-weight:700;margin-top:2px}
.shelf-slot.has-books .shelf-slot-count{color:var(--text)}
.shelf-slot.empty .shelf-slot-count{color:var(--border)}

/* Tooltip */
.shelf-tooltip{position:fixed;background:#1a1a1a;border:1px solid var(--rust);border-radius:6px;padding:10px 14px;font-size:.78rem;z-index:9999;pointer-events:none;max-width:220px;opacity:0;transition:opacity .15s;box-shadow:0 8px 32px rgba(0,0,0,.6)}
.shelf-tooltip.visible{opacity:1}
.shelf-tooltip strong{display:block;font-family:var(--font-mono);color:var(--rust);font-size:.65rem;letter-spacing:.08em;margin-bottom:6px}
.shelf-tooltip ul{padding-left:14px;display:flex;flex-direction:column;gap:3px}
.shelf-tooltip li{color:var(--text);line-height:1.3}

/* Shelf divider */
.shelf-divider{width:1px;background:var(--border);margin:0 4px;align-self:stretch;flex-shrink:0}

/* ── Historico ───────────────────────── */
.hist-table-wrap{overflow-x:auto}
.hist-table{width:100%;border-collapse:collapse;font-size:.82rem}
.hist-table th{font-family:var(--font-mono);font-size:.65rem;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);padding:8px 12px;border-bottom:1px solid var(--border);text-align:left;white-space:nowrap}
.hist-table td{padding:9px 12px;border-bottom:1px solid rgba(42,42,42,.5);vertical-align:top}
.hist-table tr:hover td{background:var(--surface2)}
.badge{font-family:var(--font-mono);font-size:.63rem;padding:2px 8px;border-radius:2px;display:inline-block}
.badge.ok{background:rgba(61,153,112,.15);color:var(--ok)}
.badge.danger{background:rgba(192,57,43,.15);color:var(--danger)}

/* ── Exportar ─────────────────────────── */
.btn{border:none;cursor:pointer;font-family:var(--font-mono);font-size:.75rem;padding:9px 16px;border-radius:4px;transition:all .2s;display:inline-flex;align-items:center;gap:6px;width:100%;justify-content:center}
.btn-rust{background:var(--rust);color:#fff}
.btn-rust:hover{background:var(--rust2)}
.btn-outline{background:transparent;color:var(--text);border:1px solid var(--border)}
.btn-outline:hover{border-color:var(--rust);color:var(--rust)}
.email-input{background:var(--surface2);border:1px solid var(--border);color:var(--text);font-family:var(--font-mono);font-size:.78rem;padding:8px 12px;border-radius:4px;width:100%;outline:none;transition:border-color .2s}
.email-input:focus{border-color:var(--rust)}
.email-input::placeholder{color:var(--muted)}

/* ── Export redesign ──────────────────── */
.export-layout{display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start}
@media(max-width:900px){.export-layout{grid-template-columns:1fr}}

/* Painel de seleção */
.export-panel{background:var(--surface);border:1px solid var(--border);border-radius:8px;overflow:hidden}
.export-panel-header{padding:18px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.export-panel-header svg{color:var(--rust);flex-shrink:0}
.export-panel-title{font-family:var(--font-serif);font-size:1.05rem}
.export-panel-title em{color:var(--rust2);font-style:italic}
.export-panel-sub{font-family:var(--font-mono);font-size:.65rem;color:var(--muted);margin-top:2px}

/* Seções de seleção */
.export-section-group{border-bottom:1px solid var(--border)}
.export-section-group:last-child{border-bottom:none}
.export-section-toggle{width:100%;background:none;border:none;padding:14px 20px;cursor:pointer;display:flex;align-items:center;gap:10px;color:var(--text);font-family:var(--font-mono);font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;transition:background .2s;text-align:left}
.export-section-toggle:hover{background:var(--rust-dim)}
.export-section-toggle .est-icon{width:28px;height:28px;border-radius:4px;background:var(--rust-dim);border:1px solid rgba(181,69,27,.3);display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .2s}
.export-section-toggle.selected .est-icon{background:var(--rust);border-color:var(--rust)}
.export-section-toggle .est-label{flex:1}
.export-section-toggle .est-arrow{color:var(--muted);transition:transform .2s;font-size:.8rem}
.export-section-toggle.open .est-arrow{transform:rotate(180deg)}
.export-section-body{display:none;padding:0 20px 14px;border-top:1px solid rgba(42,42,42,.5)}
.export-section-body.open{display:block}

/* Checkboxes */
.export-checks{display:flex;flex-direction:column;gap:8px;padding-top:12px}
.export-check-item{display:flex;align-items:flex-start;gap:10px;cursor:pointer;padding:8px 10px;border-radius:4px;border:1px solid transparent;transition:all .2s}
.export-check-item:hover{background:var(--surface2);border-color:var(--border)}
.export-check-item input[type=checkbox]{display:none}
.check-box{width:16px;height:16px;border:1.5px solid var(--border);border-radius:3px;flex-shrink:0;margin-top:1px;display:flex;align-items:center;justify-content:center;transition:all .2s}
.export-check-item input:checked ~ .check-label .check-box,
.export-check-item.checked .check-box{background:var(--rust);border-color:var(--rust)}
.export-check-item.checked .check-box::after{content:'✓';font-size:.6rem;color:#fff;font-weight:700}
.check-label{flex:1;display:flex;align-items:center;gap:8px}
.check-text{font-size:.82rem;color:var(--text)}
.check-desc{font-family:var(--font-mono);font-size:.63rem;color:var(--muted);margin-top:2px}

/* Painel lateral de ações */
.export-actions-panel{display:flex;flex-direction:column;gap:14px}
.export-action-card{background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:18px;display:flex;flex-direction:column;gap:12px}
.export-action-card.featured{border-color:rgba(0, 0, 0, 0.9);background:rgba(0, 0, 0, 0.9)}
.eac-head{display:flex;align-items:center;gap:10px}
.eac-icon{width:36px;height:36px;border-radius:6px;background:var(--rust-dim);border:1px solid rgba(181,69,27,.3);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1rem}
.eac-title{font-family:var(--font-mono);font-size:.72rem;color:var(--rust);letter-spacing:.08em;text-transform:uppercase}
.eac-desc{font-size:.8rem;color:var(--muted);line-height:1.5}
.eac-btn{border:none;cursor:pointer;font-family:var(--font-mono);font-size:.72rem;padding:9px 16px;border-radius:5px;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:7px;width:100%;letter-spacing:.04em}
.eac-btn-rust{background:var(--rust);color:#fff}
.eac-btn-rust:hover{background:var(--rust2);transform:translateY(-1px);box-shadow:0 4px 16px rgba(181,69,27,.3)}
.eac-btn-outline{background:transparent;color:var(--text);border:1px solid var(--border)}
.eac-btn-outline:hover{border-color:var(--rust);color:var(--rust)}
.eac-btn-google{background:#fff;color:#333;border:1px solid #ddd}
.eac-btn-google:hover{box-shadow:0 2px 8px rgba(0,0,0,.2);transform:translateY(-1px)}
.eac-btn-drive{background:#1a73e8;color:#fff}
.eac-btn-drive:hover{background:#1557b0;transform:translateY(-1px);box-shadow:0 4px 16px rgba(26,115,232,.35)}
.export-input{background:var(--surface2);border:1px solid var(--border);color:var(--text);font-family:var(--font-mono);font-size:.75rem;padding:9px 12px;border-radius:4px;width:100%;outline:none;transition:border-color .2s}
.export-input:focus{border-color:var(--rust)}
.export-input::placeholder{color:var(--muted)}

/* Preview do documento */
.doc-preview{background:var(--surface);border:1px solid var(--border);border-radius:8px;overflow:hidden;margin-bottom:20px}
.doc-preview-bar{padding:10px 16px;background:var(--surface2);border-bottom:1px solid var(--border);font-family:var(--font-mono);font-size:.65rem;color:var(--muted);display:flex;align-items:center;gap:8px}
.doc-preview-bar span{color:var(--rust)}
.doc-preview-body{padding:20px;font-family:var(--font-mono);font-size:.72rem;color:var(--muted);max-height:200px;overflow-y:auto;line-height:1.7}
.doc-preview-body .dp-title{font-family:var(--font-serif);font-size:1rem;color:var(--text);margin-bottom:4px}
.doc-preview-body .dp-section{color:var(--rust);letter-spacing:.08em;text-transform:uppercase;font-size:.62rem;margin:12px 0 4px;border-bottom:1px solid var(--border);padding-bottom:4px}
.doc-preview-body .dp-row{display:flex;justify-content:space-between;padding:2px 0;border-bottom:1px solid rgba(42,42,42,.3)}
.doc-preview-body .dp-row span:last-child{color:var(--text)}

/* Seleção geral rápida */
.export-quick-select{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap}
.eqs-btn{background:var(--surface);border:1px solid var(--border);color:var(--muted);font-family:var(--font-mono);font-size:.65rem;padding:6px 12px;border-radius:20px;cursor:pointer;transition:all .2s;letter-spacing:.06em}
.eqs-btn:hover{border-color:var(--rust);color:var(--rust)}
.eqs-btn.active{background:var(--rust-dim);border-color:var(--rust);color:var(--rust2)}

/* Toast de feedback */
.export-toast{position:fixed;bottom:24px;right:24px;background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px 18px;font-family:var(--font-mono);font-size:.75rem;z-index:9999;transform:translateY(80px);opacity:0;transition:all .3s cubic-bezier(.4,0,.2,1);display:flex;align-items:center;gap:10px;min-width:220px;box-shadow:0 8px 32px rgba(0,0,0,.5)}
.export-toast.show{transform:translateY(0);opacity:1}
.export-toast.ok{border-color:var(--ok)}
.export-toast.err{border-color:var(--danger)}
.toast-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.export-toast.ok .toast-dot{background:var(--ok)}
.export-toast.err .toast-dot{background:var(--danger)}

/* Modal Google */
.google-modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:8000;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .25s;backdrop-filter:blur(4px)}
.google-modal-bg.open{opacity:1;pointer-events:all}
.google-modal{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:28px;max-width:440px;width:90%;max-height:90vh;overflow-y:auto;transform:scale(.95);transition:transform .25s}
.google-modal-bg.open .google-modal{transform:scale(1)}
.gm-title{font-family:var(--font-serif);font-size:1.2rem;margin-bottom:4px}
.gm-title em{color:var(--rust2);font-style:italic}
.gm-sub{font-family:var(--font-mono);font-size:.68rem;color:var(--muted);margin-bottom:20px}
.gm-field{display:flex;flex-direction:column;gap:5px;margin-bottom:14px}
.gm-label{font-family:var(--font-mono);font-size:.65rem;color:var(--muted);letter-spacing:.08em;text-transform:uppercase}
.gm-actions{display:flex;gap:10px;margin-top:20px}
.gm-actions button{flex:1}
.gm-status{background:var(--surface2);border:1px solid var(--border);border-radius:6px;padding:12px;font-family:var(--font-mono);font-size:.72rem;color:var(--muted);margin-top:14px;display:none;line-height:1.6}
.gm-status.show{display:block}
.gm-status.ok{border-color:var(--ok);color:var(--ok)}
.gm-status.err{border-color:var(--danger);color:var(--danger)}

/* ── Scrollbar ───────────────────────── */
::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-track{background:var(--bg)}
::-webkit-scrollbar-thumb{background:var(--border);border-radius:2px}
::-webkit-scrollbar-thumb:hover{background:var(--muted)}

/* ── Responsive ──────────────────────── */
@media(max-width:700px){
  .charts-grid,.alerts-grid{grid-template-columns:1fr}
  .charts-grid .full{grid-column:1}
  .metrics-grid{grid-template-columns:repeat(2,1fr)}
}
</style>
</head>
<body>

<!-- Tooltip prateleira -->
<div class="shelf-tooltip" id="shelfTooltip"></div>

<!-- Overlay -->
<div class="overlay" id="overlay"></div>

<!-- Toast -->
<div class="export-toast" id="exportToast">
  <div class="toast-dot"></div>
  <span id="toastMsg">Operação concluída</span>
</div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <span class="pre">Biblioteca</span>
    <span class="name" style="color: #333;">E.E. <em>Ephigenia</em></span>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section">Visão Geral</div>
    <div class="nav-item active" data-section="overview">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      Dashboard geral
    </div>
    <div class="nav-section">Análises</div>
    <div class="nav-item" data-section="charts">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      Gráficos
    </div>
    <div class="nav-item" data-section="alertas">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      Alertas de prazo
    </div>
    <div class="nav-item" data-section="ranking">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      Ranking de livros
    </div>
    <div class="nav-item" data-section="prateleiras">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="4"/><rect x="2" y="10" width="20" height="4"/><rect x="2" y="17" width="20" height="4"/></svg>
      Mapa de prateleiras
    </div>
    <div class="nav-section">Registros</div>
    <div class="nav-item" data-section="historico">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      Histórico
    </div>
    <div class="nav-section">Sistema</div>
    <div class="nav-item" data-section="exportar">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Exportar dados
    </div>
    <div class="nav-item" onclick="window.location.href='acervo.php'">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
      Ir para acervo
    </div>
  </nav>
  <div class="sidebar-footer">Dashboard v1.0 · <?= date('Y') ?></div>
</aside>

<!-- Header -->
<header class="header">
  <button class="hamburger" id="hamburger" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
  <div class="header-title" style="color: #ffffff;">Biblio<em>teca</em> — Dashboard</div>
  <div class="header-right">
    <div class="nav-dot"></div>
    <button class="btn-export-top" onclick="showSection('exportar')">↓ Exportar</button>
    <div class="nav-dot"></div>
    <button class="btn" onclick="window.location.href='acervo.php'">Ir para acervo</button>
  </div>
</header>

<main class="main">

  <!-- ── OVERVIEW ─────────────────────────────── -->
  <div class="section active" id="sec-overview">
    <div class="section-header">
      <div class="section-label">Visão Geral</div>
      <h1 class="section-title" style="color: #333;">Dash<em>board</em></h1>
      <div class="section-sub">Atualizado · Leitura ao vivo dos arquivos</div>
    </div>

    <div class="metrics-grid">
      <div class="metric-card">
        <div class="metric-label">Títulos</div>
        <div class="metric-value"><?= $totalTitulos ?></div>
        <div class="metric-sub">no acervo</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">Exemplares</div>
        <div class="metric-value"><?= $totalExemplares ?></div>
        <div class="metric-sub">total de volumes</div>
      </div>
      <div class="metric-card<?= $totalEmprestados > 0 ? ' warn' : '' ?>">
        <div class="metric-label">Emprestados</div>
        <div class="metric-value"><?= $totalEmprestados ?></div>
        <div class="metric-sub">empréstimos ativos</div>
      </div>
      <div class="metric-card ok">
        <div class="metric-label">Disponíveis</div>
        <div class="metric-value"><?= $totalDisponiveis ?></div>
        <div class="metric-sub">para empréstimo</div>
      </div>
      <div class="metric-card<?= count($emAtraso) > 0 ? ' danger' : ' ok' ?>">
        <div class="metric-label">Em Atraso</div>
        <div class="metric-value"><?= count($emAtraso) ?></div>
        <div class="metric-sub">devoluções vencidas</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">Alunos Ativos</div>
        <div class="metric-value"><?= $alunosAtivos ?></div>
        <div class="metric-sub">com livros em mãos</div>
      </div>
    </div>

    <!-- Mini charts no overview -->
    <div class="charts-grid">
      <div class="chart-card">
        <div class="chart-title">Faixa Etária</div>
        <div class="chart-sub">Distribuição do acervo por categoria</div>
        <div class="chart-wrap"><canvas id="chartFaixa"></canvas></div>
      </div>
      <div class="chart-card">
        <div class="chart-title">Por Prateleira</div>
        <div class="chart-sub">Títulos em cada prateleira</div>
        <div class="chart-wrap"><canvas id="chartPrat"></canvas></div>
      </div>
      <div class="chart-card full">
        <div class="chart-title">Disponíveis vs Emprestados</div>
        <div class="chart-sub">Top 12 títulos por movimentação</div>
        <div class="chart-wrap tall"><canvas id="chartDisp"></canvas></div>
      </div>
      <div class="chart-card full">
        <div class="chart-title">Empréstimos por Sala / Turma</div>
        <div class="chart-sub">Quantos empréstimos ativos cada sala tem</div>
        <div class="chart-wrap"><canvas id="chartSala"></canvas></div>
      </div>
    </div>
  </div>

  <!-- ── GRÁFICOS ──────────────────────────────── -->
  <div class="section" id="sec-charts">
    <div class="section-header">
      <div class="section-label">Análises</div>
      <h1 class="section-title" style="color: #333;">Grá<em>ficos</em></h1>
      <div class="section-sub">Visualizações completas do acervo</div>
    </div>
    <div class="charts-grid">
      <div class="chart-card">
        <div class="chart-title">Faixa Etária — Distribuição do acervo</div>
        <div class="chart-sub">Pizza com todas as categorias</div>
        <div class="chart-wrap"><canvas id="chartFaixa2"></canvas></div>
      </div>
      <div class="chart-card">
        <div class="chart-title">Por Prateleira</div>
        <div class="chart-sub">Quantidade de títulos por prateleira</div>
        <div class="chart-wrap"><canvas id="chartPrat2"></canvas></div>
      </div>
      <div class="chart-card full">
        <div class="chart-title">Disponíveis vs Emprestados — Top 12</div>
        <div class="chart-sub">Barras empilhadas por título</div>
        <div class="chart-wrap tall"><canvas id="chartDisp2"></canvas></div>
      </div>
      <div class="chart-card full">
        <div class="chart-title">Empréstimos por Turma</div>
        <div class="chart-sub">Volume de empréstimos ativos por sala</div>
        <div class="chart-wrap"><canvas id="chartSala2"></canvas></div>
      </div>
    </div>
  </div>

  <!-- ── ALERTAS ───────────────────────────────── -->
  <div class="section" id="sec-alertas">
    <div class="section-header">
      <div class="section-label">Alertas de Prazo</div>
      <h1 class="section-title" style="color: #333;">Moni<em>toramento</em></h1>
      <div class="section-sub">Devoluções vencidas e próximas do vencimento</div>
    </div>
    <div class="alerts-grid">
      <div class="alert-card">
        <div class="alert-title danger">⚠ Em Atraso (<?= count($emAtraso) ?>)</div>
        <div class="alert-list" id="listaAtraso"></div>
      </div>
      <div class="alert-card">
        <div class="alert-title warn">⏰ Vencendo em 7 dias (<?= count($vencendo) ?>)</div>
        <div class="alert-list" id="listaVencendo"></div>
      </div>
    </div>
  </div>

  <!-- ── RANKING ───────────────────────────────── -->
  <div class="section" id="sec-ranking">
    <div class="section-header">
      <div class="section-label">Ranking</div>
      <h1 class="section-title" style="color: #333;">Mais Em<em>prestados</em></h1>
      <div class="section-sub">Livros com maior movimentação no histórico</div>
    </div>
    <div class="ranking-card" id="rankingList"></div>
  </div>

  <!-- ── PRATELEIRAS ──────────────────────────── -->
  <div class="section" id="sec-prateleiras">
    <div class="section-header">
      <div class="section-label">Mapa Físico</div>
      <h1 class="section-title" style="color: #333;">Prate<em>leiras</em></h1>
      <div class="section-sub">Passe o cursor sobre um andar para ver os títulos</div>
    </div>
    <div class="shelves-instruction">A = andar 1 (topo) → 5 (base) · Colunas: A B C D E</div>
    <div class="shelves-container" id="shelvesContainer"></div>
  </div>

  <!-- ── HISTÓRICO ─────────────────────────────── -->
  <div class="section" id="sec-historico">
    <div class="section-header">
      <div class="section-label">Registros</div>
      <h1 class="section-title" style="color: #333;">His<em>tórico</em></h1>
      <div class="section-sub">Todos os empréstimos realizados</div>
    </div>
    <div class="chart-card" style="margin-bottom:20px">
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <input type="text" id="histSearch" class="email-input" placeholder="Buscar aluno ou livro…" style="flex:1;min-width:160px">
        <input type="number" id="histAno" class="email-input" placeholder="Ano" style="width:90px" value="<?= date('Y') ?>">
        <button class="btn btn-rust" onclick="renderHistorico()">Filtrar</button>
      </div>
    </div>
    <div class="chart-card">
      <div class="hist-table-wrap">
        <table class="hist-table">
          <thead><tr><th>#</th><th>Livro</th><th>Aluno</th><th>Sala</th><th>Retirada</th><th>Devolução</th><th>Status</th></tr></thead>
          <tbody id="histTbody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ── EXPORTAR ──────────────────────────────── -->
  <div class="section" id="sec-exportar">
    <div class="section-header">
      <div class="section-label">Exportação</div>
      <h1 class="section-title" style="color: #333;">Ex<em>portar</em></h1>
      <div class="section-sub">Selecione as seções, o formato e o destino do relatório</div>
    </div>

    <!-- Seleção rápida -->
    <div class="export-quick-select">
      <button class="eqs-btn" onclick="selectAll(true)">Selecionar tudo</button>
      <button class="eqs-btn" onclick="selectAll(false)">Limpar seleção</button>
      <button class="eqs-btn" onclick="selectPreset('emprestimos')">Só empréstimos</button>
      <button class="eqs-btn" onclick="selectPreset('acervo')">Só acervo</button>
      <button class="eqs-btn" onclick="selectPreset('alertas')">Só alertas</button>
    </div>

    <div class="export-layout">

      <!-- Painel de seleção de conteúdo -->
      <div>
        <div class="export-panel">
          <div class="export-panel-header">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            <div>
              <div class="export-panel-title">Conteúdo do <em>Relatório</em></div>
              <div class="export-panel-sub">Escolha o que será incluído no documento exportado</div>
            </div>
          </div>

          <!-- Métricas Gerais -->
          <div class="export-section-group">
            <div class="export-check-item" id="chk-metricas" onclick="toggleCheck('metricas')">
              <div class="check-box" id="box-metricas"></div>
              <div class="check-label" style="flex-direction:column;align-items:flex-start">
                <span class="check-text">📊 Métricas Gerais</span>
                <span class="check-desc">Títulos, exemplares, empréstimos ativos, disponíveis, alunos</span>
              </div>
            </div>
          </div>

          <!-- Empréstimos Ativos -->
          <div class="export-section-group">
            <button class="export-section-toggle" id="tog-emprestimos" onclick="toggleGroup('emprestimos')">
              <div class="est-icon" id="icon-emprestimos">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
              </div>
              <span class="est-label">📋 Empréstimos Ativos</span>
              <span class="est-arrow">▾</span>
            </button>
            <div class="export-section-body" id="body-emprestimos">
              <div class="export-checks">
                <div class="export-check-item" id="chk-emp-lista" onclick="toggleCheck('emp-lista')">
                  <div class="check-box" id="box-emp-lista"></div>
                  <div class="check-label" style="flex-direction:column;align-items:flex-start">
                    <span class="check-text">Lista completa de empréstimos</span>
                    <span class="check-desc">Aluno, livro, sala, data de retirada e devolução</span>
                  </div>
                </div>
                <div class="export-check-item" id="chk-emp-atraso" onclick="toggleCheck('emp-atraso')">
                  <div class="check-box" id="box-emp-atraso"></div>
                  <div class="check-label" style="flex-direction:column;align-items:flex-start">
                    <span class="check-text">Devoluções em atraso</span>
                    <span class="check-desc">Somente os registros vencidos com dias de atraso</span>
                  </div>
                </div>
                <div class="export-check-item" id="chk-emp-vencendo" onclick="toggleCheck('emp-vencendo')">
                  <div class="check-box" id="box-emp-vencendo"></div>
                  <div class="check-label" style="flex-direction:column;align-items:flex-start">
                    <span class="check-text">Vencendo em 7 dias</span>
                    <span class="check-desc">Alertas de devolução próxima</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Acervo -->
          <div class="export-section-group">
            <button class="export-section-toggle" id="tog-acervo" onclick="toggleGroup('acervo')">
              <div class="est-icon" id="icon-acervo">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
              </div>
              <span class="est-label">📚 Acervo</span>
              <span class="est-arrow">▾</span>
            </button>
            <div class="export-section-body" id="body-acervo">
              <div class="export-checks">
                <div class="export-check-item" id="chk-acervo-lista" onclick="toggleCheck('acervo-lista')">
                  <div class="check-box" id="box-acervo-lista"></div>
                  <div class="check-label" style="flex-direction:column;align-items:flex-start">
                    <span class="check-text">Lista completa do acervo</span>
                    <span class="check-desc">Todos os livros com autor, prateleira e faixa etária</span>
                  </div>
                </div>
                <div class="export-check-item" id="chk-acervo-ranking" onclick="toggleCheck('acervo-ranking')">
                  <div class="check-box" id="box-acervo-ranking"></div>
                  <div class="check-label" style="flex-direction:column;align-items:flex-start">
                    <span class="check-text">Ranking dos mais emprestados</span>
                    <span class="check-desc">Top livros por número de empréstimos históricos</span>
                  </div>
                </div>
                <div class="export-check-item" id="chk-acervo-salas" onclick="toggleCheck('acervo-salas')">
                  <div class="check-box" id="box-acervo-salas"></div>
                  <div class="check-label" style="flex-direction:column;align-items:flex-start">
                    <span class="check-text">Empréstimos por sala/turma</span>
                    <span class="check-desc">Distribuição dos empréstimos ativos por turma</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Histórico -->
          <div class="export-section-group">
            <div class="export-check-item" id="chk-historico" onclick="toggleCheck('historico')">
              <div class="check-box" id="box-historico"></div>
              <div class="check-label" style="flex-direction:column;align-items:flex-start">
                <span class="check-text">🕓 Histórico Completo</span>
                <span class="check-desc">Todos os empréstimos já realizados (pode ser longo)</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Preview ao vivo -->
        <div class="doc-preview" style="margin-top:16px">
          <div class="doc-preview-bar">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            pré-visualização do documento · <span id="previewSections">nenhuma seção selecionada</span>
          </div>
          <div class="doc-preview-body" id="docPreview">
            <div style="color:var(--border);text-align:center;padding:20px 0">Selecione seções acima para pré-visualizar o relatório</div>
          </div>
        </div>
      </div>

      <!-- Painel de ações -->
      <div class="export-actions-panel">

        <!-- Downloads locais -->
        <div class="export-action-card featured">
          <div class="eac-head">
            <div class="eac-icon">📄</div>
            <div>
              <div class="eac-title">Baixar Arquivo</div>
            </div>
          </div>
          <p class="eac-desc">Gera o relatório com as seções selecionadas e faz o download direto no seu dispositivo.</p>
          <div style="display:flex;gap:8px;flex-direction:column">
            <button class="eac-btn eac-btn-rust" onclick="exportarPDF()">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              Imprimir / Salvar PDF
            </button>
            <button class="eac-btn eac-btn-outline" onclick="exportarCSVFiltrado()">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              Baixar CSV
            </button>
          </div>
        </div>

        <!-- Gmail -->
        <div class="export-action-card">
          <div class="eac-head">
            <div class="eac-icon">✉️</div>
            <div>
              <div class="eac-title">Enviar por Gmail</div>
            </div>
          </div>
          <p class="eac-desc">Copia o relatório para a área de transferência e abre o Gmail. Cole o texto no corpo do e-mail com <strong>Ctrl+V</strong>.</p>
          <button class="eac-btn eac-btn-google" onclick="enviarGmail()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M20 4H4C2.9 4 2 4.9 2 6v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2z" fill="#EA4335" opacity=".2"/><path d="M2 6l10 7 10-7" stroke="#EA4335" stroke-width="1.5"/></svg>
            Copiar e Abrir Gmail
          </button>
        </div>

        <!-- Google Drive -->
        <div class="export-action-card">
          <div class="eac-head">
            <div class="eac-icon">☁️</div>
            <div>
              <div class="eac-title">Salvar no Drive</div>
            </div>
          </div>
          <p class="eac-desc">Faz upload do relatório diretamente para o Google Drive como documento de texto.</p>
          <input type="text" class="export-input" id="driveFilename" placeholder="Nome do arquivo no Drive" value="Relatório Biblioteca <?= date('d-m-Y') ?>">
          <button class="eac-btn eac-btn-drive" onclick="salvarDrive()">
            <svg width="14" height="14" viewBox="0 0 87.3 78" fill="none"><path d="M6.6 66.85l3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3l13.75-23.8H10.55c0 1.55.4 3.1 1.2 4.5z" fill="#0066DA"/><path d="M43.65 25L29.9 1.2c-1.35.8-2.5 1.9-3.3 3.3L2.5 48.3c-.8 1.4-1.2 2.95-1.2 4.5H27.5z" fill="#00AC47"/><path d="M73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75 7.65-13.25c.8-1.4 1.2-2.95 1.2-4.5H60.8l5.85 11.5z" fill="#EA4335"/><path d="M43.65 25L57.4 1.2C56.05.4 54.5 0 52.9 0H34.4c-1.6 0-3.15.45-4.5 1.2z" fill="#00832D"/><path d="M60.8 53H27.5l-13.75 23.8c1.35.8 2.9 1.2 4.5 1.2h51.8c1.6 0 3.15-.45 4.5-1.2z" fill="#2684FC"/><path d="M73.4 26.5l-13.1-22.7C59.5 2.4 58.35 1.3 57 .5L43.25 24.3l17.55 28.7H86.8c0-1.55-.4-3.1-1.2-4.5z" fill="#FFBA00"/></svg>
            Fazer Upload para o Drive
          </button>
          <div id="driveStatus" class="gm-status"></div>
        </div>

      </div>
    </div>
  </div>

</main>

<footer class="footer">
  <div class="footer-inner">
    <span class="footer-school">E.E. Ephigenia</span>
    <span class="footer-sep">/</span>
    <span class="footer-text">Sistema de Biblioteca</span>
    <span class="footer-sep">/</span>
    <span class="footer-text">Desenvolvido por Arthur A. 2 Reg 3</span>
    <span class="footer-text" style="flex: 1; text-align: right; color: #ffffff;">&copy; <?= date('Y') ?> Todos os direitos reservados</span>
    <span class="nav-year"><?= date('Y') ?></span>
  </div>
</footer>

<script>
// ── Dados PHP → JS ────────────────────────────────────────────────────────
const LIVROS      = <?= $jsLivros ?>;
const EMPRESTIMOS = <?= $jsEmprestimos ?>;
const HISTORICO   = <?= $jsHistorico ?>;
const FAIXAS      = <?= $jsFaixas ?>;
const PRATELEIRAS_CNT = <?= $jsPrateleiras ?>;
const TOP12       = <?= $jsTop12 ?>;
const SALAS       = <?= $jsSalas ?>;
const EM_ATRASO   = <?= $jsEmAtraso ?>;
const VENCENDO    = <?= $jsVencendo ?>;
const TOP_LIVROS  = <?= $jsTopLivros ?>;
const MAPA_PRAT   = <?= $jsMapaPrat ?>;
const HOJE        = '<?= $hoje ?>';

// ── Paleta ───────────────────────────────────────────────────────────────
const RUST    = '#b5451b';
const RUST2   = '#d4572a';
const OK      = '#3d9970';
const WARN    = '#c9a84c';
const DANGER  = '#c0392b';
const MUTED   = '#7a7268';
const SURFACE = '#1e1e1e';
const TEXT    = '#e8e2d9';
const BORDER  = '#2a2a2a';

const PALETTE = ['#b5451b','#c9a84c','#3d9970','#2980b9','#8e44ad','#16a085','#d35400','#27ae60','#2c3e50','#e74c3c'];

Chart.defaults.color = '#7a7268';
Chart.defaults.font.family = "'DM Mono', monospace";
Chart.defaults.font.size   = 11;

// ── Navegação ────────────────────────────────────────────────────────────
function showSection(id) {
  document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
  document.getElementById('sec-' + id).classList.add('active');
  document.querySelectorAll('.nav-item').forEach(n => {
    n.classList.toggle('active', n.dataset.section === id);
  });
  closeSidebar();
  if (id === 'alertas')     renderAlertas();
  if (id === 'ranking')     renderRanking();
  if (id === 'prateleiras') renderPrateleiras();
  if (id === 'historico')   renderHistorico();
}

document.querySelectorAll('.nav-item[data-section]').forEach(item => {
  item.addEventListener('click', () => showSection(item.dataset.section));
});

// ── Sidebar / Hamburger ──────────────────────────────────────────────────
const sidebar   = document.getElementById('sidebar');
const hamburger = document.getElementById('hamburger');
const overlay   = document.getElementById('overlay');

function openSidebar()  { sidebar.classList.add('open'); overlay.classList.add('visible'); hamburger.classList.add('open'); }
function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('visible'); hamburger.classList.remove('open'); }

hamburger.addEventListener('click', () => sidebar.classList.contains('open') ? closeSidebar() : openSidebar());
overlay.addEventListener('click', closeSidebar);

// ── Helpers ───────────────────────────────────────────────────────────────
function nomelivro(reg) {
  const l = LIVROS.find(x => x.registro == reg);
  return l ? l.nome : 'Livro desconhecido';
}
function diasAtraso(data) {
  const d  = new Date(data + 'T00:00:00');
  const hj = new Date(HOJE + 'T00:00:00');
  return Math.floor((hj - d) / 86400000);
}

// ── Charts factory ────────────────────────────────────────────────────────
function makeDonut(id, labels, data) {
  const ctx = document.getElementById(id);
  if (!ctx) return;
  return new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels,
      datasets: [{ data, backgroundColor: PALETTE, borderColor: '#0e0e0e', borderWidth: 2 }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { position: 'right', labels: { boxWidth: 10, padding: 10, color: TEXT } }
      }
    }
  });
}

function makeBar(id, labels, datasets, opts = {}) {
  const ctx = document.getElementById(id);
  if (!ctx) return;
  return new Chart(ctx, {
    type: 'bar',
    data: { labels, datasets },
    options: {
      responsive: true, maintainAspectRatio: false,
      indexAxis: opts.horizontal ? 'y' : 'x',
      scales: {
        x: { stacked: opts.stacked, grid: { color: BORDER }, ticks: { color: MUTED, maxRotation: 40 } },
        y: { stacked: opts.stacked, grid: { color: BORDER }, ticks: { color: MUTED } }
      },
      plugins: { legend: { labels: { color: TEXT, boxWidth: 10 } } }
    }
  });
}

// ── Init charts ───────────────────────────────────────────────────────────
window.addEventListener('DOMContentLoaded', () => {
  // Faixa etária (donut)
  const fKeys = Object.keys(FAIXAS);
  const fVals = Object.values(FAIXAS);
  makeDonut('chartFaixa',  fKeys, fVals);
  makeDonut('chartFaixa2', fKeys, fVals);

  // Prateleiras (barras)
  const pKeys = Object.keys(PRATELEIRAS_CNT);
  const pVals = Object.values(PRATELEIRAS_CNT);
  const pDataset = [{ label: 'Títulos', data: pVals, backgroundColor: RUST, borderRadius: 3 }];
  makeBar('chartPrat',  pKeys, pDataset);
  makeBar('chartPrat2', pKeys, pDataset);

  // Disp vs Emp (stacked horizontal)
  const t12Labels = TOP12.map(x => x.nome);
  const t12Disp   = TOP12.map(x => x.disp);
  const t12Emp    = TOP12.map(x => x.emp);
  const dispDs = [
    { label: 'Disponíveis', data: t12Disp, backgroundColor: OK,    borderRadius: 2 },
    { label: 'Emprestados', data: t12Emp,  backgroundColor: RUST2, borderRadius: 2 }
  ];
  makeBar('chartDisp',  t12Labels, dispDs, { horizontal: true, stacked: true });
  makeBar('chartDisp2', t12Labels, dispDs, { horizontal: true, stacked: true });

  // Por sala (barras)
  const sKeys = Object.keys(SALAS);
  const sVals = Object.values(SALAS);
  const sDataset = [{ label: 'Empréstimos', data: sVals, backgroundColor: PALETTE, borderRadius: 3 }];
  makeBar('chartSala',  sKeys, sDataset);
  makeBar('chartSala2', sKeys, sDataset);

  // Alertas, ranking e prateleiras ao carregar overview
  renderAlertas();
  renderRanking();
  renderPrateleiras();
  renderHistorico();
});

// ── Alertas ───────────────────────────────────────────────────────────────
function renderAlertas() {
  const la = document.getElementById('listaAtraso');
  const lv = document.getElementById('listaVencendo');
  if (!la || !lv) return;

  la.innerHTML = '';
  lv.innerHTML = '';

  if (EM_ATRASO.length === 0) {
    la.innerHTML = '<div class="empty-alert">Nenhuma devolução em atraso ✓</div>';
  } else {
    EM_ATRASO.forEach(e => {
      const dias = diasAtraso(e.devolucao);
      la.innerHTML += `<div class="alert-item">
        <div class="alert-aluno">${e.aluno}</div>
        <div class="alert-meta">${nomelivro(e.registro)} · Sala ${e.sala}</div>
        <div class="alert-meta">Devolver: ${formatData(e.devolucao)}</div>
        <span class="alert-badge">${dias} dia${dias>1?'s':''} em atraso</span>
      </div>`;
    });
  }

  if (VENCENDO.length === 0) {
    lv.innerHTML = '<div class="empty-alert">Nenhum vencimento nos próximos 7 dias</div>';
  } else {
    VENCENDO.forEach(e => {
      const diff = Math.ceil((new Date(e.devolucao+'T00:00:00') - new Date(HOJE+'T00:00:00')) / 86400000);
      lv.innerHTML += `<div class="alert-item warn">
        <div class="alert-aluno">${e.aluno}</div>
        <div class="alert-meta">${nomelivro(e.registro)} · Sala ${e.sala}</div>
        <div class="alert-meta">Devolver: ${formatData(e.devolucao)}</div>
        <span class="alert-badge warn">em ${diff} dia${diff>1?'s':''}</span>
      </div>`;
    });
  }
}

function formatData(s) {
  const [y,m,d] = s.split('-');
  return `${d}/${m}/${y}`;
}

// ── Ranking ───────────────────────────────────────────────────────────────
function renderRanking() {
  const el = document.getElementById('rankingList');
  if (!el) return;
  const entries = Object.entries(TOP_LIVROS);
  const max = entries[0]?.[1] || 1;
  el.innerHTML = entries.map(([nome, cnt], i) => `
    <div class="ranking-item">
      <span class="rank-num">${i+1}</span>
      <span class="rank-name">${nome}</span>
      <div class="rank-bar-wrap"><div class="rank-bar" style="width:${Math.round((cnt/max)*100)}%"></div></div>
      <span class="rank-count">${cnt}</span>
    </div>
  `).join('');
  if (!entries.length) el.innerHTML = '<div class="empty-alert">Sem dados no histórico ainda.</div>';
}

// ── Prateleiras verticais ─────────────────────────────────────────────────
function renderPrateleiras() {
  const container = document.getElementById('shelvesContainer');
  if (!container) return;
  const colunas = ['A','B','C','D','E'];
  const andares  = [1,2,3,4,5];

  container.innerHTML = '';
  colunas.forEach((col, ci) => {
    // Divisor entre colunas
    if (ci > 0) {
      const div = document.createElement('div');
      div.className = 'shelf-divider';
      container.appendChild(div);
    }

    const colEl = document.createElement('div');
    colEl.className = 'shelf-column';

    const label = document.createElement('div');
    label.className = 'shelf-col-label';
    label.textContent = col;
    colEl.appendChild(label);

    const slots = document.createElement('div');
    slots.className = 'shelf-slots';

    andares.forEach(andar => {
      const key   = `${col}${andar}`;
      const livros = MAPA_PRAT[col]?.[andar] || [];
      const slot   = document.createElement('div');
      slot.className = 'shelf-slot ' + (livros.length > 0 ? 'has-books' : 'empty');

      slot.innerHTML = `<span class="shelf-slot-label">${key}</span>
        <span class="shelf-slot-count">${livros.length > 0 ? livros.length : '—'}</span>`;

      if (livros.length > 0) {
        slot.addEventListener('mouseenter', (ev) => showTooltip(ev, key, livros));
        slot.addEventListener('mousemove',  (ev) => moveTooltip(ev));
        slot.addEventListener('mouseleave', hideTooltip);
        slot.addEventListener('touchstart', (ev) => {
          showTooltip(ev.touches[0], key, livros);
          setTimeout(hideTooltip, 2500);
        });
      }
      slots.appendChild(slot);
    });

    colEl.appendChild(slots);
    container.appendChild(colEl);
  });
}

const tooltip = document.getElementById('shelfTooltip');
function showTooltip(ev, key, livros) {
  tooltip.innerHTML = `<strong>PRATELEIRA ${key}</strong><ul>${livros.map(l=>`<li>${l}</li>`).join('')}</ul>`;
  moveTooltip(ev);
  tooltip.classList.add('visible');
}
function moveTooltip(ev) {
  const x = ev.clientX + 14;
  const y = ev.clientY - 10;
  tooltip.style.left = Math.min(x, window.innerWidth - 240) + 'px';
  tooltip.style.top  = Math.max(y, 8) + 'px';
}
function hideTooltip() { tooltip.classList.remove('visible'); }

// ── Histórico ─────────────────────────────────────────────────────────────
function renderHistorico() {
  const tbody  = document.getElementById('histTbody');
  if (!tbody) return;
  const busca  = (document.getElementById('histSearch')?.value || '').toLowerCase();
  const ano    = document.getElementById('histAno')?.value || '';

  let dados = [...HISTORICO].reverse();
  if (busca) dados = dados.filter(h => h.aluno.toLowerCase().includes(busca) || h.livro.toLowerCase().includes(busca));
  if (ano)   dados = dados.filter(h => h.ano === ano);

  if (!dados.length) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:24px;font-family:var(--font-mono);font-size:.78rem">Nenhum registro encontrado.</td></tr>';
    return;
  }

  tbody.innerHTML = dados.map((h, i) => {
    const atrasado = h.devolucao < HOJE;
    return `<tr>
      <td style="font-family:var(--font-mono);color:var(--muted)">${i+1}</td>
      <td>${h.livro}</td>
      <td>${h.aluno}</td>
      <td style="font-family:var(--font-mono);font-size:.72rem">${h.sala}</td>
      <td style="font-family:var(--font-mono);font-size:.72rem">${formatData(h.retirada)}</td>
      <td style="font-family:var(--font-mono);font-size:.72rem">${formatData(h.devolucao)}</td>
      <td><span class="badge ${atrasado?'danger':'ok'}">${atrasado?'Em atraso':'Devolvido'}</span></td>
    </tr>`;
  }).join('');
}

// ── Exportação ────────────────────────────────────────────────────────────

// Estado dos checkboxes
const CHECKS = {
  'metricas': false, 'emp-lista': false, 'emp-atraso': false,
  'emp-vencendo': false, 'acervo-lista': false, 'acervo-ranking': false,
  'acervo-salas': false, 'historico': false
};

function toggleCheck(id) {
  CHECKS[id] = !CHECKS[id];
  const box = document.getElementById('box-' + id);
  const item = document.getElementById('chk-' + id);
  if (box)  box.style.cssText = CHECKS[id]
    ? 'background:var(--rust);border-color:var(--rust);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.6rem;font-weight:700;width:16px;height:16px;border-radius:3px;flex-shrink:0;margin-top:1px;content:"✓"'
    : '';
  if (box && CHECKS[id]) box.innerHTML = '✓';
  else if (box) box.innerHTML = '';
  if (item) item.style.background = CHECKS[id] ? 'var(--rust-dim)' : '';
  atualizarPreview();
}

function toggleGroup(group) {
  const body = document.getElementById('body-' + group);
  const tog  = document.getElementById('tog-' + group);
  if (!body) return;
  body.classList.toggle('open');
  if (tog) tog.classList.toggle('open');
}

function selectAll(on) {
  Object.keys(CHECKS).forEach(k => {
    if (CHECKS[k] !== on) toggleCheck(k);
  });
}

function selectPreset(preset) {
  selectAll(false);
  if (preset === 'emprestimos') { toggleCheck('metricas'); toggleCheck('emp-lista'); toggleCheck('emp-atraso'); toggleCheck('emp-vencendo'); }
  if (preset === 'acervo')      { toggleCheck('metricas'); toggleCheck('acervo-lista'); toggleCheck('acervo-ranking'); toggleCheck('acervo-salas'); }
  if (preset === 'alertas')     { toggleCheck('emp-atraso'); toggleCheck('emp-vencendo'); }
}

function secSelecionadas() {
  return Object.entries(CHECKS).filter(([,v]) => v).map(([k]) => k);
}

// ── Geração do relatório formatado ────────────────────────────────────────
function gerarRelatorio(formato = 'txt') {
  const secs = secSelecionadas();
  const hoje = new Date().toLocaleDateString('pt-BR', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
  const SEP  = '═'.repeat(60);
  const sep  = '─'.repeat(60);

  let doc = '';

  if (formato === 'html') {
    doc += `<html><head><meta charset="UTF-8"><title>Relatório Biblioteca</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#111;background:#fff}
  .page{max-width:800px;margin:0 auto;background:#fff;padding:50px 54px;min-height:100vh}

  /* Cabeçalho institucional */
  .header-inst{display:flex;align-items:flex-start;gap:18px;padding-bottom:18px;border-bottom:2px solid #003366;margin-bottom:6px}
  .header-inst-logo{width:60px;height:60px;flex-shrink:0;display:flex;align-items:center;justify-content:center;border:1px solid #ccc;border-radius:2px;font-size:8px;color:#888;text-align:center;line-height:1.3;padding:4px}
  .header-inst-text{flex:1}
  .header-inst-gov{font-size:9px;color:#555;letter-spacing:.04em;text-transform:uppercase;margin-bottom:2px}
  .header-inst-school{font-size:15px;font-weight:bold;color:#003366;margin-bottom:2px}
  .header-inst-sub{font-size:10px;color:#444}
  .doc-type{text-align:center;margin:18px 0 6px;font-size:10px;letter-spacing:.12em;text-transform:uppercase;color:#555}
  .doc-title{text-align:center;font-size:17px;font-weight:bold;color:#111;margin-bottom:4px}
  .doc-date{text-align:center;font-size:10px;color:#666;margin-bottom:6px}
  .doc-ref{text-align:center;font-size:9px;color:#888;margin-bottom:20px;font-style:italic}
  .cover-divider{border:none;border-top:1px solid #ccc;margin:0 0 24px}

  /* Seções */
  .sec-label{font-size:8.5px;letter-spacing:.15em;text-transform:uppercase;color:#003366;margin-bottom:4px;margin-top:28px;font-weight:bold}
  .sec-title{font-size:13px;font-weight:bold;color:#111;margin-bottom:10px;padding-bottom:5px;border-bottom:1px solid #003366}

  /* Métricas */
  .metrics-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:10px}
  .metric-box{border:1px solid #ccc;padding:10px 12px;text-align:center;background:#f5f7fa}
  .metric-box .val{font-size:22px;font-weight:bold;color:#111;line-height:1.1}
  .metric-box .lbl{font-size:8.5px;color:#555;text-transform:uppercase;letter-spacing:.08em;margin-top:3px}

  /* Tabelas */
  table{width:100%;border-collapse:collapse;margin-top:6px;font-size:10.5px}
  th{background:#003366;color:#fff;font-size:9px;text-transform:uppercase;letter-spacing:.08em;padding:6px 9px;text-align:left}
  td{padding:6px 9px;border-bottom:1px solid #ddd;vertical-align:top;line-height:1.4}
  tr:nth-child(even) td{background:#f5f7fa}

  /* Badges */
  .badge-danger{background:#fdecea;color:#c0392b;padding:2px 6px;font-size:9px;border:1px solid #f5c6c2}
  .badge-ok{background:#e9f7ef;color:#1e8449;padding:2px 6px;font-size:9px;border:1px solid #a9dfbf}
  .badge-warn{background:#fef9e7;color:#b7950b;padding:2px 6px;font-size:9px;border:1px solid #f9e79f}

  /* Ranking */
  .rank-row{display:flex;align-items:center;gap:10px;padding:5px 0;border-bottom:1px solid #eee}
  .rank-num{font-size:9px;color:#888;width:16px;text-align:right;flex-shrink:0}
  .rank-bar-wrap{flex:1;height:5px;background:#e8ecf0;border-radius:2px;overflow:hidden}
  .rank-bar{height:100%;background:#003366;border-radius:2px}
  .rank-cnt{font-size:10px;color:#003366;font-weight:bold;width:22px;text-align:right;flex-shrink:0}

  /* Rodapé */
  .footer-doc{margin-top:48px;padding-top:10px;border-top:2px solid #003366;font-size:9px;color:#555;display:flex;justify-content:space-between}
  .footer-doc-center{text-align:center;font-size:8.5px;color:#888;margin-top:4px}

  @media print{
    body{background:#fff}
    .page{padding:20px 28px;max-width:100%}
    .sec-label{margin-top:20px}
  }
</style></head><body><div class="page">`;
    doc += `<div class="header-inst">
  <div class="header-inst-logo"><img style="width:100%;height:auto;" src="src/static/logo.png" alt="Logo"></div>
  <div class="header-inst-text">
    <div class="header-inst-school">E.E. Ephigênia de Jesus Werneck</div>
    <div class="header-inst-sub">Biblioteca Escolar · Santa Luzia — MG</div>
  </div>
</div>
<div class="doc-type">Documento Oficial</div>
<div class="doc-title">Relatório Gerencial — Biblioteca</div>
<div class="doc-date">Emitido em ${hoje}</div>
<div class="doc-ref">Gerado automaticamente pelo Sistema de Gestão da Biblioteca</div>
<hr class="cover-divider">`;
  } else {
    doc += `RELATÓRIO GERENCIAL — BIBLIOTECA\n`;
    doc += `E.E. Ephigênia de Jesus Werneck — Santa Luzia, MG\n`;
    doc += `Emitido em: ${hoje}\n${SEP}\n\n`;
  }

  // ── Métricas
  if (secs.includes('metricas')) {
    const totalEx  = LIVROS.reduce((s,l) => s+l.quantidade, 0);
    if (formato === 'html') {
      doc += `<div class="sec-label">Visão Geral</div>
<div class="sec-title">Métricas do Acervo</div>
<div class="metrics-grid">
  <div class="metric-box"><div class="val">${LIVROS.length}</div><div class="lbl">Títulos</div></div>
  <div class="metric-box"><div class="val">${totalEx}</div><div class="lbl">Exemplares</div></div>
  <div class="metric-box"><div class="val">${EMPRESTIMOS.length}</div><div class="lbl">Emprestados</div></div>
  <div class="metric-box"><div class="val">${Math.max(0,totalEx-EMPRESTIMOS.length)}</div><div class="lbl">Disponíveis</div></div>
  <div class="metric-box"><div class="val" style="color:${EM_ATRASO.length>0?'#c0392b':'#27ae60'}">${EM_ATRASO.length}</div><div class="lbl">Em Atraso</div></div>
  <div class="metric-box"><div class="val">${[...new Set(EMPRESTIMOS.map(e=>e.aluno))].length}</div><div class="lbl">Alunos Ativos</div></div>
</div>`;
    } else {
      doc += `MÉTRICAS DO ACERVO\n${sep}\n`;
      doc += `  Títulos no acervo ............... ${LIVROS.length}\n`;
      doc += `  Exemplares totais ............... ${totalEx}\n`;
      doc += `  Empréstimos ativos .............. ${EMPRESTIMOS.length}\n`;
      doc += `  Disponíveis para empréstimo ..... ${Math.max(0,totalEx-EMPRESTIMOS.length)}\n`;
      doc += `  Devoluções em atraso ............ ${EM_ATRASO.length}\n`;
      doc += `  Alunos com livros em mãos ....... ${[...new Set(EMPRESTIMOS.map(e=>e.aluno))].length}\n\n`;
    }
  }

  // ── Empréstimos - lista
  if (secs.includes('emp-lista') && EMPRESTIMOS.length) {
    if (formato === 'html') {
      doc += `<div class="sec-label">Empréstimos</div><div class="sec-title">Empréstimos Ativos (${EMPRESTIMOS.length})</div>
<table><thead><tr><th>#</th><th>Aluno</th><th>Livro</th><th>Sala</th><th>Retirada</th><th>Devolução</th><th>Status</th></tr></thead><tbody>`;
      EMPRESTIMOS.forEach((e,i) => {
        const at = e.devolucao < HOJE;
        doc += `<tr><td>${i+1}</td><td>${e.aluno}</td><td>${nomelivro(e.registro)}</td><td>${e.sala}</td><td>${formatData(e.retirada)}</td><td>${formatData(e.devolucao)}</td><td><span class="${at?'badge-danger':'badge-ok'}">${at?'Em atraso':'No prazo'}</span></td></tr>`;
      });
      doc += `</tbody></table>`;
    } else {
      doc += `EMPRÉSTIMOS ATIVOS (${EMPRESTIMOS.length})\n${sep}\n`;
      doc += `  ${'Nº'.padEnd(4)} ${'ALUNO'.padEnd(28)} ${'LIVRO'.padEnd(30)} ${'SALA'.padEnd(8)} ${'RETIRADA'.padEnd(12)} DEVOLUÇÃO\n`;
      doc += `  ${'─'.repeat(100)}\n`;
      EMPRESTIMOS.forEach((e,i) => {
        const at = e.devolucao < HOJE ? ' ⚠ ATRASO' : '';
        doc += `  ${String(i+1).padEnd(4)} ${e.aluno.padEnd(28)} ${nomelivro(e.registro).substring(0,30).padEnd(30)} ${e.sala.padEnd(8)} ${formatData(e.retirada).padEnd(12)} ${formatData(e.devolucao)}${at}\n`;
      });
      doc += '\n';
    }
  }

  // ── Atraso
  if (secs.includes('emp-atraso')) {
    if (formato === 'html') {
      doc += `<div class="sec-label">Alertas Críticos</div><div class="sec-title">Devoluções em Atraso (${EM_ATRASO.length})</div>`;
      if (!EM_ATRASO.length) doc += `<p style="color:#27ae60;font-size:11px;padding:8px 0">✓ Nenhuma devolução em atraso.</p>`;
      else {
        doc += `<table><thead><tr><th>Aluno</th><th>Livro</th><th>Sala</th><th>Prazo</th><th>Atraso</th></tr></thead><tbody>`;
        EM_ATRASO.forEach(e => {
          const dias = diasAtraso(e.devolucao);
          doc += `<tr><td>${e.aluno}</td><td>${nomelivro(e.registro)}</td><td>${e.sala}</td><td>${formatData(e.devolucao)}</td><td><span class="badge-danger">${dias} dia${dias>1?'s':''}</span></td></tr>`;
        });
        doc += `</tbody></table>`;
      }
    } else {
      doc += `DEVOLUÇÕES EM ATRASO (${EM_ATRASO.length})\n${sep}\n`;
      if (!EM_ATRASO.length) doc += `  ✓ Nenhuma devolução em atraso.\n\n`;
      else EM_ATRASO.forEach(e => {
        const dias = diasAtraso(e.devolucao);
        doc += `  ⚠  ${e.aluno.padEnd(28)} ${nomelivro(e.registro).substring(0,30).padEnd(30)} Prazo: ${formatData(e.devolucao)}  [${dias}d de atraso]\n`;
      });
      doc += '\n';
    }
  }

  // ── Vencendo
  if (secs.includes('emp-vencendo')) {
    if (formato === 'html') {
      doc += `<div class="sec-label">Alertas</div><div class="sec-title">Vencendo em 7 Dias (${VENCENDO.length})</div>`;
      if (!VENCENDO.length) doc += `<p style="color:#888;font-size:11px;padding:8px 0">Nenhum vencimento nos próximos 7 dias.</p>`;
      else {
        doc += `<table><thead><tr><th>Aluno</th><th>Livro</th><th>Sala</th><th>Prazo</th><th>Restam</th></tr></thead><tbody>`;
        VENCENDO.forEach(e => {
          const diff = Math.ceil((new Date(e.devolucao+'T00:00:00') - new Date(HOJE+'T00:00:00')) / 86400000);
          doc += `<tr><td>${e.aluno}</td><td>${nomelivro(e.registro)}</td><td>${e.sala}</td><td>${formatData(e.devolucao)}</td><td><span class="badge-warn">${diff} dia${diff>1?'s':''}</span></td></tr>`;
        });
        doc += `</tbody></table>`;
      }
    } else {
      doc += `VENCENDO EM 7 DIAS (${VENCENDO.length})\n${sep}\n`;
      if (!VENCENDO.length) doc += `  Nenhum vencimento próximo.\n\n`;
      else VENCENDO.forEach(e => {
        const diff = Math.ceil((new Date(e.devolucao+'T00:00:00') - new Date(HOJE+'T00:00:00')) / 86400000);
        doc += `  ⏰  ${e.aluno.padEnd(28)} ${nomelivro(e.registro).substring(0,30).padEnd(30)} Prazo: ${formatData(e.devolucao)}  [${diff}d restantes]\n`;
      });
      doc += '\n';
    }
  }

  // ── Acervo lista
  if (secs.includes('acervo-lista')) {
    if (formato === 'html') {
      doc += `<div class="sec-label">Acervo</div><div class="sec-title">Lista Completa do Acervo (${LIVROS.length} títulos)</div>
<table><thead><tr><th>#</th><th>Título</th><th>Autor</th><th>Registro</th><th>Prateleira</th><th>Faixa Etária</th><th>Qtd</th></tr></thead><tbody>`;
      LIVROS.forEach((l,i) => {
        doc += `<tr><td>${i+1}</td><td>${l.nome}</td><td>${l.autor||'—'}</td><td>${l.registro}</td><td>${l.prateleira||'—'}</td><td>${l.faixaEtaria||'—'}</td><td>${l.quantidade}</td></tr>`;
      });
      doc += `</tbody></table>`;
    } else {
      doc += `ACERVO COMPLETO (${LIVROS.length} títulos)\n${sep}\n`;
      LIVROS.forEach((l,i) => {
        doc += `  ${String(i+1).padEnd(4)} ${l.nome.padEnd(40)} Autor: ${(l.autor||'—').padEnd(24)} Prat: ${l.prateleira||'—'} | Qtd: ${l.quantidade}\n`;
      });
      doc += '\n';
    }
  }

  // ── Ranking
  if (secs.includes('acervo-ranking')) {
    const entries = Object.entries(TOP_LIVROS);
    const max = entries[0]?.[1] || 1;
    if (formato === 'html') {
      doc += `<div class="sec-label">Ranking</div><div class="sec-title">Livros Mais Emprestados</div>`;
      if (!entries.length) doc += `<p style="color:#888;font-size:11px">Sem dados no histórico.</p>`;
      else {
        doc += `<div style="margin-top:8px">`;
        entries.forEach(([nome,cnt],i) => {
          const pct = Math.round((cnt/max)*100);
          doc += `<div class="rank-row"><span class="rank-num">${i+1}</span><span style="flex:1;font-size:11px">${nome}</span><div class="rank-bar-wrap"><div class="rank-bar" style="width:${pct}%"></div></div><span class="rank-cnt">${cnt}</span></div>`;
        });
        doc += `</div>`;
      }
    } else {
      doc += `RANKING — MAIS EMPRESTADOS\n${sep}\n`;
      entries.forEach(([nome,cnt],i) => { doc += `  ${String(i+1).padEnd(3)} ${nome.padEnd(44)} ${cnt} empréstimo(s)\n`; });
      doc += '\n';
    }
  }

  // ── Empréstimos por sala
  if (secs.includes('acervo-salas')) {
    if (formato === 'html') {
      doc += `<div class="sec-label">Turmas</div><div class="sec-title">Empréstimos por Sala / Turma</div>
<table><thead><tr><th>Sala</th><th>Empréstimos ativos</th></tr></thead><tbody>`;
      Object.entries(SALAS).forEach(([s,c]) => { doc += `<tr><td>${s}</td><td>${c}</td></tr>`; });
      doc += `</tbody></table>`;
    } else {
      doc += `EMPRÉSTIMOS POR SALA\n${sep}\n`;
      Object.entries(SALAS).forEach(([s,c]) => { doc += `  ${s.padEnd(16)} ${c} empréstimo(s)\n`; });
      doc += '\n';
    }
  }

  // ── Histórico
  if (secs.includes('historico')) {
    if (formato === 'html') {
      doc += `<div class="sec-label">Registros</div><div class="sec-title">Histórico Completo (${HISTORICO.length} registros)</div>
<table><thead><tr><th>#</th><th>Livro</th><th>Aluno</th><th>Sala</th><th>Retirada</th><th>Devolução</th><th>Ano</th></tr></thead><tbody>`;
      [...HISTORICO].reverse().forEach((h,i) => {
        doc += `<tr><td>${i+1}</td><td>${h.livro}</td><td>${h.aluno}</td><td>${h.sala}</td><td>${formatData(h.retirada)}</td><td>${formatData(h.devolucao)}</td><td>${h.ano}</td></tr>`;
      });
      doc += `</tbody></table>`;
    } else {
      doc += `HISTÓRICO COMPLETO (${HISTORICO.length} registros)\n${sep}\n`;
      [...HISTORICO].reverse().forEach((h,i) => {
        doc += `  ${String(i+1).padEnd(4)} ${h.livro.substring(0,36).padEnd(36)} ${h.aluno.padEnd(28)} ${formatData(h.retirada)} → ${formatData(h.devolucao)} [${h.ano}]\n`;
      });
      doc += '\n';
    }
  }

  if (formato === 'html') {
    doc += `<div class="footer-doc"><span>E.E. Ephigênia de Jesus Werneck · Biblioteca Escolar · Santa Luzia, MG</span><span>Emitido em ${hoje}</span></div><div class="footer-doc-center">Sistema de Gestão da Biblioteca · Documento gerado eletronicamente</div>`;
    doc += `</div></body></html>`;
  } else {
    doc += `${SEP}\nE.E. Ephigênia de Jesus Werneck · Sistema de Biblioteca\npor Arthur A. 2 Reg 3 · ${hoje}\n`;
  }

  return doc;
}

// ── Preview ao vivo ────────────────────────────────────────────────────────
function atualizarPreview() {
  const secs = secSelecionadas();
  const preview = document.getElementById('docPreview');
  const secLabel = document.getElementById('previewSections');

  const nomes = { 'metricas':'Métricas', 'emp-lista':'Empréstimos', 'emp-atraso':'Atrasos', 'emp-vencendo':'Vencendo',
                  'acervo-lista':'Acervo', 'acervo-ranking':'Ranking', 'acervo-salas':'Por Sala', 'historico':'Histórico' };
  if (!secs.length) {
    if (secLabel) secLabel.textContent = 'nenhuma seção selecionada';
    if (preview) preview.innerHTML = '<div style="color:var(--border);text-align:center;padding:20px 0">Selecione seções acima para pré-visualizar</div>';
    return;
  }
  if (secLabel) secLabel.textContent = secs.map(s => nomes[s]).join(' · ');

  const hoje = new Date().toLocaleDateString('pt-BR');
  let html = `<div class="dp-title">Biblioteca — Relatório Gerencial</div>`;
  html += `<div style="font-size:.62rem;color:var(--muted);margin-bottom:8px">E.E. Ephigênia · ${hoje}</div>`;

  if (secs.includes('metricas')) {
    const totEx = LIVROS.reduce((s,l)=>s+l.quantidade,0);
    html += `<div class="dp-section">Métricas</div>`;
    html += `<div class="dp-row"><span>Títulos</span><span>${LIVROS.length}</span></div>`;
    html += `<div class="dp-row"><span>Exemplares</span><span>${totEx}</span></div>`;
    html += `<div class="dp-row"><span>Emprestados</span><span>${EMPRESTIMOS.length}</span></div>`;
    html += `<div class="dp-row"><span>Em atraso</span><span style="color:${EM_ATRASO.length?'var(--danger)':'var(--ok)'}">${EM_ATRASO.length}</span></div>`;
  }
  if (secs.includes('emp-lista')) {
    html += `<div class="dp-section">Empréstimos Ativos</div>`;
    html += `<div class="dp-row"><span>Total de registros</span><span>${EMPRESTIMOS.length}</span></div>`;
    EMPRESTIMOS.slice(0,3).forEach(e => {
      html += `<div class="dp-row" style="font-size:.62rem"><span>${e.aluno}</span><span style="color:var(--muted)">${formatData(e.devolucao)}</span></div>`;
    });
    if (EMPRESTIMOS.length > 3) html += `<div style="font-size:.6rem;color:var(--muted);padding-top:4px">… e mais ${EMPRESTIMOS.length-3} registros</div>`;
  }
  if (secs.includes('emp-atraso')) {
    html += `<div class="dp-section">Em Atraso</div>`;
    html += `<div class="dp-row"><span>Ocorrências</span><span style="color:var(--danger)">${EM_ATRASO.length}</span></div>`;
  }
  if (secs.includes('acervo-lista')) {
    html += `<div class="dp-section">Acervo</div>`;
    html += `<div class="dp-row"><span>Total de títulos</span><span>${LIVROS.length}</span></div>`;
  }
  if (secs.includes('acervo-ranking')) {
    const top = Object.entries(TOP_LIVROS).slice(0,3);
    html += `<div class="dp-section">Ranking</div>`;
    top.forEach(([nome,cnt],i) => { html += `<div class="dp-row" style="font-size:.62rem"><span>${i+1}. ${nome.substring(0,28)}</span><span style="color:var(--rust)">${cnt}×</span></div>`; });
  }
  if (secs.includes('historico')) {
    html += `<div class="dp-section">Histórico</div>`;
    html += `<div class="dp-row"><span>Total de registros</span><span>${HISTORICO.length}</span></div>`;
  }

  if (preview) preview.innerHTML = html;
}

// ── Exportar PDF (impressão) ───────────────────────────────────────────────
function exportarPDF() {
  const secs = secSelecionadas();
  if (!secs.length) { showToast('Selecione ao menos uma seção', 'err'); return; }
  const html = gerarRelatorio('html');
  const win = window.open('', '_blank');
  win.document.write(html);
  win.document.close();
  setTimeout(() => win.print(), 600);
}

// ── Exportar CSV filtrado ─────────────────────────────────────────────────
function exportarCSVFiltrado() {
  const secs = secSelecionadas();
  let csv = '\uFEFF';

  if (secs.includes('metricas')) {
    csv += 'SEÇÃO,MÉTRICA,VALOR\n';
    csv += `Métricas,Títulos,${LIVROS.length}\n`;
    csv += `Métricas,Exemplares,${LIVROS.reduce((s,l)=>s+l.quantidade,0)}\n`;
    csv += `Métricas,Emprestados,${EMPRESTIMOS.length}\n`;
    csv += `Métricas,Em Atraso,${EM_ATRASO.length}\n\n`;
  }
  if (secs.includes('emp-lista')) {
    csv += 'ID,Registro,Livro,Aluno,Sala,Retirada,Devolucao,Status\n';
    EMPRESTIMOS.forEach(e => {
      const st = e.devolucao < HOJE ? 'EM ATRASO' : 'No prazo';
      csv += [e.id,e.registro,nomelivro(e.registro),e.aluno,e.sala,e.retirada,e.devolucao,st]
        .map(v=>`"${String(v).replace(/"/g,'""')}"`).join(',') + '\n';
    });
    csv += '\n';
  }
  if (secs.includes('acervo-lista')) {
    csv += 'Titulo,Autor,Registro,Prateleira,FaixaEtaria,Quantidade\n';
    LIVROS.forEach(l => {
      csv += [l.nome,l.autor,l.registro,l.prateleira,l.faixaEtaria,l.quantidade]
        .map(v=>`"${String(v||'').replace(/"/g,'""')}"`).join(',') + '\n';
    });
    csv += '\n';
  }
  if (secs.includes('acervo-ranking')) {
    csv += 'Posicao,Livro,Total de Emprestimos\n';
    Object.entries(TOP_LIVROS).forEach(([nome,cnt],i) => {
      csv += `${i+1},"${nome}",${cnt}\n`;
    });
    csv += '\n';
  }

  if (csv.trim() === '\uFEFF') { showToast('Selecione seções que contenham dados tabulares', 'err'); return; }
  downloadBlob(csv, `relatorio-biblioteca-${new Date().toISOString().slice(0,10)}.csv`, 'text/csv;charset=utf-8');
  showToast('CSV baixado com sucesso', 'ok');
}

// ── Enviar Gmail ────────────────────────────────────────────────────────────
function gerarCorpoEmail() {
  const secs = secSelecionadas();
  const hoje = new Date().toLocaleDateString('pt-BR');
  let linhas = [];

  linhas.push('RELATÓRIO — BIBLIOTECA E.E. EPHIGÊNIA DE JESUS WERNECK');
  linhas.push('Gerado em: ' + hoje);
  linhas.push('');

  if (secs.includes('metricas')) {
    linhas.push('── MÉTRICAS ──────────────────────────────────');
    const totEx = LIVROS.reduce((s,l)=>s+l.quantidade,0);
    linhas.push('Títulos cadastrados : ' + LIVROS.length);
    linhas.push('Total de exemplares : ' + totEx);
    linhas.push('Emprestados agora   : ' + EMPRESTIMOS.length);
    linhas.push('Disponíveis         : ' + (totEx - EMPRESTIMOS.length));
    linhas.push('Em atraso           : ' + EM_ATRASO.length);
    linhas.push('');
  }

  if (secs.includes('emp-lista')) {
    linhas.push('── EMPRÉSTIMOS ATIVOS (' + EMPRESTIMOS.length + ') ────────────────');
    EMPRESTIMOS.forEach((e, i) => {
      const livro = nomelivro(e.registro) || e.registro;
      const status = e.devolucao < HOJE ? '⚠ ATRASO' : 'No prazo';
      linhas.push((i+1) + '. ' + e.aluno);
      linhas.push('   Livro     : ' + livro);
      linhas.push('   Sala      : ' + (e.sala || '—'));
      linhas.push('   Retirada  : ' + formatData(e.retirada));
      linhas.push('   Devolução : ' + formatData(e.devolucao) + '  [' + status + ']');
      linhas.push('');
    });
  }

  if (secs.includes('emp-atraso') && EM_ATRASO.length) {
    linhas.push('── EM ATRASO (' + EM_ATRASO.length + ') ──────────────────────────');
    EM_ATRASO.forEach((e, i) => {
      const dias = Math.floor((new Date(HOJE+'T00:00:00') - new Date(e.devolucao+'T00:00:00')) / 86400000);
      linhas.push((i+1) + '. ' + e.aluno + ' — ' + (nomelivro(e.registro)||e.registro));
      linhas.push('   Deveria devolver em ' + formatData(e.devolucao) + ' (' + dias + ' dia(s) de atraso)');
      linhas.push('');
    });
  }

  if (secs.includes('emp-vencendo') && VENCENDO.length) {
    linhas.push('── VENCENDO EM 7 DIAS (' + VENCENDO.length + ') ─────────────────');
    VENCENDO.forEach((e, i) => {
      const diff = Math.ceil((new Date(e.devolucao+'T00:00:00') - new Date(HOJE+'T00:00:00')) / 86400000);
      linhas.push((i+1) + '. ' + e.aluno + ' — ' + (nomelivro(e.registro)||e.registro));
      linhas.push('   Devolução: ' + formatData(e.devolucao) + ' (' + diff + ' dia(s) restantes)');
      linhas.push('');
    });
  }

  if (secs.includes('acervo-ranking')) {
    linhas.push('── RANKING — MAIS EMPRESTADOS ────────────────');
    Object.entries(TOP_LIVROS).forEach(([nome, cnt], i) => {
      linhas.push((i+1) + '. ' + nome + ' — ' + cnt + ' empréstimo(s)');
    });
    linhas.push('');
  }

  if (secs.includes('acervo-salas')) {
    linhas.push('── EMPRÉSTIMOS POR SALA ──────────────────────');
    Object.entries(SALAS).forEach(([s, c]) => {
      linhas.push('Sala ' + s + ': ' + c + ' empréstimo(s)');
    });
    linhas.push('');
  }

  if (secs.includes('acervo-lista')) {
    linhas.push('── ACERVO (' + LIVROS.length + ' títulos) ──────────────────────');
    LIVROS.forEach((l, i) => {
      linhas.push((i+1) + '. ' + l.nome);
      if (l.autor) linhas.push('   Autor: ' + l.autor);
      linhas.push('   Registro: ' + l.registro + ' | Prateleira: ' + (l.prateleira||'—') + ' | Qtd: ' + l.quantidade);
      linhas.push('');
    });
  }

  if (secs.includes('historico')) {
    linhas.push('── HISTÓRICO (' + HISTORICO.length + ' registros) ──────────────');
    [...HISTORICO].reverse().forEach((h, i) => {
      linhas.push((i+1) + '. ' + h.aluno + ' — ' + h.livro);
      linhas.push('   ' + formatData(h.retirada) + ' → ' + formatData(h.devolucao) + ' [' + h.ano + ']');
      linhas.push('');
    });
  }

  linhas.push('──────────────────────────────────────────────');
  linhas.push('Sistema de Biblioteca · E.E. Ephigênia de Jesus Werneck');
  linhas.push('por Arthur A. 2 Reg 3');

  return linhas.join('\n');
}

function enviarGmail() {
  const secs = secSelecionadas();
  if (!secs.length) { showToast('Selecione ao menos uma seção', 'err'); return; }

  const corpo = gerarCorpoEmail();

  navigator.clipboard.writeText(corpo).then(() => {
    window.open('https://mail.google.com/mail/u/0/#compose', '_blank');
    showToast('Texto copiado! Cole no corpo do e-mail (Ctrl+V)', 'ok');
  }).catch(() => {
    // Fallback para navegadores sem permissão de clipboard
    const ta = document.createElement('textarea');
    ta.value = corpo;
    ta.style.cssText = 'position:fixed;opacity:0;top:0;left:0';
    document.body.appendChild(ta);
    ta.focus(); ta.select();
    try {
      document.execCommand('copy');
      window.open('https://mail.google.com/mail/u/0/#compose', '_blank');
      showToast('Texto copiado! Cole no corpo do e-mail (Ctrl+V)', 'ok');
    } catch(e) {
      showToast('Erro ao copiar. Copie manualmente.', 'err');
    }
    document.body.removeChild(ta);
  });
}

// ── Salvar no Google Drive ─────────────────────────────────────────────────
async function salvarDrive() {
  const secs = secSelecionadas();
  if (!secs.length) { showToast('Selecione ao menos uma seção', 'err'); return; }

  const status = document.getElementById('driveStatus');
  const btn = document.querySelector('[onclick="salvarDrive()"]');
  const filename = (document.getElementById('driveFilename').value.trim() || 'Relatório Biblioteca') + '.html';
  const conteudo = gerarRelatorio('html');

  btn.disabled = true;
  btn.textContent = 'Aguardando autenticação…';
  status.className = 'gm-status show';
  status.textContent = '⏳ Abrindo autenticação do Google…';

  try {
    // Abre OAuth do Google para picker/Drive
    // Como não temos backend PHP para OAuth, usamos a API de upload via token do GIS
    const CLIENT_ID = ''; // Será preenchido pelo usuário se necessário
    const SCOPES    = 'https://www.googleapis.com/auth/drive.file';

    if (!window.google?.accounts?.oauth2) {
      // Carrega GIS se ainda não foi carregado
      await new Promise((res,rej) => {
        if (document.getElementById('gis-script')) { res(); return; }
        const s = document.createElement('script');
        s.id = 'gis-script';
        s.src = 'https://accounts.google.com/gsi/client';
        s.onload = res; s.onerror = rej;
        document.head.appendChild(s);
      });
    }

    // Como não há client_id configurado neste ambiente, baixa o HTML localmente
    // e direciona o usuário para o Drive
    const blob = new Blob([conteudo], { type: 'text/html;charset=utf-8' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = filename; a.click();
    URL.revokeObjectURL(url);

    setTimeout(() => {
      window.open('https://drive.google.com/drive/my-drive', '_blank');
    }, 800);

    status.className = 'gm-status show ok';
    status.innerHTML = `✓ Arquivo <strong>${filename}</strong> baixado.<br>O Google Drive foi aberto — faça o upload do arquivo baixado.<br><small style="opacity:.7">Para upload automático, configure o OAuth Client ID nas configurações do sistema.</small>`;
    showToast('Arquivo pronto para upload', 'ok');
  } catch(err) {
    status.className = 'gm-status show err';
    status.textContent = '✗ Erro: ' + err.message;
    showToast('Erro ao acessar Drive', 'err');
  } finally {
    btn.disabled = false;
    btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 87.3 78" fill="none"><path d="M6.6 66.85l3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3l13.75-23.8H10.55c0 1.55.4 3.1 1.2 4.5z" fill="#0066DA"/><path d="M43.65 25L29.9 1.2c-1.35.8-2.5 1.9-3.3 3.3L2.5 48.3c-.8 1.4-1.2 2.95-1.2 4.5H27.5z" fill="#00AC47"/><path d="M73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75 7.65-13.25c.8-1.4 1.2-2.95 1.2-4.5H60.8l5.85 11.5z" fill="#EA4335"/><path d="M43.65 25L57.4 1.2C56.05.4 54.5 0 52.9 0H34.4c-1.6 0-3.15.45-4.5 1.2z" fill="#00832D"/><path d="M60.8 53H27.5l-13.75 23.8c1.35.8 2.9 1.2 4.5 1.2h51.8c1.6 0 3.15-.45 4.5-1.2z" fill="#2684FC"/><path d="M73.4 26.5l-13.1-22.7C59.5 2.4 58.35 1.3 57 .5L43.25 24.3l17.55 28.7H86.8c0-1.55-.4-3.1-1.2-4.5z" fill="#FFBA00"/></svg> Fazer Upload para o Drive`;
  }
}

// ── Toast ──────────────────────────────────────────────────────────────────
function showToast(msg, type = 'ok') {
  const t = document.getElementById('exportToast');
  document.getElementById('toastMsg').textContent = msg;
  t.className = `export-toast ${type} show`;
  clearTimeout(t._t);
  t._t = setTimeout(() => t.classList.remove('show'), 3500);
}

function downloadBlob(content, filename, mime) {
  const blob = new Blob([content], { type: mime });
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  a.href = url; a.download = filename; a.click();
  URL.revokeObjectURL(url);
}

// Funções antigas mantidas para compatibilidade
function exportCSV() { exportarCSVFiltrado(); }
function exportDOCX() { exportarPDF(); }
function imprimirRelatorio() { exportarPDF(); }
function enviarEmail() { enviarGmail(); }
</script>
</body>
</html>