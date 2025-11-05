<?php
// admin_panel/config-cores.php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

require_once '../config/db.php'; // Conexão com o DB
date_default_timezone_set('America/Sao_Paulo');

// --- 1. DEFINIÇÃO DAS CHAVES DE CORES ---
$color_keys = [
    'cor_acento' => 'Cor de Destaque (Botões, Links)',
    'cor_fundo_header' => 'Fundo (Header/Footer)',
    'cor_fundo_nav' => 'Fundo (Navegação/Categorias)',
    'cor_texto_principal' => 'Texto Principal (Escuro)',
    'cor_texto_medio' => 'Texto Médio',
    'cor_texto_claro' => 'Texto Claro (links do topo)',
    'cor_borda_clara' => 'Bordas Claras (Divisórias)',
    'cor_borda_media' => 'Bordas Médias (Inputs e Bordas Globais)', // Nome atualizado
    'cor_erro' => 'Cor de Erro (Vermelho)',
    'cor_sucesso' => 'Cor de Sucesso (Verde)',
    /* NOVO: Cores de Hover (Dinâmicas e Sincronizadas) */
    'cor_acento_hover' => 'Cor de Destaque (Hover)',
    'cor_fundo_hover_claro' => 'Fundo de Elementos (Hover)',
];
$cores_padrao = [
    'cor_acento' => '#9ad700',
    'cor_fundo_header' => '#e9efe8',
    'cor_fundo_nav' => '#ffffff',
    'cor_texto_principal' => '#333333',
    'cor_texto_medio' => '#555555',
    'cor_texto_claro' => '#777777',
    'cor_borda_clara' => '#eeeeee',
    'cor_borda_media' => '#cccccc',
    'cor_erro' => '#dc3545',
    'cor_sucesso' => '#28a745',
    /* NOVO: Cores de Hover (Valores Padrão) */
    'cor_acento_hover' => '#7bb500', // Exemplo: um tom mais escuro
    'cor_fundo_hover_claro' => '#f2f2f2', // Exemplo: um tom mais claro
];

// --- 2. NOVO: DEFINIÇÃO DAS CHAVES DE LAYOUT (BORDAS) ---
$layout_keys = [
    'nav_borda_superior_ativa' => 'Borda Acima da Navegação',
    'footer_borda_superior_ativa' => 'Borda Acima do Footer',
    'footer_credits_borda_ativa' => 'Borda Acima dos Créditos'
];
$layout_padrao = [
    'nav_borda_superior_ativa' => 'true',
    'footer_borda_superior_ativa' => 'true',
    'footer_credits_borda_ativa' => 'true'
];


$message = '';
$message_type = '';

// ==========================================================
// LÓGICA POST: SALVAR CORES E LAYOUT
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_cores'])) {
    try {
        $pdo->beginTransaction();

        // SQL para Cores
        $sql_color = "INSERT INTO config_site (chave, valor, descricao, tipo_input)
                      VALUES (:chave, :valor, :descricao, 'color')
                      ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor";
        $stmt_color = $pdo->prepare($sql_color);

        foreach ($color_keys as $chave => $descricao) {
            if (isset($_POST[$chave])) {
                $valor = trim($_POST[$chave]);
                // Validação de cor hexadecimal
                if (!preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/i', $valor)) {
                    $valor = $cores_padrao[$chave];
                }
                $stmt_color->execute(['chave' => $chave, 'valor' => $valor, 'descricao' => $descricao]);
            }
        }

        // NOVO: SQL para Layout (Toggles)
        $sql_layout = "INSERT INTO config_site (chave, valor, descricao, tipo_input)
                       VALUES (:chave, :valor, :descricao, 'boolean')
                       ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor";
        $stmt_layout = $pdo->prepare($sql_layout);

        foreach ($layout_keys as $chave => $descricao) {
            if (isset($_POST[$chave])) {
                $valor = ($_POST[$chave] === 'true') ? 'true' : 'false'; // Garante que é 'true' ou 'false'
                $stmt_layout->execute(['chave' => $chave, 'valor' => $valor, 'descricao' => $descricao]);
            }
        }

        $pdo->commit();
        $_SESSION['flash_message'] = "Configurações de aparência salvas com sucesso!";
        $_SESSION['flash_type'] = "success";

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash_message'] = "Erro ao salvar configurações: " . $e->getMessage();
        $_SESSION['flash_type'] = "error";
    }
    header("Location: config-cores.php");
    exit;
}

// ==========================================================
// LÓGICA GET: CARREGAR CORES E LAYOUT
// ==========================================================
$current_configs = array_merge($cores_padrao, $layout_padrao); // Combina os padrões
try {
    // Busca TODAS as chaves (cores E layout)
    $all_keys = array_merge(array_keys($color_keys), array_keys($layout_keys));
    $placeholders = implode(',', array_fill(0, count($all_keys), '?'));

    $stmt_config = $pdo->prepare("SELECT chave, valor FROM config_site WHERE chave IN ($placeholders)");
    $stmt_config->execute($all_keys);

    $config_db = $stmt_config->fetchAll(PDO::FETCH_KEY_PAIR);

    // Mescla os padrões com os do banco
    $current_configs = array_merge($current_configs, $config_db);

} catch (PDOException $e) {
    $message = "Erro ao carregar configurações: " . $e->getMessage();
    $message_type = "error";
}

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
    <title>Configurar Cores - Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <style>
        /* ==========================================================
           CSS DO PAINEL ADMIN (Base)
           ========================================================== */
        :root {
            --primary-color: #4a69bd; --secondary-color: #6a89cc; --text-color: #f9fafb;
            --light-text-color: #9ca3af; --border-color: rgba(255, 255, 255, 0.1); --background-color: #111827;
            --sidebar-color: #1f2937; --glass-background: rgba(31, 41, 55, 0.7);
            --success-bg: rgba(40, 167, 69, 0.3); --success-text: #c3e6cb; --success-border: rgba(40, 167, 69, 0.5);
            --error-bg: rgba(220, 53, 69, 0.3); --error-text: #f5c6cb; --error-border: rgba(220, 53, 69, 0.5);
            --info-bg: rgba(0, 123, 255, 0.2); --info-text: #bee5eb; --info-border: rgba(0, 123, 255, 0.4);
            --warning-bg: rgba(255, 193, 7, 0.2); --warning-text: #ffeeba; --warning-border: rgba(255, 193, 7, 0.4);
            --danger-color: #e74c3c; --danger-color-hover: #c0392b;
            --sidebar-width: 240px; --border-radius: 8px; --box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
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

        /* --- Seções CRUD --- */
        .crud-section { margin-bottom: 2.5rem; }
        .crud-section h3 { font-size: 1.25rem; color: var(--text-color); margin-bottom: 1.25rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color); font-weight: 600; }
        .form-container { background: var(--glass-background); padding: 1.5rem 2rem; border-radius: var(--border-radius); border: 1px solid var(--border-color); box-shadow: var(--box-shadow); margin-bottom: 2rem; backdrop-filter: blur(5px); }
        .form-container h4 { font-size: 1.1rem; color: var(--primary-color); margin-bottom: 1.25rem; font-weight: 600; }

        /* --- Formulários --- */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-size: 0.85em; font-weight: 500; color: var(--light-text-color); text-transform: uppercase; }
        .form-group input[type="text"], .form-group input[type="email"], .form-group input[type="date"], .form-group input[type="number"], .form-group textarea, .form-group select { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); background-color: rgba(0, 0, 0, 0.2); color: var(--text-color); box-sizing: border-box; font-size: 0.9em; transition: border-color 0.3s ease, box-shadow 0.3s ease; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 2px rgba(74, 105, 189, 0.4); }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-group input[type="date"] { color-scheme: dark; }

        .form-actions { display: flex; gap: 1rem; margin-top: 1.5rem; justify-content: flex-start; flex-wrap: wrap; }
        .btn { padding: 0.65rem 1.2rem; background-color: var(--primary-color); color: #fff; border: none; border-radius: var(--border-radius); cursor: pointer; font-weight: 600; transition: all 0.3s ease; font-size: 0.9em; display: inline-block; text-decoration: none; }
        .btn:hover { background-color: var(--secondary-color); transform: translateY(-1px); text-decoration: none; color: #fff;}
        .btn.update { background-color: #28a745; }
        .btn.update:hover { background-color: #218838; }
        .btn.cancel { background-color: var(--light-text-color); color: var(--background-color) !important; }
        .btn.cancel:hover { background-color: #bbb; }
        .btn.danger { background-color: var(--danger-color); }
        .btn.danger:hover { background-color: var(--danger-color-hover); }

        /* --- Mensagens --- */
        .message { padding: 1rem; border-radius: var(--border-radius); margin-bottom: 1.5rem; font-weight: 600; border: 1px solid transparent; backdrop-filter: blur(3px); }
        .message.success { background-color: var(--success-bg); color: var(--success-text); border-color: var(--success-border); }
        .message.error { background-color: var(--error-bg); color: var(--error-text); border-color: var(--error-border); }
        .message.info { background-color: var(--info-bg); color: var(--info-text); border-color: var(--info-border); }
        .message.warning { background-color: var(--warning-bg); color: var(--warning-text); border-color: var(--warning-border); }

        /* --- Estilo para Grupo de Input de Cor --- */
        .color-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .color-input-group input[type="color"] {
            width: 48px;
            height: 48px;
            padding: 0.25rem;
            border: none;
            border-radius: var(--border-radius);
            background-color: rgba(0, 0, 0, 0.2);
            cursor: pointer;
            flex-shrink: 0;
        }
        .color-input-group input[type="color"]::-webkit-color-swatch-wrapper { padding: 0; }
        .color-input-group input[type="color"]::-webkit-color-swatch { border: none; border-radius: 4px; }
        .color-input-group input[type="text"] {
            flex-grow: 1;
            font-family: 'Courier New', Courier, monospace;
            text-transform: uppercase;
        }

        /* --- CSS Básico para Modais --- */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.8); backdrop-filter: blur(5px); z-index: 1001; display: none; justify-content: center; align-items: center; opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay.modal-open { display: flex; opacity: 1; }
        .modal-content { background-color: var(--sidebar-color); padding: 30px; border-radius: var(--border-radius); width: 90%; max-width: 600px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); border: 1px solid var(--border-color); transform: translateY(-20px); transition: transform 0.3s ease-out; }
        .modal-overlay.modal-open .modal-content { transform: translateY(0); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid var(--border-color); }
        .modal-title { font-size: 1.3em; color: var(--text-color); margin: 0; }
        .modal-close { background: none; border: none; font-size: 1.8em; font-weight: bold; color: #aaa; cursor: pointer; padding: 0 5px; line-height: 1; }
        .modal-close:hover { color: #fff; }
        .modal-body { color: var(--light-text-color); max-height: 60vh; overflow-y: auto; }
        .modal-footer { margin-top: 25px; text-align: right; border-top: 1px solid var(--border-color); padding-top: 1.5rem; }

        /* --- Estilos para o Modal de IA --- */
        .ia-modal-tabs { display: flex; gap: 5px; margin-bottom: 20px; }
        .ia-modal-tab { flex: 1; text-align: center; padding: 10px; background: rgba(0,0,0,0.2); border-bottom: 3px solid var(--border-color); cursor: pointer; font-weight: 500; color: var(--light-text-color); }
        .ia-modal-tab.active { color: var(--text-color); border-bottom-color: var(--primary-color); }
        .ia-modal-content { display: none; }
        .ia-modal-content.active { display: block; }
        #questionario-ia-content { text-align: left; }
        #questionario-ia-content h3 { color: var(--primary-color); margin-bottom: 15px; }
        #questionario-ia-content p { color: var(--light-text-color); margin-bottom: 15px; font-size: 0.9em;}
        #questionario-ia-content .form-group label { font-size: 0.9em; text-transform: none; }
        #resposta-ia-content label { display: block; margin-bottom: 10px; color: var(--light-text-color); font-size: 0.9em; }
        #resposta-ia-json { width: 100%; height: 200px; background-color: rgba(0,0,0,0.2); border: 1px solid var(--border-color); color: var(--text-color); font-family: 'Courier New', Courier, monospace; padding: 10px; border-radius: var(--border-radius); }
        .spinner { width: 20px; height: 20px; border: 3px solid var(--border-color); border-top-color: var(--primary-color); border-radius: 50%; animation: spin 1s linear infinite; display: none; margin: 20px auto; }
        @keyframes spin { to { transform: rotate(360deg); } }

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
        }
        @media (max-width: 576px) {
            .main-content { padding: 1rem; padding-top: 4.5rem; }
            .content-header { padding: 1rem 1.5rem;}
            .content-header h1 { font-size: 1.5rem; }
            .content-header p { font-size: 0.9rem;}
            .form-container { padding: 1rem 1.5rem;}
            .crud-section h3 { font-size: 1.1rem;}
        }
    </style>
</head>
<body>
    <div class="menu-toggle" id="menu-toggle"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg></div>
    <div id="particles-js"></div>

    <?php include 'admin_sidebar.php'; ?>

    <main class="main-content">
        <div class="content-header">
            <h1>Aparência da Loja</h1>
            <p>Controle as cores e o layout globais do seu site.</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="crud-section">
            <div class="form-container">
                <form action="config-cores.php" method="POST" id="form-cores">

                    <h3>Cores Globais</h3>
                    <p style="color: var(--light-text-color); font-size: 0.9em; margin-top: -10px; margin-bottom: 20px;">Estes valores irão sobrescrever o `:root` no `header.php`.</p>

                    <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">

                        <?php foreach ($color_keys as $chave => $descricao): ?>
                        <div class="form-group">
                            <label for="<?php echo $chave; ?>"><?php echo htmlspecialchars($descricao); ?></label>
                            <div class="color-input-group">
                                <input type="color" id="<?php echo $chave; ?>_picker" value="<?php echo htmlspecialchars($current_configs[$chave]); ?>" title="Selecionar cor">
                                <input type="text" name="<?php echo $chave; ?>" id="<?php echo $chave; ?>" value="<?php echo htmlspecialchars($current_configs[$chave]); ?>" required pattern="#[A-Fa-f0-9]{6}">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <h3 style="margin-top: 2rem;">Bordas (Header/Footer)</h3>
                    <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">

                        <?php foreach ($layout_keys as $chave => $descricao): ?>
                        <div class="form-group">
                            <label for="<?php echo $chave; ?>"><?php echo htmlspecialchars($descricao); ?></label>
                            <select name="<?php echo $chave; ?>" id="<?php echo $chave; ?>">
                                <option value="true" <?php echo ($current_configs[$chave] === 'true') ? 'selected' : ''; ?>>Ativada (Usar cor "Bordas Médias")</option>
                                <option value="false" <?php echo ($current_configs[$chave] === 'false') ? 'selected' : ''; ?>>Desativada (Nenhuma)</option>
                            </select>
                        </div>
                        <?php endforeach; ?>

                    </div>
                    <div class="form-actions">
                        <button type="submit" name="salvar_cores" class="btn update">Salvar Configurações</button>
                        <button type="button" id="btn-sugerir-cores-ia" class="btn cancel">✨ Sugerir Cores (IA)</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <div class="modal-overlay" id="ia-suggestion-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="ia-modal-title">Sugerir Cores com IA</h3>
                <button class="modal-close" data-dismiss="modal">&times;</button>
            </div>

            <div class="modal-body" id="ia-modal-body">

                <div class="ia-modal-tabs">
                    <div class="ia-modal-tab active" data-tab="questionario">Passo 1: Questionário</div>
                    <div class="ia-modal-tab" data-tab="resposta">Passo 2: Aplicar Resposta</div>
                </div>

                <div class="ia-modal-content active" id="ia-content-questionario">
                    <form id="form-questionario-ia">
                        <div class="form-group">
                            <label for="ia-nicho">1. Qual é o seu nicho de e-commerce?</label>
                            <input type="text" id="ia-nicho" name="nicho" placeholder="Ex: Headshop e Tabacaria, Moda Jovem, Eletrônicos">
                        </div>
                        <div class="form-group">
                            <label for="ia-vibe">2. Qual é a personalidade da sua loja?</label>
                            <input type="text" id="ia-vibe" name="vibe" placeholder="Ex: Moderna, Luxuosa, Divertida, Rústica, Natural">
                        </div>
                        <div class="form-group">
                            <label for="ia-tema">3. Prefere um tema base?</label>
                            <select id="ia-tema" name="tema">
                                <option value="claro">Tema Claro (Light Mode)</option>
                                <option value="escuro">Tema Escuro (Dark Mode)</option>
                            </select>
                        </div>
                    </form>
                </div>

                <div class="ia-modal-content" id="ia-content-resposta">
                    <div id="resposta-ia-content">
                        <div class="spinner" id="ia-loading-spinner" style="display: none;"></div>
                        <label for="resposta-ia-json">Cole a resposta JSON da IA aqui (ou clique em "Gerar"):</label>
                        <textarea id="resposta-ia-json" placeholder='{
    "cor_acento": "#FF5733",
    "cor_fundo_header": "#FFFFFF",
    ...
}'></textarea>
                    </div>
                </div>

            </div>

            <div class="modal-footer" style="justify-content: space-between;">
                <button class="btn update" id="btn-gerar-sugestao">Gerar Sugestão</button>
                <button class="btn update" id="btn-aplicar-sugestao" style="display: none;">Aplicar Cores</button>
                <button class="btn cancel modal-close" data-dismiss="modal">Cancelar</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="info-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="info-modal-title">Atenção</h3>
                <button class="modal-close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="info-modal-body">
                <p id="info-modal-message">...</p>
            </div>
            <div class="modal-footer" style="text-align: right;">
                <button class="btn btn-primary modal-close" data-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>


    <script>
        // --- JavaScript para Partículas ---
        if (typeof particlesJS === 'function') {
            particlesJS('particles-js', {"particles":{"number":{"value":60,"density":{"enable":true,"value_area":800}},"color":{"value":"#4a69bd"},"shape":{"type":"circle"},"opacity":{"value":0.4,"random":false},"size":{"value":3,"random":true},"line_linked":{"enable":true,"distance":150,"color":"#ffffff","opacity":0.1,"width":1},"move":{"enable":true,"speed":1.5,"direction":"none","random":false,"straight":false,"out_mode":"out","bounce":false}},"interactivity":{"detect_on":"canvas","events":{"onhover":{"enable":true,"mode":"repulse"},"onclick":{"enable":true,"mode":"push"},"resize":true}},"retina_detect":true});
        } else {
            console.warn("particles.js não foi carregado.");
        }

        // --- JavaScript Geral (DOMContentLoaded) ---
        document.addEventListener('DOMContentLoaded', () => {

            // --- Lógica de Submenu (ACORDEÃO) ---
            // OBS: MANTIDO, pois esta lógica de abrir/fechar o LI e o submenu
            // com base no clique no link pai (has-children > a) é exclusiva deste script.

            // Seleciona todos os links pais que gerenciam submenus
            document.querySelectorAll('.sidebar-nav .has-children > a').forEach(menuLink => {
                menuLink.addEventListener('click', function(e) {
                    // Se o menu lateral estiver aberto (mobile) ou se estiver em desktop, permite a interação do acordeão
                    if (document.body.classList.contains('sidebar-open') || window.innerWidth > 1024) {
                        e.preventDefault();
                        // O elemento pai que contém a classe 'open' no CSS/PHP anterior era o <li>,
                        // mas no sidebar final é o <a> que tem 'data-toggle="submenu"'.
                        // No seu código anterior, você estava usando 'this.parentElement'
                        // Vamos tentar manter a lógica original, assumindo que a estrutura é:
                        // <nav> -> <a> (has-children) + <div> (submenu)
                        // Mas seu JS antigo usava 'parentLi'. Assumindo que o <a> está dentro de um <li>:
                        // Se o admin_sidebar foi ajustado para ter <li> wrapper:
                        // const parentLi = this.closest('li');

                        // Como o PHP não mostra <li>, vamos seguir a estrutura: <a> é irmão do <div>.
                        // O admin_sidebar.php atua diretamente no <a> para a classe 'open'.

                        // Se a sua estrutura HTML for: <a data-toggle>...</a> <div class="sidebar-submenu">...</div>

                        // Nota: A lógica de submenu mais robusta (fechar outros) foi movida para admin_sidebar.php.
                        // Se o clique neste script deve complementar a lógica do sidebar, vamos ajustá-la.

                        // Ajuste para replicar a lógica do toggle no <a> e no <div>
                        const submenu = this.nextElementSibling;

                        // Toggle das classes no próprio link (que está sendo clicado)
                        this.classList.toggle('open');

                        if (submenu && submenu.classList.contains('sidebar-submenu')) {
                            if (submenu.style.maxHeight && submenu.style.maxHeight !== '0px') {
                                submenu.style.maxHeight = null;
                                submenu.classList.remove('open');
                            } else {
                                submenu.classList.add('open');
                                submenu.style.maxHeight = submenu.scrollHeight + 10 + "px"; // +10 para padding/borda
                            }
                        }
                    }
                });
            });

            // --- LÓGICA DE MENU E PERFIL REMOVIDA ---
            // A lógica do menu Hambúrguer ('menuToggle', 'sidebar-open') e
            // do dropdown de Perfil ('userProfile', 'profileDropdown')
            // foi removida pois está centralizada e controlada no admin_sidebar.php.

            // --- Sincronizador de Cores ---
            const colorGroups = document.querySelectorAll('.color-input-group');
            colorGroups.forEach(group => {
                const colorPicker = group.querySelector('input[type="color"]');
                const textInput = group.querySelector('input[type="text"]');

                if (colorPicker && textInput) {
                    colorPicker.addEventListener('input', (e) => { textInput.value = e.target.value.toUpperCase(); });
                    textInput.addEventListener('change', (e) => {
                        let val = e.target.value.toUpperCase();
                        if (!val.startsWith('#')) val = '#' + val;
                        if (/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/i.test(val)) {
                            colorPicker.value = val;
                        } else {
                            e.target.value = colorPicker.value;
                        }
                    });
                }
            });

            // --- Lógica do Modal de Sugestão de IA ---
            const btnSugerirIA = document.getElementById('btn-sugerir-cores-ia');
            const iaModal = document.getElementById('ia-suggestion-modal');
            const infoModal = document.getElementById('info-modal'); // Fallback de erro

            if (btnSugerirIA && iaModal) {
                const tabs = iaModal.querySelectorAll('.ia-modal-tab');
                const contents = iaModal.querySelectorAll('.ia-modal-content');
                const btnGerar = document.getElementById('btn-gerar-sugestao');
                const btnAplicar = document.getElementById('btn-aplicar-sugestao');
                const spinner = document.getElementById('ia-loading-spinner');
                const jsonTextarea = document.getElementById('resposta-ia-json');
                const formCores = document.getElementById('form-cores');

                // --- Função para trocar Abas ---
                function switchTab(tabName) {
                    tabs.forEach(t => t.classList.remove('active'));
                    contents.forEach(c => c.classList.remove('active'));

                    const activeTab = iaModal.querySelector(`.ia-modal-tab[data-tab="${tabName}"]`);
                    const activeContent = document.getElementById(`ia-content-${tabName}`);

                    if (activeTab) activeTab.classList.add('active');
                    if (activeContent) activeContent.classList.add('active');

                    btnGerar.style.display = (tabName === 'questionario') ? 'inline-block' : 'none';
                    btnAplicar.style.display = (tabName === 'resposta') ? 'inline-block' : 'none';
                }

                // 1. Abrir o Modal de IA
                btnSugerirIA.addEventListener('click', () => {
                    switchTab('questionario'); // Reseta para a primeira aba
                    jsonTextarea.value = '';
                    spinner.style.display = 'none';
                    iaModal.classList.add('modal-open');
                });

                // 2. Lógica das Abas do Modal
                tabs.forEach(tab => {
                    tab.addEventListener('click', () => switchTab(tab.dataset.tab));
                });

                // 3. Botão "Gerar Sugestão" (chama a API)
                btnGerar.addEventListener('click', async () => {
                    const nicho = document.getElementById('ia-nicho').value;
                    const vibe = document.getElementById('ia-vibe').value;
                    const tema = document.getElementById('ia-tema').value;

                    if (!nicho || !vibe) {
                        alert("Por favor, preencha o Nicho e a Personalidade.");
                        return;
                    }

                    spinner.style.display = 'block';
                    jsonTextarea.value = 'Gerando paleta... Por favor, aguarde.';
                    switchTab('resposta');

                    try {
                        // O caminho para a API é relativo à pasta 'admin_panel/'
                        const response = await fetch('sugestao_cores_ia.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ nicho: nicho, vibe: vibe, tema: tema })
                        });

                        const data = await response.json();

                        if (data.status === 'success') {
                            jsonTextarea.value = JSON.stringify(data.cores, null, 4);
                        } else {
                            throw new Error(data.message || "Erro desconhecido na API.");
                        }

                    } catch (error) {
                        alert("Erro ao chamar a IA: " + error.message);
                        jsonTextarea.value = "Erro: " + error.message;
                        switchTab('questionario');
                    } finally {
                        spinner.style.display = 'none';
                    }
                });

                // 4. Lógica para Aplicar as Cores
                btnAplicar.addEventListener('click', () => {
                    try {
                        const coresSugeridas = JSON.parse(jsonTextarea.value);

                        // Loop nas cores sugeridas
                        for (const chave in coresSugeridas) {
                            if (coresSugeridas.hasOwnProperty(chave)) {
                                const valor = coresSugeridas[chave];

                                // Encontra os inputs correspondentes no formulário principal
                                const textInput = formCores.querySelector(`#${chave}`);
                                const pickerInput = formCores.querySelector(`#${chave}_picker`);

                                if (textInput && pickerInput) {
                                    textInput.value = valor.toUpperCase();
                                    pickerInput.value = valor.toUpperCase();
                                }
                            }
                        }

                        // Loop nos toggles (se a IA sugerir)
                        const layoutSugerido = ['nav_borda_superior_ativa', 'footer_borda_superior_ativa', 'footer_credits_borda_ativa'];
                        layoutSugerido.forEach(chave => {
                            if (coresSugeridas.hasOwnProperty(chave)) {
                                const valor = coresSugeridas[chave].toString(); // 'true' or 'false'
                                const select = formCores.querySelector(`#${chave}`);
                                if (select) {
                                    select.value = valor;
                                }
                            }
                        });

                        iaModal.classList.remove('modal-open');

                    } catch (error) {
                        alert("Erro ao ler o JSON. Verifique se o formato está correto.\n\n" + jsonTextarea.value);
                        console.error("Erro no JSON da IA:", error);
                    }
                });

                // 5. Lógica para fechar o modal
                iaModal.querySelectorAll('[data-dismiss="modal"]').forEach(btn => {
                    btn.addEventListener('click', (e) => { e.preventDefault(); iaModal.classList.remove('modal-open'); });
                });
                iaModal.addEventListener('click', (e) => {
                    if (e.target === iaModal) { iaModal.classList.remove('modal-open'); }
                });
            }

            // Fallback para o info-modal (se o scripts.php não o controlar)
            if (infoModal) {
                infoModal.querySelectorAll('[data-dismiss="modal"]').forEach(btn => {
                    btn.addEventListener('click', (e) => { e.preventDefault(); infoModal.classList.remove('modal-open'); });
                });
                infoModal.addEventListener('click', (e) => {
                    if (e.target === infoModal) { infoModal.classList.remove('modal-open'); }
                });
            }

        }); // Fim do DOMContentLoaded
    </script>
</body>
</html>