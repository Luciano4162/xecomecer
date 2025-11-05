<?php
// produtos.php (admin_panel)
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

require_once '../config/db.php'; // Conexão com o DB

$message = '';
$message_type = '';

// --- INICIALIZAÇÃO DE VARIÁVEIS ---
$edit_produto = null;
$is_editing_produto = false;
$edit_marca = null;
$is_editing_marca = false;
$produto_midias = [];

// ==========================================================
// LÓGICA CRUD PARA MARCAS
// ==========================================================
// 1M. ADICIONAR ou ATUALIZAR MARCA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_marca'])) {
    $marca_nome = trim($_POST['marca_nome']);
    $marca_id = isset($_POST['marca_id']) ? (int)$_POST['marca_id'] : null;
    if (!empty($marca_nome)) {
        try {
            if ($marca_id) { /* UPDATE */ $sql = "UPDATE marcas SET nome = :nome WHERE id = :id"; $stmt = $pdo->prepare($sql); $stmt->execute(['nome' => $marca_nome, 'id' => $marca_id]); $message = "Marca atualizada!"; } else { /* INSERT */ $sql = "INSERT INTO marcas (nome) VALUES (:nome)"; $stmt = $pdo->prepare($sql); $stmt->execute(['nome' => $marca_nome]); $message = "Marca adicionada!"; }
            $message_type = "success";
        } catch (PDOException $e) {
            if ($e->getCode() == '23505') { $message = "Erro: Já existe uma marca com este nome."; } else { $message = "Erro ao salvar marca: " . $e->getMessage(); } $message_type = "error";
        }
    } else { $message = "O nome da marca é obrigatório."; $message_type = "error"; }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $message_type;
    header("Location: produtos.php#tab-marcas"); exit;
}
// 2M. DELETAR MARCA
if (isset($_GET['delete_marca'])) {
    $marca_id = (int)$_GET['delete_marca'];
    try { $sql = "DELETE FROM marcas WHERE id = :id"; $stmt = $pdo->prepare($sql); $stmt->execute(['id' => $marca_id]); $message = "Marca removida!"; $message_type = "success";
    } catch (PDOException $e) { $message = "Erro ao remover marca: " . $e->getMessage(); $message_type = "error"; }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $message_type;
    header("Location: produtos.php#tab-marcas"); exit;
}
// 3M. MODO DE EDIÇÃO MARCA
if (isset($_GET['edit_marca'])) {
    $marca_id = (int)$_GET['edit_marca']; $is_editing_marca = true; $stmt_edit_marca = $pdo->prepare("SELECT * FROM marcas WHERE id = :id"); $stmt_edit_marca->execute(['id' => $marca_id]); $edit_marca = $stmt_edit_marca->fetch(PDO::FETCH_ASSOC);
    if (!$edit_marca) $is_editing_marca = false;
}


// ==========================================================
// LÓGICA MÍDIA DA GALERIA
// ==========================================================
// 4M. ADICIONAR MÍDIA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_midia'])) {
    $produto_id_midia = (int)$_POST['produto_id_midia'];
    $tipo = trim($_POST['tipo']);
    $url = trim($_POST['url']);
    $ordem = (int)$_POST['ordem'];
    if (!empty($produto_id_midia) && !empty($tipo) && !empty($url)) {
        try {
            $sql = "INSERT INTO produto_midia (produto_id, tipo, url, ordem) VALUES (:pid, :tipo, :url, :ordem)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['pid' => $produto_id_midia, 'tipo' => $tipo, 'url' => $url, 'ordem' => $ordem]);
            $message = "Mídia adicionada com sucesso!"; $message_type = "success";
        } catch (PDOException $e) { $message = "Erro ao salvar mídia: " . $e->getMessage(); $message_type = "error"; }
    } else { $message = "Tipo e URL são obrigatórios para adicionar mídia."; $message_type = "error"; }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $message_type;
    header("Location: produtos.php?edit_produto=" . $produto_id_midia . "#section-midia"); exit;
}
// 5M. DELETAR MÍDIA
if (isset($_GET['delete_midia'])) {
    $midia_id = (int)$_GET['delete_midia'];
    $produto_id_redirect = (int)$_GET['prod_id'];
    try {
        $sql = "DELETE FROM produto_midia WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $midia_id]);
        $message = "Mídia removida com sucesso!"; $message_type = "success";
    } catch (PDOException $e) { $message = "Erro ao remover mídia: " . $e->getMessage(); $message_type = "error"; }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $message_type;
    header("Location: produtos.php?edit_produto=" . $produto_id_redirect . "#section-midia"); exit;
}

// ==========================================================
// LÓGICA CRUD PARA PRODUTOS
// ==========================================================

// 1P. ADICIONAR ou ATUALIZAR PRODUTO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_produto'])) {
    $nome = trim($_POST['nome']);
    $descricao = trim($_POST['descricao']);
    $preco = filter_var($_POST['preco'], FILTER_VALIDATE_FLOAT);
    $estoque = filter_var($_POST['estoque'], FILTER_VALIDATE_INT);
    $imagem_url = trim($_POST['imagem_url']);
    $marca_id = (!empty($_POST['marca_id']) && $_POST['marca_id'] !== '0') ? (int)$_POST['marca_id'] : null;
    $id = isset($_POST['id']) ? (int)$_POST['id'] : null;

    // Checkboxes (Flags)
    $ativo = isset($_POST['ativo']);
    $destaque = isset($_POST['destaque']);
    $mais_vendido = isset($_POST['mais_vendido']);
    $is_lancamento = isset($_POST['is_lancamento']); // <-- NOVO

    // Correção: Se for NOVO produto (sem ID) e ATIVO não for marcado, ele deve ser inativo (false)
    if (!$id && !isset($_POST['ativo'])) { $ativo = false; }
    // Correção: Se for UPDATE e ATIVO não for marcado
    if ($id && !isset($_POST['ativo'])) { $ativo = false; }


    if (empty($nome)) { $message = "O campo 'Nome' é obrigatório."; $message_type = "error";
    } elseif ($preco === false || $preco < 0) { $message = "O campo 'Preço' é obrigatório e deve ser um número válido (ex: 19.99)."; $message_type = "error";
    } elseif ($estoque === false || $estoque < 0) { $message = "O campo 'Estoque' é obrigatório e deve ser um número inteiro igual ou maior que zero."; $message_type = "error";
    } else {
        try {
            if ($id) { // UPDATE
                $sql = "UPDATE produtos SET
                            nome = :nome,
                            descricao = :descricao,
                            preco = :preco,
                            estoque = :estoque,
                            imagem_url = :imagem_url,
                            destaque = :destaque,
                            marca_id = :marca_id,
                            ativo = :ativo,
                            mais_vendido = :mais_vendido,
                            is_lancamento = :is_lancamento -- <-- NOVO
                        WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            } else { // INSERT
                $sql = "INSERT INTO produtos (
                            nome, descricao, preco, estoque, imagem_url,
                            destaque, marca_id, ativo, mais_vendido,
                            is_lancamento, -- <-- NOVO
                            criado_em
                        )
                        VALUES (
                            :nome, :descricao, :preco, :estoque, :imagem_url,
                            :destaque, :marca_id, :ativo, :mais_vendido,
                            :is_lancamento, -- <-- NOVO
                            NOW()
                        )";
                $stmt = $pdo->prepare($sql);
            }
            $stmt->bindParam(':nome', $nome, PDO::PARAM_STR);
            $stmt->bindParam(':descricao', $descricao, PDO::PARAM_STR);
            $stmt->bindParam(':preco', $preco);
            $stmt->bindParam(':estoque', $estoque, PDO::PARAM_INT);
            $stmt->bindParam(':imagem_url', $imagem_url, PDO::PARAM_STR);
            $stmt->bindParam(':marca_id', $marca_id, $marca_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindParam(':ativo', $ativo, PDO::PARAM_BOOL);
            $stmt->bindParam(':destaque', $destaque, PDO::PARAM_BOOL);
            $stmt->bindParam(':mais_vendido', $mais_vendido, PDO::PARAM_BOOL);
            $stmt->bindParam(':is_lancamento', $is_lancamento, PDO::PARAM_BOOL); // <-- NOVO
            $stmt->execute();

            if ($id) {
                $message = "Produto atualizado com sucesso!";
                $produto_id_redirect = $id;
            } else {
                $produto_id_redirect = $pdo->lastInsertId();
                $message = "Produto adicionado com sucesso! Adicione mídias e associe categorias.";
            }
            $message_type = "success";
            $_SESSION['flash_message'] = $message;
            $_SESSION['flash_type'] = $message_type;
            header("Location: produtos.php?edit_produto=" . $produto_id_redirect . "#tab-produtos"); // Redireciona para a aba de produtos (edição)
            exit;

        } catch (PDOException $e) {
            $message = "Erro ao salvar produto: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// 2P. DELETAR PRODUTO
if (isset($_GET['delete_produto'])) {
    $id = (int)$_GET['delete_produto'];
    try {
        $sql = "DELETE FROM produtos WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $message = "Produto removido com sucesso!";
        $message_type = "success";

    } catch (PDOException $e) {
        // Verificamos se o código do erro é '23503' (Foreign Key Violation)
        if ($e->getCode() === '23503') {
            // É uma violação de chave estrangeira. Exibimos a mensagem amigável.
            // O erro DETAIL "fk_pedidos_itens_produto" confirma isso.
            $message = "Não é possível excluir um produto que está associado a um ou mais pedidos. Considere desativa-lo por completo.";
            $message_type = "error";
        } else {
            // Se for qualquer outro erro de banco de dados, exibe a mensagem técnica.
            $message = "Erro ao remover produto: " . $e->getMessage();
            $message_type = "error";
        }
    }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $message_type;
    header("Location: produtos.php#tab-lista"); exit;
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

// 3P. MODO DE EDIÇÃO PRODUTO
if (isset($_GET['edit_produto'])) {
    $id = (int)$_GET['edit_produto'];
    $is_editing_produto = true;
    $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $edit_produto = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($edit_produto) {
        $stmt_midia = $pdo->prepare("SELECT * FROM produto_midia WHERE produto_id = :id ORDER BY ordem ASC");
        $stmt_midia->execute(['id' => $id]);
        $produto_midias = $stmt_midia->fetchAll(PDO::FETCH_ASSOC);
    } else {
         $is_editing_produto = false;
         if (empty($message)) { // Só mostra warning se não houver outra msg
            $message = "Produto não encontrado."; $message_type = "warning";
         }
    }
}


// --- LEITURA DE DADOS PARA EXIBIÇÃO ---
try {
    // AJUSTE: Buscar a coluna 'is_lancamento'
    $stmt_produtos = $pdo->query("
        SELECT p.id, p.nome, p.preco, p.estoque, p.imagem_url, p.ativo,
               p.destaque, p.mais_vendido, p.is_lancamento, m.nome AS marca_nome
        FROM produtos p
        LEFT JOIN marcas m ON p.marca_id = m.id
        ORDER BY m.nome ASC, p.nome ASC
    ");
    $all_produtos = $stmt_produtos->fetchAll(PDO::FETCH_ASSOC);

    $stmt_marcas = $pdo->query("SELECT id, nome FROM marcas ORDER BY nome ASC");
    $all_marcas = $stmt_marcas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    if (empty($message)) {
        $message .= " Erro ao carregar dados: " . $e->getMessage();
        $message_type = "error";
    }
    $all_produtos = [];
    $all_marcas = [];
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Produtos - Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>

    <style>
        /* ==========================================================
           CSS COMPLETO DO PAINEL ADMIN (Base configlayout.php)
           + Estilos específicos de produtos.php
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
            /* NOVO: Cor para Lançamento */
            --info-color: #5bc0de;
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
        .form-group-check { display: flex; align-items: center; padding-top: 0; margin-bottom: 0.5rem; }
        .form-group-check label { font-weight: 400; color: var(--text-color); display: inline; margin-left: 0.5rem; text-transform: none; }
        .form-group-check input[type="checkbox"] { width: auto; vertical-align: middle; accent-color: var(--primary-color); cursor: pointer;}
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem 1.5rem; }
        .form-grid-checkboxes { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.5rem; padding-top: 1rem; }
        button[type="submit"] { padding: 0.65rem 1.2rem; background-color: var(--primary-color); color: #fff; border: none; border-radius: var(--border-radius); cursor: pointer; font-weight: 600; transition: background-color 0.3s ease, transform 0.1s ease; font-size: 0.9em; }
        button[type="submit"]:hover { background-color: var(--secondary-color); transform: translateY(-1px); }
        button[type="submit"].update { background-color: #28a745; }
        button[type="submit"].update:hover { background-color: #218838; }
        .form-container a.cancel { color: var(--light-text-color); margin-left: 1rem; font-size: 0.9em; }
        .form-container a.cancel:hover { text-decoration: underline; }

        /* --- Tabelas --- */
        .list-container { overflow-x: auto; }
        .list-container table { width: 100%; border-collapse: collapse; background-color: transparent; border-radius: 0; overflow: hidden; font-size: 0.85em; border: none; min-width: 600px; }
        .list-container th, .list-container td { border-bottom: 1px solid var(--border-color); padding: 10px 12px; text-align: left; vertical-align: middle; }
        .list-container th { background-color: rgba(0, 0, 0, 0.4); color: var(--text-color); font-weight: 600; text-transform: uppercase; font-size: 0.75em; }
        .list-container tbody tr:last-child td { border-bottom: none; }
        .list-container tbody tr:hover { background-color: rgba(255, 255, 255, 0.05); }
        .list-container td img { max-height: 40px; max-width: 80px; border-radius: 4px; vertical-align: middle; background-color: rgba(255,255,255,0.8); padding: 2px; object-fit: contain;}
        .list-container .actions { white-space: nowrap; text-align: right; }
        .list-container .actions a { color: var(--primary-color); margin-left: 0.8rem; font-size: 0.85em; transition: color 0.2s ease; }
        .list-container .actions a:last-child { margin-right: 0; }
        .list-container .actions a:hover { color: var(--secondary-color); }
        .list-container .actions a.delete { color: var(--danger-color); }
        .list-container .actions a.delete:hover { color: #c0392b; }
        .status-ativo { color: #82e0aa; font-weight: bold; }
        .status-inativo { color: var(--light-text-color); }
        .status-lancamento { color: var(--info-color); font-weight: bold; } /* NOVO */
        .list-container td .svg-preview { width: 24px; height: 24px; }
        .list-container td .svg-preview svg { width: 100%; height: 100%; fill: var(--light-text-color); }
        /* Estilo para preview de mídia */
        .midia-preview { width: 60px; height: 60px; object-fit: contain; background: #fff; border-radius: 4px; padding: 2px; }
        .midia-preview-video { display: flex; align-items: center; justify-content: center; width: 60px; height: 60px; background: #000; border-radius: 4px; }
        .midia-preview-video svg { width: 24px; height: 24px; fill: #fff; }

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
            .form-grid-checkboxes { grid-template-columns: 1fr; }
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

    <?php include 'admin_sidebar.php'; ?>

    <main class="main-content">
        <div class="content-header">
            <h1>Gerenciar Produtos e Marcas</h1>
            <p>Adicione, edite ou remova produtos e suas marcas associadas.</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo nl2br(htmlspecialchars($message)); ?>
            </div>
        <?php endif; ?>

        <div class="tab-navigation">
            <button class="tab-button active" data-tab-target="#tab-produtos">Produto (Adicionar/Editar)</button>
            <button class="tab-button" data-tab-target="#tab-lista">Lista de Produtos</button>
            <button class="tab-button" data-tab-target="#tab-marcas">Marcas</button>
        </div>

        <div class="tab-content-wrapper">

            <div class="tab-pane active" id="tab-produtos">
                <div class="crud-section" id="section-produtos">
                    <h3><?php echo $is_editing_produto ? 'Editar Produto' : 'Adicionar Novo Produto'; ?></h3>
                    <div class="form-container">
                        <form action="produtos.php#tab-produtos" method="POST">
                            <?php if ($is_editing_produto): ?>
                                <input type="hidden" name="id" value="<?php echo $edit_produto['id']; ?>">
                            <?php endif; ?>

                            <div class="form-group">
                                <label for="nome">Nome do Produto:</label>
                                <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($edit_produto['nome'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="descricao">Descrição:</label>
                                <textarea id="descricao" name="descricao"><?php echo htmlspecialchars($edit_produto['descricao'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-grid">
                                 <div class="form-group">
                                    <label for="preco">Preço (R$):</label>
                                    <input type="number" id="preco" name="preco" step="0.01" min="0" value="<?php echo htmlspecialchars($edit_produto['preco'] ?? '0.00'); ?>" required placeholder="19.99">
                                </div>
                                <div class="form-group">
                                    <label for="estoque">Estoque:</label>
                                    <input type="number" id="estoque" name="estoque" min="0" value="<?php echo htmlspecialchars($edit_produto['estoque'] ?? '0'); ?>" required>
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="marca_id">Marca:</label>
                                    <select id="marca_id" name="marca_id">
                                        <option value="0">-- Nenhuma --</option>
                                        <?php foreach($all_marcas as $marca): ?>
                                            <option value="<?php echo $marca['id']; ?>"
                                                <?php echo ($is_editing_produto && $edit_produto['marca_id'] == $marca['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($marca['nome']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="imagem_url">URL da Imagem Principal (Capa):</label>
                                    <input type="text" id="imagem_url" name="imagem_url" value="<?php echo htmlspecialchars($edit_produto['imagem_url'] ?? ''); ?>" placeholder="https://... ou /uploads/imagem.jpg">
                                </div>
                            </div>

                            <div class="form-grid-checkboxes">
                                <div class="form-group-check">
                                   <input type="checkbox" id="ativo" name="ativo" value="1"
                                        <?php
                                        // Se estiver editando, usa o valor do DB. Se for novo, marca por padrão.
                                        echo ($is_editing_produto && !empty($edit_produto['ativo'])) || !$is_editing_produto ? 'checked' : '';
                                        ?>>
                                   <label for="ativo">Ativo (Disponível para venda)</label>
                                </div>
                                <div class="form-group-check">
                                   <input type="checkbox" id="destaque" name="destaque" value="1"
                                        <?php echo ($is_editing_produto && !empty($edit_produto['destaque'])) ? 'checked' : ''; ?>>
                                   <label for="destaque">Destaque (Homepage)</label>
                                </div>
                                <div class="form-group-check">
                                   <input type="checkbox" id="mais_vendido" name="mais_vendido" value="1"
                                        <?php echo ($is_editing_produto && !empty($edit_produto['mais_vendido'])) ? 'checked' : ''; ?>>
                                   <label for="mais_vendido">Mais Vendido (Homepage)</label>
                                </div>
                                <div class="form-group-check">
                                   <input type="checkbox" id="is_lancamento" name="is_lancamento" value="1"
                                        <?php echo ($is_editing_produto && !empty($edit_produto['is_lancamento'])) ? 'checked' : ''; ?>>
                                   <label for="is_lancamento">Lançamento (Tag "Lançamento!")</label>
                                </div>
                            </div>

                            <button type="submit" name="salvar_produto" class="<?php echo $is_editing_produto ? 'update' : ''; ?>" style="margin-top: 1.5rem;">
                                <?php echo $is_editing_produto ? 'Salvar Alterações' : 'Adicionar Produto (e ir para Mídia)'; ?>
                            </button>
                            <?php if ($is_editing_produto): ?>
                                <a href="produtos.php#tab-lista" class="cancel">Cancelar Edição</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <?php if ($is_editing_produto): ?>
                    <div class="crud-section" id="section-midia">
                        <h3>Mídia da Galeria (Imagens Adicionais e Vídeo)</h3>
                        <div class="form-container">
                            <h4>Adicionar Nova Mídia para "<?php echo htmlspecialchars($edit_produto['nome']); ?>"</h4>
                            <form action="produtos.php?edit_produto=<?php echo $edit_produto['id']; ?>#section-midia" method="POST">
                                <input type="hidden" name="produto_id_midia" value="<?php echo $edit_produto['id']; ?>">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="tipo">Tipo</label>
                                        <select name="tipo" id="tipo" required>
                                            <option value="imagem">Imagem (proporção da imagem deve ser: 600px x 600px)</option>
                                            <option value="video">Vídeo (Vimeo URL)</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="url">URL</label>
                                        <input type="text" id="url" name="url" placeholder="https://vimeo.com/12345 or /uploads/img.jpg" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="ordem">Ordem</label>
                                        <input type="number" id="ordem" name="ordem" value="0">
                                    </div>
                                </div>
                                <button type="submit" name="salvar_midia">Adicionar Mídia</button>
                            </form>
                        </div>

                        <div class="list-container">
                            <h4>Mídias Atuais da Galeria</h4>
                            <table>
                                <thead>
                                    <tr>
                                        <th data-label="Ordem">Ordem</th>
                                        <th data-label="Tipo">Tipo</th>
                                        <th data-label="Preview">Preview</th>
                                        <th data-label="URL">URL</th>
                                        <th class="actions">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($produto_midias as $midia): ?>
                                    <tr>
                                        <td data-label="Ordem"><?php echo $midia['ordem']; ?></td>
                                        <td data-label="Tipo"><?php echo htmlspecialchars($midia['tipo']); ?></td>
                                        <td data-label="Preview">
                                            <?php if ($midia['tipo'] == 'imagem'): ?>
                                                <img src="<?php echo htmlspecialchars($midia['url']); ?>" alt="Preview" class="midia-preview">
                                            <?php else: ?>
                                                <div class="midia-preview-video" title="Vídeo">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="URL"><?php echo htmlspecialchars($midia['url']); ?></td>
                                        <td class="actions">
                                            <a href="produtos.php?delete_midia=<?php echo $midia['id']; ?>&prod_id=<?php echo $edit_produto['id']; ?>#section-midia" onclick="return confirm('Tem certeza?');" class="delete">Remover</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($produto_midias)): ?>
                                    <tr><td colspan="5" style="text-align: center; color: var(--light-text-color);">Nenhuma mídia adicional cadastrada.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="tab-pane" id="tab-lista">
                <div class="crud-section" id="section-lista-produtos">
                    <h3>Lista de Produtos</h3>
                    <?php
                    if (empty($all_produtos)):
                        echo '<div class="list-container"><p style="text-align: center; color: var(--light-text-color);">Nenhum produto cadastrado.</p></div>';
                    else:
                        $current_marca_nome = -1;
                        foreach ($all_produtos as $produto):
                            $marca_nome = $produto['marca_nome'] ?? 'Sem Marca';
                            if ($marca_nome !== $current_marca_nome):
                                if ($current_marca_nome !== -1):
                                    echo '</tbody></table></div>'; // Fecha tabela anterior
                                endif;
                                $current_marca_nome = $marca_nome;
                    ?>
                                <h3 style="margin-top: 2rem;"><?php echo htmlspecialchars($current_marca_nome); ?></h3>
                                <div class="list-container" style="margin-bottom: 0.5rem; padding-top: 0; border: none; background: none; box-shadow: none; border-radius: 0;">
                                    <table style="border-radius: var(--border-radius); border: 1px solid var(--border-color);">
                                        <thead>
                                            <tr>
                                                <th>Nome</th>
                                                <th>Status</th>
                                                <th>Preço</th>
                                                <th>Estoque</th>
                                                <th>Imagem (Capa)</th>
                                                <th>Flags</th> <th class="actions">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                    <?php
                            endif;
                    ?>
                                            <tr>
                                                <td data-label="Nome"><?php echo htmlspecialchars($produto['nome']); ?></td>
                                                <td data-label="Status">
                                                    <?php if ($produto['ativo']): ?>
                                                        <span class="status-ativo">Ativo</span>
                                                    <?php else: ?>
                                                        <span class="status-inativo">Inativo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="Preço">R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?></td>
                                                <td data-label="Estoque"><?php echo $produto['estoque']; ?></td>
                                                <td data-label="Imagem (Capa)">
                                                    <?php if (!empty($produto['imagem_url'])): ?>
                                                        <img src="<?php echo htmlspecialchars($produto['imagem_url']); ?>" alt="Preview">
                                                    <?php else: ?>
                                                        (sem imagem)
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="Flags"> <?php if ($produto['is_lancamento']): ?>
                                                        <span class="status-lancamento" style="font-size: 0.9em; display: block;">Lançamento</span>
                                                    <?php endif; ?>
                                                    <?php if ($produto['destaque']): ?>
                                                        <span class="status-ativo" style="font-size: 0.9em; display: block;">Destaque</span>
                                                    <?php endif; ?>
                                                    <?php if ($produto['mais_vendido']): ?>
                                                        <span class="status-ativo" style="font-size: 0.9em; display: block;">Mais Vendido</span>
                                                    <?php endif; ?>
                                                    <?php if (!$produto['destaque'] && !$produto['mais_vendido'] && !$produto['is_lancamento']): ?>
                                                        <span class="status-inativo" style="font-weight: 400;">Nenhum</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="actions">
                                                    <a href="produtos.php?edit_produto=<?php echo $produto['id']; ?>#tab-produtos">Editar</a>
                                                    <a href="produtos.php?delete_produto=<?php echo $produto['id']; ?>" onclick="return confirm('Tem certeza?');" class="delete">Remover</a>
                                                </td>
                                            </tr>
                    <?php
                        endforeach; // Fim do loop de produtos
                        echo '</tbody></table></div>'; // Fecha a última tabela
                    endif; // Fim do if (empty($all_produtos))
                    ?>
                </div>
            </div>

            <div class="tab-pane" id="tab-marcas">
                <div class="crud-section" id="section-marcas">
                    <h3>Gerenciar Marcas</h3>
                    <div class="form-container">
                        <h4><?php echo $is_editing_marca ? 'Editar Marca' : 'Adicionar Nova Marca'; ?></h4>
                        <form action="produtos.php#tab-marcas" method="POST">
                                <?php if ($is_editing_marca): ?>
                                    <input type="hidden" name="marca_id" value="<?php echo $edit_marca['id']; ?>">
                                <?php endif; ?>
                                <div class="form-group">
                                    <label for="marca_nome">Nome da Marca:</label>
                                    <input type="text" id="marca_nome" name="marca_nome" value="<?php echo htmlspecialchars($edit_marca['nome'] ?? ''); ?>" required>
                                </div>
                                <button type="submit" name="salvar_marca" class="<?php echo $is_editing_marca ? 'update' : ''; ?>">
                                    <?php echo $is_editing_marca ? 'Salvar Alterações' : 'Adicionar Marca'; ?>
                                </button>
                                <?php if ($is_editing_marca): ?>
                                    <a href="produtos.php#tab-marcas" class="cancel">Cancelar Edição</a>
                                <?php endif; ?>
                        </form>
                    </div>
                    <div class="list-container">
                        <h4>Marcas Cadastradas</h4>
                        <table>
                            <thead>
                                <tr>
                                    <th data-label="Nome">Nome</th>
                                    <th class="actions">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_marcas as $marca): ?>
                                    <tr>
                                        <td data-label="Nome"><?php echo htmlspecialchars($marca['nome']); ?></td>
                                        <td class="actions">
                                            <a href="produtos.php?edit_marca=<?php echo $marca['id']; ?>#section-marcas">Editar</a>
                                            <a href="produtos.php?delete_marca=<?php echo $marca['id']; ?>" onclick="return confirm('Tem certeza? Produtos associados ficarão sem marca.');" class="delete">Remover</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($all_marcas)): ?>
                                    <tr><td colspan="2" style="text-align: center; color: var(--light-text-color);">Nenhuma marca cadastrada.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

<script>
    // --- JavaScript para Partículas ---
    // MANTIDO: Inicializa a biblioteca particlesJS para o fundo animado
    particlesJS('particles-js', {"particles":{"number":{"value":60,"density":{"enable":true,"value_area":800}},"color":{"value":"#4a69bd"},"shape":{"type":"circle"},"opacity":{"value":0.4,"random":false},"size":{"value":3,"random":true},"line_linked":{"enable":true,"distance":150,"color":"#ffffff","opacity":0.1,"width":1},"move":{"enable":true,"speed":1.5,"direction":"none","random":false,"straight":false,"out_mode":"out","bounce":false}},"interactivity":{"detect_on":"canvas","events":{"onhover":{"enable":true,"mode":"repulse"},"onclick":{"enable":true,"mode":"push"},"resize":true}},"retina_detect":true});

    // --- JavaScript Específico da Página ---
    document.addEventListener('DOMContentLoaded', () => {
        // A Lógica de Menu e Perfil foi removida, pois está no admin_sidebar.php

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

                // Limpa a query string (se houver) e define o hash da aba
                const newUrl = window.location.pathname + targetPaneId; // Ex: produtos.php#tab-marcas
                history.pushState(null, null, newUrl);
            });
        });

        // --- Lógica para Ancoragem e Abas ---
        const urlParams = new URLSearchParams(window.location.search);
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
                // Ativa a aba "Produtos" (form) se edit_produto estiver na URL
                if (urlParams.has('edit_produto')) {
                     const tabBtn = document.querySelector('.tab-button[data-tab-target="#tab-produtos"]');
                     if(tabBtn) tabBtn.click();
                     elementToScroll = document.querySelector('#section-produtos'); // Define o scroll para o form
                }
                // Ativa a aba "Marcas" se edit_marca estiver na URL
                else if (urlParams.has('edit_marca')) {
                    const tabBtn = document.querySelector('.tab-button[data-tab-target="#tab-marcas"]');
                    if(tabBtn) tabBtn.click();
                    elementToScroll = document.querySelector('#section-marcas');
                }
                // Ativa a aba "Lista" se deletou um produto
                else if (urlHash === '#tab-lista') {
                    const tabBtn = document.querySelector('.tab-button[data-tab-target="#tab-lista"]');
                    if(tabBtn) tabBtn.click();
                    elementToScroll = document.querySelector('#tab-lista');
                }
                 // Ativa a aba "Marcas" se salvou ou deletou marca
                else if (urlHash === '#tab-marcas') {
                    const tabBtn = document.querySelector('.tab-button[data-tab-target="#tab-marcas"]');
                    if(tabBtn) tabBtn.click();
                    elementToScroll = document.querySelector('#section-marcas');
                }
                // Padrão: Ativa a primeira aba
                else {
                    const firstTabButton = document.querySelector('.tab-navigation .tab-button:first-child');
                    const firstPane = document.querySelector('.tab-content-wrapper .tab-pane:first-child');
                    if (firstTabButton && firstPane && !document.querySelector('.tab-button.active')) {
                        firstTabButton.classList.add('active');
                        firstPane.classList.add('active');
                    }
                }
            }

            // Rola para a seção específica
            if (elementToScroll) {
                setTimeout(() => {
                    // Tenta rolar para o primeiro h3, h4 ou o próprio elemento
                    const headerElement = elementToScroll.querySelector('h3') || elementToScroll.querySelector('h4') || elementToScroll;
                    if(headerElement) {
                         headerElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }, 150);
            }
        }

        // Ativa a aba correta no carregamento da página
        activateTabFromHash(urlHash);

    });
</script>
</body>
</html>