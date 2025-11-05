<?php
// admin_sidebar.php
// Este arquivo é universal para todas as páginas do painel de administração.

// Pega o nome do arquivo atual (ex: "produtos.php")
$current_page_base = basename($_SERVER['PHP_SELF']);

// Pega o "hash" da URL (ex: "#tab-lista") e garante que o '#' esteja lá
$current_hash_fragment = parse_url($_SERVER['REQUEST_URI'], PHP_URL_FRAGMENT);
// Trata o hash para corresponder aos IDs das abas (ex: #tab-lista)
$current_hash = ($current_hash_fragment) ? '#' . $current_hash_fragment : '#';

// Define a ESTRUTURA DO MENU ORIGINAL, mas com links CORRIGIDOS PARA AS ABAS
$menu_items = [
    [
        'title' => 'Dashboard',
        'file' => 'index.php',
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25A2.25 2.25 0 0 1 13.5 8.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" /></svg>',
        'key' => 'index.php' // Chave para links simples
    ],
    // --- Pedidos (Apenas Ver Todos os Pedidos) ---
    [
        'title' => 'Pedidos',
        'file' => 'pedidos.php',
        'file_key' => 'pedidos.php',
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>',
        'children' => [
            ['title' => 'Ver Todos os Pedidos', 'file' => 'pedidos.php'],
        ]
    ],
    // --- Envios (Links atualizados para abas) ---
    [
        'title' => 'Envios',
        'file' => 'envios.php#tab-metodos', // Link padrão do pai
        'file_key' => 'envios.php',
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" /></svg>',
        'children' => [
            ['title' => 'Métodos de Envio', 'file' => 'envios.php#tab-metodos'],
        ]
    ],
    // --- Catálogo (Links atualizados para abas) ---
    [
        'title' => 'Catálogo',
        'file' => 'produtos.php#tab-lista', // Link padrão do pai
        'file_key' => 'catalogo', // Chave customizada
        'children_files' => ['produtos.php', 'categorias.php', 'avaliacoes.php'], // <-- ATUALIZADO: Inclui avaliacoes.php
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" /></svg>',
        'children' => [
            ['title' => 'Gerenciar Produtos', 'file' => 'produtos.php#tab-lista'],
            ['title' => 'Gerenciar Categorias', 'file' => 'categorias.php#tab-lista'],
            ['title' => 'Moderar Avaliações', 'file' => 'avaliacoes.php#tab-lista'], // <-- NOVO LINK
        ]
    ],
    // --- Checkout Configs (Links atualizados para abas) ---
    [
        'title' => 'Checkout Configs',
        'file' => 'checkout.php#tab-gateway', // Link padrão do pai
        'file_key' => 'checkout.php',
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" /></svg>',
        'children' => [
            ['title' => 'Gateway', 'file' => 'checkout.php#tab-gateway'],
            ['title' => 'Formas de Pagamento', 'file' => 'checkout.php#tab-pagamentos']
        ]
    ],
    // --- Usuários (OK) ---
    [
        'title' => 'Usuários',
        'file' => 'usuarios.php',
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>',
        'key' => 'usuarios.php' // Chave para links simples
    ],
    [
        'title' => 'Suporte ao Cliente',
        'file' => 'tickets.php',
        'file_key' => 'tickets.php',
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-chat-text" viewBox="0 0 16 16"><path d="M2.678 11.894a1 1 0 0 1 .287.801 11 11 0 0 1-.398 2c1.395-.323 2.247-.697 2.634-.893a1 1 0 0 1 .71-.074A8 8 0 0 0 8 14c3.996 0 7-2.807 7-6s-3.004-6-7-6-7 2.808-7 6c0 1.468.617 2.83 1.678 3.894m-.493 3.905a22 22 0 0 1-.713.129c-.2.032-.352-.176-.273-.362a10 10 0 0 0 .244-.637l.003-.01c.248-.72.45-1.548.524-2.319C.743 11.37 0 9.76 0 8c0-3.866 3.582-7 8-7s8 3.134 8 7-3.582 7-8 7a9 9 0 0 1-2.347-.306c-.52.263-1.639.742-3.468 1.105"/><path d="M4 5.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5M4 8a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7A.5.5 0 0 1 4 8m0 2.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5"/></svg>',
        'children' => [
             ['title' => 'Tickets', 'file' => 'tickets.php'],
        ]
    ],

    // ⭐ NOVO ITEM: INTEGRAÇÕES (Para Mailgun, etc.)
    [
        'title' => 'Integrações',
        'file' => 'config_mailgun.php',
        'file_key' => 'config_mailgun.php',
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-motherboard" viewBox="0 0 16 16"><path d="M11.5 2a.5.5 0 0 1 .5.5v7a.5.5 0 0 1-1 0v-7a.5.5 0 0 1 .5-.5m2 0a.5.5 0 0 1 .5.5v7a.5.5 0 0 1-1 0v-7a.5.5 0 0 1 .5-.5m-10 8a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1zm0 2a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1zM5 3a1 1 0 0 0-1 1h-.5a.5.5 0 0 0 0 1H4v1h-.5a.5.5 0 0 0 0 1H4a1 1 0 0 0 1 1v.5a.5.5 0 0 0 1 0V8h1v.5a.5.5 0 0 0 1 0V8a1 1 0 0 0 1-1h.5a.5.5 0 0 0 0-1H9V5h.5a.5.5 0 0 0 0-1H9a1 1 0 0 0-1-1v-.5a.5.5 0 0 0-1 0V3H6v-.5a.5.5 0 0 0-1 0zm0 1h3v3H5zm6.5 7a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h2a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5z"/><path d="M1 2a2 2 0 0 1 2-2h11a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2v-2H.5a.5.5 0 0 1-.5-.5v-1A.5.5 0 0 1 .5 9H1V8H.5a.5.5 0 0 1-.5-.5v-1A.5.5 0 0 1 .5 6H1V5H.5a.5.5 0 0 1-.5-.5v-2A.5.5 0 0 1 .5 2zm1 11a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H3a1 1 0 0 0-1 1z"/></svg>',
        'children' => [
             ['title' => 'Mailgun (E-mail)', 'file' => 'config_mailgun.php'],
        ]
    ],
    [
        'title' => 'Dados',
        'file' => 'dados.php',
        'file_key' => 'dados.php',
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25M9 16.5v.75m3-3v3M15 12v5.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>',
        'children' => [
             ['title' => 'Dados obtidos', 'file' => 'dados.php'],
        ]
    ],
    // --- Layout & Configs ---
    [
        'title' => 'Layout & Configs',
        'file' => 'configlayout.php#tab-geral', // Link padrão do pai

        'children_files' => ['configlayout.php', 'config-cores.php'], // (Linha nova)

        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0h3.75m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h3.75" /></svg>',
        'children' => [
            ['title' => 'Geral (Logo)', 'file' => 'configlayout.php#tab-geral'],
            ['title' => 'Homepage', 'file' => 'configlayout.php#tab-homepage'],
            ['title' => 'Rodapé', 'file' => 'configlayout.php#tab-footer'],
            ['title' => 'Cores da Loja', 'file' => 'config-cores.php']
        ]
    ]
];

$user_initial = strtoupper(substr($_SESSION['admin_username'], 0, 1));

?>

<style>
    /* ------------------------------------------------------------------
    ** AJUSTES CRÍTICOS PARA SCROLL DO SIDEBAR **
    ------------------------------------------------------------------ */
    .sidebar {
        /* Garante que o sidebar ocupe toda a altura da viewport */
        position: fixed; /* Se estiver fixo na tela */
        top: 0;
        bottom: 0;
        /* Inclua aqui seus estilos de fundo e largura (width) */
        /* width: 250px; background-color: var(--sidebar-bg); */
        height: 100vh;
        overflow-y: auto; /* Permite a rolagem quando o conteúdo exceder 100vh */
        /* Estilos de transição e posição do sidebar em mobile seriam definidos aqui */
    }

    /* Estilo da barra de rolagem (opcional, para navegadores baseados em WebKit) */
    .sidebar::-webkit-scrollbar {
        width: 8px;
    }
    .sidebar::-webkit-scrollbar-thumb {
        background-color: rgba(255, 255, 255, 0.2);
        border-radius: 4px;
    }
    .sidebar::-webkit-scrollbar-track {
        background: transparent;
    }
    /* ------------------------------------------------------------------ */

    /* --- NOVO CSS: OCULTAR HAMBÚRGUER QUANDO SIDEBAR ESTIVER ABERTO (Mobile) --- */

    /* Assume que o botão hambúrguer tem o ID 'menu-toggle' e está no HEADER/corpo da página.
       Geralmente, ele é visível apenas em mobile. */
    @media (max-width: 1024px) {
        /* Torna o botão visível em mobile por padrão (se não estiver aberto) */
        /* Exemplo: #menu-toggle { display: block; } */

        /* OCULTA o botão quando a classe 'sidebar-open' estiver no body */
        body.sidebar-open #menu-toggle {
            display: none !important; /* Força a ocultação */
        }
    }
    /* ------------------------------------------------------------------ */

    /* O CSS fornecido permanece o mesmo, garantindo a aparência do submenu */
    .sidebar nav .sidebar-submenu {
        padding-left: 20px;
        margin-top: -5px;
        margin-bottom: 5px;
        overflow: hidden;
        transition: max-height 0.3s ease-out;
        max-height: 0;
    }
    .sidebar nav .sidebar-submenu.open {
        max-height: 500px; /* Mantido para o efeito de transição suave */
    }
    .sidebar nav a.has-children {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .sidebar nav a .menu-chevron {
        width: 16px;
        height: 16px;
        color: var(--light-text-color);
        transition: transform 0.3s ease;
    }
    .sidebar nav a.open .menu-chevron {
        transform: rotate(90deg);
    }
    .sidebar-submenu a {
        font-size: 0.9em;
        padding: 0.7rem 1rem 0.7rem 1.5rem;
        color: var(--light-text-color);
        position: relative;
    }
    .sidebar-submenu a::before {
        content: '';
        position: absolute;
        left: 0.5rem;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 4px;
        border-radius: 50%;
        background-color: var(--light-text-color);
        transition: all 0.3s ease;
    }
    .sidebar-submenu a:hover {
        color: var(--text-color);
        background-color: transparent;
        border-color: transparent;
        box-shadow: none;
    }
    .sidebar-submenu a:hover::before {
        background-color: var(--primary-color);
    }
    .sidebar-submenu a.active-child {
        color: #fff;
        font-weight: 600;
    }
    .sidebar-submenu a.active-child::before {
        background-color: var(--primary-color);
        transform: translateY(-50%) scale(1.5);
    }
    /* Estilos de perfil do usuário (Garantir que não fique colado ao fundo) */
    .user-profile {
        /* Adicionar padding/margin na parte inferior do sidebar-nav ou no user-profile se for a última coisa */
        padding-bottom: 20px;
    }
</style>

<div class="sidebar" id="admin-sidebar">
    <div class="logo-area">
        <div class="logo-circle" style="background-color: #f3f3f3;">
            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path></svg>
        </div>
        <span class="logo-text">PAINEL ADMIN</span>
    </div>

    <div class="divider"></div>

    <nav class="sidebar-nav">
        <?php foreach ($menu_items as $item): ?>
            <?php
            $has_children = !empty($item['children']);
            $link_is_parent_open = false; // Flag para abrir o accordion
            $link_class = ''; // Classe do link <a> principal
            $href = $item['file'] ?? '#'; // Link padrão

            // --- Lógica de Ativação ---
            // Usa 'children_files' se existir, senão 'file_key', senão 'file'
            $parent_key_files = $item['children_files'] ?? [$item['file_key'] ?? ($item['key'] ?? $item['file'])];

            // Adiciona o arquivo principal do item se houver children_files
            if (isset($item['file_key']) && !in_array($item['file_key'], $parent_key_files)) {
                $parent_key_files[] = $item['file_key'];
            }

            // 1. Verifica se a página base é a mesma
            if (!$has_children) {
                if (in_array($current_page_base, $parent_key_files)) {
                    $link_class = 'active';
                }
            } else {
                // É um link pai (com filhos)
                $href = $item['file']; // Mantém o link do pai como o primeiro filho ou link de navegação
                $link_class = 'has-children';

                // Lógica para abrir o acordeão
                if (in_array($current_page_base, $parent_key_files)) {
                    $link_is_parent_open = true;
                }

                if ($link_is_parent_open) {
                    $link_class .= ' open active'; // Deixa o pai azul e aberto
                }
            }
            ?>

            <a href="<?php echo htmlspecialchars($href); ?>"
                class="<?php echo $link_class; ?>"
                <?php if($has_children) echo 'data-toggle="submenu"'; ?>>

                <span style="display: flex; align-items: center; gap: 0.8rem;">
                    <?php echo $item['icon']; ?>
                    <span><?php echo htmlspecialchars($item['title']); ?></span>
                </span>

                <?php if ($has_children): ?>
                    <svg class="menu-chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" /></svg>
                <?php endif; ?>
            </a>

            <?php if ($has_children): ?>
                <div class="sidebar-submenu <?php echo $link_is_parent_open ? 'open' : ''; ?>">
                    <?php foreach ($item['children'] as $index => $child): ?>
                        <?php
                        $child_parts = parse_url($child['file']);
                        $child_file = basename($child_parts['path']);
                        $child_hash = isset($child_parts['fragment']) ? '#' . $child_parts['fragment'] : '#';

                        $is_child_active = false;
                        $is_editing_this_page = false;
                        $child_full_link = $child['file']; // Link completo, incluindo o hash se houver.

                        // Determinar se este link filho está ativo
                        if ($child_file == $current_page_base) {

                            // Lógica de Edição (tem prioridade)
                            if ($current_page_base == 'produtos.php' && isset($_GET['edit_produto']) && $child['file'] == 'produtos.php#tab-lista') { $is_child_active = true; $is_editing_this_page = true; }
                            if ($current_page_base == 'categorias.php' && isset($_GET['edit']) && $child['file'] == 'categorias.php#tab-lista') { $is_child_active = true; $is_editing_this_page = true; }
                            if ($current_page_base == 'envios.php' && isset($_GET['edit_envio']) && $child['file'] == 'envios.php#tab-metodos') { $is_child_active = true; $is_editing_this_page = true; }
                            if ($current_page_base == 'checkout.php' && isset($_GET['edit_pagamento']) && $child['file'] == 'checkout.php#tab-pagamentos') { $is_child_active = true; $is_editing_this_page = true; }
                            if ($current_page_base == 'configlayout.php' && (isset($_GET['edit_banner']) || isset($_GET['edit_promo_banner'])) && $child['file'] == 'configlayout.php#tab-homepage') { $is_child_active = true; $is_editing_this_page = true; }
                            if ($current_page_base == 'configlayout.php' && (isset($_GET['edit_link']) || isset($_GET['edit_icon']) || isset($_GET['edit_social_icon'])) && $child['file'] == 'configlayout.php#tab-footer') { $is_child_active = true; $is_editing_this_page = true; }
                            if ($current_page_base == 'avaliacoes.php' && isset($_GET['edit_avaliacao']) && $child_hash == '#tab-adicionar') { $is_child_active = true; $is_editing_this_page = true; }

                            // Se não estiver editando, checa hash
                            if (!$is_editing_this_page) {
                                if ($child_hash == $current_hash) {
                                    $is_child_active = true;
                                }
                                // Caso Padrão (Sem hash na URL E não é ?edit=)
                                else if ($current_hash == '#') {
                                    // Ativa o *primeiro* link do submenu se nenhum hash for fornecido E ele for a página correta
                                    if ($index == 0 && !isset($_GET['edit_produto']) && !isset($_GET['edit']) && !isset($_GET['edit_envio']) && !isset($_GET['edit_pagamento'])) {
                                        $is_child_active = true;
                                    }
                                }
                            }

                            // Caso especial para páginas sem abas (config-cores, config_mailgun, avaliacoes)
                            if (($child_file == 'config-cores.php' || $child_file == 'avaliacoes.php' || $child_file == 'config_mailgun.php' || $child_file == 'dados.php' || $child_file == 'pedidos.php' || $child_file == 'tickets.php' || $child_file == 'usuarios.php') && $current_page_base == $child_file) {
                                 // Garante que o link da página sem abas seja ativado se o arquivo for o atual.
                                 $is_child_active = true;
                            }
                        }

                        $child_class = $is_child_active ? 'active-child' : '';
                        ?>
                        <a href="<?php echo htmlspecialchars($child_full_link); ?>" class="<?php echo $child_class; ?>">
                            <span><?php echo htmlspecialchars($child['title']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php endforeach; ?>
    </nav>


    <div class="user-profile" id="user-profile-menu">
        <div class="avatar"><?php echo $user_initial; ?></div>
        <div class="user-info">
            <span class="user-name"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
            <span class="user-level">Administrador</span>
        </div>
        <div class="profile-dropdown" id="profile-dropdown">
            <a href="admin_logout.php">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                Logout
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // --- Lógica do Submenu (Accordion) ---
    const submenuToggles = document.querySelectorAll('.sidebar nav a[data-toggle="submenu"]');

    submenuToggles.forEach(toggle => {
        const submenu = toggle.nextElementSibling;

        // 1. Garante que submenus ativos por PHP tenham a altura correta no carregamento
        if (toggle.classList.contains('open') && submenu) {
            // Usa scrollHeight + 10px para garantir que a borda/padding caiba
            submenu.style.maxHeight = submenu.scrollHeight + 10 + "px";
        }

        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const isAlreadyOpen = this.classList.contains('open');

            // 1. Fecha todos os *outros* submenus
            submenuToggles.forEach(otherToggle => {
                if (otherToggle !== this) {
                    otherToggle.classList.remove('open', 'active');
                    const otherSubmenu = otherToggle.nextElementSibling;
                    if (otherSubmenu && otherSubmenu.classList.contains('sidebar-submenu')) {
                        otherSubmenu.classList.remove('open');
                        otherSubmenu.style.maxHeight = null;
                    }
                }
            });

            // 2. Abre/Fecha o submenu ATUAL
            if (isAlreadyOpen) {
                this.classList.remove('open', 'active');
                if (submenu) {
                    submenu.style.maxHeight = null;
                    submenu.classList.remove('open');
                }
            } else {
                this.classList.add('open', 'active');
                if (submenu) {
                    submenu.classList.add('open');
                    // Usa scrollHeight + 10px para garantir que a borda/padding caiba
                    submenu.style.maxHeight = submenu.scrollHeight + 10 + "px";
                }
            }
        });
    });

    // Garante que o submenu ativo (aberto por PHP) tenha a altura correta no carregamento
    const openSubmenus = document.querySelectorAll('.sidebar-submenu.open');
    openSubmenus.forEach(submenu => {
        if (submenu) {
            // Usa scrollHeight + 10px para garantir que a borda/padding caiba
            submenu.style.maxHeight = submenu.scrollHeight + 10 + "px";
        }
    });

    // Adiciona evento de clique aos links filhos para fechar o menu mobile (para responsividade)
    const childLinks = document.querySelectorAll('.sidebar-submenu a');
    childLinks.forEach(childLink => {
        childLink.addEventListener('click', () => {
            if (window.innerWidth <= 1024) {
                document.body.classList.remove('sidebar-open');
            }
        });
    });

    // --- LÓGICA DE INTERAÇÃO (Hambúrguer e Perfil) ---
    // ... (Seu código JavaScript de toggle de menu/perfil mantido) ...
    const menuToggle = document.getElementById('menu-toggle');
    const sidebar = document.getElementById('admin-sidebar');
    const body = document.body;
    const userProfileMenu = document.getElementById('user-profile-menu');
    const dropdown = document.getElementById('profile-dropdown');

    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', (event) => { event.stopPropagation(); body.classList.toggle('sidebar-open'); });
        body.addEventListener('click', (event) => {
            if (body.classList.contains('sidebar-open') && !sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                body.classList.remove('sidebar-open');
            }
        });
    }
    if (userProfileMenu && dropdown) {
        userProfileMenu.addEventListener('click', (event) => { event.stopPropagation(); dropdown.classList.toggle('show'); });
        window.addEventListener('click', (event) => {
            if (dropdown.classList.contains('show') && !userProfileMenu.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
    }

    // --- CORREÇÃO JS: Garante que o pai esteja aberto se um filho estiver ativo ---
    const activeChild = document.querySelector('.sidebar-submenu a.active-child');
    if (activeChild) {
        const submenu = activeChild.closest('.sidebar-submenu');
        const parentLink = submenu ? submenu.previousElementSibling : null;

        if (submenu && !submenu.classList.contains('open')) {
            submenu.classList.add('open');
            submenu.style.maxHeight = submenu.scrollHeight + 10 + "px"; // Ajuste de altura
        }
        if (parentLink && !parentLink.classList.contains('open')) {
            parentLink.classList.add('open', 'active');
        }
    }

});
</script>