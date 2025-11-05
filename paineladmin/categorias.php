<?php
// categorias.php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

require_once '../config/db.php'; // Conexão com o DB

$message = '';
$message_type = '';

// --- INICIALIZAÇÃO DE VARIÁVEIS ---
$edit_categoria = null;
$is_editing = false;
$edit_categoria_produtos_ids = [];

// ==========================================================
// LÓGICA CRUD CATEGORIAS
// ==========================================================

// 1. ADICIONAR ou ATUALIZAR CATEGORIA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_categoria'])) {
    $nome = trim($_POST['nome']);
    $url = null; // Definido como null
    $ordem = (int)$_POST['ordem'];
    $parent_id = (!empty($_POST['parent_id']) && $_POST['parent_id'] !== '0') ? (int)$_POST['parent_id'] : null;
    $id = isset($_POST['id']) ? (int)$_POST['id'] : null;

    if (!empty($nome)) {
        try {
            if ($id) {
                // ATUALIZAR (UPDATE) CATEGORIA
                $sql = "UPDATE categorias SET nome = :nome, url = :url, ordem = :ordem, parent_id = :parent_id WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['nome' => $nome, 'url' => $url, 'ordem' => $ordem, 'parent_id' => $parent_id, 'id' => $id]);
                $_SESSION['flash_message'] = "Categoria atualizada com sucesso!";
                $_SESSION['flash_type'] = "success";
                header("Location: categorias.php?edit=" . $id . "#tab-categorias"); // Volta para aba de edição
                exit;
            } else {
                // ADICIONAR (INSERT) CATEGORIA
                $sql = "INSERT INTO categorias (nome, url, ordem, parent_id) VALUES (:nome, :url, :ordem, :parent_id)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['nome' => $nome, 'url' => $url, 'ordem' => $ordem, 'parent_id' => $parent_id]);
                $newId = $pdo->lastInsertId();
                $_SESSION['flash_message'] = "Categoria adicionada! Agora associe os produtos.";
                $_SESSION['flash_type'] = "info";
                header("Location: categorias.php?edit=" . $newId . "#section-produtos"); // Pula direto para produtos
                exit;
            }
        } catch (PDOException $e) {
            $message = "Erro ao salvar categoria: " . $e->getMessage();
            $message_type = "error";
        }
    } else {
        $message = "O campo 'Nome' é obrigatório.";
        $message_type = "error";
    }
}

// 2. DELETAR CATEGORIA (LÓGICA ADICIONADA)
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        // Tenta deletar
        $sql = "DELETE FROM categorias WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $_SESSION['flash_message'] = "Categoria removida com sucesso!";
        $_SESSION['flash_type'] = "success";

    } catch (PDOException $e) {
        if ($e->getCode() == '23503') { // Erro de chave estrangeira
             $_SESSION['flash_message'] = "Erro: Não é possível remover esta categoria pois ela contém sub-categorias ou produtos associados.";
        } else {
            $_SESSION['flash_message'] = "Erro ao remover categoria: " . $e->getMessage();
        }
         $_SESSION['flash_type'] = "error";
    }
    header("Location: categorias.php#tab-lista"); // Volta para a lista
    exit;
}

// 3. SALVAR ASSOCIAÇÕES DE PRODUTOS
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_associacoes'])) {
    $categoria_id = isset($_POST['categoria_id_assoc']) ? (int)$_POST['categoria_id_assoc'] : null;
    $produtos_selecionados_ids = $_POST['produtos_associados'] ?? [];

    if ($categoria_id) {
        $pdo->beginTransaction();
        try {
            // 1. Remove associações antigas
            $sql_delete_prods = "DELETE FROM produto_categorias WHERE categoria_id = :categoria_id";
            $stmt_delete_prods = $pdo->prepare($sql_delete_prods);
            $stmt_delete_prods->execute(['categoria_id' => $categoria_id]);

            // 2. Insere as novas
            if (!empty($produtos_selecionados_ids)) {
                $sql_insert_prod = "INSERT INTO produto_categorias (produto_id, categoria_id) VALUES (:produto_id, :categoria_id)";
                $stmt_insert_prod = $pdo->prepare($sql_insert_prod);
                foreach ($produtos_selecionados_ids as $prod_id) {
                    if (filter_var($prod_id, FILTER_VALIDATE_INT)) {
                        $stmt_insert_prod->execute(['produto_id' => (int)$prod_id, 'categoria_id' => $categoria_id]);
                    }
                }
            }
            $pdo->commit();
            $_SESSION['flash_message'] = "Associações de produtos salvas com sucesso!";
            $_SESSION['flash_type'] = "success";
            header("Location: categorias.php?edit=" . $categoria_id . "#section-produtos");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "Erro ao salvar associações: " . $e->getMessage();
            $message_type = "error";
        }
    } else {
        $message = "ID da categoria inválido para salvar associações.";
        $message_type = "error";
    }
}


// --- LÓGICA DE LEITURA ---

// Pega mensagens flash da sessão
if (isset($_SESSION['flash_message']) && empty($message)) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// MODO DE EDIÇÃO
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $is_editing = true;

    // Só busca do DB se não houver erro de POST (que recarrega $edit_categoria)
    if (empty($message) || $message_type !== 'error') {
        $stmt = $pdo->prepare("SELECT * FROM categorias WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $edit_categoria = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($edit_categoria) {
        $stmt_prods = $pdo->prepare("SELECT produto_id FROM produto_categorias WHERE categoria_id = :id");
        $stmt_prods->execute(['id' => $id]);
        $edit_categoria_produtos_ids = $stmt_prods->fetchAll(PDO::FETCH_COLUMN);

        if (isset($_GET['saved']) && empty($message)) { $message = "Categoria salva com sucesso!"; $message_type = "success"; }
        if (isset($_GET['assoc_saved']) && empty($message)) { $message = "Associações de produtos salvas com sucesso!"; $message_type = "success"; }
        if (isset($_GET['new']) && empty($message)) { $message = "Categoria criada! Agora associe os produtos."; $message_type = "info"; }
    } else {
        $is_editing = false;
        if (empty($message)) { // Só mostra warning se não houver outra msg
            $message = "Categoria não encontrada para edição.";
            $message_type = "warning";
        }
    }
}


// LER TODAS AS CATEGORIAS
try {
    $stmt_all_cats = $pdo->query("SELECT * FROM categorias ORDER BY parent_id ASC, ordem ASC");
    $all_categorias = $stmt_all_cats->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
     $message .= " Erro ao buscar categorias: " . $e->getMessage(); $message_type = "error"; $all_categorias = [];
}

// LER TODOS OS PRODUTOS E MARCAS (se estiver editando)
$produtos_agrupados = [];
if ($is_editing) {
    try {
        $stmt_all_prods = $pdo->query("
            SELECT p.id, p.nome, m.nome AS marca_nome
            FROM produtos p
            LEFT JOIN marcas m ON p.marca_id = m.id
            ORDER BY m.nome ASC, p.nome ASC
        ");
        while ($produto = $stmt_all_prods->fetch(PDO::FETCH_ASSOC)) {
            $marca_nome = $produto['marca_nome'] ?? 'Sem Marca';
            $produtos_agrupados[$marca_nome][] = $produto;
        }
    } catch (PDOException $e) {
        $message .= " Erro ao buscar lista de produtos: " . $e->getMessage(); $message_type = "error";
    }
}


// Organiza categorias para exibir em lista hierárquica
$categorias_list = [];
foreach($all_categorias as $cat) {
    if($cat['parent_id'] == null) {
        $categorias_list[$cat['id']] = $cat;
        $categorias_list[$cat['id']]['children'] = [];
    }
}
foreach($all_categorias as $cat) {
    if($cat['parent_id'] != null && isset($categorias_list[$cat['parent_id']])) {
        $categorias_list[$cat['parent_id']]['children'][] = $cat;
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Categorias - Admin</title>

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
            --success-bg: rgba(40, 167, 69, 0.3); --success-text: #c3e6cb;
            --error-bg: rgba(220, 53, 69, 0.3); --error-text: #f5c6cb;
            --info-bg: rgba(0, 123, 255, 0.2); --info-text: #bee5eb;
            --danger-color: #e74c3c; --danger-color-hover: #c0392b;
            --sidebar-width: 240px; --border-radius: 8px;
            --box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--background-color); color: var(--text-color); display: flex; min-height: 100vh; overflow-x: hidden; }
        #particles-js { position: fixed; width: 100%; height: 100%; top: 0; left: 0; z-index: -1; opacity: 0.6; }

        /* --- Sidebar --- */
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
        .form-container, .list-container, .association-container { background: var(--glass-background); padding: 1.5rem 2rem; border-radius: var(--border-radius); border: 1px solid var(--border-color); box-shadow: var(--box-shadow); margin-bottom: 2rem; backdrop-filter: blur(5px); }
        .tab-pane .crud-section:first-child .form-container,
        .tab-pane .crud-section:first-child .list-container,
        .tab-pane .crud-section:first-child .association-container {
             border-top-left-radius: 0;
             border-top-right-radius: 0;
        }
        .form-container h4, .association-container h4 { font-size: 1.1rem; color: var(--primary-color); margin-bottom: 1.25rem; font-weight: 600; }

        /* --- Formulários --- */
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--light-text-color); font-size: 0.8rem; text-transform: uppercase; }
        .form-group input[type="text"], .form-group input[type="email"], .form-group input[type="number"], .form-group textarea, .form-group select { width: 100%; padding: 0.65rem 0.8rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); background-color: rgba(0, 0, 0, 0.3); color: var(--text-color); box-sizing: border-box; transition: border-color 0.3s ease, box-shadow 0.3s ease; font-size: 0.9em; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color: var(--primary-color); box-shadow: 0 0 8px rgba(74, 105, 189, 0.5); outline: none; }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .form-group-check { display: flex; align-items: center; padding-top: 1rem; }
        .form-group-check label { font-weight: 400; color: var(--text-color); display: inline; margin-left: 0.5rem; text-transform: none; }
        .form-group-check input { width: auto; vertical-align: middle; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem 1.5rem; }
        button[type="submit"] { padding: 0.65rem 1.2rem; background-color: var(--primary-color); color: #fff; border: none; border-radius: var(--border-radius); cursor: pointer; font-weight: 600; transition: background-color 0.3s ease, transform 0.1s ease; font-size: 0.9em; }
        button[type="submit"]:hover { background-color: var(--secondary-color); transform: translateY(-1px); }
        button[type="submit"].update { background-color: #28a745; }
        button[type="submit"].update:hover { background-color: #218838; }
        .form-container a.cancel, .association-container a.cancel { color: var(--light-text-color); margin-left: 1rem; font-size: 0.9em; }
        .form-container a.cancel:hover, .association-container a.cancel:hover { text-decoration: underline; }
        .form-group select[multiple] { min-height: 150px; }

        /* --- Tabelas --- */
        .list-container { overflow-x: auto; }
        .list-container table { width: 100%; border-collapse: collapse; background-color: transparent; border-radius: 0; overflow: hidden; font-size: 0.85em; border: none; min-width: 600px; }
        .list-container th, .list-container td { border-bottom: 1px solid var(--border-color); padding: 10px 12px; text-align: left; vertical-align: middle; }
        .list-container th { background-color: rgba(0, 0, 0, 0.4); color: var(--text-color); font-weight: 600; text-transform: uppercase; font-size: 0.75em; }
        .list-container tbody tr:last-child td { border-bottom: none; }
        .list-container tbody tr:hover { background-color: rgba(255, 255, 255, 0.05); }
        .list-container .actions { white-space: nowrap; text-align: right; }
        .list-container .actions a { color: var(--primary-color); margin-left: 0.8rem; font-size: 0.85em; }
        .list-container .actions a:last-child { margin-right: 0; }
        .list-container .actions a.delete { color: var(--danger-color); }
        .list-container .actions a.delete:hover { color: #c0392b; }
        .sub-category { padding-left: 25px; color: var(--light-text-color); }
        .sub-category strong { font-weight: 600; color: var(--text-color); }
        .sub-category a { margin-left: 0; }

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
            .product-cards-grid { grid-template-columns: 1fr; }
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
            .form-container, .list-container, .association-container { padding: 1rem 1.5rem;}
            .crud-section h3 { font-size: 1.1rem;}
            .form-container h4, .association-container h4 { font-size: 1rem;}
            .tab-navigation { flex-wrap: wrap; }
            .tab-button { width: 100%; border-radius: var(--border-radius); margin-bottom: 0.5rem; }
            .tab-button.active { border-radius: var(--border-radius); }
            .tab-pane .crud-section:first-child .form-container,
            .tab-pane .crud-section:first-child .list-container,
            .tab-pane .crud-section:first-child .association-container {
                 border-top-left-radius: var(--border-radius);
                 border-top-right-radius: var(--border-radius);
            }
            .list-container td, .list-container td::before { font-size: 0.8em; }
        }

        /* --- Estilos para Associação de Produtos (AGRUPADO POR MARCA) --- */
        .product-association-wrapper { margin-top: 1.5rem; }
        .product-search-bar { position: relative; margin-bottom: 1.5rem; }
        .product-search-bar input { width: 100%; padding-left: 2.5rem; }
        .product-search-bar svg { position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; color: var(--light-text-color); }
        .product-cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .product-group-card { background-color: rgba(0,0,0,0.2); border-radius: var(--border-radius); padding: 1rem 1.5rem; border: 1px solid var(--border-color); }
        .product-group-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color); }
        .product-group-header h5 { font-size: 1.1rem; color: var(--primary-color); margin: 0; }
        .product-group-header .select-all-btn { font-size: 0.8em; color: var(--light-text-color); cursor: pointer; text-decoration: underline; }
        .product-group-card ul { list-style: none; padding: 0; margin: 0; max-height: 250px; overflow-y: auto; }
        .product-group-card ul::-webkit-scrollbar { width: 6px; }
        .product-group-card ul::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); border-radius: 3px; }
        .product-group-card ul::-webkit-scrollbar-thumb { background: var(--light-text-color); border-radius: 3px; }
        .product-group-card ul::-webkit-scrollbar-thumb:hover { background: #bbb; }
        .product-group-card li { padding: 0.4rem 0; display: flex; align-items: center; }
        .product-group-card li:not(:last-child) { border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        .product-group-card input[type="checkbox"] { margin-right: 0.75rem; width: 16px; height: 16px; accent-color: var(--primary-color); cursor: pointer; }
        .product-group-card label { font-size: 0.9em; color: var(--text-color); font-weight: 400; cursor: pointer; flex-grow: 1; }
        .association-container .button-wrapper { text-align: right; margin-top: 1.5rem; }

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
            <h1>Gerenciar Categorias do Menu</h1>
            <p>Adicione, edite ou remova as categorias e associe produtos a elas.</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo nl2br(htmlspecialchars($message)); ?>
            </div>
        <?php endif; ?>

        <div class="tab-navigation">
            <button class="tab-button <?php echo !$is_editing ? 'active' : ''; ?>" data-tab-target="#tab-categorias">
                <?php echo $is_editing ? 'Editar Categoria' : 'Adicionar Categoria'; ?>
            </button>
            <button class="tab-button <?php echo $is_editing ? '' : 'active'; ?>" data-tab-target="#tab-lista">Lista de Categorias</button>
        </div>

        <div class="tab-content-wrapper">

            <div class="tab-pane <?php echo $is_editing ? 'active' : ''; ?>" id="tab-categorias">
                <div class="crud-section" id="section-form-categoria">
                    <h3><?php echo $is_editing ? 'Editar Categoria' : 'Adicionar Nova Categoria'; ?></h3>
                    <div class="form-container">
                        <form action="categorias.php#tab-categorias" method="POST">
                            <?php if ($is_editing): ?>
                                <input type="hidden" name="id" value="<?php echo $edit_categoria['id']; ?>">
                            <?php endif; ?>

                            <div class="form-group">
                                <label for="nome">Nome:</label>
                                <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($edit_categoria['nome'] ?? ''); ?>" required>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="parent_id">Categoria Mãe:</label>
                                    <select id="parent_id" name="parent_id">
                                        <option value="0">-- Nenhuma (Categoria Principal) --</option>
                                        <?php foreach($all_categorias as $cat): ?>
                                            <?php if($cat['parent_id'] == null && (!$is_editing || $cat['id'] !== $edit_categoria['id'])): ?>
                                                <option value="<?php echo $cat['id']; ?>" <?php echo ($is_editing && $edit_categoria['parent_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cat['nome']); ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="ordem">Ordem (ex: 0, 1, 2...):</label>
                                    <input type="number" id="ordem" name="ordem" value="<?php echo $edit_categoria['ordem'] ?? '0'; ?>" required>
                                </div>
                            </div>

                            <button type="submit" name="salvar_categoria" class="<?php echo $is_editing ? 'update' : ''; ?>">
                                <?php echo $is_editing ? 'Salvar Alterações' : 'Adicionar e Associar Produtos'; ?>
                            </button>
                            <?php if ($is_editing): ?>
                                <a href="categorias.php#tab-lista" class="cancel">Cancelar Edição</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div> <?php if ($is_editing && $edit_categoria): ?>
                <div class="crud-section" id="section-produtos"> <div class="association-container">
                        <h4>Associar Produtos a "<?php echo htmlspecialchars($edit_categoria['nome']); ?>"</h4>
                        <form action="categorias.php?edit=<?php echo $edit_categoria['id']; ?>#section-produtos" method="POST">
                            <input type="hidden" name="categoria_id_assoc" value="<?php echo $edit_categoria['id']; ?>">

                            <div class="product-search-bar form-group">
                                 <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                     <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                 </svg>
                                <input type="text" id="product-search" onkeyup="filterProducts()" placeholder="Buscar produto...">
                            </div>

                            <div class="product-cards-grid">
                                <?php if (empty($produtos_agrupados)): ?>
                                    <p style="color: var(--light-text-color);">Nenhum produto cadastrado para associar.</p>
                                <?php endif; ?>

                                <?php foreach ($produtos_agrupados as $marca_nome => $produtos): $marca_slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($marca_nome)); ?>
                                    <div class="product-group-card" data-card-marca="<?php echo htmlspecialchars($marca_nome); ?>">
                                        <div class="product-group-header">
                                            <h5><?php echo htmlspecialchars($marca_nome); ?></h5>
                                            <span class="select-all-btn" data-marca-slug="<?php echo $marca_slug; ?>">Selecionar Todos</span>
                                        </div>
                                        <ul id="list-<?php echo $marca_slug; ?>">
                                            <?php foreach ($produtos as $produto): ?>
                                            <li data-produto-nome="<?php echo strtolower(htmlspecialchars($produto['nome'])); ?>">
                                                <input type="checkbox"
                                                       id="prod_<?php echo $produto['id']; ?>"
                                                       name="produtos_associados[]"
                                                       value="<?php echo $produto['id']; ?>"
                                                       class="check-marca-<?php echo $marca_slug; ?>"
                                                       <?php echo in_array($produto['id'], $edit_categoria_produtos_ids) ? 'checked' : ''; ?>
                                                >
                                                <label for="prod_<?php echo $produto['id']; ?>">
                                                    <?php echo htmlspecialchars($produto['nome']); ?>
                                                </label>
                                            </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="button-wrapper">
                                 <button type="submit" name="salvar_associacoes" class="update">Salvar Associações de Produtos</button>
                            </div>
                        </form>
                    </div>
                </div> <?php endif; ?>
            </div> <div class="tab-pane <?php echo !$is_editing ? 'active' : ''; ?>" id="tab-lista">
                 <div class="crud-section" id="section-lista-categorias">
                    <h3>Categorias Atuais</h3>
                    <div class="list-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Ordem</th>
                                    <th>Nome</th>
                                    <th class="actions">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categorias_list as $categoria): ?>
                                    <tr>
                                        <td data-label="Ordem"><?php echo $categoria['ordem']; ?></td>
                                        <td data-label="Nome"><strong><?php echo htmlspecialchars($categoria['nome']); ?></strong></td>
                                        <td class="actions">
                                            <a href="categorias.php?edit=<?php echo $categoria['id']; ?>#tab-categorias">Editar / Associar Produtos</a>
                                            <a href="categorias.php?delete=<?php echo $categoria['id']; ?>" onclick="return confirm('Tem certeza? Isso pode deixar sub-categorias órfãs e removerá associações de produtos.');" class="delete">Remover</a>
                                        </td>
                                    </tr>
                                    <?php foreach($categoria['children'] as $sub_cat): ?>
                                    <tr>
                                        <td data-label="Ordem" class="sub-category"><?php echo $sub_cat['ordem']; ?></td>
                                        <td data-label="Nome" class="sub-category">∟ <strong><?php echo htmlspecialchars($sub_cat['nome']); ?></strong></td>
                                        <td class="actions">
                                            <a href="categorias.php?edit=<?php echo $sub_cat['id']; ?>#tab-categorias">Editar / Associar Produtos</a>
                                            <a href="categorias.php?delete=<?php echo $sub_cat['id']; ?>" onclick="return confirm('Tem certeza? Removerá associações de produtos.');" class="delete">Remover</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                                <?php if (empty($all_categorias)): ?> <tr><td colspan="3" style="text-align: center; color: var(--light-text-color);">Nenhuma categoria cadastrada.</td></tr> <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                 </div> </div> </div> </main>

    <script>
        // --- JavaScript para Partículas ---
        particlesJS('particles-js', {"particles":{"number":{"value":60,"density":{"enable":true,"value_area":800}},"color":{"value":"#4a69bd"},"shape":{"type":"circle"},"opacity":{"value":0.4,"random":false},"size":{"value":3,"random":true},"line_linked":{"enable":true,"distance":150,"color":"#ffffff","opacity":0.1,"width":1},"move":{"enable":true,"speed":1.5,"direction":"none","random":false,"straight":false,"out_mode":"out","bounce":false}},"interactivity":{"detect_on":"canvas","events":{"onhover":{"enable":true,"mode":"repulse"},"onclick":{"enable":true,"mode":"push"},"resize":true}},"retina_detect":true});

        // --- JavaScript Específico da Página de Categorias ---
        document.addEventListener('DOMContentLoaded', () => {

             // --- Lógica de Menu e Perfil REMOVIDA (Assumindo estar em admin_sidebar.php) ---

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

                    const newUrl = window.location.pathname + targetPaneId; // Ex: categorias.php#tab-lista
                    history.pushState(null, null, newUrl);
                });
             });

             // --- Lógica para Ancoragem e Abas ---
             const urlParams = new URLSearchParams(window.location.search);
             const editId = urlParams.get('edit');
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
                     const targetTabButton = document.querySelector(`.tab-button[data-tab-target="#${targetPane.id}"]`);
                     if (targetTabButton && !targetTabButton.classList.contains('active')) {
                         tabButtons.forEach(btn => btn.classList.remove('active'));
                         tabPanes.forEach(pane => pane.classList.remove('active'));
                         targetTabButton.classList.add('active');
                         targetPane.classList.add('active');
                     }
                 } else {
                      // Padrão: Se ?edit=... está na URL, abre a aba de Categorias.
                      if (editId) {
                           document.querySelector('.tab-button[data-tab-target="#tab-categorias"]').click();
                           // Se o hash específico #section-produtos não foi passado, define-o para rolar
                           elementToScroll = document.querySelector(urlHash || '#section-produtos');
                      }
                      // Se não, ativa a aba Lista por padrão
                      else {
                           const listTabButton = document.querySelector('.tab-button[data-tab-target="#tab-lista"]');
                           const listPane = document.querySelector('#tab-lista');
                           if(listTabButton && listPane && !listTabButton.classList.contains('active')) {
                                tabButtons.forEach(btn => btn.classList.remove('active'));
                                tabPanes.forEach(pane => pane.classList.remove('active'));
                                listTabButton.classList.add('active');
                                listPane.classList.add('active');
                           }
                      }
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

            // ==========================================================
            // JS PARA FILTRO E SELEÇÃO DE PRODUTOS
            // ==========================================================

            // --- Botão "Selecionar Todos" ---
            const selectAllButtons = document.querySelectorAll('.select-all-btn');
            selectAllButtons.forEach(button => {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    const slug = button.getAttribute('data-marca-slug');
                    const checkboxes = document.querySelectorAll('.check-marca-' + slug);
                    const isAnyChecked = Array.from(checkboxes).some(cb => cb.checked && cb.parentElement.style.display !== 'none');

                    checkboxes.forEach(cb => {
                        if (cb.parentElement.style.display !== 'none') {
                            cb.checked = !isAnyChecked;
                        }
                    });
                    button.textContent = isAnyChecked ? 'Selecionar Todos' : 'Desselecionar Todos';
                });
            });

            // --- Lógica de Filtro/Busca (exposta globalmente) ---
            window.filterProducts = function() {
                const filter = document.getElementById('product-search').value.toLowerCase();
                const productCards = document.querySelectorAll('.product-group-card');
                let totalVisibleCards = 0;

                productCards.forEach(card => {
                    const productsList = card.querySelectorAll('ul li');
                    let visibleProductCount = 0;

                    productsList.forEach(li => {
                        const productName = li.getAttribute('data-produto-nome');
                        if (productName.includes(filter)) {
                            li.style.display = 'flex';
                            visibleProductCount++;
                        } else {
                            li.style.display = 'none';
                        }
                    });

                    if (visibleProductCount > 0) {
                        card.style.display = 'block';
                        totalVisibleCards++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                // Opcional: Mostrar msg se nenhum produto/marca for encontrado
                const noProductsMsg = document.querySelector('.association-container p');
                if (noProductsMsg) {
                    noProductsMsg.style.display = (totalVisibleCards === 0) ? 'block' : 'none';
                    if (totalVisibleCards === 0) noProductsMsg.textContent = 'Nenhum produto encontrado para o filtro.';
                }
            }

        });
    </script>
</body>
</html>