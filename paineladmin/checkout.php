<?php
// admin_panel/checkout.php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

require_once '../config/db.php'; // Conexão com o DB

$message = '';
$message_type = ''; // success, error, info, warning

// --- INICIALIZAÇÃO DE VARIÁVEIS ---
$edit_pagamento = null; // Para carregar dados no form de edição/adição
$is_editing_pagamento = false;
$pixup_settings = []; // Array para carregar as configs da API
$zeroone_settings = [];
$gateway_ativo = 'pixup'; // Default

// ==========================================================
// LÓGICA CRUD: GATEWAY PIXUP
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_pixup'])) {
    try {
        $pdo->beginTransaction();
        $sql = "UPDATE config_api SET valor = :valor, atualizado_em = NOW() WHERE chave = :chave";
        $stmt = $pdo->prepare($sql);

        // CHAVES PIXUP CORRIGIDAS: Removido 'pixup_postback_url'
        $chaves_pixup = ['pixup_client_id', 'pixup_client_secret'];

        foreach ($chaves_pixup as $chave) {
            if (isset($_POST[$chave])) {
                $valor = trim($_POST[$chave]);
                // Se a chave for a 'secret' e o campo veio vazio, não atualiza (mantém o valor existente)
                if ($chave === 'pixup_client_secret' && empty($valor)) {
                    $check_sql = "SELECT valor FROM config_api WHERE chave = :chave";
                    $check_stmt = $pdo->prepare($check_sql);
                    $check_stmt->execute([':chave' => $chave]);
                    $existing_secret = $check_stmt->fetchColumn();
                    if (!empty($existing_secret)) {
                        continue; // Pula a atualização desta chave
                    }
                }
                $stmt->execute([':valor' => $valor, ':chave' => $chave]);
            }
        }

        // NOVO: Salva a configuração de Gateway Ativo
        $gateway_ativo_post = trim($_POST['gateway_ativo'] ?? 'pixup');
        $stmt->execute([':valor' => $gateway_ativo_post, ':chave' => 'checkout_gateway_ativo']);

        $pdo->commit();
        $message = "Configurações do Gateway PixUp e Gateway Ativo salvas!"; $message_type = "success";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = "Erro ao salvar config. do gateway: " . $e->getMessage(); $message_type = "error";
    }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $message_type;
    header("Location: checkout.php#tab-gateway"); exit;
}

// ==========================================================
// LÓGICA CRUD: GATEWAY ZEROONEPAY
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_zeroone'])) {
    try {
        $pdo->beginTransaction();
        $sql = "UPDATE config_api SET valor = :valor, atualizado_em = NOW() WHERE chave = :chave";
        $stmt = $pdo->prepare($sql);

        // CHAVES DO ZERO ONE PAY
        $chaves_zeroone = ['zeroone_api_token', 'zeroone_api_url', 'zeroone_offer_hash', 'zeroone_product_hash'];

        foreach ($chaves_zeroone as $chave) {
            if (isset($_POST[$chave])) {
                $valor = trim($_POST[$chave]);
                $stmt->execute([':valor' => $valor, ':chave' => $chave]);
            }
        }

        // Salva a configuração de Gateway Ativo
        $gateway_ativo_post = trim($_POST['gateway_ativo'] ?? 'zeroone');
        $stmt->execute([':valor' => $gateway_ativo_post, ':chave' => 'checkout_gateway_ativo']);

        $pdo->commit();
        $message = "Configurações do Gateway Zero One Pay salvas!"; $message_type = "success";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = "Erro ao salvar config. do gateway Zero One Pay: " . $e->getMessage(); $message_type = "error";
    }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $message_type;
    header("Location: checkout.php#tab-gateway"); exit;
}

// ==========================================================
// LÓGICA CRUD: FORMAS DE PAGAMENTO
// ==========================================================

// 1P. ADICIONAR ou ATUALIZAR Pagamento
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_pagamento'])) {
    $id = isset($_POST['pag_id']) ? (int)$_POST['pag_id'] : null;
    $nome = trim($_POST['nome']);
    $tipo = trim($_POST['tipo']);
    $instrucoes = trim($_POST['instrucoes']);
    $config_json = trim($_POST['config_json']);
    $ativo = isset($_POST['ativo']);

    if (empty($nome) || empty($tipo)) {
          $message = "Erro: Nome e Tipo são obrigatórios."; $message_type = "error";
    } elseif (!empty($config_json) && json_decode($config_json) === null && $config_json !== '') {
          $message = "Erro: O campo 'Config JSON' contém um JSON inválido."; $message_type = "error";
    } else {
        try {
            if ($id) { // UPDATE
                $sql = "UPDATE formas_pagamento SET nome = :nome, tipo = :tipo, instrucoes = :instrucoes, config_json = :config_json, ativo = :ativo WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $message = "Forma de pagamento atualizada!";
            } else { // INSERT
                $sql = "INSERT INTO formas_pagamento (nome, tipo, instrucoes, config_json, ativo) VALUES (:nome, :tipo, :instrucoes, :config_json, :ativo)";
                $stmt = $pdo->prepare($sql);
                $message = "Forma de pagamento adicionada!";
            }
            $config_json_param = !empty($config_json) ? $config_json : null;
            $stmt->bindParam(':nome', $nome, PDO::PARAM_STR);
            $stmt->bindParam(':tipo', $tipo, PDO::PARAM_STR);
            $stmt->bindParam(':instrucoes', $instrucoes, PDO::PARAM_STR);
            $stmt->bindParam(':config_json', $config_json_param, PDO::PARAM_STR);
            $stmt->bindParam(':ativo', $ativo, PDO::PARAM_BOOL);
            if($id) $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $message_type = "success";
        } catch (PDOException $e) {
             if ($e->getCode() == '23505') { $message = "Erro: Já existe uma forma de pagamento com este nome ou identificador."; }
             else { $message = "Erro ao salvar forma de pagamento: " . $e->getMessage(); }
             $message_type = "error";
             $edit_pagamento = ['id' => $id, 'nome' => $nome, 'tipo' => $tipo, 'instrucoes' => $instrucoes, 'config_json' => $config_json, 'ativo' => $ativo];
             $is_editing_pagamento = (bool)$id;
        }
    }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $message_type;
    header("Location: checkout.php#tab-pagamentos"); exit;
}

// 2P. DELETAR Pagamento
if (isset($_GET['delete_pagamento'])) {
    $id = (int)$_GET['delete_pagamento'];
    try {
        $sql = "DELETE FROM formas_pagamento WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $message = "Forma de pagamento removida!"; $message_type = "success";
    } catch (PDOException $e) {
        $message = "Erro ao remover forma de pagamento: " . $e->getMessage(); $message_type = "error";
    }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $message_type;
    header("Location: checkout.php#tab-pagamentos"); exit;
}


// ==========================================================
// LÓGICA DE LEITURA
// ==========================================================

// Pega mensagens flash da sessão
if (isset($_SESSION['flash_message']) && empty($message)) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// 3P. MODO DE EDIÇÃO Pagamento
if (isset($_GET['edit_pagamento'])) {
    $id = (int)$_GET['edit_pagamento'];
    $stmt = $pdo->prepare("SELECT * FROM formas_pagamento WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $edit_pagamento = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($edit_pagamento) {
        $is_editing_pagamento = true;
    } else {
        if (empty($message)) { // Só mostra warning se não houver outra msg
            $message = "Forma de pagamento não encontrada para edição."; $message_type = "warning";
        }
    }
}

// --- LEITURA DE DADOS PARA EXIBIÇÃO ---
try {
    // 1. Ler configs do Gateway (TODOS)
    $stmt_api = $pdo->query("SELECT id, chave, valor, descricao, atualizado_em FROM config_api WHERE chave LIKE 'pixup_%' OR chave LIKE 'zeroone_%' OR chave = 'checkout_gateway_ativo' ORDER BY chave ASC");
    $all_api_settings = $stmt_api->fetchAll(PDO::FETCH_ASSOC);

    // Organiza as configurações por Gateway e a chave ativa
    $pixup_settings_db = []; // Dados do DB
    $zeroone_settings_db = []; // Dados do DB
    $gateway_ativo = 'pixup'; // Default

    foreach($all_api_settings as $setting) {
        if ($setting['chave'] === 'checkout_gateway_ativo') {
            $gateway_ativo = $setting['valor'];
            continue;
        }
        // REMOVIDO: 'pixup_postback_url' não é mais lido
        if (strpos($setting['chave'], 'pixup_') === 0 && $setting['chave'] !== 'pixup_postback_url') {
            $pixup_settings_db[$setting['chave']] = $setting;
        } elseif (strpos($setting['chave'], 'zeroone_') === 0) {
            $zeroone_settings_db[$setting['chave']] = $setting;
        }
    }

    // Garantir que as chaves existam para preencher o formulário PixUp
    $pixup_settings = array_merge([
        'pixup_client_id' => ['valor' => '', 'descricao' => 'Client ID da PixUp'],
        'pixup_client_secret' => ['valor' => '', 'descricao' => 'Client Secret da PixUp'],
    ], $pixup_settings_db);

    // Garantir que as chaves existam para preencher o formulário Zero One Pay
    $zeroone_settings = array_merge([
        'zeroone_api_token' => ['valor' => '', 'descricao' => 'Token de API do Zero One Pay'],
        'zeroone_api_url' => ['valor' => 'https://api.zeroonepay.com.br/api', 'descricao' => 'Endpoint Base'],
        'zeroone_offer_hash' => ['valor' => '', 'descricao' => 'Offer Hash (Oferta Padrão)'],
        'zeroone_product_hash' => ['valor' => '', 'descricao' => 'Product Hash (Produto Padrão)'],
    ], $zeroone_settings_db);


    // 2. Ler Formas de Pagamento
    $stmt_pag = $pdo->query("SELECT * FROM formas_pagamento ORDER BY nome ASC");
    $all_pagamento = $stmt_pag->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    if (empty($message)) {
        $message .= " Erro ao carregar dados: " . $e->getMessage();
        $message_type = "error";
    }
    // Preenche com padrões se a query falhar
    $pixup_settings = [
        'pixup_client_id' => ['valor' => '', 'descricao' => 'Client ID da PixUp'],
        'pixup_client_secret' => ['valor' => '', 'descricao' => 'Client Secret da PixUp'],
    ];
    $zeroone_settings = [
        'zeroone_api_token' => ['valor' => '', 'descricao' => 'Token de API do Zero One Pay'],
        'zeroone_api_url' => ['valor' => 'https://api.zeroonepay.com.br/api', 'descricao' => 'Endpoint Base'],
        'zeroone_offer_hash' => ['valor' => '', 'descricao' => 'Offer Hash (Oferta Padrão)'],
        'zeroone_product_hash' => ['valor' => '', 'descricao' => 'Product Hash (Produto Padrão)'],
    ];
    $all_pagamento = [];
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Gateway e Pagamentos - Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>

    <style>
        /* ==========================================================
            CSS COMPLETO DO PAINEL ADMIN (Base no produtos.php)
            ========================================================== */
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
        body { font-family: 'Poppins', sans-serif; background-color: var(--background-color); color: var(--text-color); display: flex; min-height: 100vh; overflow-x: hidden; line-height: 1.6; }
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

        /* --- CSS PARA ABAS (TABS) --- */
        .tab-navigation { display: flex; gap: 0.5rem; margin-bottom: -1px; position: relative; z-index: 10; padding-left: 1rem; }
        .tab-button { padding: 0.8rem 1.5rem; border: 1px solid var(--border-color); background-color: var(--sidebar-color); color: var(--light-text-color); border-radius: var(--border-radius) var(--border-radius) 0 0; cursor: pointer; font-weight: 500; transition: all 0.2s ease-in-out; border-bottom-color: var(--border-color); }
        .tab-button:hover { background-color: var(--glass-background); color: var(--text-color); }
        .tab-button.active { background-color: var(--glass-background); color: var(--primary-color); font-weight: 600; border-bottom-color: var(--glass-background); box-shadow: 0 2px 8px rgba(0,0,0,0.3); }
        .tab-pane { display: none; padding: 0; background: transparent; }
        .tab-pane.active { display: block; animation: fadeIn 0.4s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* --- Seções CRUD --- */
        .crud-section { margin-bottom: 2.5rem; }
        .tab-pane .crud-section:last-child { margin-bottom: 0; }
        .crud-section h3 { font-size: 1.25rem; color: var(--text-color); margin-bottom: 1.25rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color); font-weight: 600; }
        .form-container, .list-container { background: var(--glass-background); padding: 1.5rem 2rem; border-radius: var(--border-radius); border: 1px solid var(--border-color); box-shadow: var(--box-shadow); margin-bottom: 2rem; backdrop-filter: blur(5px); }
        /* Remove borda superior do primeiro form/lista dentro de uma tab */
        .tab-content-wrapper > .tab-pane > .crud-section:first-child .form-container,
        .tab-content-wrapper > .tab-pane > .crud-section:first-child .list-container {
             border-top-left-radius: 0;
             border-top-right-radius: 0;
        }
        /* Ajuste para abas internas */
        #gateway-config-tabs .tab-pane:first-child .form-container,
        #gateway-config-tabs .tab-pane {
            border-top-left-radius: 0;
        }

        .form-container h4 { font-size: 1.1rem; color: var(--primary-color); margin-bottom: 1.25rem; font-weight: 600; }

        /* --- Formulários --- */
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--light-text-color); font-size: 0.8rem; text-transform: uppercase; }
        .form-group input[type="text"], .form-group input[type="password"], .form-group textarea, .form-group select { width: 100%; padding: 0.65rem 0.8rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); background-color: rgba(0, 0, 0, 0.3); color: var(--text-color); box-sizing: border-box; transition: border-color 0.3s ease, box-shadow 0.3s ease; font-size: 0.9em; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color: var(--primary-color); box-shadow: 0 0 8px rgba(74, 105, 189, 0.5); outline: none; }
        .form-group textarea { min-height: 80px; resize: vertical; }
        .form-group-check { display: flex; align-items: center; padding-top: 0; margin-bottom: 0.5rem; }
        .form-group-check label { font-weight: 400; color: var(--text-color); display: inline; margin-left: 0.5rem; text-transform: none; }
        .form-group-check input[type="checkbox"] { width: auto; vertical-align: middle; accent-color: var(--primary-color); cursor: pointer;}
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem 1.5rem; }
        button[type="submit"] { padding: 0.65rem 1.2rem; background-color: var(--primary-color); color: #fff; border: none; border-radius: var(--border-radius); cursor: pointer; font-weight: 600; transition: background-color 0.3s ease, transform 0.1s ease; font-size: 0.9em; }
        button[type="submit"]:hover { background-color: var(--secondary-color); transform: translateY(-1px); }
        button[type="submit"].update { background-color: #28a745; }
        button[type="submit"].update:hover { background-color: #218838; }
        .form-container a.cancel { color: var(--light-text-color); margin-left: 1rem; font-size: 0.9em; }
        .form-container a.cancel:hover { text-decoration: underline; }
        textarea.json-input { font-family: 'Courier New', Courier, monospace; background-color: rgba(0,0,0,0.5); color: #a5d6ff; font-size: 0.95em; }

        /* --- Tabelas (Listagem Superior) --- */
        .list-container { overflow-x: auto; }
        .list-container table { width: 100%; border-collapse: collapse; background-color: transparent; border-radius: 0; overflow: hidden; font-size: 0.85em; border: none; min-width: 600px; }
        .list-container th, .list-container td { border-bottom: 1px solid var(--border-color); padding: 10px 12px; text-align: left; vertical-align: middle; }
        .list-container th { background-color: rgba(0, 0, 0, 0.4); color: var(--text-color); font-weight: 600; text-transform: uppercase; font-size: 0.75em; white-space: nowrap; }
        .list-container tbody tr:last-child td { border-bottom: none; }
        .list-container tbody tr:hover { background-color: rgba(255, 255, 255, 0.05); }
        .list-container td.chave-col { font-family: 'Courier New', Courier, monospace; color: var(--secondary-color); font-weight: 600; }
        .list-container td.valor-col { word-break: break-all; }
        .list-container td.actions { white-space: nowrap; text-align: right; }
        .list-container .actions a { color: var(--primary-color); margin-left: 1rem; font-size: 0.85em; transition: color 0.2s ease; }
        .list-container .actions a:hover { color: var(--secondary-color); }
        .list-container .actions a.delete { color: var(--danger-color); }
        .list-container .actions a.delete:hover { color: #c0392b; }
        .status-ativo { color: #82e0aa; font-weight: bold; }
        .status-inativo { color: var(--danger-color); font-weight: bold; }

        /* --- Mensagens --- */
        .message { padding: 1rem; border-radius: var(--border-radius); margin-bottom: 1.5rem; font-weight: 600; border: 1px solid transparent; backdrop-filter: blur(3px); }
        .message.success { background-color: var(--success-bg); color: var(--success-text); border-color: rgba(21, 87, 36, 0.5); }
        .message.error { background-color: var(--error-bg); color: var(--error-text); border-color: rgba(114, 28, 36, 0.5); }
        .message.info { background-color: var(--info-bg); color: var(--info-text); border-color: rgba(8, 66, 152, 0.5); }
        .message.warning { background-color: var(--warning-bg); color: var(--warning-text); border-color: rgba(255,193,7,0.5); }

        /* --- Modal (Necessário para o JS de menu) --- */
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 2000; }

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
            .tab-navigation { gap: 0.2rem; padding-left: 0.5rem; }
            .tab-button { padding: 0.7rem 1rem; font-size: 0.85em; }
            /* Tabelas Mobile */
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
            .tab-navigation { flex-wrap: wrap; }
            .tab-button { width: 100%; border-radius: var(--border-radius); margin-bottom: 0.5rem; }
            .tab-button.active { border-radius: var(--border-radius); }
            /* Correção para borda em mobile */
            .tab-content-wrapper > .tab-pane > .crud-section:first-child .form-container,
            .tab-content-wrapper > .tab-pane > .crud-section:first-child .list-container {
                 border-top-left-radius: var(--border-radius);
                 border-top-right-radius: var(--border-radius);
            }
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
            <h1>Gerenciar Gateway e Pagamentos</h1>
            <p>Configure as chaves de API do gateway e as formas de pagamento disponíveis.</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo nl2br(htmlspecialchars($message)); ?>
            </div>
        <?php endif; ?>

        <div class="tab-navigation">
            <button class="tab-button" data-tab-target="#tab-gateway">Gateway (Pix)</button>
            <button class="tab-button" data-tab-target="#tab-pagamentos">Formas de Pagamento</button>
        </div>

        <div class="tab-content-wrapper">

            <div class="tab-pane" id="tab-gateway">
                <div class="crud-section" id="form-gateway">
                    <h3>Configuração de Gateways de Pagamento</h3>
                    <div class="form-container">

                        <div class="form-group" style="border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                             <h4>Gateway Ativo para o Checkout do Cliente</h4>
                             <label for="gateway_ativo_select">Gateway Selecionado:</label>
                             <select id="gateway_ativo_select" name="gateway_ativo_select">
                                 <option value="pixup" <?php echo ($gateway_ativo == 'pixup') ? 'selected' : ''; ?>>PixUp</option>
                                 <option value="zeroone" <?php echo ($gateway_ativo == 'zeroone') ? 'selected' : ''; ?>>Zero One Pay</option>
                             </select>
                             <p style="margin-top: 0.5rem; color: var(--warning-text); font-size: 0.85em;">**Atenção:** Mudar aqui define qual API será usada para gerar o PIX no lado do cliente.</p>
                        </div>

                        <div class="tab-navigation" style="padding-left: 0;" id="internal-tabs-gateway">
                            <button class="tab-button" data-tab-target="#gateway-pixup-config">PixUp</button>
                            <button class="tab-button" data-tab-target="#gateway-zeroone-config">Zero One Pay</button>
                        </div>

                        <div class="tab-content-wrapper" id="gateway-config-tabs">

                            <div class="tab-pane" id="gateway-pixup-config">
                                <div class="form-container" style="border-top-left-radius: 0; border-top-right-radius: 0;">
                                    <h4>Chaves da API PixUp (Tabela: config_api)</h4>
                                    <form action="checkout.php#tab-gateway" method="POST" class="gateway-config-form">
                                        <input type="hidden" name="salvar_pixup" value="1">
                                        <input type="hidden" name="gateway_ativo" id="pixup_gateway_ativo" value="<?php echo htmlspecialchars($gateway_ativo); ?>">

                                        <div class="form-group">
                                            <label for="pixup_client_id"><?php echo htmlspecialchars($pixup_settings['pixup_client_id']['descricao']); ?> (Chave: pixup_client_id)</label>
                                            <input type="text" id="pixup_client_id" name="pixup_client_id" value="<?php echo htmlspecialchars($pixup_settings['pixup_client_id']['valor'] ?? ''); ?>" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="pixup_client_secret"><?php echo htmlspecialchars($pixup_settings['pixup_client_secret']['descricao']); ?> (Chave: pixup_client_secret)</label>
                                            <input type="password" id="pixup_client_secret" name="pixup_client_secret" placeholder="Mantenha em branco para não alterar" autocomplete="new-password">
                                            <?php if (!empty($pixup_settings['pixup_client_secret']['valor'] ?? '')): ?>
                                                <p style="color: var(--light-text-color); font-size: 0.8em; margin-top: 0.5rem;">Valor armazenado. Deixe em branco para manter.</p>
                                            <?php endif; ?>
                                        </div>

                                        <button type="submit" class="update">Salvar Configurações PixUp</button>
                                    </form>
                                </div>
                            </div>

                            <div class="tab-pane" id="gateway-zeroone-config">
                                <div class="form-container" style="border-top-left-radius: 0; border-top-right-radius: 0;">
                                    <h4>Chaves da API Zero One Pay (Tabela: config_api)</h4>
                                    <form action="checkout.php#tab-gateway" method="POST" class="gateway-config-form">
                                        <input type="hidden" name="salvar_zeroone" value="1">
                                        <input type="hidden" name="gateway_ativo" id="zeroone_gateway_ativo" value="<?php echo htmlspecialchars($gateway_ativo); ?>">

                                        <div class="form-group">
                                            <label for="zeroone_api_token"><?php echo htmlspecialchars($zeroone_settings['zeroone_api_token']['descricao']); ?> (Chave: zeroone_api_token)</label>
                                            <input type="text" id="zeroone_api_token" name="zeroone_api_token" value="<?php echo htmlspecialchars($zeroone_settings['zeroone_api_token']['valor'] ?? ''); ?>" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="zeroone_api_url"><?php echo htmlspecialchars($zeroone_settings['zeroone_api_url']['descricao']); ?> (Chave: zeroone_api_url)</label>
                                            <input type="text" id="zeroone_api_url" name="zeroone_api_url" value="<?php echo htmlspecialchars($zeroone_settings['zeroone_api_url']['valor'] ?? 'https://api.zeroonepay.com.br/api'); ?>" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="zeroone_offer_hash"><?php echo htmlspecialchars($zeroone_settings['zeroone_offer_hash']['descricao']); ?> (Chave: zeroone_offer_hash)</label>
                                            <input type="text" id="zeroone_offer_hash" name="zeroone_offer_hash" value="<?php echo htmlspecialchars($zeroone_settings['zeroone_offer_hash']['valor'] ?? ''); ?>" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="zeroone_product_hash"><?php echo htmlspecialchars($zeroone_settings['zeroone_product_hash']['descricao']); ?> (Chave: zeroone_product_hash)</label>
                                            <input type="text" id="zeroone_product_hash" name="zeroone_product_hash" value="<?php echo htmlspecialchars($zeroone_settings['zeroone_product_hash']['valor'] ?? ''); ?>" required>
                                        </div>

                                        <button type="submit" class="update">Salvar Configurações Zero One Pay</button>
                                    </form>
                                </div>
                            </div>

                        </div>

                    </div>
                </div>
            </div>

            <div class="tab-pane" id="tab-pagamentos">
                <div class="crud-section" id="form-pagamento">
                    <h3><?php echo $is_editing_pagamento ? 'Editar Forma de Pagamento' : 'Adicionar Nova Forma de Pagamento'; ?></h3>
                    <div class="form-container">
                        <form action="checkout.php#tab-pagamentos" method="POST">
                            <?php if ($is_editing_pagamento): ?>
                                <input type="hidden" name="pag_id" value="<?php echo $edit_pagamento['id']; ?>">
                            <?php endif; ?>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="nome_pag">Nome (Ex: Pix, Cartão de Crédito)</label>
                                    <input type="text" id="nome_pag" name="nome" value="<?php echo htmlspecialchars($edit_pagamento['nome'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="tipo_pag">Tipo</label>
                                    <select id="tipo_pag" name="tipo" required>
                                        <option value="pix" <?php echo (($edit_pagamento['tipo'] ?? '') == 'pix') ? 'selected' : ''; ?>>Pix</option>
                                        <option value="cartao_credito" <?php echo (($edit_pagamento['tipo'] ?? '') == 'cartao_credito') ? 'selected' : ''; ?>>Cartão de Crédito</option>
                                        <option value="boleto" <?php echo (($edit_pagamento['tipo'] ?? '') == 'boleto') ? 'selected' : ''; ?>>Boleto</option>
                                        <option value="outro" <?php echo (($edit_pagamento['tipo'] ?? '') == 'outro') ? 'selected' : ''; ?>>Outro</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="instrucoes_pag">Instruções (Ex: Chave pix: 123.456...)</label>
                                <textarea id="instrucoes_pag" name="instrucoes"><?php echo htmlspecialchars($edit_pagamento['instrucoes'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="config_json">Config JSON (Avançado - Ex: chaves de API)</label>
                                <textarea id="config_json" name="config_json" class="json-input" placeholder='{ "chave_api": "...", "client_id": "..." }'><?php echo htmlspecialchars($edit_pagamento['config_json'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group-check" style="padding-top: 0;">
                                <input type="checkbox" id="ativo_pag" name="ativo" value="1"
                                    <?php
                                        $isChecked = ($is_editing_pagamento && !empty($edit_pagamento['ativo'])) || !$is_editing_pagamento;
                                        echo $isChecked ? 'checked' : '';
                                    ?>>
                                <label for="ativo_pag">Ativo (Disponível no checkout)</label>
                            </div>
                            <button type="submit" name="salvar_pagamento" class="<?php echo $is_editing_pagamento ? 'update' : ''; ?>">
                                <?php echo $is_editing_pagamento ? 'Salvar Alterações' : 'Adicionar Pagamento'; ?>
                            </button>
                            <?php if ($is_editing_pagamento): ?>
                                <a href="checkout.php#tab-pagamentos" class="cancel">Cancelar Edição</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="crud-section" id="list-pagamentos">
                    <h3>Formas de Pagamento Cadastradas</h3>
                    <div class="list-container">
                        <?php if (empty($all_pagamento)): ?>
                            <p style="text-align: center; color: var(--light-text-color); padding: 1rem 0;">Nenhuma forma de pagamento cadastrada.</p>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Tipo</th>
                                        <th>Status</th>
                                        <th class="actions">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_pagamento as $pag): ?>
                                        <tr>
                                            <td data-label="Nome"><?php echo htmlspecialchars($pag['nome']); ?></td>
                                            <td data-label="Tipo"><?php echo htmlspecialchars($pag['tipo']); ?></td>
                                            <td data-label="Status">
                                                <?php if ($pag['ativo']): ?>
                                                    <span class="status-ativo">Ativo</span>
                                                <?php else: ?>
                                                    <span class="status-inativo">Inativo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="actions">
                                                <a href="checkout.php?edit_pagamento=<?php echo $pag['id']; ?>#form-pagamento">Editar</a>
                                                <a href="checkout.php?delete_pagamento=<?php echo $pag['id']; ?>" onclick="return confirm('Tem certeza?');" class="delete">Remover</a>
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
        // MANTIDO: Inicializa a biblioteca particlesJS para o fundo animado
        particlesJS('particles-js', {"particles":{"number":{"value":60,"density":{"enable":true,"value_area":800}},"color":{"value":"#4a69bd"},"shape":{"type":"circle"},"opacity":{"value":0.4,"random":false},"size":{"value":3,"random":true},"line_linked":{"enable":true,"distance":150,"color":"#ffffff","opacity":0.1,"width":1},"move":{"enable":true,"speed":1.5,"direction":"none","random":false,"straight":false,"out_mode":"out","bounce":false}},"interactivity":{"detect_on":"canvas","events":{"onhover":{"enable":true,"mode":"repulse"},"onclick":{"enable":true,"mode":"push"},"resize":true}},"retina_detect":true});

        // --- JavaScript Específico da Página (Checkout) ---
        document.addEventListener('DOMContentLoaded', () => {
            // A Lógica de Menu, Perfil e Submenus foi removida, pois está centralizada no admin_sidebar.php

            // --- Lógica das ABAS (TABS) - (Principal) ---
            const tabButtons = document.querySelectorAll('main > .tab-navigation .tab-button');
            const tabPanes = document.querySelectorAll('main > .tab-content-wrapper > .tab-pane');

            tabButtons.forEach(button => {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabPanes.forEach(pane => pane.classList.remove('active'));

                    button.classList.add('active');
                    const targetPaneId = button.getAttribute('data-tab-target');
                    const targetPane = document.querySelector(targetPaneId);
                    if (targetPane) {
                        targetPane.classList.add('active');
                    }

                    // Limpa a query string (se houver) e define o hash da aba
                    const newUrl = window.location.pathname + targetPaneId;
                    history.pushState(null, null, newUrl);
                });
            });

            // --- Lógica das ABAS (TABS) - (Internas Gateway) ---
            const internalTabButtons = document.querySelectorAll('#internal-tabs-gateway .tab-button');
            const internalTabPanes = document.querySelectorAll('#gateway-config-tabs > .tab-pane');

            internalTabButtons.forEach(button => {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    internalTabButtons.forEach(btn => btn.classList.remove('active'));
                    internalTabPanes.forEach(pane => pane.classList.remove('active'));

                    button.classList.add('active');
                    const targetPaneId = button.getAttribute('data-tab-target');
                    const targetPane = document.querySelector(targetPaneId);
                    if (targetPane) {
                        targetPane.classList.add('active');
                    }
                });
            });

            // --- Lógica para Ancoragem e Abas ---
            const urlParams = new URLSearchParams(window.location.search);
            const editPagamentoId = urlParams.get('edit_pagamento');
            const urlHash = window.location.hash;

            function activateTabFromHash(hash) {
                let targetPane = null;
                let elementToScroll = null;

                if (hash.startsWith('#tab-')) {
                    targetPane = document.querySelector(hash);
                    elementToScroll = targetPane;
                } else if (hash) {
                    const targetElement = document.querySelector(hash);
                    if (targetElement) {
                        targetPane = targetElement.closest('.tab-pane');
                        elementToScroll = targetElement;
                    }
                }

                if (targetPane) {
                    // Ativa a aba principal
                    const targetTabButton = document.querySelector(`main > .tab-navigation .tab-button[data-tab-target="#${targetPane.id}"]`);
                    if (targetTabButton && !targetTabButton.classList.contains('active')) {
                        tabButtons.forEach(btn => btn.classList.remove('active'));
                        tabPanes.forEach(pane => pane.classList.remove('active'));
                        targetTabButton.classList.add('active');
                        targetPane.classList.add('active');
                    }
                } else {
                    // Ativa a aba "Formas de Pagamento" se houver ?edit_pagamento na URL
                    if (editPagamentoId) {
                        const tabBtn = document.querySelector('.tab-button[data-tab-target="#tab-pagamentos"]');
                        if(tabBtn) tabBtn.click();
                        elementToScroll = document.querySelector('#form-pagamento');
                    }
                    // Padrão: Ativa a primeira aba principal
                    else if (tabButtons.length > 0 && !document.querySelector('main > .tab-navigation .tab-button.active')) {
                        tabButtons[0].click();
                    }
                }

                // Ativa a aba interna correta baseada no gateway salvo (dentro da aba 'tab-gateway')
                const gatewaySelect = document.getElementById('gateway_ativo_select');
                if(gatewaySelect) {
                    const activeGateway = gatewaySelect.value;
                    let internalTabBtn;
                    if (activeGateway === 'zeroone') {
                        internalTabBtn = document.querySelector('#internal-tabs-gateway .tab-button[data-tab-target="#gateway-zeroone-config"]');
                    } else if (activeGateway === 'pixup') {
                        // Nota: Assume 'pixup' como padrão se não for 'zeroone'
                        internalTabBtn = document.querySelector('#internal-tabs-gateway .tab-button[data-tab-target="#gateway-pixup-config"]');
                    }
                    if(internalTabBtn) internalTabBtn.click();
                }

                // Rola para a seção específica
                if (elementToScroll) {
                    setTimeout(() => {
                        const headerElement = elementToScroll.querySelector('h3') || elementToScroll.querySelector('h4') || elementToScroll;
                        if(headerElement) {
                            headerElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    }, 150);
                }
            }

            // Ativa a aba correta no carregamento da página
            activateTabFromHash(urlHash);

            // --- Atualiza campo hidden 'gateway_ativo' ao selecionar ---
            const gatewaySelect = document.getElementById('gateway_ativo_select');
            if(gatewaySelect) {
                gatewaySelect.addEventListener('change', function() {
                    const selectedGateway = this.value;

                    // Atualiza os campos hidden de ambos os formulários (PixUp e ZeroOne)
                    const pixupField = document.getElementById('pixup_gateway_ativo');
                    const zerooneField = document.getElementById('zeroone_gateway_ativo');

                    if(pixupField) pixupField.value = selectedGateway;
                    if(zerooneField) zerooneField.value = selectedGateway;

                    // Clica na aba de configuração do gateway selecionado para visualização
                    const targetTab = document.querySelector(`#internal-tabs-gateway .tab-button[data-tab-target="#gateway-${selectedGateway}-config"]`);
                    if(targetTab) {
                        targetTab.click();
                    }
                });
            }
        });
    </script>
</body>
</html>