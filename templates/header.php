<?php
// templates/header.php
// Garante que a conexão exista (se já não foi incluída antes)
if (!isset($pdo)) {
    require_once __DIR__ . '/../config/db.php';
}
// Inclui as funções (necessário para carregar as cores e configs)
if (!function_exists('carregarConfigApi')) { // Evita redeclaração
     require_once __DIR__ . '/../funcoes.php';
}

// ==========================================================
// FUNÇÃO HELPER DE CORES
// ==========================================================
/**
 * Clareia ou escurece um código de cor HEX.
 */
if (!function_exists('adjust_color_brightness')) { // Garante que a função não seja redeclarada
    function adjust_color_brightness($hex, $steps) {
        $hex = str_replace('#', '', $hex);

        if (strlen($hex) == 3) {
            $hex = str_repeat(substr($hex,0,1), 2) . str_repeat(substr($hex,1,1), 2) . str_repeat(substr($hex,2,1), 2);
        }

        // CORREÇÃO: Removido 'null' de substr() para compatibilidade com PHP 8+
        $r = hexdec(substr($hex,0,2));
        $g = hexdec(substr($hex,2,2));
        $b = hexdec(substr($hex,4,2));

        $r = max(0, min(255, $r + $steps));
        $g = max(0, min(255, $g + $steps));
        $b = max(0, min(255, $b + $steps));

        return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT)
                 . str_pad(dechex($g), 2, '0', STR_PAD_LEFT)
                 . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
    }
}
// ==========================================================
// FIM DA FUNÇÃO HELPER
// ==========================================================


// --- Verificar status de login ---
$is_logged_in = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
$user_nome = $_SESSION['user_nome'] ?? 'Visitante';
$primeiro_nome = $is_logged_in ? htmlspecialchars(explode(' ', $user_nome)[0]) : 'Visitante';


// --- Busca Logo ---
$logo_url = '../uploads/default-logo.png';
try {
    $stmt_logo = $pdo->prepare("SELECT valor FROM config_site WHERE chave = :chave");
    $stmt_logo->execute(['chave' => 'logo_url']);
    $resultado_logo = $stmt_logo->fetchColumn();
    if ($resultado_logo) {
         if (preg_match('/^(http:\/\/|https:\/\/|\/\/)/i', $resultado_logo)) {
             $logo_url = $resultado_logo;
         } else {
             $base_path = ltrim(trim($resultado_logo), './');
             $logo_url = "/" . ltrim($base_path, '/');
         }
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar logo no header.php: " . $e->getMessage());
}


// --- Busca Categorias ---
$category_tree = [];
try {
    $stmt_cat = $pdo->query("SELECT * FROM categorias ORDER BY parent_id ASC, ordem ASC");
    $all_categories = $stmt_cat->fetchAll();
    $lookup = [];
    foreach ($all_categories as $cat) {
        $lookup[$cat['id']] = $cat;
        $lookup[$cat['id']]['children'] = [];
    }
    foreach ($lookup as $id => &$cat_ref) {
        if ($cat_ref['parent_id'] != null) {
            if (isset($lookup[$cat_ref['parent_id']])) {
                $lookup[$cat_ref['parent_id']]['children'][] =& $cat_ref;
            }
        } else {
            $category_tree[] =& $cat_ref;
        }
    }
    unset($cat_ref);
} catch (PDOException $e) {
    error_log("Erro ao buscar categorias no header.php: " . $e->getMessage());
}

// ==========================================================
// BUSCAR CORES E CONFIGS DIN MICAS
// ==========================================================
$configs_padrao = [
    'cor_acento' => '#9ad700',
    'cor_fundo_header' => '#e9efe8',
    'cor_fundo_nav' => '#fff',
    'cor_texto_principal' => '#333',
    'cor_texto_medio' => '#555',
    'cor_texto_claro' => '#777',
    'cor_borda_clara' => '#eee',
    'cor_borda_media' => '#ccc',
    'cor_erro' => '#dc3545',
    'cor_sucesso' => '#28a745',
    'nav_borda_superior_ativa' => 'true',
    'cor_acento_hover' => '#7bb500',
    'cor_fundo_hover_claro' => '#f2f2f2'
];
$configs = [];
try {
    // Lista de todas as chaves de configuração do site
    $chaves_config_site = [
        'cor_acento', 'cor_fundo_header', 'cor_fundo_nav',
        'cor_texto_principal', 'cor_texto_medio', 'cor_texto_claro',
        'cor_borda_clara', 'cor_borda_media', 'cor_erro', 'cor_sucesso',
        'nav_borda_superior_ativa', 'footer_borda_superior_ativa', 'footer_credits_borda_ativa',
        'cor_acento_hover', 'cor_fundo_hover_claro'
    ];
    $placeholders = implode(',', array_fill(0, count($chaves_config_site), '?'));

    $stmt_config = $pdo->prepare("SELECT chave, valor FROM config_site WHERE chave IN ($placeholders)");
    $stmt_config->execute($chaves_config_site);

    $config_db = $stmt_config->fetchAll(PDO::FETCH_KEY_PAIR);
    $configs = array_merge($configs_padrao, $config_db);
} catch (PDOException $e) {
    error_log("Erro ao buscar cores/configs dinâmicas: " . $e->getMessage());
    $configs = $configs_padrao;
}
// ==========================================================
// FIM DA LÓGICA DE CONFIGS
// ==========================================================

// --- Processa a variável da borda NAV ---
$nav_border_style = 'none';
if (isset($configs['nav_borda_superior_ativa']) && filter_var($configs['nav_borda_superior_ativa'], FILTER_VALIDATE_BOOLEAN)) {
    $nav_border_style = '1px solid ' . htmlspecialchars($configs['cor_borda_media']);
}

// ==========================================================
// GERAR CORES DE HOVER (LENDO DO DB)
// ==========================================================
$cor_acento_hover = $configs['cor_acento_hover'] ?? $configs_padrao['cor_acento_hover'];
$cor_fundo_hover_claro = $configs['cor_fundo_hover_claro'] ?? $configs_padrao['cor_fundo_hover_claro'];
// ==========================================================
?>

<style>
    /* CSS GLOBAL (Fallback) */
    :root {
        /* Cores Base */
        --green-accent: #9ad700;
        --header-footer-bg: #e9efe8;
        --nav-bg: #fff;
        --text-color-dark: #333;
        --text-color-medium: #555;
        --text-color-light: #777;
        --border-color-light: #eee;
        --border-color-medium: #ccc;
        --error-color: #dc3545;
        --success-color: #28a745;

        /* Cores de Borda (controladas pelo admin) */
        --nav-border-top: none;

        /* NOVO: Cores de Hover (Fallback) */
        --cor-acento-hover: #8cc600;
        --cor-acento-fundo-hover: #f7f7f7;
    }

    html, body { height: 100%; }
    body { margin: 0; font-family: Arial, sans-serif; background-color: #f9f9f9; color: var(--text-color-dark); display: flex; flex-direction: column; }
    .container { width: 90%; max-width: 1140px; margin: 0 auto; padding: 0 15px; box-sizing: border-box; }
    a { text-decoration: none; color: inherit; }
    button { cursor: pointer; border: none; background: none; padding: 0;}
    *, *::before, *::after { box-sizing: border-box; }

    /* --- Header --- */
    .sticky-header-wrapper { background-color: var(--header-footer-bg); }
    header { background-color: var(--header-footer-bg); width: 100%; padding: 0 2%; box-sizing: border-box; flex-shrink: 0; }
    .header-top { display: flex; justify-content: flex-end; padding: 8px 2%; box-sizing: border-box; width: 100%; background-color: var(--header-footer-bg); transition: transform 0.3s ease-in-out; transform: translateY(0); will-change: transform; }
    .header-top a { font-size: 11px; color: var(--text-color-medium); margin-left: 20px; text-transform: uppercase; }
    .header-top-hidden { transform: translateY(-100%); }
    .header-main { display: flex; align-items: center; justify-content: center; height: auto; padding: 0px 0; background-color: var(--header-footer-bg); }
    .header-main > div { padding: 0 10px; }
    .hamburger-menu { display: none; background: none; border: none; padding: 0;}
    .logo { flex-shrink: 0; }
    .search-bar { text-align: center; width: 450px; margin: 0 30px; }
    .search-bar form { position: relative; width: 100%; }
    .search-bar input[type="search"]::placeholder { color: #999; opacity: 1; }
    .search-bar input[type="search"] { width: 100%; height: 40px; border-radius: 20px; border: 1px solid var(--border-color-medium); background-color: #fff; padding: 0 50px 0 20px; box-sizing: border-box; font-size: 14px; }
    .search-bar button { position: absolute; right: 5px; top: 50%; transform: translateY(-50%); width: 35px; height: 35px; border: none; background: none; cursor: pointer; color: var(--text-color-light); display: flex; align-items: center; justify-content: center; padding: 0; }
    .search-bar button svg { width: 20px; height: 20px; color: var(--text-color-dark); }
    .user-actions { flex-shrink: 0; display: flex; justify-content: flex-end; align-items: center; }
    .action-item { display: flex; align-items: center; margin-left: 25px; cursor: pointer; }
    .action-item svg { width: 28px; height: 28px; margin-right: 8px; color: var(--text-color-dark); stroke-width: 1.5; }
    .action-item .text-content { display: flex; flex-direction: column; }
    .action-item .text-content strong { font-size: 14px; color: var(--text-color-dark); line-height: 1.2; }
    .action-item .text-content span { font-size: 12px; color: var(--text-color-dark); line-height: 1.2; }

    /* --- Nav --- */
    .header-nav {
        background-color: var(--nav-bg);
        display: flex;
        justify-content: center;
        padding: 10px 0;
        border-top: var(--nav-border-top); /* <- Usa a variável */
        flex-shrink: 0;
    }
    .header-nav ul { list-style: none; margin: 0; padding: 0; display: flex; }
    .header-nav li { position: relative; }
    .header-nav li a { padding: 10px 15px; font-size: 13px; font-weight: bold; color: var(--text-color-dark); text-transform: uppercase; transition: background-color 0.2s ease, color 0.2s ease; display: block; }
    .mobile-nav-header, .header-nav .chevron { display: none; }
    .header-nav .sub-menu { display: none; }
    .header-nav li.visible > a { background-color: var(--header-footer-bg); color: #fff; }
    .header-nav .sub-menu { opacity: 0; visibility: hidden; transition: opacity 0.2s ease, visibility 0s linear 0.2s; position: absolute; left: 0; top: 100%; background-color: #fff; padding: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); border: 2px solid var(--green-accent); border-radius: 8px; margin-top: 5px; width: 220px; min-width: max-content; z-index: 100; list-style: none; margin-left: 0; }
    .header-nav li.visible .sub-menu { opacity: 1; visibility: visible; transition: opacity 0.2s ease; display: block; }
    .header-nav .sub-menu li { border: none; width: 100%; }
    .header-nav .sub-menu li a { padding: 8px 10px; font-size: 14px; font-weight: normal; color: var(--text-color-dark); text-transform: none; display: block; }
    .header-nav .sub-menu li a:hover { color: var(--green-accent); background-color: var(--cor-acento-fundo-hover); }

    /* --- Dropdown Conta --- */
    #minha-conta { position: relative; cursor: pointer; }
    .text-content .dropdown-chevron { display: inline-block; width: 6px; height: 6px; border-right: 2px solid var(--text-color-light); border-bottom: 2px solid var(--text-color-light); transform: rotate(45deg); margin-left: 5px; transition: transform 0.2s ease; vertical-align: middle; }
    #minha-conta.visible .dropdown-chevron { transform: rotate(225deg); }
    .account-dropdown { opacity: 0; visibility: hidden; transition: opacity 0.2s ease, visibility 0s linear 0.2s; position: absolute; top: calc(100% + 10px); right: -15px; width: 240px; background-color: #4a4a4a; color: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.15); z-index: 100; }
    .account-dropdown.visible { opacity: 1; visibility: visible; transition: opacity 0.2s ease; }
    .account-dropdown::before { content: ""; position: absolute; bottom: 100%; right: 25px; margin-bottom: -1px; border-left: 10px solid transparent; border-right: 10px solid transparent; border-bottom: 10px solid #4a4a4a; }
    .account-dropdown .btn-entrar { display: block; width: 100%; padding: 10px; background-color: #fff; color: #333; font-size: 15px; font-weight: bold; text-align: center; border: none; border-radius: 25px; cursor: pointer; box-sizing: border-box; transition: transform 0.2s ease; }
    .account-dropdown .btn-entrar:hover { transform: scale(1.03); }
    .account-dropdown .divider { text-align: center; margin: 15px 0; color: #ccc; position: relative; font-size: 13px; }
    .account-dropdown .divider::before, .account-dropdown .divider::after { content: ""; position: absolute; top: 50%; width: 38%; height: 1px; background-color: #888; }
    .account-dropdown .divider::before { left: 0; } .account-dropdown .divider::after { right: 0; }
    .account-dropdown .link-cadastrar { display: block; text-align: center; color: #fff; font-size: 14px; }
    .account-dropdown .link-cadastrar:hover { text-decoration: underline; }

    /* --- Sticky Header --- */
    @media (min-width: 769px) {
        .sticky-header-wrapper { position: sticky; top: 0; z-index: 999; background-color: var(--header-footer-bg); }
        .header-top-hidden + header { box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    }
    .sticky-header-wrapper > header,
    .sticky-header-wrapper > nav.header-nav { margin-bottom: 1px; }
    .sticky-header-wrapper > header { background-color: var(--header-footer-bg); }
    .sticky-header-wrapper > header > .header-main { background-color: var(--header-footer-bg); }
    .sticky-header-wrapper > nav.header-nav { background-color: var(--nav-bg); }

    /* --- Main Content (Layout Base) --- */
    .main-content { flex-grow: 1; padding: 20px 0; background-color: #f9f9f9; }

    /* --- Modais --- */
    .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6); display: flex; justify-content: center; align-items: center; z-index: 1000; opacity: 0; visibility: hidden; transition: opacity 0.3s ease, visibility 0s linear 0.3s; }
    .modal-overlay.modal-open { opacity: 1; visibility: visible; transition: opacity 0.3s ease; }
    .modal-content { background-color: #fff; padding: 30px; border-radius: 5px; width: 90%; max-width: 450px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); position: relative; transform: translateY(-20px); transition: transform 0.3s ease-out; }
    .modal-overlay.modal-open .modal-content { transform: translateY(0); }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid var(--border-color-light); }
    .modal-title { font-size: 1.3em; color: var(--text-color-dark); margin: 0; font-weight: normal; }
    .modal-close { background: none; border: none; font-size: 1.8em; font-weight: bold; color: #aaa; cursor: pointer; padding: 0 5px; line-height: 1; }
    .modal-close:hover { color: #777; }
    .modal-body .form-group { margin-bottom: 15px; text-align: left; }
    .modal-body .form-group label { display: block; margin-bottom: 5px; font-weight: normal; color: var(--text-color-medium); }
    .modal-body .form-group input { width: 100%; padding: 12px; border: 1px solid var(--border-color-medium); border-radius: 4px; box-sizing: border-box; font-size: 1em; }
    .modal-footer { margin-top: 25px; text-align: right; }
    .modal-footer .btn-primary { min-width: 120px; }
    .modal-body .error-message { color: var(--error-color); font-size: 0.9em; margin-top: 10px; text-align: left; }

    /* --- Modal Carrinho --- */
    .cart-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6); z-index: 1001; opacity: 0; visibility: hidden; transition: opacity 0.3s ease; }
    .cart-overlay.open { opacity: 1; visibility: visible; }
    .cart-modal { position: fixed; top: 0; right: 0; width: 100%; max-width: 400px; height: 100%; background-color: #fff; z-index: 1002; display: flex; flex-direction: column; box-shadow: -5px 0 15px rgba(0,0,0,0.15); transform: translateX(100%); transition: transform 0.3s ease-in-out; }
    .cart-modal.open { transform: translateX(0); }
    .cart-header { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-bottom: 1px solid var(--border-color-light); flex-shrink: 0; }
    .cart-header-title { display: flex; align-items: center; gap: 10px; font-size: 1.1em; font-weight: bold; color: var(--text-color-dark); }
    .cart-header-title svg { width: 24px; height: 24px; stroke: var(--text-color-dark); fill: none; stroke-width: 1.5; }
    .cart-close-btn { cursor: pointer; padding: 5px; }
    .cart-close-btn svg { width: 40px; height: 40px; fill: var(--text-color-dark); }
    .cart-body { flex-grow: 1; overflow-y: auto; padding: 20px; }
    .cart-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--text-color-light); padding: 20px 0; }
    .cart-empty svg { width: 60px; height: 60px; stroke: var(--border-color-medium); fill: none; stroke-width: 1.5; }
    .cart-empty p { font-size: 1.1em; font-weight: bold; color: var(--text-color-medium); margin-top: 15px; }
    .cart-footer { flex-shrink: 0; border-top: 1px solid var(--border-color-light); padding: 20px; background-color: #f9f9f9; }
    .cart-footer-info { font-size: 0.8em; color: var(--text-color-light); border-bottom: 1px dashed var(--border-color-medium); padding-bottom: 15px; margin-bottom: 15px; }
    .cart-footer-info p { margin: 5px 0; }
    .cart-footer-actions { display: flex; flex-direction: column; gap: 15px; }
    .cart-continue-btn { text-align: center; color: var(--text-color-medium); font-size: 0.9em; font-weight: bold; }
    .cart-continue-btn:hover { color: var(--text-color-dark); }
    .cart-checkout-btn { width: 100%; text-align: center; padding: 15px; font-size: 1em; border-radius: 25px; }

    /* --- Botões --- */
    .btn { display: inline-block; padding: 12px 35px; border-radius: 4px; font-size: 1em; font-weight: normal; text-align: center; text-decoration: none; cursor: pointer; transition: all 0.3s ease; border: 1px solid transparent; min-width: 150px; }
    .btn-primary { background-color: var(--green-accent); color: #fff; border-color: var(--green-accent); }
    .btn-primary:hover {
        background-color: var(--cor-acento-hover);
        border-color: var(--cor-acento-hover);
    }
    .btn-secondary { background-color: #fff; color: var(--green-accent); border-color: var(--green-accent); }
    .btn-secondary:hover {
        background-color: var(--cor-acento-fundo-hover);
        border-color: var(--cor-acento-hover);
        color: var(--cor-acento-hover);
    }
    .btn-light { background-color: #f8f9fa; color: #333; border-color: #ccc; }
    .btn-light:hover { background-color: #e2e6ea; border-color: #bbb; }
    .btn-danger { background-color: var(--error-color); color: #fff; border-color: var(--error-color); }
    .btn-danger:hover { background-color: #c82333; border-color: #bd2130; }

    /* --- Itens no Carrinho --- */
    .cart-items-list { display: flex; flex-direction: column; gap: 15px; width: 100%; }
    .cart-item { display: flex; gap: 10px; border-bottom: 1px solid var(--border-color-light); padding-bottom: 10px; position: relative; }
    .cart-item-img { width: 70px; height: 70px; object-fit: contain; border: 1px solid var(--border-color-light); border-radius: 4px; }
    .cart-item-info { flex-grow: 1; display: flex; flex-direction: column; }
    .cart-item-name { font-size: 0.9em; font-weight: bold; color: var(--text-color-dark); margin: 0 0 5px 0; }
    .cart-item-controls { display: flex; align-items: center; gap: 10px; margin: 5px 0; }
    .cart-qty-btn { width: 22px; height: 22px; background-color: var(--border-color-light); color: var(--text-color-medium); border: none; border-radius: 50%; font-weight: bold; cursor: pointer; }
    .cart-qty-btn:hover { background-color: var(--border-color-medium); color: var(--text-color-dark); }
    .cart-item-qty { font-size: 0.9em; font-weight: bold; }
    .cart-item-price { font-size: 1em; font-weight: bold; color: var(--text-color-dark); margin: 0; }
    .cart-item-remove { position: absolute; top: 5px; right: 5px; background: none; border: none; font-size: 1.5rem; color: var(--text-color-light); cursor: pointer; padding: 0 5px; line-height: 1; }
    .cart-item-remove:hover { color: var(--error-color); }
    .cart-summary { padding-top: 15px; margin-top: 15px; border-top: 1px solid var(--border-color-medium); }
    .cart-subtotal-display { display: flex; justify-content: space-between; font-size: 1.1em; font-weight: bold; margin-bottom: 15px; }
    .cart-clear-btn { display: block; text-align: center; font-size: 0.9em; color: var(--error-color); text-decoration: underline; cursor: pointer; }
    .cart-clear-btn:hover { color: #a00; }

    /* --- Responsividade (Header/Nav/Modals/Cart) --- */
    @media (max-width: 768px) {
        header { padding: 0; } .header-top, #minha-conta, .action-item .text-content { display: none; } .header-main { flex-wrap: wrap; justify-content: space-between; padding: 10px 15px; } .hamburger-menu { display: block; order: 1; background: none; border: none; cursor: pointer; padding: 0; } .hamburger-menu svg { width: 30px; height: 30px; stroke: #333; } .logo { order: 2; flex-grow: 1; text-align: center; margin: 0; padding: 0; } .logo img { max-width: 180px !important; max-height: 100px !important; height: auto !important; width: auto !important; } .user-actions { order: 3; }
        .action-item { margin-left: 0; cursor: pointer; } .action-item svg { margin-right: 0; }
        .search-bar { order: 4; width: 100%; margin: 15px 0 10px 0; padding: 0; }
        .header-nav { position: fixed; top: 0; left: 0; width: 280px; height: 100%; background: #fff; z-index: 1000; flex-direction: column; justify-content: flex-start; padding: 0; border-top: none; transform: translateX(-100%); transition: transform 0.3s ease-in-out; overflow-y: auto; } .header-nav.nav-open { transform: translateX(0); } .mobile-nav-header { display: flex; align-items: center; padding: 10px; background: var(--header-footer-bg); color: #fff; } .mobile-nav-close { display: block; background: none; border: none; cursor: pointer; padding: 0 10px; } .mobile-nav-close svg { width: 24px; height: 24px; stroke: #fff; } .mobile-nav-account { flex-grow: 1; padding-left: 10px; } .mobile-nav-account span { display: block; font-size: 12px; } .mobile-nav-account a { font-size: 16px; font-weight: bold; } .header-nav ul { flex-direction: column; width: 100%; } .header-nav li { border-bottom: 1px solid #f0f0f0; } .header-nav li a { padding: 15px 20px; font-size: 14px; font-weight: normal; color: #333; text-transform: none; display: block; } .header-nav li.has-children > a { display: flex; justify-content: space-between; align-items: center; } .header-nav .chevron { display: block; width: 8px; height: 8px; border-right: 2px solid #555; border-bottom: 2px solid #555; transform: rotate(45deg); transition: transform 0.2s ease; } .header-nav li.item-open > a > .chevron { transform: rotate(225deg); } .header-nav .sub-menu { display: block; position: static; width: 100%; border: none; border-radius: 0; margin-top: 0; box-shadow: none; padding: 0; margin-left: 0; list-style: none; background: #f7f7f7; max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; } .header-nav li.item-open .sub-menu { max-height: 500px; } .header-nav .sub-menu li a { padding-left: 35px; font-size: 13px; color: #444; } .header-nav .sub-menu li a:hover { background-color: #f0f0f0; color: var(--green-accent); }
        .modal-content { width: 90%; padding: 20px; } .modal-title { font-size: 1.2em; }
        .cart-modal { max-width: 100%; width: 100%; }
    }
</style>

<style id="dynamic-colors-root">
    :root {
        --green-accent: <?php echo htmlspecialchars($configs['cor_acento']); ?>;
        --header-footer-bg: <?php echo htmlspecialchars($configs['cor_fundo_header']); ?>;
        --nav-bg: <?php echo htmlspecialchars($configs['cor_fundo_nav']); ?>;
        --text-color-dark: <?php echo htmlspecialchars($configs['cor_texto_principal']); ?>;
        --text-color-medium: <?php echo htmlspecialchars($configs['cor_texto_medio']); ?>;
        --text-color-light: <?php echo htmlspecialchars($configs['cor_texto_claro']); ?>;
        --border-color-light: <?php echo htmlspecialchars($configs['cor_borda_clara']); ?>;
        --border-color-medium: <?php echo htmlspecialchars($configs['cor_borda_media']); ?>;
        --error-color: <?php echo htmlspecialchars($configs['cor_erro']); ?>;
        --success-color: <?php echo htmlspecialchars($configs['cor_sucesso']); ?>;

        /* Borda da Nav (Dinâmica) */
        --nav-border-top: <?php echo $nav_border_style; ?>;

        /* Cores de Hover (Dinâmicas) */
        --cor-acento-hover: <?php echo htmlspecialchars($cor_acento_hover); ?>;
        --cor-acento-fundo-hover: <?php echo htmlspecialchars($cor_fundo_hover_claro); ?>;
    }
</style>

<div class="sticky-header-wrapper">

    <header>
        <div class="header-top">
            <a href="rastreio.php">Rastrear Pedido</a>
            <a href="suporte.php">Ajuda e Suporte</a>
        </div>

        <div class="header-main">
            <button class="hamburger-menu" id="hamburger-btn">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>
            <div class="logo">
                <a href="index.php">
                    <img alt="Logo da Loja" style="height:119px;width:221px; max-width:221px!important; max-height:120px!important;"
                         src="<?php echo htmlspecialchars($logo_url); ?>"
                         width="221"
                         height="119">
                </a>
            </div>
            <div class="search-bar">

                <form action="buscar.php" method="GET">
                    <input type="search" name="q" placeholder="O que deseja procurar?">
                    <button type="submit">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                    </button>
                </form>
            </div>
            <div class="user-actions">

                <div class="action-item" id="minha-conta">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>

                    <div class="text-content">
                        <?php if ($is_logged_in): ?>
                            <strong>Olá, <?php echo $primeiro_nome; ?></strong>
                            <span>Minha Conta <span class="dropdown-chevron"></span></span>
                        <?php else: ?>
                            <strong>Minha Conta</strong>
                            <span>Entrar / Cadastrar <span class="dropdown-chevron"></span></span>
                        <?php endif; ?>
                    </div>

                    <div class="account-dropdown" id="account-dropdown-content">
                        <?php if ($is_logged_in): ?>
                            <a href="logout.php" class="btn-entrar">SAIR ></a>
                            <div class="divider">OU</div>
                            <a href="perfil.php" class="link-cadastrar">Minha Conta</a>
                        <?php else: ?>
                            <a href="login.php" class="btn-entrar">ENTRAR ></a>
                            <div class="divider">OU</div>
                            <a href="cadastro.php" class="link-cadastrar">Cliente novo? Cadastrar</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="action-item" id="cart-trigger-btn"> <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>
                    <div class="text-content">
                        <strong>Sacola</strong>
                        <span id="cart-item-count-header">0 Itens</span> </div>
                </div>
            </div>
        </div>
    </header>

    <nav class="header-nav" id="nav-menu">
        <div class="mobile-nav-header">
            <button class="mobile-nav-close" id="close-btn"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg></button>

            <div class="mobile-nav-account">
                <?php if ($is_logged_in): ?>
                    <span>Olá, <?php echo $primeiro_nome; ?></span>
                    <a href="perfil.php">MINHA CONTA ></a>
                <?php else: ?>
                    <span>Olá visitante</span>
                    <a href="login.php">MINHA CONTA ></a>
                <?php endif; ?>
            </div>
            </div>
        <ul>
            <?php foreach ($category_tree as $categoria): ?>
                <?php $has_children = !empty($categoria['children']); ?>
                <li class="<?php echo $has_children ? 'has-children' : ''; ?>">

                    <a href="produtos.php?id=<?php echo $categoria['id']; ?>">
                    <?php echo htmlspecialchars($categoria['nome']); ?>
                        <?php if ($has_children): ?><span class="chevron"></span><?php endif; ?>
                    </a>
                    <?php if ($has_children): ?>
                        <ul class="sub-menu">
                            <?php foreach ($categoria['children'] as $sub_categoria): ?>
                                <li><a href="produtos.php?id=<?php echo $sub_categoria['id']; ?>"><?php echo htmlspecialchars($sub_categoria['nome']); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

</div>

<div class="cart-overlay" id="cart-overlay"></div>
<div class="cart-modal" id="cart-modal">
    <div class="cart-header">
        <div class="cart-header-title">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>
            <span>Sacola de Compras</span>
        </div>
        <button class="cart-close-btn" data-dismiss="modal">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                   <path fill-rule="evenodd" d="M4 8a.5.5 0 0 1 .5-.5h5.793L8.146 5.354a.5.5 0 1 1 .708-.708l3 3a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708-.708L10.293 8.5H4.5A.5.5 0 0 1 4 8z"/>
            </svg>
        </button>
    </div>
    <div class="cart-body" id="cart-body-content">
        <div class="cart-empty">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>
            <p>Sua sacola está vazia</p>
        </div>
    </div>
    <div class="cart-footer">
        <div class="cart-footer-info">
            <p>* Calcule seu frete na página de finalização.</p>
            <p>* Insira seu cupom de desconto na página de finalização.</p>
        </div>
        <div class="cart-footer-actions">
            <a href="#" class="cart-continue-btn" data-dismiss="modal">< Continuar Comprando</a>
            <a href="checkout.php" class="btn btn-primary cart-checkout-btn">COMPRAR AGORA</a>
        </div>
    </div>
</div>