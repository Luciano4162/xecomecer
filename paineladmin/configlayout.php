<?php
// configlayout.php - Arquivo Mestre para Configurações e Layout
session_start();
if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['admin_tipo'] ?? '') !== 'admin') {
    // header('Location: admin_login.php'); exit;
}
require_once '../config/db.php';
require_once '../funcoes.php'; // Inclui a função carregarConfigApi

// Carrega as constantes (NOME_DA_LOJA, Mailgun keys, etc.)
if (isset($pdo)) {
    carregarConfigApi($pdo);
}

$message = ''; $message_type = '';

// --- INICIALIZAÇÃO DE VARIÁVEIS ---
$edit_link = null; $is_editing_link = false; // <-- Lógica restaurada
$edit_icon = null; $is_editing_icon = false;
$edit_banner = null; $is_editing_banner = false;
$edit_promo_banner = null; $is_editing_promo_banner = false;
$edit_social_icon = null; $is_editing_social_icon = false;

// Variáveis para os dados atuais (serão preenchidas no GET)
$configs = [];
$all_footer_links = []; // <-- Restaurado
$all_footer_icons = [];
$banners = [];
$all_promo_banners = [];
$all_social_icons = [];
$all_produtos = [];
$all_categorias = [];


// ==========================================================
// LÓGICA DE SALVAMENTO (Dividida em múltiplos handlers)
// ==========================================================

// --- LÓGICA PARA ATUALIZAR CONFIG_SITE (GERAL: Logo, Nome, URL) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_geral'])) {

    // ▼▼▼ MODIFICADO (Adicionada 'categoria_da_loja') ▼▼▼
    $configs_to_update = [
        'logo_url' => $_POST['logo_url'] ?? '',
        'nome_da_loja' => $_POST['nome_da_loja'] ?? 'Sua Loja',
        'categoria_da_loja' => $_POST['categoria_da_loja'] ?? '', // <--- NOVO
        'site_base_url' => rtrim(trim($_POST['site_base_url'] ?? ''), '/') . '/' // Garante a barra no final
    ];
    // ▲▲▲ FIM DA MODIFICAÇÃO ▲▲▲

    $pdo->beginTransaction();
    try {
        $sql = "INSERT INTO config_site (chave, valor) VALUES (:chave, :valor)
                ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor";
        $stmt = $pdo->prepare($sql);
        foreach ($configs_to_update as $chave => $valor) {
            $stmt->execute(['chave' => $chave, 'valor' => trim($valor)]);
        }
        $pdo->commit();
        $_SESSION['flash_message'] = "Configurações gerais salvas com sucesso!";
        $_SESSION['flash_type'] = "success";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['flash_message'] = "Erro ao salvar Configurações Gerais: " . $e->getMessage();
        $_SESSION['flash_type'] = "error";
    }
    header("Location: configlayout.php#tab-geral"); exit;
}

// --- LÓGICA PARA ATUALIZAR CONFIG_SITE (RODAPÉ) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_footer_configs'])) {
    $configs_to_update = [
        'telefone_contato' => $_POST['telefone_contato'] ?? null,
        'email_contato' => $_POST['email_contato'] ?? null,
        'horario_atendimento' => $_POST['horario_atendimento'] ?? null,
        'footer_credits' => $_POST['footer_credits'] ?? null,
    ];
    $updated_count = 0;
    $pdo->beginTransaction();
    try {
        $sql = "INSERT INTO config_site (chave, valor) VALUES (:chave, :valor)
                ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor";
        $stmt = $pdo->prepare($sql);
        foreach ($configs_to_update as $chave => $valor) {
            if ($valor !== null) {
                $stmt->execute(['chave' => $chave, 'valor' => trim($valor)]);
                $updated_count++;
            }
        }
        $pdo->commit();
        if ($updated_count > 0) { $message = "Informações do Rodapé salvas!"; $message_type = "success"; }
        else { $message = "Nenhuma alteração nas Informações do Rodapé."; $message_type = "info"; }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = "Erro ao salvar Informações do Rodapé: " . $e->getMessage();
        $message_type = "error";
    }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $message_type;
    header("Location: configlayout.php#tab-footer"); exit;
}


// --- LÓGICA PARA ATUALIZAR BANNER WHATSAPP ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_wpp_banner'])) {
    $configs_to_update = [
        'whatsapp_banner_ativo' => isset($_POST['whatsapp_banner_ativo']),
        'whatsapp_banner_img_url' => $_POST['whatsapp_banner_img_url'] ?? '',
        'whatsapp_banner_link_url' => $_POST['whatsapp_banner_link_url'] ?? '',
    ];
     $updated_count = 0;
     $pdo->beginTransaction();
    try {
        $sql = "INSERT INTO config_site (chave, valor) VALUES (:chave, :valor)
                ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor";
        $stmt = $pdo->prepare($sql);
        foreach ($configs_to_update as $chave => $valor) {
           if (is_bool($valor)) { $valor = $valor ? 'true' : 'false'; }
           $stmt->execute(['chave' => $chave, 'valor' => trim($valor)]);
           $updated_count++;
        }
        $pdo->commit();
        if ($updated_count > 0) { $message = "Banner WhatsApp salvo com sucesso!"; $message_type = "success"; }
    } catch (PDOException $e) {
         $pdo->rollBack();
         $message = "Erro ao atualizar Banner WhatsApp: " . $e->getMessage();
         $message_type = "error";
    }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $message_type;
    header("Location: configlayout.php#tab-homepage"); exit;
}


// --- ⭐ LÓGICA CRUD PARA FOOTER LINKS (RESTAURADA) ---
// 1L. ADICIONAR ou ATUALIZAR Link
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_link'])) {
    $link_texto = trim($_POST['link_texto']); $link_url = trim($_POST['link_url']);
    $link_coluna = trim($_POST['link_coluna']); $link_ordem = (int)$_POST['link_ordem'];
    $link_ativo = isset($_POST['link_ativo']);
    $link_id = isset($_POST['link_id']) && !empty($_POST['link_id']) ? (int)$_POST['link_id'] : null;
    if (!empty($link_texto)) {
        try {
            if ($link_id) { // UPDATE
                $sql = "UPDATE footer_links SET texto = :texto, url = :url, coluna = :coluna, ordem = :ordem, ativo = :ativo WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $link_id, PDO::PARAM_INT);
            } else { // INSERT
                $sql = "INSERT INTO footer_links (texto, url, coluna, ordem, ativo) VALUES (:texto, :url, :coluna, :ordem, :ativo)";
                $stmt = $pdo->prepare($sql);
            }
            $stmt->bindParam(':texto', $link_texto, PDO::PARAM_STR); $stmt->bindParam(':url', $link_url, PDO::PARAM_STR); $stmt->bindParam(':coluna', $link_coluna, PDO::PARAM_STR);
            $stmt->bindParam(':ordem', $link_ordem, PDO::PARAM_INT); $stmt->bindParam(':ativo', $link_ativo, PDO::PARAM_BOOL);
            $stmt->execute();
            $message = $link_id ? "Link do footer atualizado!" : "Link do footer adicionado!";
            $message_type = "success";
        } catch (PDOException $e) { $message = "Erro ao salvar link: " . $e->getMessage(); $message_type = "error"; }
    } else { $message = "O campo 'Texto' do link é obrigatório."; $message_type = "error"; }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $message_type;
    header("Location: configlayout.php#tab-footer"); exit;
}
// 2L. DELETAR Link
if (isset($_GET['delete_link'])) {
    $link_id = (int)$_GET['delete_link'];
    try {
        $sql = "DELETE FROM footer_links WHERE id = :id"; $stmt = $pdo->prepare($sql); $stmt->execute(['id' => $link_id]);
        $_SESSION['flash_message'] = "Link do footer removido!"; $_SESSION['flash_type'] = "success";
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = "Erro ao remover link: " . $e->getMessage(); $_SESSION['flash_type'] = "error";
    }
    header('Location: configlayout.php#tab-footer'); exit;
}
// 3L. MODO DE EDIÇÃO Link
if (isset($_GET['edit_link'])) {
    $link_id = (int)$_GET['edit_link']; $is_editing_link = true;
    $stmt_edit_link = $pdo->prepare("SELECT * FROM footer_links WHERE id = :id"); $stmt_edit_link->execute(['id' => $link_id]);
    $edit_link = $stmt_edit_link->fetch(PDO::FETCH_ASSOC);
    if (!$edit_link) $is_editing_link = false;
}


// --- LÓGICA CRUD PARA FOOTER ICONS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_icon'])) {
    $icon_imagem_url = trim($_POST['icon_imagem_url']); $icon_alt_text = trim($_POST['icon_alt_text']); $icon_link_url = trim($_POST['icon_link_url']);
    $icon_coluna = trim($_POST['icon_coluna']); $icon_ordem = (int)$_POST['icon_ordem']; $icon_ativo = isset($_POST['icon_ativo']);
    $icon_id = isset($_POST['icon_id']) && !empty($_POST['icon_id']) ? (int)$_POST['icon_id'] : null;
    if (!empty($icon_imagem_url)) {
         try {
             if ($icon_id) { // UPDATE
                 $sql = "UPDATE footer_icons SET imagem_url = :img, alt_text = :alt, link_url = :link, coluna = :col, ordem = :ord, ativo = :ativo WHERE id = :id";
                 $stmt = $pdo->prepare($sql);
                 $stmt->bindParam(':id', $icon_id, PDO::PARAM_INT);
             } else { // INSERT
                 $sql = "INSERT INTO footer_icons (imagem_url, alt_text, link_url, coluna, ordem, ativo) VALUES (:img, :alt, :link, :col, :ord, :ativo)";
                 $stmt = $pdo->prepare($sql);
             }
             $stmt->bindParam(':img', $icon_imagem_url, PDO::PARAM_STR); $stmt->bindParam(':alt', $icon_alt_text, PDO::PARAM_STR); $stmt->bindParam(':link', $icon_link_url, PDO::PARAM_STR);
             $stmt->bindParam(':col', $icon_coluna, PDO::PARAM_STR); $stmt->bindParam(':ord', $icon_ordem, PDO::PARAM_INT); $stmt->bindParam(':ativo', $icon_ativo, PDO::PARAM_BOOL);
             $stmt->execute();
             $message = $icon_id ? "Ícone do footer atualizado!" : "Ícone do footer adicionado!";
             $message_type = "success";
    } catch (PDOException $e) { $message = "Erro ao salvar ícone: " . $e->getMessage(); $message_type = "error"; }
    } else { $message = "O campo 'URL Imagem' do ícone é obrigatório."; $message_type = "error"; }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $message_type;
    header('Location: configlayout.php#tab-footer'); exit;
}
if (isset($_GET['delete_icon'])) {
    $icon_id = (int)$_GET['delete_icon'];
     try {
         $sql = "DELETE FROM footer_icons WHERE id = :id"; $stmt = $pdo->prepare($sql); $stmt->execute(['id' => $icon_id]);
         $_SESSION['flash_message'] = "Ícone do footer removido!"; $_SESSION['flash_type'] = "success";
    } catch (PDOException $e) {
         $_SESSION['flash_message'] = "Erro ao remover ícone: " . $e->getMessage(); $_SESSION['flash_type'] = "error";
    }
    header('Location: configlayout.php#tab-footer'); exit;
}
if (isset($_GET['edit_icon'])) {
    $icon_id = (int)$_GET['edit_icon']; $is_editing_icon = true;
    $stmt_edit_icon = $pdo->prepare("SELECT * FROM footer_icons WHERE id = :id"); $stmt_edit_icon->execute(['id' => $icon_id]);
    $edit_icon = $stmt_edit_icon->fetch(PDO::FETCH_ASSOC);
     if (!$edit_icon) $is_editing_icon = false;
}


// --- LÓGICA CRUD PARA BANNERS (Carrossel) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_banner'])) {
    $imagem_url = trim($_POST['imagem_url']); $alt_text = trim($_POST['alt_text']);
    $ordem = (int)$_POST['ordem']; $ativo = isset($_POST['ativo']);
    $id = isset($_POST['id']) && !empty($_POST['id'])? (int)$_POST['id'] : null;
    $link_tipo = trim($_POST['link_tipo']);
    $link_url = ($link_tipo == 'url') ? trim($_POST['link_url']) : '#';
    $produto_id = ($link_tipo == 'produto' && !empty($_POST['produto_id'])) ? (int)$_POST['produto_id'] : null;
    $categoria_id = ($link_tipo == 'categoria' && !empty($_POST['categoria_id'])) ? (int)$_POST['categoria_id'] : null;

    if (empty($imagem_url)) {
        $message = "O campo 'URL da Imagem' do banner é obrigatório."; $message_type = "error";
    } else {
        try {
            $params = [
                ':img' => $imagem_url, ':link' => $link_url, ':alt' => $alt_text,
                ':ativo' => $ativo, ':ord' => $ordem, ':link_tipo' => $link_tipo,
                ':pid' => $produto_id, ':cid' => $categoria_id
            ];
            if ($id) { // UPDATE
                $sql = "UPDATE banners SET imagem_url = :img, link_url = :link, alt_text = :alt, ativo = :ativo, ordem = :ord, link_tipo = :link_tipo, produto_id = :pid, categoria_id = :cid WHERE id = :id";
                $params[':id'] = $id;
                $stmt = $pdo->prepare($sql);
            } else { // INSERT
                $sql = "INSERT INTO banners (imagem_url, link_url, alt_text, ativo, ordem, link_tipo, produto_id, categoria_id)
                        VALUES (:img, :link, :alt, :ativo, :ord, :link_tipo, :pid, :cid)";
                $stmt = $pdo->prepare($sql);
            }
            $stmt->bindParam(':img', $params[':img'], PDO::PARAM_STR);
            $stmt->bindParam(':link', $params[':link'], PDO::PARAM_STR);
            $stmt->bindParam(':alt', $params[':alt'], PDO::PARAM_STR);
            $stmt->bindParam(':ativo', $params[':ativo'], PDO::PARAM_BOOL);
            $stmt->bindParam(':ord', $params[':ord'], PDO::PARAM_INT);
            $stmt->bindParam(':link_tipo', $params[':link_tipo'], PDO::PARAM_STR);
            $stmt->bindParam(':pid', $params[':pid'], $params[':pid'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindParam(':cid', $params[':cid'], $params[':cid'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            if($id) $stmt->bindParam(':id', $params[':id'], PDO::PARAM_INT);
            $stmt->execute();
            $message = "Banner (Carrossel) salvo!"; $message_type = "success";
        } catch (PDOException $e) { $message = "Erro ao salvar banner: " . $e->getMessage(); $message_type = "error"; }
    }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $message_type;
    header("Location: configlayout.php#tab-homepage"); exit;
}
if (isset($_GET['delete_banner'])) {
    $id = (int)$_GET['delete_banner'];
    try {
        $sql = "DELETE FROM banners WHERE id = :id"; $stmt = $pdo->prepare($sql); $stmt->execute(['id' => $id]);
        $_SESSION['flash_message'] = "Banner (Carrossel) removido!"; $_SESSION['flash_type'] = "success";
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = "Erro ao remover banner: " . $e->getMessage(); $_SESSION['flash_type'] = "error";
    }
    header('Location: configlayout.php#tab-homepage'); exit;
}
if (isset($_GET['edit_banner'])) {
    $id = (int)$_GET['edit_banner']; $is_editing_banner = true;
    $stmt_edit_banner = $pdo->prepare("SELECT * FROM banners WHERE id = :id"); $stmt_edit_banner->execute(['id' => $id]);
    $edit_banner = $stmt_edit_banner->fetch(PDO::FETCH_ASSOC);
    if (!$edit_banner) $is_editing_banner = false;
}

// --- LÓGICA CRUD PARA PROMO BANNERS (Grid) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_promo_banner'])) {
    $img_url = trim($_POST['promo_imagem_url']); $alt_text = trim($_POST['promo_alt_text']);
    $section = trim($_POST['promo_section_key']); $ordem = (int)$_POST['promo_ordem'];
    $ativo = isset($_POST['promo_ativo']); $id = isset($_POST['promo_id']) && !empty($_POST['promo_id']) ? (int)$_POST['promo_id'] : null;
    $link_tipo = trim($_POST['promo_link_tipo']);
    $link_url = ($link_tipo == 'url') ? trim($_POST['promo_link_url']) : '#';
    $produto_id = ($link_tipo == 'produto' && !empty($_POST['promo_produto_id'])) ? (int)$_POST['promo_produto_id'] : null;
    $categoria_id = ($link_tipo == 'categoria' && !empty($_POST['promo_categoria_id'])) ? (int)$_POST['promo_categoria_id'] : null;

    if (!empty($img_url) && !empty($section)) {
        try {
            $params = [
                ':img' => $img_url, ':link' => $link_url, ':alt' => $alt_text, ':sec' => $section,
                ':ord' => $ordem, ':ativo' => $ativo, ':link_tipo' => $link_tipo,
                ':pid' => $produto_id, ':cid' => $categoria_id
            ];
            if ($id) { // UPDATE
                $sql = "UPDATE promo_banners SET imagem_url = :img, link_url = :link, alt_text = :alt, section_key = :sec, ordem = :ord, ativo = :ativo, link_tipo = :link_tipo, produto_id = :pid, categoria_id = :cid WHERE id = :id";
                $params[':id'] = $id;
                $stmt = $pdo->prepare($sql);
            } else { // INSERT
                $sql = "INSERT INTO promo_banners (imagem_url, link_url, alt_text, section_key, ordem, ativo, link_tipo, produto_id, categoria_id)
                        VALUES (:img, :link, :alt, :sec, :ord, :ativo, :link_tipo, :pid, :cid)";
                $stmt = $pdo->prepare($sql);
            }
            $stmt->bindParam(':img', $params[':img'], PDO::PARAM_STR); $stmt->bindParam(':link', $params[':link'], PDO::PARAM_STR);
            $stmt->bindParam(':alt', $params[':alt'], PDO::PARAM_STR); $stmt->bindParam(':sec', $params[':sec'], PDO::PARAM_STR);
            $stmt->bindParam(':ord', $params[':ord'], PDO::PARAM_INT); $stmt->bindParam(':ativo', $params[':ativo'], PDO::PARAM_BOOL);
            $stmt->bindParam(':link_tipo', $params[':link_tipo'], PDO::PARAM_STR);
            $stmt->bindParam(':pid', $params[':pid'], $params[':pid'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindParam(':cid', $params[':cid'], $params[':cid'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            if($id) $stmt->bindParam(':id', $params[':id'], PDO::PARAM_INT);
            $stmt->execute();
            $message = "Banner Promocional salvo!"; $message_type = "success";
        } catch (PDOException $e) { $message = "Erro ao salvar Banner Promocional: " . $e->getMessage(); $message_type = "error"; }
    } else { $message = "URL da Imagem e Seção são obrigatórios para Banners Promocionais."; $message_type = "error"; }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $message_type;
    header("Location: configlayout.php#tab-homepage"); exit;
}
if (isset($_GET['delete_promo_banner'])) {
    $id = (int)$_GET['delete_promo_banner'];
    try {
        $sql = "DELETE FROM promo_banners WHERE id = :id"; $stmt = $pdo->prepare($sql); $stmt->execute(['id' => $id]);
        $_SESSION['flash_message'] = "Banner Promocional removido!"; $_SESSION['flash_type'] = "success";
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = "Erro ao remover Banner Promocional: " . $e->getMessage(); $_SESSION['flash_type'] = "error";
    }
    header('Location: configlayout.php#tab-homepage'); exit;
}
if (isset($_GET['edit_promo_banner'])) {
    $id = (int)$_GET['edit_promo_banner']; $is_editing_promo_banner = true;
    $stmt_edit_promo = $pdo->prepare("SELECT * FROM promo_banners WHERE id = :id"); $stmt_edit_promo->execute(['id' => $id]);
    $edit_promo_banner = $stmt_edit_promo->fetch(PDO::FETCH_ASSOC);
    if (!$edit_promo_banner) $is_editing_promo_banner = false;
}

// --- LÓGICA CRUD PARA ÍCONES SOCIAIS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_social_icon'])) {
    $social_nome = trim($_POST['social_nome']); $social_link_url = trim($_POST['social_link_url']);
    $social_svg_code = trim($_POST['social_svg_code']); $social_ordem = (int)$_POST['social_ordem'];
    $social_ativo = isset($_POST['social_ativo']); $social_id = isset($_POST['social_id']) && !empty($_POST['social_id']) ? (int)$_POST['social_id'] : null;
    if (!empty($social_nome) && !empty($social_svg_code)) {
        try {
            if ($social_id) { // UPDATE
                $sql = "UPDATE social_icons SET nome = :nome, link_url = :link, svg_code = :svg, ordem = :ord, ativo = :ativo WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $social_id, PDO::PARAM_INT);
            } else { // INSERT
                $sql = "INSERT INTO social_icons (nome, link_url, svg_code, ordem, ativo) VALUES (:nome, :link, :svg, :ord, :ativo)";
                $stmt = $pdo->prepare($sql);
            }
            $stmt->bindParam(':nome', $social_nome, PDO::PARAM_STR); $stmt->bindParam(':link', $social_link_url, PDO::PARAM_STR); $stmt->bindParam(':svg', $social_svg_code, PDO::PARAM_STR);
            $stmt->bindParam(':ord', $social_ordem, PDO::PARAM_INT); $stmt->bindParam(':ativo', $social_ativo, PDO::PARAM_BOOL);
            $stmt->execute();
            $message = $social_id ? "Ícone Social atualizado!" : "Ícone Social adicionado!";
            $message_type = "success";
        } catch (PDOException $e) { $message = "Erro ao salvar Ícone Social: " . $e->getMessage(); $message_type = "error"; }
    } else { $message = "Os campos 'Nome' e 'Código SVG' do Ícone Social são obrigatórios."; $message_type = "error"; }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $message_type;
    header("Location: configlayout.php#tab-footer"); exit;
}
if (isset($_GET['delete_social_icon'])) {
    $social_id = (int)$_GET['delete_social_icon'];
    try {
        $sql = "DELETE FROM social_icons WHERE id = :id"; $stmt = $pdo->prepare($sql); $stmt->execute(['id' => $social_id]);
        $_SESSION['flash_message'] = "Ícone Social removido!"; $_SESSION['flash_type'] = "success";
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = "Erro ao remover Ícone Social: " . $e->getMessage(); $_SESSION['flash_type'] = "error";
    }
    header('Location: configlayout.php#tab-footer'); exit;
}
if (isset($_GET['edit_social_icon'])) {
    $social_id = (int)$_GET['edit_social_icon']; $is_editing_social_icon = true;
    $stmt_edit_social = $pdo->prepare("SELECT * FROM social_icons WHERE id = :id"); $stmt_edit_social->execute(['id' => $social_id]);
    $edit_social_icon = $stmt_edit_social->fetch(PDO::FETCH_ASSOC);
    if (!$edit_social_icon) $is_editing_social_icon = false;
}

// ==========================================================
// LÓGICA PARA LER TUDO
// ==========================================================
if (isset($_SESSION['flash_message']) && empty($message)) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}
try {
    // ⭐ ATUALIZADO: Busca configs da config_site
    $stmt_configs = $pdo->query("SELECT chave, valor FROM config_site");
    $configs = $stmt_configs->fetchAll(PDO::FETCH_KEY_PAIR);

    // ⭐ ATUALIZADO: Lógica de footer_links restaurada
    $stmt_all_links = $pdo->query("SELECT * FROM footer_links ORDER BY coluna, ordem ASC");
    $all_footer_links = $stmt_all_links->fetchAll(PDO::FETCH_ASSOC);

    $stmt_all_icons = $pdo->query("SELECT * FROM footer_icons ORDER BY coluna, ordem ASC");
    $all_footer_icons = $stmt_all_icons->fetchAll(PDO::FETCH_ASSOC);
    $stmt_banners = $pdo->query("SELECT * FROM banners ORDER BY ordem ASC");
    $banners = $stmt_banners->fetchAll(PDO::FETCH_ASSOC);
    $stmt_promo_banners = $pdo->query("SELECT * FROM promo_banners ORDER BY section_key, ordem ASC");
    $all_promo_banners = $stmt_promo_banners->fetchAll(PDO::FETCH_ASSOC);
    $stmt_social_icons = $pdo->query("SELECT * FROM social_icons ORDER BY ordem ASC");
    $all_social_icons = $stmt_social_icons->fetchAll(PDO::FETCH_ASSOC);
    $stmt_all_produtos = $pdo->query("SELECT id, nome FROM produtos ORDER BY nome ASC");
    $all_produtos = $stmt_all_produtos->fetchAll(PDO::FETCH_ASSOC);
    $stmt_all_categorias = $pdo->query("SELECT id, nome FROM categorias ORDER BY nome ASC");
    $all_categorias = $stmt_all_categorias->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    if(empty($message)) {
        $message = "Erro ao carregar dados: " . $e->getMessage();
        $message_type = "error";
    }
    // Inicializa todos os arrays para evitar erros no HTML
    $configs = []; $all_footer_links = []; $all_footer_icons = []; $banners = []; $all_promo_banners = [];
    $all_social_icons = []; $all_produtos = []; $all_categorias = [];
}

// ⭐ ATUALIZADO: Atribui valores (lidos da config_site)
$current_logo_url = $configs['logo_url'] ?? '';
$current_nome_da_loja = $configs['nome_da_loja'] ?? 'Sua Loja E-commerce';
$current_site_base_url = $configs['site_base_url'] ?? 'http://localhost/seu-site/';
$current_telefone = $configs['telefone_contato'] ?? '';
$current_email    = $configs['email_contato'] ?? '';
$current_horario  = $configs['horario_atendimento'] ?? '';
$current_footer_credits = $configs['footer_credits'] ?? '';
$current_wpp_ativo = filter_var($configs['whatsapp_banner_ativo'] ?? false, FILTER_VALIDATE_BOOLEAN);
$current_wpp_img_url = $configs['whatsapp_banner_img_url'] ?? '';
$current_wpp_link_url = $configs['whatsapp_banner_link_url'] ?? '';

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Layout e Configurações</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>

    <style>
        /* ==========================================================
            CSS COMPLETO DO PAINEL ADMIN (Com Abas)
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

         /* --- Sidebar (CSS OMITIDO, assumindo que vem do admin_sidebar.php) --- */
         .sidebar { width: var(--sidebar-width); background-color: var(--sidebar-color); height: 100vh; position: fixed; left: 0; top: 0; padding: 1.5rem; display: flex; flex-direction: column; border-right: 1px solid var(--border-color); z-index: 1000; transition: transform 0.3s ease; box-shadow: var(--box-shadow); }
         .sidebar .logo-area { display: flex; flex-direction: column; align-items: center; margin-bottom: 1rem; } .sidebar .logo-circle { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 0.5rem; box-shadow: 0 0 10px rgba(74, 105, 189, 0.6); overflow: hidden; background-color: #fff; } .sidebar .logo-circle svg { color: var(--primary-color); width: 24px; height: 24px; } .sidebar .logo-text { font-size: 1rem; font-weight: 600; color: var(--text-color); text-align: center; } .sidebar .divider { width: 100%; height: 1px; background-color: var(--border-color); margin: 1rem 0; } .sidebar nav { flex-grow: 1; } .sidebar nav a { display: flex; align-items: center; gap: 0.8rem; padding: 0.8rem 1rem; color: var(--light-text-color); text-decoration: none; border-radius: var(--border-radius); margin-bottom: 0.5rem; transition: all 0.3s ease; border: 1px solid transparent; background-color: transparent; } .sidebar nav a:hover, .sidebar nav a.active { background-color: var(--glass-background); color: var(--text-color); border-color: var(--primary-color); box-shadow: 0 2px 8px rgba(74, 105, 189, 0.4); } .sidebar nav a svg { width: 20px; height: 20px; flex-shrink: 0; } .user-profile { position: relative; margin-top: auto; background-color: var(--glass-background); padding: 0.75rem; border-radius: var(--border-radius); display: flex; align-items: center; gap: 1rem; cursor: pointer; border: 1px solid var(--border-color); transition: all 0.3s ease; } .user-profile:hover { border-color: var(--primary-color); } .avatar { width: 35px; height: 35px; border-radius: 50%; background-color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.9rem; } .user-info .user-name { font-weight: 600; font-size: 0.85rem; line-height: 1.2; } .user-info .user-level { font-size: 0.7rem; color: var(--light-text-color); } .profile-dropdown { position: absolute; bottom: calc(100% + 10px); left: 0; width: 100%; background-color: #2c3a4f; border-radius: var(--border-radius); border: 1px solid var(--border-color); padding: 0.5rem; z-index: 20; visibility: hidden; opacity: 0; transform: translateY(10px); transition: all 0.3s ease; } .profile-dropdown.show { visibility: visible; opacity: 1; transform: translateY(0); } .profile-dropdown a { display: flex; gap: 0.75rem; padding: 0.75rem; color: var(--light-text-color); font-size: 0.85rem; border-radius: 6px; } .profile-dropdown a:hover { background-color: var(--glass-background); color: var(--text-color); }
         .sidebar nav .sidebar-submenu { padding-left: 20px; margin-top: -5px; margin-bottom: 5px; overflow: hidden; transition: max-height 0.3s ease-out; max-height: 0; } .sidebar nav .sidebar-submenu.open { max-height: 500px; } .sidebar nav a.has-children { display: flex; justify-content: space-between; align-items: center; } .sidebar nav a .menu-chevron { width: 16px; height: 16px; color: var(--light-text-color); transition: transform 0.3s ease; } .sidebar nav a.open .menu-chevron { transform: rotate(90deg); } .sidebar-submenu a { font-size: 0.9em; padding: 0.7rem 1rem 0.7rem 1.5rem; color: var(--light-text-color); position: relative; } .sidebar-submenu a::before { content: ''; position: absolute; left: 0.5rem; top: 50%; transform: translateY(-50%); width: 4px; height: 4px; border-radius: 50%; background-color: var(--light-text-color); transition: all 0.3s ease; } .sidebar-submenu a:hover { color: var(--text-color); background-color: transparent; border-color: transparent; box-shadow: none; } .sidebar-submenu a:hover::before { background-color: var(--primary-color); } .sidebar-submenu a.active-child { color: #fff; font-weight: 600; } .sidebar-submenu a.active-child::before { background-color: var(--primary-color); transform: translateY(-50%) scale(1.5); }

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
         .tab-pane .crud-section:first-child .form-container,
         .tab-pane .crud-section:first-child .list-container {
             border-top-left-radius: 0;
             border-top-right-radius: 0;
         }
         .form-container h4 { font-size: 1.1rem; color: var(--primary-color); margin-bottom: 1.25rem; font-weight: 600; }

         /* --- Formulários --- */
         .form-group { margin-bottom: 1.25rem; }
         .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--light-text-color); font-size: 0.8rem; text-transform: uppercase; }
         .form-group input[type="text"], .form-group input[type="email"], .form-group input[type="number"], .form-group textarea, .form-group select { width: 100%; padding: 0.65rem 0.8rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); background-color: rgba(0, 0, 0, 0.3); color: var(--text-color); box-sizing: border-box; transition: border-color 0.3s ease, box-shadow 0.3s ease; font-size: 0.9em; }
         .form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color: var(--primary-color); box-shadow: 0 0 8px rgba(74, 105, 189, 0.5); outline: none; }
         .form-group textarea { min-height: 100px; resize: vertical; }
         .form-group-check { display: flex; align-items: center; padding-top: 1rem; }
         .form-group-check label { font-weight: 400; color: var(--text-color); display: inline; margin-left: 0.5rem; text-transform: none; }
         .form-group-check input[type="checkbox"] { width: auto; vertical-align: middle; accent-color: var(--primary-color); cursor: pointer;}
         .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem 1.5rem; }
         button[type="submit"] { padding: 0.65rem 1.2rem; background-color: var(--primary-color); color: #fff; border: none; border-radius: var(--border-radius); cursor: pointer; font-weight: 600; transition: background-color 0.3s ease, transform 0.1s ease; font-size: 0.9em; }
         button[type="submit"]:hover { background-color: var(--secondary-color); transform: translateY(-1px); }
         button[type="submit"].update { background-color: #28a745; }
         button[type="submit"].update:hover { background-color: #218838; }
         .form-container a.cancel { color: var(--light-text-color); margin-left: 1rem; font-size: 0.9em; }
         .form-container a.cancel:hover { text-decoration: underline; }
         .link-field-wrapper { display: none; }

         /* --- Tabelas --- */
         .list-container { overflow-x: auto; }
         .list-container table { width: 100%; border-collapse: collapse; background-color: transparent; border-radius: 0; overflow: hidden; font-size: 0.85em; border: none; min-width: 600px; }
         .list-container th, .list-container td { border-bottom: 1px solid var(--border-color); padding: 10px 12px; text-align: left; vertical-align: middle; }
         .list-container th { background-color: rgba(0, 0, 0, 0.4); color: var(--text-color); font-weight: 600; text-transform: uppercase; font-size: 0.75em; }
         .list-container tbody tr:last-child td { border-bottom: none; }
         .list-container tbody tr:hover { background-color: rgba(255, 255, 255, 0.05); }
         .list-container td img { max-height: 40px; max-width: 120px; border-radius: 4px; vertical-align: middle; background-color: rgba(255,255,255,0.8); padding: 2px; object-fit: contain;}
         .list-container .actions { white-space: nowrap; text-align: right; }
         .list-container .actions a { color: var(--primary-color); margin-left: 0.8rem; font-size: 0.85em; transition: color 0.2s ease; }
         .list-container .actions a:hover { color: var(--secondary-color); }
         .list-container .actions a.delete { color: var(--danger-color); }
         .list-container .actions a.delete:hover { color: #c0392b; }
         .status-ativo { color: #82e0aa; font-weight: bold; }
         .status-inativo { color: var(--light-text-color); }
         .list-container td .svg-preview { width: 24px; height: 24px; }
         .list-container td .svg-preview svg { width: 100%; height: 100%; fill: var(--light-text-color); }
         .list-container td span.link-type { font-size: 0.8em; color: var(--light-text-color); display: block; }
         .list-container td span.link-dest { font-weight: bold; }

         /* --- Mensagens --- */
         .message { padding: 1rem; border-radius: var(--border-radius); margin-bottom: 1.5rem; font-weight: 600; border: 1px solid transparent; backdrop-filter: blur(3px); }
         .message.success { background-color: var(--success-bg); color: var(--success-text); border-color: rgba(21, 87, 36, 0.5); }
         .message.error { background-color: var(--error-bg); color: var(--error-text); border-color: rgba(114, 28, 36, 0.5); }
         .message.info { background-color: var(--info-bg); color: var(--info-text); border-color: rgba(8, 66, 152, 0.5); }

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
             .tab-pane .crud-section:first-child .form-container,
             .tab-pane .crud-section:first-child .list-container {
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

    <?php include 'admin_sidebar.php'; // Inclui a sidebar ?>

    <main class="main-content">
        <div class="content-header">
            <h1>Layout e Configurações Globais</h1>
            <p>Gerencie informações de contato, links do rodapé, banners e a aparência geral do site.</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo nl2br(htmlspecialchars($message)); ?>
            </div>
        <?php endif; ?>

        <div class="tab-navigation">
            <button class="tab-button active" data-tab-target="#tab-geral">Geral</button>
            <button class="tab-button" data-tab-target="#tab-homepage">Homepage</button>
            <button class="tab-button" data-tab-target="#tab-footer">Rodapé</button>
        </div>

        <div class="tab-content-wrapper">

            <div class="tab-pane active" id="tab-geral">
                <div class="crud-section" id="section-configs">
                    <h3>1. Configurações Globais (Site e Header)</h3>
                    <div class="form-container">
                        <form action="configlayout.php#tab-geral" method="POST">
                            <div class="form-group">
                                <label for="nome_da_loja">Nome da Loja (será usado no e-mail, e na página Quem Somos)</label>
                                <input type="text" id="nome_da_loja" name="nome_da_loja" value="<?php echo htmlspecialchars($configs['nome_da_loja'] ?? ''); ?>" placeholder="Nome que aparece em títulos e e-mails">
                            </div>

                            <div class="form-group">
                                <label for="categoria_da_loja">Categoria Principal da Loja (será usado na página Quem Somos)</label>
                                <input type="text"
                                       id="categoria_da_loja"
                                       name="categoria_da_loja"
                                       value="<?php echo htmlspecialchars($configs['categoria_da_loja'] ?? ''); ?>"
                                       placeholder="Ex: tecnologia, moda, produtos inovadores">
                            </div>
                            <div class="form-group">
                                <label for="site_base_url">URL Base do Site (será usado no e-mail para redirecionar o usuário no e-mail de bem-vindo e de pagamento aprovado e também será usado no getway de pagamentos)</label>
                                <input type="text" id="site_base_url" name="site_base_url" value="<?php echo htmlspecialchars($configs['site_base_url'] ?? ''); ?>" placeholder="https://seudominio.com/">
                            </div>
                            <div class="form-group">
                                <label for="logo_url">URL Logo Header (proporção da imagem deve ser: 221x119)</label>
                                <input type="text" id="logo_url" name="logo_url" value="<?php echo htmlspecialchars($configs['logo_url'] ?? ''); ?>" placeholder="https://... ou /uploads/logo.png">
                            </div>

                            <button type="submit" name="salvar_geral">Salvar Configurações Gerais</button>
                        </form>
                    </div>
                </div>
            </div>


            <div class="tab-pane" id="tab-homepage">
                <div class="crud-section" id="section-wpp-banner">
                    <h3>2. Banner WhatsApp (Homepage)</h3>
                    <div class="form-container">
                        <form action="configlayout.php#tab-homepage" method="POST">
                            <div class="form-group"><label for="wpp_img_url">URL da Imagem do Banner (proporção do banner deve ser aproximadamente: larg.:1110px x alt.:330px) </label><input type="text" id="wpp_img_url" name="whatsapp_banner_img_url" value="<?php echo htmlspecialchars($current_wpp_img_url); ?>" placeholder="/uploads/wpp-banner.png"></div>
                            <div class="form-group"><label for="wpp_link_url">URL do Link (WhatsApp)</label><input type="text" id="wpp_link_url" name="whatsapp_banner_link_url" value="<?php echo htmlspecialchars($current_wpp_link_url); ?>" placeholder="https://wa.me/5511..."></div>
                            <div class="form-group form-group-check" style="padding-top: 0;">
                                <input type="checkbox" id="wpp_ativo" name="whatsapp_banner_ativo" value="1" <?php echo $current_wpp_ativo ? 'checked' : ''; ?>>
                                <label for="wpp_ativo">Ativo (Exibir banner no site)</label>
                            </div>
                            <button type="submit" name="salvar_wpp_banner">Salvar Banner WhatsApp</button>
                        </form>
                    </div>
                </div>

                <div class="crud-section" id="section-banners">
                    <h3>3. Banners do Carrossel (Homepage)</h3>
                    <div class="form-container">
                        <h4><?php echo $is_editing_banner ? 'Editar Banner' : 'Adicionar Novo Banner'; ?></h4>
                        <form action="configlayout.php#tab-homepage" method="POST">
                            <?php if ($is_editing_banner): ?><input type="hidden" name="id" value="<?php echo $edit_banner['id']; ?>"><?php endif; ?>
                            <div class="form-group"><label for="imagem_url">URL da Imagem(proporção do banner deve ser aproximadamente: larg.:1015px x alt.:280px)</label><input type="text" id="imagem_url" name="imagem_url" value="<?php echo htmlspecialchars($edit_banner['imagem_url'] ?? ''); ?>" required placeholder="https://..."></div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="link_tipo">Tipo de Link</label>
                                    <select name="link_tipo" id="main_banner_link_tipo" onchange="showHideLinkFields('main_banner', this.value)">
                                        <option value="url" <?php echo (($edit_banner['link_tipo'] ?? 'url') == 'url') ? 'selected' : ''; ?>>URL Externa</option>
                                        <option value="produto" <?php echo (($edit_banner['link_tipo'] ?? '') == 'produto') ? 'selected' : ''; ?>>Produto</option>
                                        <option value="categoria" <?php echo (($edit_banner['link_tipo'] ?? '') == 'categoria') ? 'selected' : ''; ?>>Categoria</option>
                                        <option value="none" <?php echo (($edit_banner['link_tipo'] ?? '') == 'none') ? 'selected' : ''; ?>>Nenhum (Não clicável)</option>
                                    </select>
                                </div>
                                <div class="form-group link-field-wrapper" id="main_banner_link_url_wrapper">
                                    <label for="link_url">URL do Link (http://...)</label><input type="text" id="link_url" name="link_url" value="<?php echo htmlspecialchars($edit_banner['link_url'] ?? '#'); ?>">
                                </div>
                                <div class="form-group link-field-wrapper" id="main_banner_produto_id_wrapper">
                                    <label for="produto_id">Produto</label>
                                    <select name="produto_id" id="produto_id">
                                        <option value="">-- Selecione um Produto --</option>
                                        <?php foreach($all_produtos as $prod): ?>
                                        <option value="<?php echo $prod['id']; ?>" <?php echo ($edit_banner && $edit_banner['produto_id'] == $prod['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($prod['nome']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group link-field-wrapper" id="main_banner_categoria_id_wrapper">
                                    <label for="categoria_id">Categoria</label>
                                    <select name="categoria_id" id="categoria_id">
                                        <option value="">-- Selecione uma Categoria --</option>
                                        <?php foreach($all_categorias as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo ($edit_banner && $edit_banner['categoria_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['nome']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group"><label for="alt_text">Texto Alternativo (Alt)</label><input type="text" id="alt_text" name="alt_text" value="<?php echo htmlspecialchars($edit_banner['alt_text'] ?? 'banner'); ?>"></div>
                            <div class="form-grid">
                                <div class="form-group"><label for="ordem_banner">Ordem</label><input type="number" id="ordem_banner" name="ordem" value="<?php echo $edit_banner['ordem'] ?? 0; ?>" required></div>
                                <div class="form-group form-group-check" style="align-self: end; padding-top: 1rem;">
                                    <input type="checkbox" id="ativo_banner" name="ativo" value="1" <?php echo ($edit_banner === null || !empty($edit_banner['ativo'])) ? 'checked' : ''; ?>> <label for="ativo_banner">Ativo</label>
                                </div>
                            </div>
                            <button type="submit" name="salvar_banner"><?php echo $is_editing_banner ? 'Salvar Alterações' : 'Adicionar Banner'; ?></button>
                            <?php if ($is_editing_banner): ?><a href="configlayout.php?#tab-homepage" class="cancel">Cancelar Edição</a><?php endif; ?>
                        </form>
                    </div>
                    <div class="list-container">
                        <h4>Banners Atuais (Carrossel)</h4>
                        <table>
                            <thead><tr><th>Ordem</th><th>Preview</th><th>Destino do Link</th><th>Status</th><th class="actions">Ações</th></tr></thead>
                            <tbody>
                                <?php foreach ($banners as $banner): ?>
                                <tr>
                                    <td data-label="Ordem"><?php echo $banner['ordem']; ?></td>
                                    <td data-label="Preview"><img src="<?php echo htmlspecialchars($banner['imagem_url']); ?>" alt="preview"></td>
                                    <td data-label="Destino">
                                        <span class="link-type">Tipo: <?php echo htmlspecialchars($banner['link_tipo']); ?></span>
                                        <?php if($banner['link_tipo'] == 'url'): ?> <span class="link-dest"><?php echo htmlspecialchars($banner['link_url']); ?></span>
                                        <?php elseif($banner['link_tipo'] == 'produto'): ?> <span class="link-dest">ID Produto: <?php echo htmlspecialchars($banner['produto_id']); ?></span>
                                        <?php elseif($banner['link_tipo'] == 'categoria'): ?> <span class="link-dest">ID Cat: <?php echo htmlspecialchars($banner['categoria_id']); ?></span>
                                        <?php else: ?> <span class="link-dest">Nenhum</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Status"><span class="<?php echo $banner['ativo'] ? 'status-ativo' : 'status-inativo'; ?>"><?php echo $banner['ativo'] ? 'Ativo' : 'Inativo'; ?></span></td>
                                    <td class="actions">
                                        <a href="configlayout.php?edit_banner=<?php echo $banner['id']; ?>#section-banners">Editar</a>
                                        <a href="configlayout.php?delete_banner=<?php echo $banner['id']; ?>" onclick="return confirm('Tem certeza?');" class="delete">Remover</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($banners)): ?> <tr><td colspan="5" style="text-align: center; color: var(--light-text-color);">Nenhum banner cadastrado.</td></tr> <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="crud-section" id="section-promo-banners">
                    <h3>4. Banners Promocionais (Grid da Homepage)</h3>
                    <div class="form-container">
                        <h4><?php echo $is_editing_promo_banner ? 'Editar Banner Promocional' : 'Adicionar Novo Banner Promocional'; ?></h4>
                        <form action="configlayout.php?#tab-homepage" method="POST">
                            <input type="hidden" name="promo_id" value="<?php echo $edit_promo_banner['id'] ?? ''; ?>">
                            <div class="form-group"><label>URL da Imagem</label><input type="text" name="promo_imagem_url" value="<?php echo htmlspecialchars($edit_promo_banner['imagem_url'] ?? ''); ?>" required placeholder="https://..."></div>
                            <div class="form-group"><label>Texto Alternativo (Alt)</label><input type="text" name="promo_alt_text" value="<?php echo htmlspecialchars($edit_promo_banner['alt_text'] ?? 'Banner Promocional'); ?>"></div>
                            <div class="form-grid">
                                 <div class="form-group">
                                    <label for="promo_link_tipo">Tipo de Link</label>
                                    <select name="promo_link_tipo" id="promo_banner_link_tipo" onchange="showHideLinkFields('promo_banner', this.value)">
                                        <option value="url" <?php echo (($edit_promo_banner['link_tipo'] ?? 'url') == 'url') ? 'selected' : ''; ?>>URL Externa</option>
                                        <option value="produto" <?php echo (($edit_promo_banner['link_tipo'] ?? '') == 'produto') ? 'selected' : ''; ?>>Produto</option>
                                        <option value="categoria" <?php echo (($edit_promo_banner['link_tipo'] ?? '') == 'categoria') ? 'selected' : ''; ?>>Categoria</option>
                                        <option value="none" <?php echo (($edit_promo_banner['link_tipo'] ?? '') == 'none') ? 'selected' : ''; ?>>Nenhum (Não clicável)</option>
                                    </select>
                                 </div>
                                 <div class="form-group link-field-wrapper" id="promo_banner_link_url_wrapper">
                                     <label for="promo_link_url">URL do Link (http://...)</label><input type="text" id="promo_link_url" name="promo_link_url" value="<?php echo htmlspecialchars($edit_promo_banner['link_url'] ?? '#'); ?>">
                                 </div>
                                 <div class="form-group link-field-wrapper" id="promo_banner_produto_id_wrapper">
                                     <label for="promo_produto_id">Produto</label>
                                     <select name="promo_produto_id" id="promo_produto_id">
                                         <option value="">-- Selecione um Produto --</option>
                                         <?php foreach($all_produtos as $prod): ?>
                                         <option value="<?php echo $prod['id']; ?>" <?php echo ($edit_promo_banner && $edit_promo_banner['produto_id'] == $prod['id']) ? 'selected' : ''; ?>>
                                             <?php echo htmlspecialchars($prod['nome']); ?>
                                         </option>
                                         <?php endforeach; ?>
                                     </select>
                                 </div>
                                 <div class="form-group link-field-wrapper" id="promo_banner_categoria_id_wrapper">
                                     <label for="promo_categoria_id">Categoria</label>
                                     <select name="promo_categoria_id" id="promo_categoria_id">
                                         <option value="">-- Selecione uma Categoria --</option>
                                         <?php foreach($all_categorias as $cat): ?>
                                         <option value="<?php echo $cat['id']; ?>" <?php echo ($edit_promo_banner && $edit_promo_banner['categoria_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                             <?php echo htmlspecialchars($cat['nome']); ?>
                                         </option>
                                         <?php endforeach; ?>
                                     </select>
                                 </div>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Seção do Grid</label>
                                    <select name="promo_section_key" required>
                                        <option value="">-- Selecione a Seção --</option>
                                        <option value="top_4_col" <?php echo ($edit_promo_banner && $edit_promo_banner['section_key'] == 'top_4_col') ? 'selected' : ''; ?>>4 Colunas (Topo) (Imagem deve ser: larg.:263px x alt.:215px )</option>
                                        <option value="mid_2_col" <?php echo ($edit_promo_banner && $edit_promo_banner['section_key'] == 'mid_2_col') ? 'selected' : ''; ?>>2 Colunas (Meio) (Imagem deve ser: larg.:545px x alt.:233px )</option>
                                        <option value="bottom_2_col" <?php echo ($edit_promo_banner && $edit_promo_banner['section_key'] == 'bottom_2_col') ? 'selected' : ''; ?>>2 Colunas (Inferior) (Imagem deve ser: larg.:545px x alt.:233px )</option>
                                    </select>
                                 </div>
                                 <div class="form-group"><label>Ordem</label><input type="number" name="promo_ordem" value="<?php echo $edit_promo_banner['ordem'] ?? 0; ?>"></div>
                            </div>
                            <div class="form-group form-group-check" style="padding-top: 0;">
                                 <input type="checkbox" id="promo_ativo" name="promo_ativo" value="1" <?php echo ($edit_promo_banner === null || !empty($edit_promo_banner['ativo'])) ? 'checked' : ''; ?>>
                                 <label for="promo_ativo">Ativo</label>
                            </div>
                            <button type="submit" name="salvar_promo_banner"><?php echo $is_editing_promo_banner ? 'Salvar Alterações' : 'Adicionar Banner Promo'; ?></button>
                            <?php if ($is_editing_promo_banner): ?><a href="configlayout.php?#tab-homepage" class="cancel">Cancelar Edição</a><?php endif; ?>
                        </form>
                    </div>
                    <div class="list-container">
                        <h4>Lista de Banners Promocionais</h4>
                        <table>
                            <thead><tr><th>Seção</th><th>Ordem</th><th>Preview</th><th>Destino do Link</th><th>Status</th><th class="actions">Ações</th></tr></thead>
                            <tbody>
                                <?php foreach ($all_promo_banners as $promo): ?>
                                <tr>
                                    <td data-label="Seção"><?php echo htmlspecialchars($promo['section_key']); ?></td>
                                    <td data-label="Ordem"><?php echo $promo['ordem']; ?></td>
                                    <td data-label="Preview"><img src="<?php echo htmlspecialchars($promo['imagem_url']); ?>" alt="preview"></td>
                                    <td data-label="Destino">
                                        <span class="link-type">Tipo: <?php echo htmlspecialchars($promo['link_tipo']); ?></span>
                                        <?php if($promo['link_tipo'] == 'url'): ?> <span class="link-dest"><?php echo htmlspecialchars($promo['link_url']); ?></span>
                                        <?php elseif($promo['link_tipo'] == 'produto'): ?> <span class="link-dest">ID Produto: <?php echo htmlspecialchars($promo['produto_id']); ?></span>
                                        <?php elseif($promo['link_tipo'] == 'categoria'): ?> <span class="link-dest">ID Cat: <?php echo htmlspecialchars($promo['categoria_id']); ?></span>
                                        <?php else: ?> <span class="link-dest">Nenhum</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Status"><span class="<?php echo $promo['ativo'] ? 'status-ativo' : 'status-inativo'; ?>"><?php echo $promo['ativo'] ? 'Ativo' : 'Inativo'; ?></span></td>
                                    <td class="actions">
                                        <a href="configlayout.php?edit_promo_banner=<?php echo $promo['id']; ?>#section-promo-banners">Editar</a>
                                        <a href="configlayout.php?delete_promo_banner=<?php echo $promo['id']; ?>" onclick="return confirm('Certeza?');" class="delete">Remover</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($all_promo_banners)): ?> <tr><td colspan="6" style="text-align: center; color: var(--light-text-color);">Nenhum banner promocional cadastrado.</td></tr> <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="tab-pane" id="tab-footer">

                <div class="crud-section" id="section-footer-configs">
                    <h3>5. Informações de Contato e Créditos</h3>
                    <div class="form-container">
                        <form action="configlayout.php?#tab-footer" method="POST">
                            <div class="form-grid">
                                <div class="form-group"><label for="telefone_contato">Telefone Footer</label><input type="text" id="telefone_contato" name="telefone_contato" value="<?php echo htmlspecialchars($configs['telefone_contato'] ?? ''); ?>" placeholder="(XX) XXXXX-XXXX"></div>
                                <div class="form-group"><label for="email_contato">Email Footer</label><input type="email" id="email_contato" name="email_contato" value="<?php echo htmlspecialchars($configs['email_contato'] ?? ''); ?>" placeholder="contato@seudominio.com"></div>
                                <div class="form-group" style="grid-column: 1 / -1;"><label for="horario_atendimento">Horário Atendimento Footer</label><input type="text" id="horario_atendimento" name="horario_atendimento" value="<?php echo htmlspecialchars($configs['horario_atendimento'] ?? ''); ?>" placeholder="Ex: Seg. a Sex., 9h-18h"></div>
                            </div>
                            <div class="form-group"><label for="footer_credits">Créditos Footer (Texto no final)</label><textarea id="footer_credits" name="footer_credits" placeholder="Nome da Loja, CNPJ..."><?php echo htmlspecialchars($configs['footer_credits'] ?? ''); ?></textarea></div>
                            <button type="submit" name="salvar_footer_configs">Salvar Informações do Rodapé</button>
                        </form>
                    </div>
                </div>

                <div class="crud-section" id="section-links">
                    <h3>6. Links Institucionais do Rodapé</h3>
                    <div class="form-container">
                         <h4><?php echo $is_editing_link ? 'Editar Link' : 'Adicionar Novo Link'; ?></h4>
                         <form action="configlayout.php?#tab-footer" method="POST">
                             <input type="hidden" name="link_id" value="<?php echo $edit_link['id'] ?? ''; ?>">
                             <div class="form-grid">
                                 <div class="form-group"><label>Texto</label><input type="text" name="link_texto" value="<?php echo htmlspecialchars($edit_link['texto'] ?? ''); ?>" required></div>
                                 <div class="form-group"><label>URL</label><input type="text" name="link_url" value="<?php echo htmlspecialchars($edit_link['url'] ?? '#'); ?>" placeholder="Ex: sobre-nos.php"></div>
                                 <div class="form-group">
                                     <label>Coluna</label>
                                     <select name="link_coluna">
                                         <option value="institucional" <?php echo (($edit_link['coluna'] ?? 'institucional') == 'institucional') ? 'selected' : ''; ?>>Institucional</option>
                                     </select>
                                 </div>
                                 <div class="form-group"><label>Ordem</label><input type="number" name="link_ordem" value="<?php echo $edit_link['ordem'] ?? 0; ?>"></div>
                             </div>
                             <div class="form-group form-group-check" style="padding-top: 0;">
                                 <input type="checkbox" id="link_ativo" name="link_ativo" value="1" <?php echo ($edit_link === null || !empty($edit_link['ativo'])) ? 'checked' : ''; ?>>
                                 <label for="link_ativo">Ativo (Exibir no rodapé)</label>
                             </div>
                            <button type="submit" name="salvar_link"><?php echo $is_editing_link ? 'Salvar Alterações' : 'Adicionar Link'; ?></button>
                            <?php if ($is_editing_link): ?><a href="configlayout.php?#tab-footer" class="cancel">Cancelar Edição</a><?php endif; ?>
                         </form>
                    </div>
                    <div class="list-container">
                         <h4>Lista de Links</h4>
                         <table>
                             <thead><tr><th>Coluna</th><th>Ordem</th><th>Texto</th><th>URL</th><th>Status</th><th class="actions">Ações</th></tr></thead>
                             <tbody>
                                 <?php foreach ($all_footer_links as $link): ?>
                                 <tr>
                                     <td data-label="Coluna"><?php echo htmlspecialchars($link['coluna']); ?></td>
                                     <td data-label="Ordem"><?php echo $link['ordem']; ?></td>
                                     <td data-label="Texto"><?php echo htmlspecialchars($link['texto']); ?></td>
                                     <td data-label="URL"><?php echo htmlspecialchars($link['url']); ?></td>
                                     <td data-label="Status"><span class="<?php echo $link['ativo'] ? 'status-ativo' : 'status-inativo'; ?>"><?php echo $link['ativo'] ? 'Ativo' : 'Inativo'; ?></span></td>
                                     <td class="actions">
                                         <a href="configlayout.php?edit_link=<?php echo $link['id']; ?>#section-links">Editar</a>
                                         <a href="configlayout.php?delete_link=<?php echo $link['id']; ?>" onclick="return confirm('Tem certeza?');" class="delete">Remover</a>
                                     </td>
                                 </tr>
                                 <?php endforeach; ?>
                                 <?php if (empty($all_footer_links)): ?> <tr><td colspan="6" style="text-align: center; color: var(--light-text-color);">Nenhum link cadastrado.</td></tr> <?php endif; ?>
                             </tbody>
                         </table>
                    </div>
                </div>


                <div class="crud-section" id="section-icons">
                         <h3>7. Ícones e Bandeiras do Footer</h3>
                         <div class="form-container">
                             <h4><?php echo $is_editing_icon ? 'Editar Ícone' : 'Adicionar Novo Ícone'; ?></h4>
                             <form action="configlayout.php?#tab-footer" method="POST">
                                 <input type="hidden" name="icon_id" value="<?php echo $edit_icon['id'] ?? ''; ?>">
                                 <div class="form-grid">
                                     <div class="form-group"><label>URL Imagem</label><input type="text" name="icon_imagem_url" value="<?php echo htmlspecialchars($edit_icon['imagem_url'] ?? ''); ?>" required placeholder="https://..."></div>
                                     <div class="form-group"><label>Texto Alt</label><input type="text" name="icon_alt_text" value="<?php echo htmlspecialchars($edit_icon['alt_text'] ?? ''); ?>"></div>
                                     <div class="form-group"><label>URL Link</label><input type="text" name="icon_link_url" value="<?php echo htmlspecialchars($edit_icon['link_url'] ?? '#'); ?>"></div>
                                     <div class="form-group">
                                         <label>Coluna</label>
                                         <select name="icon_coluna">
                                             <option value="pagamento_prazo" <?php echo (($edit_icon['coluna'] ?? 'pagamento_prazo') == 'pagamento_prazo') ? 'selected' : ''; ?>>Pag. Prazo (imagem deve ser: larg.: 42px x alt.:34px)</option>
                                             <option value="pagamento_vista" <?php echo ($edit_icon && $edit_icon['coluna'] == 'pagamento_vista') ? 'selected' : ''; ?>>Pag. Vista (imagem deve ser: larg.: 42px x alt.:34px)</option>
                                             <option value="seguranca" <?php echo ($edit_icon && $edit_icon['coluna'] == 'seguranca') ? 'selected' : ''; ?>>Segurança(imagem deve ser: larg.: 120px x alt.:30px)</option>
                                         </select>
                                     </div>
                                     <div class="form-group"><label>Ordem</label><input type="number" name="icon_ordem" value="<?php echo $edit_icon['ordem'] ?? 0; ?>"></div>
                                 </div>
                                 <div class="form-group form-group-check" style="padding-top: 0;">
                                     <input type="checkbox" id="icon_ativo" name="icon_ativo" value="1" <?php echo ($edit_icon === null || !empty($edit_icon['ativo'])) ? 'checked' : ''; ?>>
                                     <label for="icon_ativo">Ativo</label>
                                 </div>
                                 <button type="submit" name="salvar_icon"><?php echo $is_editing_icon ? 'Salvar Alterações' : 'Adicionar Ícone'; ?></button>
                                 <?php if ($is_editing_icon): ?><a href="configlayout.php?#tab-footer" class="cancel">Cancelar Edição</a><?php endif; ?>
                             </form>
                         </div>
                         <div class="list-container">
                             <h4>Lista de Ícones</h4>
                             <table>
                                 <thead><tr><th>Coluna</th><th>Ordem</th><th>Preview</th><th>Alt Text</th><th>Status</th><th class="actions">Ações</th></tr></thead>
                                 <tbody>
                                     <?php foreach ($all_footer_icons as $icon): ?>
                                     <tr>
                                         <td data-label="Coluna"><?php echo htmlspecialchars($icon['coluna']); ?></td>
                                         <td data-label="Ordem"><?php echo $icon['ordem']; ?></td>
                                         <td data-label="Preview"><img src="<?php echo htmlspecialchars($icon['imagem_url']); ?>" alt="preview"></td>
                                         <td data-label="Alt Text"><?php echo htmlspecialchars($icon['alt_text']); ?></td>
                                         <td data-label="Status"><span class="<?php echo $icon['ativo'] ? 'status-ativo' : 'status-inativo'; ?>"><?php echo $icon['ativo'] ? 'Ativo' : 'Inativo'; ?></span></td>
                                         <td class="actions">
                                             <a href="configlayout.php?edit_icon=<?php echo $icon['id']; ?>#section-icons">Editar</a>
                                             <a href="configlayout.php?delete_icon=<?php echo $icon['id']; ?>" onclick="return confirm('Tem certeza?');" class="delete">Remover</a>
                                         </td>
                                     </tr>
                                     <?php endforeach; ?>
                                     <?php if (empty($all_footer_icons)): ?> <tr><td colspan="6" style="text-align: center; color: var(--light-text-color);">Nenhum ícone cadastrado.</td></tr> <?php endif; ?>
                                 </tbody>
                             </table>
                         </div>
                </div>

                <div class="crud-section" id="section-social-icons">
                         <h3>8. Ícones de Redes Sociais (Footer)</h3>
                         <div class="form-container">
                             <h4><?php echo $is_editing_social_icon ? 'Editar Ícone Social' : 'Adicionar Novo Ícone Social'; ?></h4>
                             <form action="configlayout.php?#tab-footer" method="POST">
                                 <input type="hidden" name="social_id" value="<?php echo $edit_social_icon['id'] ?? ''; ?>">

                                 <div class="form-group">
                                     <label for="social_preset_select">Presets de Redes Sociais (Opcional)</label>
                                     <select id="social_preset_select" onchange="applySocialPreset(this.value)">
                                         <option value="">-- Selecione para preencher --</option>
                                         <option value="instagram">Instagram</option>
                                         <option value="facebook">Facebook</option>
                                         <option value="whatsapp">WhatsApp</option>
                                         <option value="twitter_x">X (Twitter)</option>
                                         <option value="tiktok">TikTok</option>
                                         <option value="linkedin">LinkedIn</option>
                                         <option value="youtube">YouTube</option>
                                     </select>
                                 </div>

                                 <div class="form-grid">
                                     <div class="form-group"><label>Nome</label><input type="text" id="social_nome" name="social_nome" value="<?php echo htmlspecialchars($edit_social_icon['nome'] ?? ''); ?>" required placeholder="Ex: Instagram"></div>
                                     <div class="form-group"><label>URL do Link</label><input type="text" name="social_link_url" value="<?php echo htmlspecialchars($edit_social_icon['link_url'] ?? '#'); ?>" required placeholder="https://www.instagram.com/..."></div>
                                 </div>
                                 <div class="form-group">
                                     <label for="social_svg_code">Código SVG do Ícone</label>
                                     <textarea id="social_svg_code" name="social_svg_code" placeholder="Cole o código <svg>...</svg> aqui." required><?php echo htmlspecialchars($edit_social_icon['svg_code'] ?? ''); ?></textarea>
                                     <small style="color: var(--light-text-color); margin-top: 5px; display: block;">Dica: Os presets já usam `fill="currentColor"` para herdar a cor do site.</small>
                                 </div>
                                 <div class="form-grid">
                                     <div class="form-group"><label>Ordem</label><input type="number" name="social_ordem" value="<?php echo $edit_social_icon['ordem'] ?? 0; ?>"></div>
                                     <div class="form-group form-group-check" style="align-self: end;">
                                         <input type="checkbox" id="social_ativo" name="social_ativo" value="1" <?php echo ($edit_social_icon === null || !empty($edit_social_icon['ativo'])) ? 'checked' : ''; ?>>
                                         <label for="social_ativo">Ativo</label>
                                     </div>
                                 </div>
                                 <button type="submit" name="salvar_social_icon"><?php echo $is_editing_social_icon ? 'Salvar Alterações' : 'Adicionar Ícone Social'; ?></button>
                                 <?php if ($is_editing_social_icon): ?><a href="configlayout.php?#tab-footer" class="cancel">Cancelar Edição</a><?php endif; ?>
                             </form>
                         </div>
                         <div class="list-container">
                             <h4>Lista de Ícones Sociais</h4>
                             <table>
                                 <thead><tr><th>Ordem</th><th>Nome</th><th>Preview</th><th>Link</th><th>Status</th><th class="actions">Ações</th></tr></thead>
                                 <tbody>
                                     <?php foreach ($all_social_icons as $social): ?>
                                     <tr>
                                         <td data-label="Ordem"><?php echo $social['ordem']; ?></td>
                                         <td data-label="Nome"><?php echo htmlspecialchars($social['nome']); ?></td>
                                         <td data-label="Preview"><div class="svg-preview"><?php echo $social['svg_code']; ?></div></td>
                                         <td data-label="Link"><?php echo htmlspecialchars($social['link_url']); ?></td>
                                         <td data-label="Status"><span class="<?php echo $social['ativo'] ? 'status-ativo' : 'status-inativo'; ?>"><?php echo $social['ativo'] ? 'Ativo' : 'Inativo'; ?></span></td>
                                         <td class="actions">
                                             <a href="configlayout.php?edit_social_icon=<?php echo $social['id']; ?>#section-social-icons">Editar</a>
                                             <a href="configlayout.php?delete_social_icon=<?php echo $social['id']; ?>" onclick="return confirm('Tem certeza?');" class="delete">Remover</a>
                                         </td>
                                     </tr>
                                     <?php endforeach; ?>
                                     <?php if (empty($all_social_icons)): ?> <tr><td colspan="6" style="text-align: center; color: var(--light-text-color);">Nenhum ícone social cadastrado.</td></tr> <?php endif; ?>
                                 </tbody>
                             </table>
                         </div>
                </div>
            </div>

        </div>
    </main>

    <script>
        // --- JavaScript para Partículas ---
        particlesJS('particles-js', {"particles":{"number":{"value":60,"density":{"enable":true,"value_area":800}},"color":{"value":"#4a69bd"},"shape":{"type":"circle"},"opacity":{"value":0.4,"random":false},"size":{"value":3,"random":true},"line_linked":{"enable":true,"distance":150,"color":"#ffffff","opacity":0.1,"width":1},"move":{"enable":true,"speed":1.5,"direction":"none","random":false,"straight":false,"out_mode":"out","bounce":false}},"interactivity":{"detect_on":"canvas","events":{"onhover":{"enable":true,"mode":"repulse"},"onclick":{"enable":true,"mode":"push"},"resize":true}},"retina_detect":true});

        // --- JS Para Mostrar/Esconder Campos de Link ---
        function showHideLinkFields(prefix, selectedType) {
            const urlWrapper = document.getElementById(prefix + '_link_url_wrapper');
            const produtoWrapper = document.getElementById(prefix + '_produto_id_wrapper');
            const categoriaWrapper = document.getElementById(prefix + '_categoria_id_wrapper');

            if(urlWrapper) urlWrapper.style.display = 'none';
            if(produtoWrapper) produtoWrapper.style.display = 'none';
            if(categoriaWrapper) categoriaWrapper.style.display = 'none';

            if (selectedType === 'url') {
                if(urlWrapper) urlWrapper.style.display = 'block';
            } else if (selectedType === 'produto') {
                if(produtoWrapper) produtoWrapper.style.display = 'block';
            } else if (selectedType === 'categoria') {
                if(categoriaWrapper) categoriaWrapper.style.display = 'block';
            }
        }

        // --- JS para Presets de Ícones Sociais (Bootstrap Icons 16x16) ---
        const socialPresets = {
            'instagram': {
                nome: 'Instagram',
                svg: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-instagram" viewBox="0 0 16 16"><path d="M8 0C5.829 0 5.556.01 4.703.048 3.85.088 3.269.222 2.76.42a3.9 3.9 0 0 0-1.417.923A3.9 3.9 0 0 0 .42 2.76C.222 3.268.087 3.85.048 4.7.01 5.555 0 5.827 0 8.001c0 2.172.01 2.444.048 3.297.04.852.174 1.433.372 1.942.205.526.478.972.923 1.417.444.445.89.719 1.416.923.51.198 1.09.333 1.942.372C5.555 15.99 5.827 16 8 16s2.444-.01 3.298-.048c.851-.04 1.434-.174 1.943-.372a3.9 3.9 0 0 0 1.416-.923c.445-.445.718-.891.923-1.417.197-.509.332-1.09.372-1.942C15.99 10.445 16 10.173 16 8s-.01-2.445-.048-3.299c-.04-.851-.175-1.433-.372-1.941a3.9 3.9 0 0 0-.923-1.417A3.9 3.9 0 0 0 13.24.42c-.51-.198-1.092-.333-1.943-.372C10.443.01 10.172 0 7.998 0zm-.717 1.442h.718c2.136 0 2.389.007 3.232.046.78.035 1.204.166 1.486.275.373.145.64.319.92.599s.453.546.598.92c.11.281.24.705.275 1.485.039.843.047 1.096.047 3.231s-.008 2.389-.047 3.232c-.035.78-.166 1.203-.275 1.485a2.5 2.5 0 0 1-.599.919c-.28.28-.546.453-.92.598-.28.11-.704.24-1.485.276-.843.038-1.096.047-3.232.047s-2.39-.009-3.233-.047c-.78-.036-1.203-.166-1.485-.276a2.5 2.5 0 0 1-.92-.598 2.5 2.5 0 0 1-.6-.92c-.109-.281-.24-.705-.275-1.485-.038-.843-.046-1.096-.046-3.233s.008-2.388.046-3.231c.036-.78.166-1.204.276-1.486.145-.373.319-.64.599-.92s.546-.453.92-.598c.282-.11.705-.24 1.485-.276.738-.034 1.024-.044 2.515-.045zm4.988 1.328a.96.96 0 1 0 0 1.92.96.96 0 0 0 0-1.92m-4.27 1.122a4.109 4.109 0 1 0 0 8.217 4.109 4.109 0 0 0 0-8.217m0 1.441a2.667 2.667 0 1 1 0 5.334 2.667 2.667 0 0 1 0-5.334"/></svg>'
            },
            'facebook': {
                nome: 'Facebook',
                svg: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-facebook" viewBox="0 0 16 16"><path d="M16 8.049c0-4.446-3.582-8.05-8-8.05C3.58 0-.002 3.603-.002 8.05c0 4.017 2.926 7.347 6.75 7.951v-5.625h-2.03V8.05H6.75V6.275c0-2.017 1.195-3.131 3.022-3.131.876 0 1.791.157 1.791.157v1.98h-1.009c-.993 0-1.303.621-1.303 1.258v1.51h2.218l-.354 2.326H9.25V16c3.824-.604 6.75-3.934 6.75-7.951"/></svg>'
            },
            'whatsapp': {
                nome: 'WhatsApp',
                svg: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-whatsapp" viewBox="0 0 16 16"><path d="M13.601 2.326A7.85 7.85 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.9 7.9 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.9 7.9 0 0 0 13.6 2.326zM7.994 14.521a6.6 6.6 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.56 6.56 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592m3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.73.73 0 0 0-.529.247c-.182.198-.691.677-.691 1.654s.71 1.916.81 2.049c.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232"/></svg>'
            },
            'twitter_x': {
                nome: 'X (Twitter)',
                svg: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-twitter-x" viewBox="0 0 16 16"><path d="M12.6.75h2.454l-5.36 6.142L16 15.25h-4.937l-3.867-5.07-4.425 5.07H.316l5.733-6.57L0 .75h5.063l3.495 4.633L12.601.75Zm-.86 13.028h1.36L4.323 2.145H2.865z"/></svg>'
            },
            'tiktok': {
                nome: 'TikTok',
                svg: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-tiktok" viewBox="0 0 16 16"><path d="M9 0h1.98c.144.715.54 1.617 1.235 2.512C12.895 3.389 13.797 4 15 4v2c-1.753 0-3.07-.814-4-1.829V11a5 5 0 1 1-5-5v2a3 3 0 1 0 3 3z"/></svg>'
            },
            'linkedin': {
                nome: 'LinkedIn',
                svg: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-linkedin" viewBox="0 0 16 16"><path d="M0 1.146C0 .513.526 0 1.175 0h13.65C15.474 0 16 .513 16 1.146v13.708c0 .633-.526 1.146-1.175 1.146H1.175C.526 16 0 15.487 0 14.854zm4.943 12.248V6.169H2.542v7.225zm-1.2-8.212c.837 0 1.358-.554 1.358-1.248-.015-.709-.52-1.248-1.342-1.248S2.4 3.226 2.4 3.934c0 .694.521 1.248 1.327 1.248zm4.908 8.212V9.359c0-.216.016-.432.08-.586.173-.431.568-.878 1.232-.878.869 0 1.216.662 1.216 1.634v3.865h2.401V9.25c0-2.22-1.184-3.252-2.764-3.252-1.274 0-1.845.7-2.165 1.193v.025h-.016l.016-.025V6.169h-2.4c.03.678 0 7.225 0 7.225z"/></svg>'
            },
            'youtube': {
                nome: 'YouTube',
                svg: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-youtube" viewBox="0 0 16 16"><path d="M8.051 1.999h.089c.822.003 4.987.033 6.11.335a2.01 2.01 0 0 1 1.415 1.42c.101.38.172.883.22 1.402l.01.104.022.26.008.104c.065.914.073 1.77.074 1.957v.075c-.001.194-.01 1.108-.082 2.06l-.008.105-.009.104c-.05.572-.124 1.14-.235 1.558a2.01 2.01 0 0 1-1.415 1.42c-1.16.312-5.569.334-6.18.335h-.142c-.309 0-1.587-.006-2.927-.052l-.17-.006-.087-.004-.171-.007-.171-.007c-1.11-.049-2.167-.128-2.654-.26a2.01 2.01 0 0 1-1.415-1.419c-.111-.417-.185-.986-.235-1.558L.09 9.82l-.008-.104A31 31 0 0 1 0 7.68v-.123c.002-.215.01-.958.064-1.778l.007-.103.003-.052.008-.104.022-.26.01-.104c.048-.519.119-1.023.22-1.402a2.01 2.01 0 0 1 1.415-1.42c.487-.13 1.544-.21 2.654-.26l.17-.007.172-.006.086-.003.171-.007A100 100 0 0 1 7.858 2zM6.4 5.209v4.818l4.157-2.408z"/></svg>'
            }
        };

        function applySocialPreset(networkName) {
            const preset = socialPresets[networkName];
            const nomeInput = document.getElementById('social_nome');
            const svgTextarea = document.getElementById('social_svg_code');

            if (preset) {
                nomeInput.value = preset.nome;
                svgTextarea.value = preset.svg;
            } else if (networkName === "") {
                // Limpa se o usuário selecionar a opção "-- Selecione"
                // (Mantém os valores de edição se estiver editando)
                <?php if ($is_editing_social_icon): ?>
                nomeInput.value = '<?php echo htmlspecialchars($edit_social_icon['nome']); ?>';
                svgTextarea.value = '<?php echo htmlspecialchars($edit_social_icon['svg_code']); ?>';
                <?php else: ?>
                nomeInput.value = '';
                svgTextarea.value = '';
                <?php endif; ?>
            }
        }
        // ▲▲▲ FIM DA MODIFICAÇÃO (JS) ▲▲▲


        document.addEventListener('DOMContentLoaded', () => {
             // --- Lógica das ABAS (TABS) ---
             const tabButtons = document.querySelectorAll('.tab-navigation .tab-button');
             const tabPanes = document.querySelectorAll('.tab-content-wrapper .tab-pane');

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

                     const newUrl = window.location.pathname + targetPaneId;
                     history.pushState(null, null, newUrl);
                 });
             });

             // --- Lógica para Ancoragem e Abas ---
             const urlHash = window.location.hash;

             function activateTabFromHash(hash) {
                 let targetPane = null;
                 let elementToScroll = null;

                 if (hash && hash !== '#' && !hash.startsWith('#tab-')) {
                     const targetElement = document.querySelector(hash);
                     if (targetElement) {
                         targetPane = targetElement.closest('.tab-pane');
                         elementToScroll = targetElement;
                     }
                 }
                 else if (hash && hash.startsWith('#tab-')) {
                     targetPane = document.querySelector(hash);
                     elementToScroll = targetPane;
                 }

                 if (targetPane) {
                     const targetTabButton = document.querySelector(`.tab-button[data-tab-target="#${targetPane.id}"]`);
                     if (targetTabButton && !targetTabButton.classList.contains('active')) {
                         tabButtons.forEach(btn => btn.classList.remove('active'));
                         tabPanes.forEach(pane => pane.classList.remove('active'));
                         targetTabButton.classList.add('active');
                         targetPane.classList.add('active');
                     }
                 } else {
                     // Ativa a primeira aba se nenhum hash válido for encontrado
                     const firstTabButton = document.querySelector('.tab-navigation .tab-button:first-child');
                     const firstPane = document.querySelector('.tab-content-wrapper .tab-pane:first-child');
                     if (firstTabButton && firstPane && !document.querySelector('.tab-button.active')) {
                         tabButtons.forEach(btn => btn.classList.remove('active'));
                         tabPanes.forEach(pane => pane.classList.remove('active'));
                         firstTabButton.classList.add('active');
                         firstPane.classList.add('active');
                     }
                 }

                 if (elementToScroll) {
                     setTimeout(() => {
                         const headerElement = elementToScroll.querySelector('h3') || elementToScroll.querySelector('h4') || elementToScroll;
                         if(headerElement) {
                            headerElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                         }
                     }, 150);
                 }
             }

             activateTabFromHash(urlHash);

             // --- Inicializa os campos de link dinâmicos ---
             const mainBannerSelect = document.getElementById('main_banner_link_tipo');
             const promoBannerSelect = document.getElementById('promo_banner_link_tipo');

             // Lógica de inicialização para formulários de edição
             <?php if ($is_editing_banner): ?>
                 showHideLinkFields('main_banner', '<?php echo $edit_banner['link_tipo'] ?? 'url'; ?>');
             <?php else: ?>
                 if (mainBannerSelect) showHideLinkFields('main_banner', mainBannerSelect.value);
             <?php endif; ?>

             <?php if ($is_editing_promo_banner): ?>
                 showHideLinkFields('promo_banner', '<?php echo $edit_promo_banner['link_tipo'] ?? 'url'; ?>');
             <?php else: ?>
                 if (promoBannerSelect) showHideLinkFields('promo_banner', promoBannerSelect.value);
             <?php endif; ?>
        });
    </script>
</body>
</html>