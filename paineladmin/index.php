<?php
// index.php - Novo Dashboard (Index do Painel Admin)
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) { header('Location: admin_login.php'); exit; }
require_once '../config/db.php';
// Incluído para a função formatCurrency
require_once '../funcoes.php';
date_default_timezone_set('America/Sao_Paulo');

// ==========================================================
// LÓGICA DO DASHBOARD
// ==========================================================

// --- Métricas dos 7 Cartões Superiores ---
$total_pedidos_pendentes = 0;
$total_usuarios = 0;
$faturamento_hoje = 0.00;
$produtos_em_destaque = 0;
$tickets_abertos = 0;
$media_avaliacoes = 0.00;
$estoque_baixo = 0;
$limite_estoque_baixo = 5;

// --- Métricas do FUNIL DE VENDAS (ATUALIZADO) ---
$stats = [
    'pendentes' => 0, 'aprovados_hoje' => 0, 'em_transporte' => 0,
    'cancelados' => 0,
    'faturamento_total' => 0.00,
    'faturamento_mes' => 0.00,
    'faturamento_semana' => 0.00,
    'faturamento_dia' => 0.00
];

// Define os limites de data para as queries
$hoje = date('Y-m-d');
$primeiro_dia_mes = date('Y-m-01');
$primeiro_dia_semana = date('Y-m-d', strtotime('monday this week'));

try {
    // --- LÓGICA DOS 7 CARTÕES ---
    $stmt_pedidos = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE status = :status");
    $stmt_pedidos->execute(['status' => 'PENDENTE']);
    $total_pedidos_pendentes = $stmt_pedidos->fetchColumn();

    $total_usuarios = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();

    $stmt_faturamento = $pdo->prepare("
        SELECT SUM(valor_total) FROM pedidos
        WHERE (status = :status1 OR status = :status2) AND criado_em >= CURRENT_DATE
    ");
    $stmt_faturamento->execute(['status1' => 'APROVADO', 'status2' => 'CONCLUIDO']);
    $faturamento_hoje = $stmt_faturamento->fetchColumn() ?? 0.00;

    $stmt_destaque = $pdo->query("SELECT COUNT(*) FROM produtos WHERE destaque = TRUE")->fetchColumn() ?? 0;

    $stmt_tickets = $pdo->prepare("
        SELECT COUNT(t.id) FROM tickets t
        JOIN ticket_status ts ON t.status_id = ts.id
        WHERE ts.nome <> :status_fechado
    ");
    $stmt_tickets->execute(['status_fechado' => 'FECHADO']);
    $tickets_abertos = $stmt_tickets->fetchColumn() ?? 0;

    $stmt_avaliacoes = $pdo->query("SELECT AVG(classificacao) FROM avaliacoes_produto WHERE aprovado = TRUE");
    $media_avaliacoes = round($stmt_avaliacoes->fetchColumn() ?? 0.0, 1);

    $stmt_estoque = $pdo->prepare("SELECT COUNT(*) FROM produtos WHERE estoque <= :limite AND ativo = TRUE");
    $stmt_estoque->execute(['limite' => $limite_estoque_baixo]);
    $estoque_baixo = $stmt_estoque->fetchColumn() ?? 0;


    // --- LÓGICA DO FUNIL DE VENDAS (QUERY ATUALIZADA) ---
    $paid_status = "status IN ('APROVADO', 'PROCESSANDO', 'EM TRANSPORTE', 'ENTREGUE')";

    $stmt_stats = $pdo->prepare("
        SELECT
            SUM(CASE WHEN status IN ('PENDENTE', 'AGUARDANDO PAGAMENTO') THEN 1 ELSE 0 END) AS pendentes,
            SUM(CASE WHEN status IN ('APROVADO', 'ENTREGUE') AND DATE(criado_em) = :hoje THEN 1 ELSE 0 END) AS aprovados_hoje,
            SUM(CASE WHEN status = 'EM TRANSPORTE' THEN 1 ELSE 0 END) AS em_transporte,
            SUM(CASE WHEN status = 'CANCELADO' THEN 1 ELSE 0 END) AS cancelados,

            SUM(CASE WHEN $paid_status THEN valor_total ELSE 0 END) AS faturamento_total,
            SUM(CASE WHEN $paid_status AND criado_em >= :primeiro_dia_mes THEN valor_total ELSE 0 END) AS faturamento_mes,
            SUM(CASE WHEN $paid_status AND criado_em >= :primeiro_dia_semana THEN valor_total ELSE 0 END) AS faturamento_semana,
            SUM(CASE WHEN $paid_status AND criado_em >= :hoje THEN valor_total ELSE 0 END) AS faturamento_dia
        FROM pedidos
    ");
    $stmt_stats->execute([
        ':hoje' => $hoje,
        ':primeiro_dia_mes' => $primeiro_dia_mes,
        ':primeiro_dia_semana' => $primeiro_dia_semana
    ]);

    $stats_data = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    if($stats_data) {
        $stats = $stats_data;
    }

} catch (PDOException $e) {
    // Tratamento de Erro
    $total_pedidos_pendentes = $total_usuarios = $faturamento_hoje = $produtos_em_destaque = $tickets_abertos = $media_avaliacoes = $estoque_baixo = 'Erro';
    $stats = array_fill_keys(array_keys($stats), 'Erro');
    error_log("Erro no Dashboard: " . $e->getMessage());
}

// Helper de formatação (Fallback caso funcoes.php não a tenha)
if (!function_exists('formatCurrency')) {
    function formatCurrency($value) {
        $numericValue = filter_var($value, FILTER_VALIDATE_FLOAT);
        if ($numericValue === false) { $numericValue = 0.00; }
        return 'R$ ' . number_format($numericValue, 2, ',', '.');
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Dashboard</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>

    <style>
        /* ==========================================================
           CSS COMPLETO DO PAINEL ADMIN
           ========================================================== */
        :root {
            --primary-color: #4a69bd;
            --secondary-color: #6a89cc;
            --text-color: #f9fafb;
            --light-text-color: #9ca3af;
            --border-color: rgba(255, 255, 255, 0.1);
            --background-color: #111827;
            --sidebar-color: #1f2937;
            --glass-background: rgba(31, 41, 55, 0.7);
            --success-bg: rgba(40, 167, 69, 0.3);
            --success-text: #c3e6cb;
            --error-bg: rgba(220, 53, 69, 0.3);
            --error-text: #f5c6cb;
            --info-bg: rgba(0, 123, 255, 0.2);
            --info-text: #bee5eb;
            --warning-bg: rgba(255, 193, 7, 0.2);
            --warning-text: #ffeeba;
            --danger-color: #e74c3c;
            --sidebar-width: 240px;
            --border-radius: 8px;
            --box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);

            /* Status Específicos */
            --status-aprovado: #28a745;
            --status-pendente: #ffc107;
            --status-cancelado: #dc3545;
            --status-processando: #17a2b8;
            --status-faturamento: #009688; /* Verde Escuro (Teal) */
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }
        #particles-js { position: fixed; width: 100%; height: 100%; top: 0; left: 0; z-index: -1; opacity: 0.6; }

        /* --- Sidebar (CSS Básico) --- */
        .sidebar { width: var(--sidebar-width); background-color: var(--sidebar-color); height: 100vh; position: fixed; left: 0; top: 0; padding: 1.5rem; display: flex; flex-direction: column; border-right: 1px solid var(--border-color); z-index: 1000; transition: transform 0.3s ease; box-shadow: var(--box-shadow); }
        .sidebar .logo-area { display: flex; flex-direction: column; align-items: center; margin-bottom: 1rem; }
        .sidebar .logo-circle { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 0.5rem; box-shadow: 0 0 10px rgba(74, 105, 189, 0.6); overflow: hidden; background-color: #fff; }
        .sidebar .logo-circle svg { color: var(--primary-color); width: 24px; height: 24px; }
        .sidebar .logo-text { font-size: 1rem; font-weight: 600; color: var(--text-color); text-align: center; }
        .sidebar .divider { width: 100%; height: 1px; background-color: var(--border-color); margin: 1rem 0; }
        .sidebar nav { flex-grow: 1; }
        .sidebar nav a { display: flex; align-items: center; gap: 0.8rem; padding: 0.8rem 1rem; color: var(--light-text-color); text-decoration: none; border-radius: var(--border-radius); margin-bottom: 0.5rem; transition: all 0.3s ease; border: 1px solid transparent; background-color: transparent; }
        .sidebar nav a:hover, .sidebar nav a.active { background-color: var(--glass-background); color: var(--text-color); border-color: var(--primary-color); box-shadow: 0 2px 8px rgba(74, 105, 189, 0.4); }
        .sidebar nav a svg { width: 20px; height: 20px; flex-shrink: 0; }
        .user-profile { position: relative; margin-top: auto; background-color: var(--glass-background); padding: 0.75rem; border-radius: var(--border-radius); display: flex; align-items: center; gap: 1rem; cursor: pointer; border: 1px solid var(--border-color); transition: all 0.3s ease; }
        .user-profile:hover { border-color: var(--primary-color); }
        .avatar { width: 35px; height: 35px; border-radius: 50%; background-color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.9rem; }
        .user-info .user-name { font-weight: 600; font-size: 0.85rem; line-height: 1.2; }
        .user-info .user-level { font-size: 0.7rem; color: var(--light-text-color); }
        .profile-dropdown { position: absolute; bottom: calc(100% + 10px); left: 0; width: 100%; background-color: #2c3a4f; border-radius: var(--border-radius); border: 1px solid var(--border-color); padding: 0.5rem; z-index: 20; visibility: hidden; opacity: 0; transform: translateY(10px); transition: all 0.3s ease; }
        .profile-dropdown.show { visibility: visible; opacity: 1; transform: translateY(0); }
        .profile-dropdown a { display: flex; gap: 0.75rem; padding: 0.75rem; color: var(--light-text-color); font-size: 0.85rem; border-radius: 6px; }
        .profile-dropdown a:hover { background-color: var(--glass-background); color: var(--text-color); }

        /* --- Conteúdo Principal --- */
        .main-content { margin-left: var(--sidebar-width); flex-grow: 1; padding: 2rem 2.5rem; min-height: 100vh; overflow-y: auto; transition: margin-left 0.3s ease, width 0.3s ease; width: calc(100% - var(--sidebar-width)); }
        .content-header { margin-bottom: 2rem; background: var(--glass-background); padding: 1.5rem 2rem; border-radius: var(--border-radius); border: 1px solid var(--border-color); backdrop-filter: blur(5px); }
        .content-header h1 { font-size: 1.8rem; font-weight: 600; color: var(--primary-color); margin: 0 0 0.25rem 0; }
        .content-header p { font-size: 1rem; color: var(--light-text-color); margin: 0; }

        /* Estilos do Dashboard (7 Cartões) */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }
        .stat-card {
            background: var(--glass-background);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            box-shadow: var(--box-shadow);
            text-align: left;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .stat-card-header h4 { font-size: 1rem; color: var(--light-text-color); margin: 0; font-weight: 500; }
        .stat-card-header .icon {
            font-size: 1.5rem;
            color: var(--primary-color);
        }
        .stat-card .value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-color);
            line-height: 1.2;
        }
        .stat-card .card-footer {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }
        .stat-card .card-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9em;
            font-weight: 500;
        }
        .stat-card .card-footer a:hover {
            text-decoration: underline;
        }

        /* Card de Destaque */
        .stat-card.highlight {
            border-color: var(--warning-text);
            background: var(--warning-bg);
        }
        .stat-card.highlight h4 { color: var(--warning-text); }
        .stat-card.highlight .value { color: var(--warning-text); }
        .stat-card.highlight .icon { color: var(--warning-text); }
        .stat-card.highlight .card-footer a { color: var(--warning-text); }

        .stat-card.success .value,
        .stat-card.success .icon {
            color: var(--success-text);
        }

        /* ==========================================================
           CSS: Funil de Vendas (Trazido do pedidos.php)
           ========================================================== */
        .funnel-report-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 2.5rem;
            margin-top: 2.5rem;
            background: var(--glass-background);
            padding: 1.5rem 2rem;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            box-shadow: var(--box-shadow);
        }
        .funnel-visual-container {
            flex: 1;
            min-width: 350px;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            padding-top: 50px;
        }
        .funnel-stage-visual {
            width: 90%; max-width: 600px; height: 60px; margin-top: -10px; position: relative;
            display: flex; justify-content: center; align-items: center;
            color: var(--text-color); font-weight: 600; font-size: 1rem;
            padding: 0 1rem; text-align: center; cursor: default;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 2px solid rgba(255, 255, 255, 0.1);
            border-right: 2px solid rgba(255, 255, 255, 0.1);
            clip-path: polygon(0% 0%, 100% 0%, 90% 100%, 10% 100%);
            background-color: var(--glass-background);
        }

        /* Cores das 5 Fatias */
        .funnel-stage-visual:nth-child(1) { width: 95%; max-width: 700px; background-color: rgba(40, 167, 69, 0.8); z-index: 5;} /* Aprovados (Verde) */
        .funnel-stage-visual:nth-child(2) { width: 85%; max-width: 600px; background-color: rgba(23, 162, 184, 0.8); z-index: 4;} /* Transporte (Azul) */
        .funnel-stage-visual:nth-child(3) { width: 75%; max-width: 500px; background-color: rgba(255, 193, 7, 0.8); z-index: 3;} /* Pendentes (Amarelo) */
        .funnel-stage-visual:nth-child(5) { width: 55%; max-width: 300px; background-color: rgba(220, 53, 69, 0.8); z-index: 1;} /* Cancelados (Vermelho) */

        .funnel-stage-visual:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3); z-index: 6; }

        /* NOVO: Estilo para o Bloco de Faturamento (Posição 4) */
        .funnel-stage-visual.revenue-stage {
            width: 65%; max-width: 400px;
            height: auto;
            min-height: 110px;
            padding-top: 0.75rem;
            padding-bottom: 0.75rem;
            align-items: center;
            background-color: rgba(0, 150, 136, 0.8) !important;
            z-index: 2;
        }

        .revenue-details {
            display: flex;
            flex-direction: column;
            text-align: left;
            font-size: 0.8em;
            line-height: 1.6;
            width: 100%;
            padding-left: 25%;
        }

        .revenue-details span { white-space: nowrap; }
        .revenue-details strong { color: var(--text-color); font-weight: 700; width: 60px; display: inline-block; }

        .funnel-label-top {
            position: absolute; top: -30px; font-size: 0.9em; font-weight: 500;
            color: var(--light-text-color); text-transform: uppercase; white-space: nowrap;
        }
        .funnel-stage-visual:nth-child(1) .funnel-label-top { color: var(--status-aprovado); }
        .funnel-stage-visual:nth-child(2) .funnel-label-top { color: var(--status-processando); }
        .funnel-stage-visual:nth-child(3) .funnel-label-top { color: var(--status-pendente); }
        .funnel-stage-visual.revenue-stage .funnel-label-top { color: var(--status-faturamento); }
        .funnel-stage-visual:nth-child(5) .funnel-label-top { color: var(--status-cancelado); }
        .funnel-value { font-size: 1.5rem; font-weight: 700; margin-right: 10px; }

        /* --- ESTILIZAÇÃO DO CARD DE LEGENDA (CORREÇÃO DE LAYOUT) --- */
        .funnel-legend {
            flex: 1;
            min-width: 300px;
            /* Estilos de Card: */
            background-color: var(--sidebar-color);
            border: 1px solid #000; /* Borda preta pedida */
            border-radius: var(--border-radius);
            padding: 1.5rem;
        }

        .funnel-legend h4 {
            color: var(--primary-color);
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.5rem;
            font-weight: 600;
        }
        .funnel-legend ul {
            list-style: none;
            padding-left: 0;
        }
        /* CORREÇÃO: Envolvemos o texto em um <div> no HTML, e este CSS o ajusta */
        .funnel-legend ul li {
            margin-bottom: 1rem;
            font-size: 0.9em;
            color: var(--light-text-color);
            display: flex;
            align-items: flex-start;
            line-height: 1.5;
        }
        .funnel-legend ul li > div {
            flex: 1; /* Permite que o texto preencha o espaço sem quebra desnecessária */
        }
        .funnel-legend ul li strong {
            color: var(--text-color);
            display: block;
        }

        .legend-dot { width: 10px; height: 10px; border-radius: 50%; margin-right: 10px; flex-shrink: 0; margin-top: 5px; }
        .legend-dot.aprovado { background-color: rgba(40, 167, 69, 1); }
        .legend-dot.transporte { background-color: rgba(23, 162, 184, 1); }
        .legend-dot.pendente { background-color: rgba(255, 193, 7, 1); }
        .legend-dot.faturamento { background-color: rgba(0, 150, 136, 1); }
        .legend-dot.cancelado { background-color: rgba(220, 53, 69, 1); }

        /* --- Mobile / Responsivo --- */
        .menu-toggle { display: none; position: fixed; top: 1rem; left: 1rem; z-index: 1003; cursor: pointer; padding: 8px; background-color: var(--sidebar-color); border-radius: var(--border-radius); border: 1px solid var(--border-color); box-shadow: var(--box-shadow);}
        .menu-toggle svg { width: 20px; height: 20px; color: var(--text-color); display: block; }

        @media (max-width: 1024px) {
            /* Responsividade geral */
            body { position: relative; }
            .sidebar { width: 280px; transform: translateX(-280px); box-shadow: 5px 0 15px rgba(0, 0, 0, 0.5); z-index: 1002; height: 100%; overflow-y: auto; }
            .menu-toggle { display: flex; }
            .main-content { margin-left: 0; width: 100%; padding: 1.5rem; padding-top: 5rem; min-width: unset; }
            body.sidebar-open .sidebar { transform: translateX(0); }
            body.sidebar-open::after { content: ''; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.7); z-index: 1001; backdrop-filter: blur(2px);}

            /* Funil Responsivo (Vira Coluna) */
            .funnel-report-wrapper { flex-direction: column; gap: 1rem; padding: 1rem 1.5rem;}
            .funnel-visual-container { flex-basis: 100%; min-width: unset; padding-top: 0; }
            .funnel-legend { flex-basis: 100%; padding: 0.5rem; }

            /* Transforma fatias em blocos retangulares em mobile */
            .funnel-stage-visual {
                clip-path: none;
                width: 100% !important;
                max-width: 100% !important;
                margin-top: 10px;
                height: auto;
                min-height: 50px;
                border-radius: var(--border-radius);
                border: 1px solid var(--border-color);
                justify-content: space-between;
                padding: 0.75rem 1rem;
            }
            .funnel-stage-visual:first-child { margin-top: 0; }
            .funnel-label-top { display: none; }
            .funnel-stage-visual::before {
                content: attr(data-label);
                font-size: 0.85em;
                font-weight: 500;
                color: var(--text-color);
                text-transform: uppercase;
            }
            .funnel-stage-visual .funnel-value { font-size: 1.2rem; margin-right: 0; }
            .funnel-stage-visual > div { display: flex; align-items: center; }
            .funnel-stage-visual > div > span:last-child { margin-left: 5px; font-size: 0.8em; }

            /* Ajuste responsivo do bloco de faturamento */
            .funnel-stage-visual.revenue-stage {
                padding: 1rem 1.5rem;
                justify-content: space-between;
                min-height: 0;
            }
            .revenue-details {
                text-align: right;
                padding-left: 0;
                font-size: 0.8em;
            }
            .revenue-details strong {
                 width: auto;
            }
            .funnel-stage-visual.revenue-stage::before {
                content: attr(data-label);
            }
        }

        @media (max-width: 576px) {
            .main-content { padding: 1rem; padding-top: 4.5rem; }
            .content-header { padding: 1rem 1.5rem;}
            .content-header h1 { font-size: 1.5rem; }
            .content-header p { font-size: 0.9rem;}
        }
    </style>
</head>
<body>

    <div class="menu-toggle" id="menu-toggle">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
        </svg>
    </div>

    <div id="particles-js"></div>

    <?php include 'admin_sidebar.php'; ?>

    <main class="main-content">
        <div class="content-header">
            <h1>Dashboard</h1>
            <p>Visão geral e resumo das atividades da loja.</p>
        </div>

        <div class="dashboard-grid">

            <div class="stat-card <?php echo ($total_pedidos_pendentes > 0) ? 'highlight' : ''; ?>">
                <div class="stat-card-header">
                    <h4>Pedidos Pendentes</h4>
                    <span class="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-hourglass-split" viewBox="0 0 16 16">
                            <path d="M2.5 15a.5.5 0 1 1 0-1h1v-1a4.5 4.5 0 0 1 2.557-4.06c.29-.139.443-.377.443-.59v-.7c0-.213-.154-.451-.443-.59A4.5 4.5 0 0 1 3.5 3V2h-1a.5.5 0 0 1 0-1h11a.5.5 0 0 1 0 1h-1v1a4.5 4.5 0 0 1-2.557 4.06c-.29.139-.443.377-.443.59v.7c0 .213.154.451.443.59A4.5 4.5 0 0 1 12.5 13v1h1a.5.5 0 0 1 0 1zm2-13v1c0 .537.12 1.045.337 1.5h6.326c.216-.455.337-.963.337-1.5V2zm3 6.35c0 .701-.478 1.236-1.011 1.492A3.5 3.5 0 0 0 4.5 13s.866-1.299 3-1.48zm1 0v3.17c2.134.181 3 1.48 3 1.48a3.5 3.5 0 0 0-1.989-3.158C8.978 9.586 8.5 9.052 8.5 8.351z"></path>
                        </svg>
                    </span>
                </div>
                <div class="value"><?php echo $total_pedidos_pendentes; ?></div>
                <div class="card-footer">
                    <a href="pedidos.php">Gerenciar Pedidos &rarr;</a>
                </div>
            </div>

            <div class="stat-card success">
                <div class="stat-card-header">
                    <h4>Faturamento do Dia</h4>
                    <span class="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-coin" viewBox="0 0 16 16">
                            <path d="M5.5 9.511c.076.954.83 1.697 2.182 1.785V12h.6v-.709c1.4-.098 2.218-.846 2.218-1.932 0-.987-.626-1.496-1.745-1.76l-.473-.112V5.57c.6.068.982.396 1.074.85h1.052c-.076-.919-.864-1.638-2.126-1.716V4h-.6v.719c-1.195.117-2.01.836-2.01 1.853 0 .9.606 1.472 1.613 1.707l.397.098v2.034c-.615-.093-1.022-.43-1.114-.9zm2.177-2.166c-.59-.137-.91-.416-.91-.836 0-.47.345-.822.915-.925v1.76h-.005zm.692 1.193c.717.166 1.048.435 1.048.91 0 .542-.412.914-1.135.982V8.518z"/>
                            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
                            <path d="M8 13.5a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11m0 .5A6 6 0 1 0 8 2a6 6 0 0 0 0 12"/>
                        </svg>
                    </span>
                </div>
                <div class="value"><?php echo formatCurrency($faturamento_hoje); ?></div>
                <div class="card-footer">
                    <a href="pedidos.php">Ver Relatório de Vendas &rarr;</a>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <h4>Total de Usuários</h4>
                    <span class="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-person-fill-add" viewBox="0 0 16 16">
                            <path d="M12.5 16a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7m.5-5v1h1a.5.5 0 0 1 0 1h-1v1a.5.5 0 0 1-1 0v-1h-1a.5.5 0 0 1 0-1h1v-1a.5.5 0 0 1 1 0m-2-6a3 3 0 1 1-6 0 3 3 0 0 1 6 0"/>
                            <path d="M2 13c0 1 1 1 1 1h5.256A4.5 4.5 0 0 1 8 12.5a4.5 4.5 0 0 1 1.544-3.393Q8.844 9.002 8 9c-5 0-6 3-6 4"/>
                        </svg>
                    </span>
                </div>
                <div class="value"><?php echo $total_usuarios; ?></div>
                <div class="card-footer">
                    <a href="usuarios.php">Gerenciar Usuários &rarr;</a>
                </div>
            </div>

            <div class="stat-card <?php echo ($estoque_baixo > 0) ? 'highlight' : ''; ?>">
                <div class="stat-card-header">
                    <h4>Produtos c/ Estoque Baixo</h4>
                    <span class="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-cart-x" viewBox="0 0 16 16">
                            <path d="M7.354 5.646a.5.5 0 1 0-.708.708L7.793 7.5 6.646 8.646a.5.5 0 1 0 .708.708L8.5 8.207l1.146 1.147a.5.5 0 0 0 .708-.708L9.207 7.5l1.147-1.146a.5.5 0 0 0-.708-.708L8.5 6.793z"></path>
                            <path d="M.5 1a.5.5 0 0 0 0 1h1.11l.401 1.607 1.498 7.985A.5.5 0 0 0 4 12h1a2 2 0 1 0 0 4 2 2 0 0 0 0-4h7a2 2 0 1 0 0 4 2 2 0 0 0 0-4h1a.5.5 0 0 0 .491-.408l1.5-8A.5.5 0 0 0 14.5 3H2.89l-.405-1.621A.5.5 0 0 0 2 1zm3.915 10L3.102 4h10.796l-1.313 7zM6 14a1 1 0 1 1-2 0 1 1 0 0 1 2 0m7 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0"></path>
                        </svg>
                    </span>
                </div>
                <div class="value"><?php echo $estoque_baixo; ?></div>
                <div class="card-footer">
                    <a href="produtos.php#tab-lista">Ajustar Estoque &rarr;</a>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <h4>Tickets de Suporte Abertos</h4>
                    <span class="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-chat-text-fill" viewBox="0 0 16 16">
                            <path d="M16 8c0 3.866-3.582 7-8 7a9 9 0 0 1-2.347-.306c-.584.296-1.925.864-4.181 1.234-.2.032-.352-.176-.273-.362.354-.836.674-1.95.77-2.966C.744 11.37 0 9.76 0 8c0-3.866 3.582-7 8-7s8 3.134 8 7M4.5 5a.5.5 0 0 0 0 1h7a.5.5 0 0 0 0-1zm0 2.5a.5.5 0 0 0 0 1h7a.5.5 0 0 0 0-1zm0 2.5a.5.5 0 0 0 0 1h4a.5.5 0 0 0 0-1z"></path>
                        </svg>
                    </span>
                </div>
                <div class="value"><?php echo $tickets_abertos; ?></div>
                <div class="card-footer">
                    <a href="tickets.php">Gerenciar Tickets &rarr;</a>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <h4>Avaliação Média (5)</h4>
                    <span class="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-star" viewBox="0 0 16 16">
                            <path d="M2.866 14.85c-.078.444.36.791.746.593l4.39-2.256 4.389 2.256c.386.198.824-.149.746-.592l-.83-4.73 3.522-3.356c.33-.314.16-.888-.282-.95l-4.898-.696L8.465.792a.513.513 0 0 0-.927 0L5.354 5.12l-4.898.696c-.441.062-.612.636-.283.95l3.523 3.356-.83 4.73zm4.905-2.767-3.686 1.894.694-3.957a.56.56 0 0 0-.163-.505L1.71 6.745l4.052-.576a.53.53 0 0 0 .393-.288L8 2.223l1.847 3.658a.53.53 0 0 0 .393.288l4.052.575-2.906 2.77a.56.56 0 0 0-.163.506l.694 3.957-3.686-1.894a.5.5 0 0 0-.461 0z"/>
                        </svg>
                    </span>
                </div>
                <div class="value"><?php echo ($media_avaliacoes !== 'Erro') ? number_format($media_avaliacoes, 1, '.', '') : $media_avaliacoes; ?></div>
                <div class="card-footer">
                    <a href="avaliacoes.php#tab-lista">Moderar Avaliações &rarr;</a>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <h4>Produtos em Destaque</h4>
                    <span class="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-cart-plus-fill" viewBox="0 0 16 16">
                            <path d="M.5 1a.5.5 0 0 0 0 1h1.11l.401 1.607 1.498 7.985A.5.5 0 0 0 4 12h1a2 2 0 1 0 0 4 2 2 0 0 0 0-4h7a2 2 0 1 0 0 4 2 2 0 0 0 0-4h1a.5.5 0 0 0 .491-.408l1.5-8A.5.5 0 0 0 14.5 3H2.89l-.405-1.621A.5.5 0 0 0 2 1zM6 14a1 1 0 1 1-2 0 1 1 0 0 1 2 0m7 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0M9 5.5V7h1.5a.5.5 0 0 1 0 1H9v1.5a.5.5 0 0 1-1 0V8H6.5a.5.5 0 0 1 0-1H8V5.5a.5.5 0 0 1 1 0"></path>
                        </svg>
                    </span>
                </div>
                <div class="value"><?php echo $produtos_em_destaque; ?></div>
                <div class="card-footer">
                    <a href="produtos.php#tab-produtos">Gerenciar Destaques &rarr;</a>
                </div>
            </div>
        </div>

        <div class="funnel-report-wrapper">

            <div class="funnel-visual-container">
                <div class="funnel-stage-visual" data-label="Aprovados/Entregues HOJE">
                    <span class="funnel-label-top">Aprovados/Entregues HOJE</span>
                    <div>
                        <span class="funnel-value"><?php echo htmlspecialchars($stats['aprovados_hoje'] ?? 0); ?></span>
                        <span style="font-size: 0.85em;">Pedidos</span>
                    </div>
                </div>

                <div class="funnel-stage-visual" data-label="Em Transporte">
                    <span class="funnel-label-top">Em Transporte</span>
                    <div>
                        <span class="funnel-value"><?php echo htmlspecialchars($stats['em_transporte'] ?? 0); ?></span>
                        <span style="font-size: 0.85em;">Pedidos</span>
                    </div>
                </div>

                <div class="funnel-stage-visual" data-label="Pendentes/Aguardando">
                    <span class="funnel-label-top">Pendentes / Aguardando</span>
                    <div>
                        <span class="funnel-value"><?php echo htmlspecialchars($stats['pendentes'] ?? 0); ?></span>
                        <span style="font-size: 0.85em;">Pedidos</span>
                    </div>
                </div>

                <div class="funnel-stage-visual revenue-stage" data-label="Faturamento (Pedidos Pagos)">
                    <span class="funnel-label-top">Faturamento (Pedidos Pagos)</span>
                    <div class="revenue-details">
                        <span><strong>Total:</strong> <?php echo formatCurrency($stats['faturamento_total'] ?? 0.00); ?></span>
                        <span><strong>Mês:</strong> <?php echo formatCurrency($stats['faturamento_mes'] ?? 0.00); ?></span>
                        <span><strong>Semana:</strong> <?php echo formatCurrency($stats['faturamento_semana'] ?? 0.00); ?></span>
                        <span><strong>Hoje:</strong> <?php echo formatCurrency($stats['faturamento_dia'] ?? 0.00); ?></span>
                    </div>
                </div>

                <div class="funnel-stage-visual" data-label="Cancelados">
                    <span class="funnel-label-top">Cancelados</span>
                    <div>
                        <span class="funnel-value"><?php echo htmlspecialchars($stats['cancelados'] ?? 0); ?></span>
                        <span style="font-size: 0.85em;">Pedidos</span>
                    </div>
                </div>
            </div>

            <div class="funnel-legend">
                <h4>Legenda do Funil de Pedidos</h4>
                <ul>
                    <li>
                        <span class="legend-dot aprovado"></span>
                        <div>
                            <strong>Aprovados/Entregues HOJE:</strong> Pedidos com status 'APROVADO' ou 'ENTREGUE' criados na data atual.
                        </div>
                    </li>
                    <li>
                        <span class="legend-dot transporte"></span>
                        <div>
                            <strong>Em Transporte:</strong> Pedidos que foram enviados e aguardam a entrega ao cliente.
                        </div>
                    </li>
                    <li>
                        <span class="legend-dot pendente"></span>
                        <div>
                            <strong>Pendentes / Aguardando:</strong> Pedidos que estão no início do processo (status 'PENDENTE' ou 'AGUARDANDO PAGAMENTO').
                        </div>
                    </li>
                    <li>
                        <span class="legend-dot faturamento"></span>
                        <div>
                            <strong>Faturamento (Pedidos Pagos):</strong> Resumo do faturamento (Total, Mês, Semana, Hoje) considerando pedidos com status 'APROVADO', 'PROCESSANDO', 'EM TRANSPORTE' ou 'ENTREGUE'.
                        </div>
                    </li>
                    <li>
                        <span class="legend-dot cancelado"></span>
                        <div>
                            <strong>Cancelados:</strong> Número total de pedidos cancelados.
                        </div>
                    </li>
                </ul>
            </div>

        </div>

    </main>

    <script>
        // Inicializa o fundo de partículas
        particlesJS('particles-js', {"particles":{"number":{"value":60,"density":{"enable":true,"value_area":800}},"color":{"value":"#4a69bd"},"shape":{"type":"circle"},"opacity":{"value":0.4,"random":false},"size":{"value":3,"random":true},"line_linked":{"enable":true,"distance":150,"color":"#ffffff","opacity":0.1,"width":1},"move":{"enable":true,"speed":1.5,"direction":"none","random":false,"straight":false,"out_mode":"out","bounce":false}},"interactivity":{"detect_on":"canvas","events":{"onhover":{"enable":true,"mode":"repulse"},"onclick":{"enable":true,"mode":"push"},"resize":true}},"retina_detect":true});

        // O SCRIPT 'DOMContentLoaded' foi removido daqui e está no admin_sidebar.php
    </script>
    </body>
</html>