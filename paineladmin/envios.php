<?php
// admin_panel/envios.php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

require_once '../config/db.php'; // Conexão com o DB
require_once '../funcoes.php'; // Para carregarConfigApi (usado pelo sidebar)

// Carrega as constantes (NOME_DA_LOJA, etc.) para o sidebar
if (isset($pdo)) {
    carregarConfigApi($pdo);
}

$message = '';
$message_type = ''; // success, error, info, warning

// --- INICIALIZAÇÃO DE VARIÁVEIS ---
$edit_envio = null;
$is_editing_envio = false;

// ==========================================================
// LÓGICA CRUD: REGRAS DE FRETE (INSERT/UPDATE/DELETE/EDIT)
// ==========================================================
// 1E. ADICIONAR ou ATUALIZAR Regra de Frete
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_envio'])) {
    $id = isset($_POST['envio_id']) ? (int)$_POST['envio_id'] : null;
    $nome = trim($_POST['nome']);
    $descricao = trim($_POST['descricao']);

    // Novos campos de localidade
    $uf = trim($_POST['uf'] ?? '');
    $cidade_ibge_id = trim($_POST['cidade_ibge_id'] ?? '');
    $cidade_nome = trim($_POST['cidade_nome'] ?? ''); // Vem do input hidden

    $custo_base = filter_var($_POST['custo_base'], FILTER_VALIDATE_FLOAT);
    $prazo_estimado_dias = filter_var($_POST['prazo_estimado_dias'], FILTER_VALIDATE_INT);
    $ativo = isset($_POST['ativo']);

    // Validações
    if (empty($nome) || $custo_base === false || $custo_base < 0) {
        $message = "Erro: Nome da Regra e Custo Base (>= 0) são obrigatórios.";
        $message_type = "error";
    } elseif ($prazo_estimado_dias === false || $prazo_estimado_dias < 0) {
        $message = "Erro: O Prazo Estimado (em dias) deve ser um número maior ou igual a zero.";
        $message_type = "error";
    } elseif (empty($uf)) {
         $message = "Erro: Você deve selecionar um Estado (UF).";
         $message_type = "error";
    } else {

        // Se 'cidade_ibge_id' for '0' ou vazio (opção "Todos os Municípios"), salvamos NULL
        $cidade_id_param = (!empty($cidade_ibge_id) && $cidade_ibge_id != '0') ? (int)$cidade_ibge_id : null;
        $cidade_nome_param = $cidade_id_param ? $cidade_nome : null;

        try {
            if ($id) { // UPDATE
                $sql = "UPDATE formas_envio SET
                            nome = :nome,
                            descricao = :descricao,
                            custo_base = :custo_base,
                            prazo_estimado_dias = :prazo,
                            uf = :uf,
                            cidade_nome = :cidade_nome,
                            cidade_ibge_id = :cidade_id,
                            ativo = :ativo
                        WHERE id = :id";
                $stmt = $pdo->prepare($sql);
            } else { // INSERT
                $sql = "INSERT INTO formas_envio (nome, descricao, custo_base, prazo_estimado_dias, uf, cidade_nome, cidade_ibge_id, ativo)
                        VALUES (:nome, :descricao, :custo_base, :prazo, :uf, :cidade_nome, :cidade_id, :ativo)";
                $stmt = $pdo->prepare($sql);
            }

            $stmt->bindParam(':nome', $nome, PDO::PARAM_STR);
            $stmt->bindParam(':descricao', $descricao, PDO::PARAM_STR);
            $stmt->bindParam(':custo_base', $custo_base);
            $stmt->bindParam(':prazo', $prazo_estimado_dias, PDO::PARAM_INT);
            $stmt->bindParam(':uf', $uf, PDO::PARAM_STR);
            $stmt->bindParam(':cidade_nome', $cidade_nome_param, PDO::PARAM_STR);
            $stmt->bindParam(':cidade_id', $cidade_id_param, PDO::PARAM_INT);
            $stmt->bindParam(':ativo', $ativo, PDO::PARAM_BOOL);
            if($id) $stmt->bindParam(':id', $id, PDO::PARAM_INT);

            $stmt->execute();
            $message = "Regra de frete salva com sucesso!"; $message_type = "success";

        } catch (PDOException $e) {
             if ($e->getCode() == '23505') { $message = "Erro: Já existe uma regra com este Nome."; }
             else { $message = "Erro ao salvar regra de frete: ". $e->getMessage(); }
             $message_type = "error";

             // Manter dados no formulário em caso de erro
             $edit_envio = [
                'id' => $id, 'nome' => $nome, 'descricao' => $descricao,
                'custo_base' => $custo_base, 'prazo_estimado_dias' => $prazo_estimado_dias,
                'uf' => $uf, 'cidade_ibge_id' => $cidade_ibge_id, 'cidade_nome' => $cidade_nome,
                'ativo' => $ativo
             ];
             $is_editing_envio = (bool)$id;
        }
    }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $message_type;
    header("Location: envios.php#form-envio"); exit;
}

// 2E. DELETAR Regra de Frete
if (isset($_GET['delete_envio'])) {
    $id = (int)$_GET['delete_envio'];
    try {
        $sql = "DELETE FROM formas_envio WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $message = "Regra de frete removida!"; $message_type = "success";
    } catch (PDOException $e) {
        if ($e->getCode() == '23503') { $message = "Erro: Não é possível remover esta regra de frete pois existem pedidos associados a ela."; }
        else { $message = "Erro ao remover regra de frete: " . $e->getMessage(); }
        $message_type = "error";
    }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $message_type;
    header("Location: envios.php"); exit;
}

// 3E. MODO DE EDIÇÃO (Carrega dados no formulário)
if (isset($_GET['edit_envio'])) {
    $id = (int)$_GET['edit_envio'];
    $is_editing_envio = true;
    $stmt = $pdo->prepare("SELECT * FROM formas_envio WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $edit_envio = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$edit_envio) {
        $is_editing_envio = false;
        $message = "Regra de frete não encontrada para edição.";
        $message_type = "warning";
    }
}

// ==========================================================
// LÓGICA DE LEITURA (PARA PREENCHER PÁGINA)
// ==========================================================

// Pega mensagens flash da sessão
if (isset($_SESSION['flash_message']) && empty($message)) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

try {
    // 1. Ler Regras de Frete Cadastradas
    // Ordena por UF e depois por Cidade
    $stmt_envio = $pdo->query("SELECT * FROM formas_envio ORDER BY uf, cidade_nome ASC");
    $all_envio = $stmt_envio->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    if (empty($message)) {
        $message .= " Erro ao carregar dados: " . $e->getMessage();
        $message_type = "error";
    }
    $all_envio = [];
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Regras de Frete - Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>

    <style>
         :root {
             --primary-color: #4a69bd; --secondary-color: #6a89cc; --text-color: #f9fafb;
             --light-text-color: #9ca3af; --border-color: rgba(255, 255, 255, 0.1); --background-color: #111827;
             --sidebar-color: #1f2937; --glass-background: rgba(31, 41, 55, 0.7);
             --success-bg: rgba(40, 167, 69, 0.3); --success-text: #c3e6cb;
             --error-bg: rgba(220, 53, 69, 0.3); --error-text: #f5c6cb;
             --info-bg: rgba(0, 123, 255, 0.2); --info-text: #bee5eb;
             --warning-bg: rgba(255, 193, 7, 0.2); --warning-text: #ffeeba;
             --danger-color: #e74c3c; --sidebar-width: 240px; --border-radius: 8px;
             --box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
         }
         * { margin: 0; padding: 0; box-sizing: border-box; }
         body { font-family: 'Poppins', sans-serif; background-color: var(--background-color); color: var(--text-color); display: flex; min-height: 100vh; overflow-x: hidden; line-height: 1.6;}
         #particles-js { position: fixed; width: 100%; height: 100%; top: 0; left: 0; z-index: -1; opacity: 0.6; }
         a { color: var(--primary-color); text-decoration: none; transition: color 0.2s ease;} a:hover { color: var(--secondary-color); text-decoration: underline;}

         /* --- Sidebar --- */
         .sidebar { width: var(--sidebar-width); background-color: var(--sidebar-color); height: 100vh; position: fixed; left: 0; top: 0; padding: 1.5rem; display: flex; flex-direction: column; border-right: 1px solid var(--border-color); z-index: 1000; transition: transform 0.3s ease; box-shadow: var(--box-shadow); }
         .sidebar .logo-area { display: flex; flex-direction: column; align-items: center; margin-bottom: 1rem; } .sidebar .logo-circle { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 0.5rem; box-shadow: 0 0 10px rgba(74, 105, 189, 0.6); overflow: hidden; background-color: #fff; } .sidebar .logo-circle svg { color: var(--primary-color); width: 24px; height: 24px; } .sidebar .logo-text { font-size: 1rem; font-weight: 600; color: var(--text-color); text-align: center; } .sidebar .divider { width: 100%; height: 1px; background-color: var(--border-color); margin: 1rem 0; } .sidebar nav { flex-grow: 1; } .sidebar nav a { display: flex; align-items: center; gap: 0.8rem; padding: 0.8rem 1rem; color: var(--light-text-color); text-decoration: none; border-radius: var(--border-radius); margin-bottom: 0.5rem; transition: all 0.3s ease; border: 1px solid transparent; background-color: transparent; } .sidebar nav a:hover, .sidebar nav a.active { background-color: var(--glass-background); color: var(--text-color); border-color: var(--primary-color); box-shadow: 0 2px 8px rgba(74, 105, 189, 0.4); } .sidebar nav a svg { width: 20px; height: 20px; flex-shrink: 0; } .user-profile { position: relative; margin-top: auto; background-color: var(--glass-background); padding: 0.75rem; border-radius: var(--border-radius); display: flex; align-items: center; gap: 1rem; cursor: pointer; border: 1px solid var(--border-color); transition: all 0.3s ease; } .user-profile:hover { border-color: var(--primary-color); } .avatar { width: 35px; height: 35px; border-radius: 50%; background-color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.9rem; } .user-info .user-name { font-weight: 600; font-size: 0.85rem; line-height: 1.2; } .user-info .user-level { font-size: 0.7rem; color: var(--light-text-color); } .profile-dropdown { position: absolute; bottom: calc(100% + 10px); left: 0; width: 100%; background-color: #2c3a4f; border-radius: var(--border-radius); border: 1px solid var(--border-color); padding: 0.5rem; z-index: 20; visibility: hidden; opacity: 0; transform: translateY(10px); transition: all 0.3s ease; } .profile-dropdown.show { visibility: visible; opacity: 1; transform: translateY(0); } .profile-dropdown a { display: flex; gap: 0.75rem; padding: 0.75rem; color: var(--light-text-color); font-size: 0.85rem; border-radius: 6px; } .profile-dropdown a:hover { background-color: var(--glass-background); color: var(--text-color); }
         .sidebar nav .sidebar-submenu { padding-left: 20px; margin-top: -5px; margin-bottom: 5px; overflow: hidden; transition: max-height 0.3s ease-out; max-height: 0; } .sidebar nav .sidebar-submenu.open { max-height: 500px; } .sidebar nav a.has-children { display: flex; justify-content: space-between; align-items: center; } .sidebar nav a .menu-chevron { width: 16px; height: 16px; color: var(--light-text-color); transition: transform 0.3s ease; } .sidebar nav a.open .menu-chevron { transform: rotate(90deg); }

         /* --- Conteúdo Principal --- */
         .main-content { margin-left: var(--sidebar-width); flex-grow: 1; padding: 2rem 2.5rem; min-height: 100vh; overflow-y: auto; transition: margin-left 0.3s ease, width 0.3s ease; width: calc(100% - var(--sidebar-width)); }
         .content-header { margin-bottom: 2rem; background: var(--glass-background); padding: 1.5rem 2rem; border-radius: var(--border-radius); border: 1px solid var(--border-color); backdrop-filter: blur(5px); }
         .content-header h1 { font-size: 1.8rem; font-weight: 600; color: var(--primary-color); margin: 0 0 0.25rem 0; }
         .content-header p { font-size: 1rem; color: var(--light-text-color); margin: 0; }

         /* --- Seções CRUD --- */
         .crud-section { margin-bottom: 2.5rem; }
         .crud-section h3 { font-size: 1.25rem; color: var(--text-color); margin-bottom: 1.25rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color); font-weight: 600; }
         .form-container, .list-container { background: var(--glass-background); padding: 1.5rem 2rem; border-radius: var(--border-radius); border: 1px solid var(--border-color); box-shadow: var(--box-shadow); margin-bottom: 2rem; backdrop-filter: blur(5px); }
         .form-container h4 { font-size: 1.1rem; color: var(--primary-color); margin-bottom: 1.25rem; font-weight: 600; }

         /* --- Formulários --- */
         .form-group { margin-bottom: 1.25rem; }
         .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--light-text-color); font-size: 0.8rem; text-transform: uppercase; }
         .form-group input[type="text"], .form-group input[type="password"], .form-group input[type="number"], .form-group textarea, .form-group select { width: 100%; padding: 0.65rem 0.8rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); background-color: rgba(0, 0, 0, 0.3); color: var(--text-color); box-sizing: border-box; transition: border-color 0.3s ease, box-shadow 0.3s ease; font-size: 0.9em; }
         .form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color: var(--primary-color); box-shadow: 0 0 8px rgba(74, 105, 189, 0.5); outline: none; }
         .form-group textarea { min-height: 80px; resize: vertical; }
         .form-group small { font-size: 0.8em; color: var(--light-text-color); display: block; margin-top: 0.3rem; }
         .form-group-check { display: flex; align-items: center; padding-top: 0; margin-bottom: 0.5rem; }
         .form-group-check label { font-weight: 400; color: var(--text-color); display: inline; margin-left: 0.5rem; text-transform: none; }
         .form-group-check input[type="checkbox"] { width: auto; vertical-align: middle; accent-color: var(--primary-color); cursor: pointer;}
         .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem 1.5rem; }
         .localidade-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 1rem 1.5rem; } /* Grid para UF e Cidade */

         button[type="submit"] { padding: 0.65rem 1.2rem; background-color: var(--primary-color); color: #fff; border: none; border-radius: var(--border-radius); cursor: pointer; font-weight: 600; transition: background-color 0.3s ease, transform 0.1s ease; font-size: 0.9em; }
         button[type="submit"]:hover { background-color: var(--secondary-color); transform: translateY(-1px); }
         button[type="submit"].update { background-color: #28a745; }
         button[type="submit"].update:hover { background-color: #218838; }
         button:disabled, .form-group select:disabled { background-color: rgba(0,0,0,0.2); cursor: not-allowed; opacity: 0.6; }
         .form-container a.cancel { color: var(--light-text-color); margin-left: 1rem; font-size: 0.9em; }
         .form-container a.cancel:hover { text-decoration: underline; }
         .loading-spinner { display: inline-block; border: 3px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: var(--primary-color); width: 16px; height: 16px; animation: spin 1s ease-in-out infinite; margin-left: 10px; vertical-align: middle; }
         @keyframes spin { to { transform: rotate(360deg); } }

         /* --- Tabelas --- */
         .list-container { overflow-x: auto; }
         .list-container table { width: 100%; border-collapse: collapse; background-color: transparent; border-radius: 0; overflow: hidden; font-size: 0.85em; border: none; min-width: 600px; }
         .list-container th, .list-container td { border-bottom: 1px solid var(--border-color); padding: 10px 12px; text-align: left; vertical-align: middle; }
         .list-container th { background-color: rgba(0, 0, 0, 0.4); color: var(--text-color); font-weight: 600; text-transform: uppercase; font-size: 0.75em; white-space: nowrap; }
         .list-container tbody tr:last-child td { border-bottom: none; }
         .list-container tbody tr:hover { background-color: rgba(255, 255, 255, 0.05); }
         .list-container td.actions { white-space: nowrap; text-align: right; }
         .list-container .actions a { color: var(--primary-color); margin-left: 1rem; font-size: 0.85em; transition: color 0.2s ease; }
         .list-container .actions a:hover { color: var(--secondary-color); }
         .list-container .actions a.delete { color: var(--danger-color); }
         .list-container .actions a.delete:hover { color: #c0392b; }
         .status-ativo { color: #82e0aa; font-weight: bold; }
         .status-inativo { color: var(--danger-color); font-weight: bold; }
         .localidade-info { font-size: 0.9em; }
         .localidade-info .estado { font-weight: 600; }
         .localidade-info .cidade { color: var(--light-text-color); display: block; font-size: 0.9em; }


         /* --- Mensagens --- */
         .message { padding: 1rem; border-radius: var(--border-radius); margin-bottom: 1.5rem; font-weight: 600; border: 1px solid transparent; backdrop-filter: blur(3px); }
         .message.success { background-color: var(--success-bg); color: var(--success-text); border-color: rgba(21, 87, 36, 0.5); }
         .message.error { background-color: var(--error-bg); color: var(--error-text); border-color: rgba(114, 28, 36, 0.5); }
         .message.info { background-color: var(--info-bg); color: var(--info-text); border-color: rgba(8, 66, 152, 0.5); }
         .message.warning { background-color: var(--warning-bg); color: var(--warning-text); border-color: rgba(255,193,7,0.5); }

         /* --- Mobile / Responsivo --- */
         .menu-toggle { display: none; position: fixed; top: 1rem; left: 1rem; z-index: 1003; cursor: pointer; padding: 8px; background-color: var(--sidebar-color); border-radius: var(--border-radius); border: 1px solid var(--border-color); box-shadow: var(--box-shadow);}
         .menu-toggle svg { width: 20px; height: 20px; color: var(--text-color); display: block; }
         @media (max-width: 1024px) {
             body { position: relative; }
             .sidebar { width: 280px; transform: translateX(-280px); box-shadow: 5px 0 15px rgba(0, 0, 0, 0.5); z-index: 1002; height: 100%; overflow-y: auto; }
             .menu-toggle { display: flex; }
             .main-content { margin-left: 0; width: 100%; padding: 1.5rem; padding-top: 5rem; }
             body.sidebar-open .sidebar { transform: translateX(0); }
             body.sidebar-open::after { content: ''; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.7); z-index: 1001; backdrop-filter: blur(2px);}
         }
         @media (max-width: 768px) {
             .form-grid { grid-template-columns: 1fr; }
             .localidade-grid { grid-template-columns: 1fr; } /* UF e Cidade um embaixo do outro */

             /* Tabela vira cards */
             .list-container table { border: none; min-width: auto; display: block; }
             .list-container thead { display: none; }
             .list-container tr { display: block; margin-bottom: 1rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 1rem; background: rgba(0,0,0,0.1); }
             .list-container td { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: none; text-align: right; }
             .list-container td::before { content: attr(data-label); font-weight: 600; color: var(--light-text-color); text-align: left; margin-right: 1rem; flex-basis: 40%;}
             .list-container td.actions { justify-content: flex-end; }
             .list-container td.actions::before { display: none; }
         }
         @media (max-width: 576px) {
             .main-content { padding: 1rem; padding-top: 4.5rem; }
             .content-header { padding: 1rem 1.5rem;}
             .content-header h1 { font-size: 1.5rem; }
             .content-header p { font-size: 0.9rem;}
             .form-container, .list-container { padding: 1rem 1.5rem;}
             .crud-section h3 { font-size: 1.1rem;}
             .form-container h4 { font-size: 1rem;}
             .list-container td, .list-container td::before { font-size: 0.8em; }
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
            <h1>Gerenciar Regras de Frete</h1>
            <p>Adicione regras de frete baseadas em localidade (Estado/Município).</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo nl2br(htmlspecialchars($message)); ?>
            </div>
        <?php endif; ?>

        <div class="tab-content-wrapper">

            <div class="tab-pane active" id="tab-metodos">
            <div class="crud-section" id="form-envio">
                    <h3><?php echo $is_editing_envio ? 'Editar Regra de Frete' : 'Adicionar Nova Regra de Frete'; ?></h3>
                    <div class="form-container">
                        <form action="envios.php#form-envio" method="POST">
                            <input type="hidden" name="envio_id" value="<?php echo htmlspecialchars($edit_envio['id'] ?? ''); ?>">
                            <input type="hidden" id="cidade_nome_hidden" name="cidade_nome" value="<?php echo htmlspecialchars($edit_envio['cidade_nome'] ?? ''); ?>">

                            <div class="form-group">
                                <label for="nome_envio">Nome da Regra (Ex: Sedex - SP Capital, PAC - RJ)</label>
                                <input type="text" id="nome_envio" name="nome" value="<?php echo htmlspecialchars($edit_envio['nome'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="descricao_envio">Descrição (Ex: Entrega em até 3 dias úteis)</label>
                                <textarea id="descricao_envio" name="descricao"><?php echo htmlspecialchars($edit_envio['descricao'] ?? ''); ?></textarea>
                                <small>Este texto aparecerá na página de Prazos de Entrega.</small>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="custo_base">Custo Fixo (R$)</label>
                                    <input type="number" id="custo_base" name="custo_base" step="0.01" min="0" value="<?php echo htmlspecialchars($edit_envio['custo_base'] ?? '0.00'); ?>" required placeholder="10.50">
                                </div>
                                <div class="form-group">
                                    <label for="prazo_estimado_dias">Prazo Estimado (em dias)</label>
                                    <input type="number" id="prazo_estimado_dias" name="prazo_estimado_dias" min="0" value="<?php echo htmlspecialchars($edit_envio['prazo_estimado_dias'] ?? '5'); ?>" required>
                                </div>
                            </div>

                            <h4>Região de Atendimento</h4>
                            <div class="localidade-grid">
                                <div class="form-group">
                                    <label for="select-estado">Estado (UF)</label>
                                    <select id="select-estado" name="uf" required>
                                        <option value="">Carregando...</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="select-cidade">Município <span id="cidade-loader" class="loading-spinner" style="display: none;"></span></label>
                                    <select id="select-cidade" name="cidade_ibge_id" disabled>
                                        <option value="0">-- Selecione um estado primeiro --</option>
                                    </select>
                                    <small>Selecione "Todos os Municípios" para aplicar a regra ao estado inteiro.</small>
                                </div>
                            </div>

                            <div class="form-group-check" style="padding-top: 1rem;">
                               <input type="checkbox" id="ativo_envio" name="ativo" value="1"
                                       <?php
                                            $isChecked = ($is_editing_envio && !empty($edit_envio['ativo'])) || !$is_editing_envio;
                                            echo $isChecked ? 'checked' : '';
                                        ?>>
                               <label for="ativo_envio">Ativo (Disponível no checkout)</label>
                            </div>

                            <button type="submit" name="salvar_envio" class="<?php echo $is_editing_envio ? 'update' : ''; ?>">
                                <?php echo $is_editing_envio ? 'Salvar Alterações' : 'Adicionar Regra de Frete'; ?>
                            </button>
                            <?php if ($is_editing_envio): ?>
                                <a href="envios.php" class="cancel">Cancelar Edição</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="crud-section" id="list-envio">
                    <h3>Regras de Frete Cadastradas</h3>
                    <div class="list-container">
                        <?php if (empty($all_envio)): ?>
                            <p style="text-align: center; color: var(--light-text-color); padding: 1rem 0;">Nenhuma regra de frete cadastrada.</p>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nome da Regra</th>
                                        <th>Localidade</th>
                                        <th>Custo (R$)</th>
                                        <th>Prazo</th>
                                        <th>Status</th>
                                        <th class="actions">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_envio as $envio): ?>
                                        <tr>
                                            <td data-label="Nome">
                                                <?php echo htmlspecialchars($envio['nome']); ?>
                                            </td>
                                            <td data-label="Localidade" class="localidade-info">
                                                <span class="estado"><?php echo htmlspecialchars($envio['uf']); ?></span>
                                                <span class="cidade">
                                                    <?php echo htmlspecialchars($envio['cidade_nome'] ?? 'Todos os Municípios'); ?>
                                                </span>
                                            </td>
                                            <td data-label="Custo (R$)">R$ <?php echo number_format($envio['custo_base'], 2, ',', '.'); ?></td>
                                            <td data-label="Prazo"><?php echo htmlspecialchars($envio['prazo_estimado_dias']); ?> dias</td>
                                            <td data-label="Status">
                                                <?php if ($envio['ativo']): ?>
                                                    <span class="status-ativo">Ativo</span>
                                                <?php else: ?>
                                                    <span class="status-inativo">Inativo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="actions">
                                                <a href="envios.php?edit_envio=<?php echo $envio['id']; ?>#form-envio">Editar</a>
                                                <a href="envios.php?delete_envio=<?php echo $envio['id']; ?>" onclick="return confirm('Tem certeza? Esta ação não pode ser desfeita.');" class="delete">Remover</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </main>
    <script>
        // --- JavaScript para Partículas ---
        particlesJS('particles-js', {"particles":{"number":{"value":60,"density":{"enable":true,"value_area":800}},"color":{"value":"#4a69bd"},"shape":{"type":"circle"},"opacity":{"value":0.4,"random":false},"size":{"value":3,"random":true},"line_linked":{"enable":true,"distance":150,"color":"#ffffff","opacity":0.1,"width":1},"move":{"enable":true,"speed":1.5,"direction":"none","random":false,"straight":false,"out_mode":"out","bounce":false}},"interactivity":{"detect_on":"canvas","events":{"onhover":{"enable":true,"mode":"repulse"},"onclick":{"enable":true,"mode":"push"},"resize":true}},"retina_detect":true});

        // --- JavaScript Específico da Página ---
        document.addEventListener('DOMContentLoaded', () => {

            // --- Lógica para Ancoragem (scrollar para o formulário se estiver editando) ---
            function scrollToHash(hash) {
                if (hash && hash !== '#') {
                    const targetElement = document.querySelector(hash);
                    if (targetElement) {
                         setTimeout(() => {
                            const headerElement = targetElement.querySelector('h3') || targetElement.querySelector('h4') || targetElement;
                            if(headerElement) {
                                headerElement.scrollIntoView({ behavior: 'smooth', block: 'start', inline: 'nearest' });
                            }
                        }, 150);
                    }
                }
            }

            // Ativa o scroll no carregamento da página se houver hash
            scrollToHash(window.location.hash);


            // ==========================================================
            // LÓGICA DA API IBGE (ESTADOS E MUNICÍPIOS)
            // ==========================================================

            const estadoSelect = document.getElementById('select-estado');
            const cidadeSelect = document.getElementById('select-cidade');
            const cidadeLoader = document.getElementById('cidade-loader');
            const cidadeNomeHidden = document.getElementById('cidade_nome_hidden');

            // --- Pega os valores do PHP (se estiver editando) ---
            const editUF = '<?php echo $edit_envio['uf'] ?? ''; ?>';
            const editCidadeID = '<?php echo $edit_envio['cidade_ibge_id'] ?? '0'; ?>';

            /**
             * Carrega todos os estados (UF) da API do IBGE
             */
            function loadEstados() {
                return fetch('https://servicodados.ibge.gov.br/api/v1/localidades/estados?orderBy=nome')
                    .then(response => response.json())
                    .then(estados => {
                        estadoSelect.innerHTML = '<option value="">-- Selecione o Estado --</option>';
                        estados.forEach(estado => {
                            const option = document.createElement('option');
                            option.value = estado.sigla;
                            option.textContent = estado.nome;
                            estadoSelect.appendChild(option);
                        });
                    })
                    .catch(error => {
                        console.error('Erro ao carregar estados:', error);
                        estadoSelect.innerHTML = '<option value="">Erro ao carregar UFs</option>';
                    });
            }

            /**
             * Carrega os municípios de um estado (UF) específico
             */
            function loadCidades(uf) {
                if (!uf) {
                    cidadeSelect.innerHTML = '<option value="0">-- Selecione um estado primeiro --</option>';
                    cidadeSelect.disabled = true;
                    return Promise.resolve(); // Retorna uma promessa resolvida
                }

                cidadeSelect.disabled = true;
                cidadeLoader.style.display = 'inline-block';
                cidadeSelect.innerHTML = '<option value="">Carregando...</option>';

                return fetch(`https://servicodados.ibge.gov.br/api/v1/localidades/estados/${uf}/municipios?orderBy=nome`)
                    .then(response => response.json())
                    .then(cidades => {
                        cidadeSelect.innerHTML = '<option value="0">-- Todos os Municípios --</option>';
                        cidades.forEach(cidade => {
                            const option = document.createElement('option');
                            option.value = cidade.id; // Salva o ID do IBGE
                            option.textContent = cidade.nome;
                            option.setAttribute('data-nome', cidade.nome); // Guarda o nome
                            cidadeSelect.appendChild(option);
                        });
                        cidadeSelect.disabled = false;
                        cidadeLoader.style.display = 'none';
                    })
                    .catch(error => {
                        console.error('Erro ao carregar municípios:', error);
                        cidadeSelect.innerHTML = '<option value="0">Erro ao carregar</option>';
                        cidadeSelect.disabled = false;
                        cidadeLoader.style.display = 'none';
                    });
            }

            // --- Event Listeners ---

            // Quando o usuário troca o estado
            estadoSelect.addEventListener('change', () => {
                loadCidades(estadoSelect.value);
                // Limpa o nome da cidade hidden, pois o usuário trocou o estado
                cidadeNomeHidden.value = '';
            });

            // Quando o usuário troca a cidade
            cidadeSelect.addEventListener('change', () => {
                const selectedOption = cidadeSelect.options[cidadeSelect.selectedIndex];
                const nomeCidade = selectedOption.getAttribute('data-nome');
                // Atualiza o input hidden com o nome da cidade
                cidadeNomeHidden.value = nomeCidade || '';
            });


            // --- Lógica de Inicialização (para carregar e pré-selecionar) ---
            loadEstados().then(() => {
                // Depois que os estados carregarem...
                if (editUF) {
                    estadoSelect.value = editUF;
                    // Carrega as cidades do estado que estava salvo
                    return loadCidades(editUF);
                }
            }).then(() => {
                // Depois que as cidades carregarem...
                if (editCidadeID) {
                    cidadeSelect.value = editCidadeID;
                    // Dispara o evento 'change' manualmente para garantir que o 'cidade_nome_hidden' seja preenchido
                    cidadeSelect.dispatchEvent(new Event('change'));
                }
            });

        });
    </script>
</body>
</html>