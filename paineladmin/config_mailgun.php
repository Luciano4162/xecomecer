<?php
// config_mailgun.php - Configurações da API Mailgun e Templates de E-mail
session_start();
if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['admin_tipo'] ?? '') !== 'admin') {
    // header('Location: admin_login.php'); exit;
}

// ==========================================================
// 1. DEFINIÇÃO GLOBAL DAS CHAVES (APENAS E-MAIL)
// ==========================================================
// --- CHAVES API ---
$api_keys = [
    'MAILGUN_API_KEY' => 'Chave Secreta da API (Private Key)',
    'MAILGUN_DOMAIN' => 'Domínio de Envio',
    'MAILGUN_FROM_EMAIL' => 'Apenas o E-mail Remetente (ex: noreply@seusite.com)',
    'MAILGUN_API_URL' => 'URL Base da API (Ex: https://api.mailgun.net/v3)',
];

// --- CHAVES CONFIG E-MAIL ---
// ▼▼▼ MODIFICADO ▼▼▼
// Chave 'EMAIL_LINK_URL' removida daqui
$email_config_keys = [
    'EMAIL_COR_PRINCIPAL' => 'Cor Principal (Header/Botão) - Ex: #a4d32a',
    'EMAIL_COR_FUNDO' => 'Cor de Fundo do Container (Ex: #f5f5f5)',
    'EMAIL_TEXTO_BEM_VINDO' => 'Texto Principal de Boas-Vindas',
    'EMAIL_LINK_TEXTO' => 'Texto do Botão de Ação (Boas-Vindas)',
];
// ▲▲▲ FIM DA MODIFICAÇÃO ▲▲▲

// Combina todas as chaves que ESTA PÁGINA gerencia
$all_keys = array_merge($api_keys, $email_config_keys);

// --- VALORES PADRÃO (FALLBACK) ---
// ▼▼▼ MODIFICADO ▼▼▼
// Chave 'EMAIL_LINK_URL' removida daqui
$defaults = [
    'MAILGUN_API_KEY' => '',
    'MAILGUN_DOMAIN' => '',
    'MAILGUN_FROM_EMAIL' => '',
    'MAILGUN_API_URL' => 'https://api.mailgun.net/v3',
    'EMAIL_COR_PRINCIPAL' => '#a4d32a',
    'EMAIL_COR_FUNDO' => '#f5f5f5',
    'EMAIL_LINK_TEXTO' => 'Acessar Minha Conta Agora',
    'EMAIL_TEXTO_BEM_VINDO' => 'Sua conta foi criada com sucesso! Estamos muito felizes por você se juntar à nossa comunidade.'
];
// ▲▲▲ FIM DA MODIFICAÇÃO ▲▲▲


require_once '../config/db.php';
require_once '../funcoes.php'; // funcoes.php ainda é necessário para carregarConfigApi

$config_data = [];
$success_message = '';
$errors = [];

// Carrega as configurações da API e E-mail para definir as constantes
if (isset($pdo)) {
    // carregarConfigApi agora lê TODAS as chaves, incluindo NOME_DA_LOJA (da config_site)
    // e as chaves da config_api
    carregarConfigApi($pdo);
}

// Função auxiliar para processar qualquer grupo de chaves
function process_keys($pdo, $keys, &$config_data, &$errors, $success_msg, $defaults) {
    foreach ($keys as $chave => $descricao) {
        $valor = trim($_POST[$chave] ?? '');

        // Lógicas de fallback para valores padrões
        if (empty($valor) && isset($defaults[$chave])) {
            $valor = $defaults[$chave];
        }

        try {
            // UPSERT na config_api
            $sql = "INSERT INTO config_api (chave, valor, descricao)
                    VALUES (:chave, :valor, :descricao)
                    ON CONFLICT (chave) DO UPDATE
                    SET valor = EXCLUDED.valor,
                        descricao = EXCLUDED.descricao,
                        atualizado_em = CURRENT_TIMESTAMP";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':chave' => $chave,
                ':valor' => $valor,
                ':descricao' => $descricao
            ]);
            $config_data[$chave] = $valor;

        } catch (PDOException $e) {
            $errors['db'] = "Erro ao salvar: " . $e->getMessage();
            error_log("Erro no UPSERT da Configuração (config_api): " . $e->getMessage());
            return false;
        }
    }

    if (empty($errors)) {
        $GLOBALS['success_message'] = $success_msg;
        // Recarrega as constantes globais
        carregarConfigApi($pdo);
    }
    return true;
}


// ==========================================================
// 2. LÓGICA POST: PROCESSAMENTO DE FORMULÁRIOS
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Processa CHAVES DE API
    if (isset($_POST['salvar_api'])) {
        process_keys($pdo, $api_keys, $config_data, $errors, "Chaves de API salvas com sucesso!", $defaults);
    }

    // Processa CONFIGURAÇÃO DE E-MAIL
    if (isset($_POST['salvar_email_config'])) {
        process_keys($pdo, $email_config_keys, $config_data, $errors, "Personalização de E-mail salva com sucesso!", $defaults);
    }
}


// ==========================================================
// 3. BUSCA DOS VALORES ATUAIS (GET)
// ==========================================================
try {
    $chaves_array = array_keys($all_keys); // Apenas chaves desta página
    $placeholders = implode(',', array_fill(0, count($chaves_array), '?'));

    $stmt_fetch = $pdo->prepare("SELECT chave, valor FROM config_api WHERE chave IN ($placeholders)");
    $stmt_fetch->execute($chaves_array);
    $current_config = $stmt_fetch->fetchAll(PDO::FETCH_KEY_PAIR);

    foreach ($all_keys as $chave => $descricao) {
        if (isset($config_data[$chave])) {
            continue;
        }
        $valor_db = $current_config[$chave] ?? null;

        if (!empty($valor_db)) {
            $config_data[$chave] = $valor_db;
        }
        else {
            $config_data[$chave] = $defaults[$chave] ?? '';
        }
    }
} catch (PDOException $e) {
    $errors['db_fetch'] = "Erro ao buscar configurações: " . $e->getMessage();
    foreach ($all_keys as $chave => $descricao) {
        $config_data[$chave] = $config_data[$chave] ?? $defaults[$chave] ?? '';
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Configuração Mailgun</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>

    <style>
        /* ==========================================================
            CSS COMPLETO DO PAINEL ADMIN (MANTENDO PADRÃO DO INDEX)
            (CSS OMITIDO PARA ECONOMIZAR ESPAÇO - IDÊNTICO AO SEU ORIGINAL)
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
             --sidebar-width: 240px;
             --border-radius: 8px;
             --box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
             --danger-color: #e74c3c;
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

         /* --- Sidebar (CSS OMITIDO PARA FOCO, MANTENHA O SEU) --- */
         .sidebar { width: var(--sidebar-width); background-color: var(--sidebar-color); height: 100vh; position: fixed; left: 0; top: 0; padding: 1.5rem; display: flex; flex-direction: column; border-right: 1px solid var(--border-color); z-index: 1000; transition: transform 0.3s ease; box-shadow: var(--box-shadow); }
         .sidebar .logo-area { display: flex; flex-direction: column; align-items: center; margin-bottom: 1rem; } .sidebar .logo-circle { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 0.5rem; box-shadow: 0 0 10px rgba(74, 105, 189, 0.6); overflow: hidden; background-color: #fff; } .sidebar .logo-circle svg { color: var(--primary-color); width: 24px; height: 24px; } .sidebar .logo-text { font-size: 1rem; font-weight: 600; color: var(--text-color); text-align: center; } .sidebar .divider { width: 100%; height: 1px; background-color: var(--border-color); margin: 1rem 0; } .sidebar nav { flex-grow: 1; } .sidebar nav a { display: flex; align-items: center; gap: 0.8rem; padding: 0.8rem 1rem; color: var(--light-text-color); text-decoration: none; border-radius: var(--border-radius); margin-bottom: 0.5rem; transition: all 0.3s ease; border: 1px solid transparent; background-color: transparent; } .sidebar nav a:hover, .sidebar nav a.active { background-color: var(--glass-background); color: var(--text-color); border-color: var(--primary-color); box-shadow: 0 2px 8px rgba(74, 105, 189, 0.4); } .sidebar nav a svg { width: 20px; height: 20px; flex-shrink: 0; } .user-profile { position: relative; margin-top: auto; background-color: var(--glass-background); padding: 0.75rem; border-radius: var(--border-radius); display: flex; align-items: center; gap: 1rem; cursor: pointer; border: 1px solid var(--border-color); transition: all 0.3s ease; } .user-profile:hover { border-color: var(--primary-color); } .avatar { width: 35px; height: 35px; border-radius: 50%; background-color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.9rem; } .user-info .user-name { font-weight: 600; font-size: 0.85rem; line-height: 1.2; } .user-info .user-level { font-size: 0.7rem; color: var(--light-text-color); } .profile-dropdown { position: absolute; bottom: calc(100% + 10px); left: 0; width: 100%; background-color: #2c3a4f; border-radius: var(--border-radius); border: 1px solid var(--border-color); padding: 0.5rem; z-index: 20; visibility: hidden; opacity: 0; transform: translateY(10px); transition: all 0.3s ease; } .profile-dropdown.show { visibility: visible; opacity: 1; transform: translateY(0); } .profile-dropdown a { display: flex; gap: 0.75rem; padding: 0.75rem; color: var(--light-text-color); font-size: 0.85rem; border-radius: 6px; } .profile-dropdown a:hover { background-color: var(--glass-background); color: var(--text-color); }
         .sidebar nav .sidebar-submenu { padding-left: 20px; margin-top: -5px; margin-bottom: 5px; overflow: hidden; transition: max-height 0.3s ease-out; max-height: 0; } .sidebar nav .sidebar-submenu.open { max-height: 500px; } .sidebar nav a.has-children { display: flex; justify-content: space-between; align-items: center; } .sidebar nav a .menu-chevron { width: 16px; height: 16px; color: var(--light-text-color); transition: transform 0.3s ease; } .sidebar nav a.open .menu-chevron { transform: rotate(90deg); } .sidebar-submenu a { font-size: 0.9em; padding: 0.7rem 1rem 0.7rem 1.5rem; color: var(--light-text-color); position: relative; } .sidebar-submenu a::before { content: ''; position: absolute; left: 0.5rem; top: 50%; transform: translateY(-50%); width: 4px; height: 4px; border-radius: 50%; background-color: var(--light-text-color); transition: all 0.3s ease; } .sidebar-submenu a:hover { color: var(--text-color); background-color: transparent; border-color: transparent; box-shadow: none; } .sidebar-submenu a:hover::before { background-color: var(--primary-color); } .sidebar-submenu a.active-child { color: #fff; font-weight: 600; } .sidebar-submenu a.active-child::before { background-color: var(--primary-color); transform: translateY(-50%) scale(1.5); }

         /* --- Conteúdo Principal --- */
         .main-content { margin-left: var(--sidebar-width); flex-grow: 1; padding: 2rem 2.5rem; min-height: 100vh; overflow-y: auto; transition: margin-left 0.3s ease, width 0.3s ease; width: calc(100% - var(--sidebar-width)); }
         .content-header { margin-bottom: 2rem; background: var(--glass-background); padding: 1.5rem 2rem; border-radius: var(--border-radius); border: 1px solid var(--border-color); backdrop-filter: blur(5px); }
         .content-header h1 { font-size: 1.8rem; font-weight: 600; color: var(--primary-color); margin: 0 0 0.25rem 0; }
         .content-header p { font-size: 1rem; color: var(--light-text-color); margin: 0; }

         /* --- Estilos do Formulário Específico --- */
         .config-card { background: var(--sidebar-color); padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--box-shadow); border: 1px solid var(--border-color); }
         .form-group { margin-bottom: 1.5rem; }
         .form-group label { display: block; font-weight: 500; margin-bottom: 0.5rem; color: var(--text-color); font-size: 0.95em; }
         .form-group input[type="text"], .form-group textarea { width: 100%; padding: 0.75rem; background-color: var(--background-color); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-color); font-size: 1rem; transition: border-color 0.3s ease, box-shadow 0.3s ease; }
         .form-group input[type="text"]:focus, .form-group textarea:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 5px var(--primary-color); }

         .hint { font-size: 0.75rem; color: var(--light-text-color); margin-top: 5px; margin-bottom: 20px; }

         .btn-primary { background-color: var(--primary-color); color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 1em; font-weight: 600; transition: background-color 0.2s; }
         .btn-primary:hover { background-color: #385392; }
         .alert-success { background-color: var(--success-bg); color: var(--success-text); padding: 1rem; border: 1px solid var(--success-text); border-radius: var(--border-radius); margin-bottom: 1.5rem; }
         .alert-error { background-color: var(--error-bg); color: var(--error-text); padding: 1rem; border: 1px solid var(--error-text); border-radius: var(--border-radius); margin-bottom: 1.5rem; }

         .form-group textarea { height: 100px; resize: vertical; }

         .color-input-group { display: flex; align-items: center; gap: 10px; }
         .color-input-group input[type="color"] { width: 48px; height: 48px; padding: 0.25rem; border: none; border-radius: var(--border-radius); background-color: rgba(0, 0, 0, 0.2); cursor: pointer; flex-shrink: 0; }
         .color-input-group input[type="text"] { flex-grow: 1; font-family: 'Courier New', Courier, monospace; text-transform: uppercase; }

         /* --- Mobile / Responsivo --- */
         .menu-toggle { display: none; position: fixed; top: 1rem; left: 1rem; z-index: 1001; cursor: pointer; padding: 8px; background-color: var(--sidebar-color); border-radius: var(--border-radius); border: 1px solid var(--border-color); box-shadow: var(--box-shadow);}
         .menu-toggle svg { width: 20px; height: 20px; color: var(--text-color); display: block; }
         @media (max-width: 1024px) {
             body { position: relative; }
             .sidebar { width: 280px; transform: translateX(-280px); box-shadow: 5px 0 15px rgba(0, 0, 0, 0.5); z-index: 1002; height: 100%; overflow-y: auto; }
             .menu-toggle { display: flex; }
             .main-content { margin-left: 0; width: 100%; padding: 1.5rem; padding-top: 5rem; }
             body.sidebar-open .sidebar { transform: translateX(0); }
             body.sidebar-open::after { content: ''; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.7); z-index: 1001; backdrop-filter: blur(2px);}
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
            <h1>📧 Configuração de E-mail</h1>
            <p>Gerencie as chaves de API (Mailgun) e a aparência dos e-mails transacionais.</p>
        </div>

        <div class="config-card">

            <?php if ($success_message): ?>
                <div class="alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert-error">
                    Houve um erro: <?php echo htmlspecialchars(implode(', ', $errors)); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="config_mailgun.php">

                <h2>Chaves de API da Mailgun</h2>
                <p class="hint" style="margin-bottom:20px;">Necessário para que qualquer e-mail seja enviado.</p>

                <?php
                $api_keys_form = $api_keys;
                foreach ($api_keys_form as $chave => $descricao):
                ?>
                    <div class="form-group">
                        <label for="<?php echo strtolower($chave); ?>"><?php echo htmlspecialchars($descricao); ?></label>
                        <input type="text"
                               id="<?php echo strtolower($chave); ?>"
                               name="<?php echo $chave; ?>"
                               value="<?php echo htmlspecialchars($config_data[$chave] ?? ''); ?>"
                               required
                               <?php echo ($chave === 'MAILGUN_API_URL') ? 'placeholder="https://api.mailgun.net/v3"' : ''; ?>
                        >
                    </div>
                <?php endforeach; ?>

                <div class="form-group" style="margin-top: 30px;">
                    <button type="submit" name="salvar_api" class="btn-primary">Salvar Chaves de API</button>
                </div>
            </form>

            <div style="height: 1px; background: var(--border-color); margin: 30px 0;"></div>

            <form method="POST" action="config_mailgun.php">

                <h2>Personalização do E-mail de Boas-Vindas</h2>
                <p class="hint" style="margin-bottom:20px;">Define cores e textos usados no e-mail de confirmação de cadastro.</p>

                <?php
                // ▼▼▼ MODIFICADO ▼▼▼
                // O loop agora usa o array $email_config_keys que não tem mais 'EMAIL_LINK_URL'.
                // O campo de formulário para a URL não será mais renderizado.
                $email_keys_form = $email_config_keys;
                // ▲▲▲ FIM DA MODIFICAÇÃO ▲▲▲
                foreach ($email_keys_form as $chave => $descricao):
                ?>
                    <div class="form-group">
                        <label for="<?php echo strtolower($chave); ?>"><?php echo htmlspecialchars($descricao); ?></label>

                        <?php if ($chave === 'EMAIL_TEXTO_BEM_VINDO'): ?>
                            <textarea id="<?php echo strtolower($chave); ?>" name="<?php echo $chave; ?>" required><?php echo htmlspecialchars($config_data[$chave] ?? ''); ?></textarea>
                        <?php elseif (strpos($chave, 'COR') !== false): // Campos de cor ?>
                            <div class="color-input-group">
                                <input type="color" id="<?php echo strtolower($chave); ?>_picker" value="<?php echo htmlspecialchars($config_data[$chave] ?? '#FFFFFF'); ?>" title="Selecionar cor">
                                <input type="text"
                                   name="<?php echo $chave; ?>"
                                   id="<?php echo strtolower($chave); ?>"
                                   value="<?php echo htmlspecialchars($config_data[$chave] ?? ''); ?>"
                                   required
                                   pattern="#[A-Fa-f0-9]{6}"
                                >
                            </div>
                        <?php else: // Campos de texto normais (como EMAIL_LINK_TEXTO) ?>
                            <input type="text"
                               id="<?php echo strtolower($chave); ?>"
                               name="<?php echo $chave; ?>"
                               value="<?php echo htmlspecialchars($config_data[$chave] ?? ''); ?>"
                               required
                            >
                        <?php endif; ?>

                    </div>
                <?php endforeach; ?>


                <div class="form-group" style="margin-top: 30px;">
                    <button type="submit" name="salvar_email_config" class="btn-primary">Salvar Personalização</button>
                </div>
            </form>
        </div>
    </main>

    <script>
        // --- JavaScript Geral (DOMContentLoaded) ---
        document.addEventListener('DOMContentLoaded', function() {
             // Inicializa o fundo de partículas
            if (typeof particlesJS === 'function') {
                particlesJS('particles-js', {"particles":{"number":{"value":60,"density":{"enable":true,"value_area":800}},"color":{"value":"#4a69bd"},"shape":{"type":"circle"},"opacity":{"value":0.4,"random":false},"size":{"value":3,"random":true},"line_linked":{"enable":true,"distance":150,"color":"#ffffff","opacity":0.1,"width":1},"move":{"enable":true,"speed":1.5,"direction":"none","random":false,"straight":false,"out_mode":"out","bounce":false}},"interactivity":{"detect_on":"canvas","events":{"onhover":{"enable":true,"mode":"repulse"},"onclick":{"enable":true,"mode":"push"},"resize":true}},"retina_detect":true});
            }

            // --- Sincronizador de Cores ---
            const colorGroups = document.querySelectorAll('.color-input-group');
            colorGroups.forEach(group => {
                const colorPicker = group.querySelector('input[type="color"]');
                const textInput = group.querySelector('input[type="text"]');

                if (colorPicker && textInput) {
                    // Inicializa o picker com o valor do input (que veio do DB ou fallback)
                    if (textInput.value) {
                        colorPicker.value = textInput.value;
                    }

                    colorPicker.addEventListener('input', (e) => {
                        let val = e.target.value.toUpperCase();
                        textInput.value = val;
                        if (textInput.checkValidity()) {
                           colorPicker.value = val;
                        }
                    });

                    textInput.addEventListener('change', (e) => {
                        let val = e.target.value.toUpperCase();
                        if (!val.startsWith('#')) val = '#' + val;

                        if (/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/i.test(val)) {
                            colorPicker.value = val;
                            e.target.value = val;
                        } else {
                            e.target.value = colorPicker.value;
                            alert("Código de cor inválido. Use o formato #RRGGBB.");
                        }
                    });
                }
            });

            // (A lógica do Sidebar deve ser incluída pelo admin_sidebar.php)
        });
    </script>
</body>
</html>