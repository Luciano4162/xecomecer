<?php
// admin_panel/avaliacoes.php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

require_once '../config/db.php'; // Conexão com o DB

$message = '';
$message_type = '';

// --- INICIALIZAÇÃO DE VARIÁVEIS ---
$edit_avaliacao = null;
$is_editing_avaliacao = false;

// ==========================================================
// LÓGICA CRUD PARA AVALIAÇÕES
// ==========================================================

// 1A. ADICIONAR ou ATUALIZAR AVALIAÇÃO (Manual do Admin)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_avaliacao'])) {
    $produto_id = (int)$_POST['produto_id'];
    $nome_avaliador = trim($_POST['nome_avaliador']);
    $classificacao = (int)$_POST['classificacao'];
    $comentario = trim($_POST['comentario']);
    $foto_url = trim($_POST['foto_url'] ?? '');
    // Aprovado é sempre TRUE se o checkbox estiver marcado (ou se o admin marcar/desmarcar na edição)
    $aprovado = isset($_POST['aprovado']);
    $avaliacao_id = isset($_POST['avaliacao_id']) ? (int)$_POST['avaliacao_id'] : null;

    if (empty($produto_id) || empty($nome_avaliador) || $classificacao < 1 || $classificacao > 5) {
        $message = "Produto, Nome do Avaliador e Classificação (1-5) são obrigatórios.";
        $message_type = "error";
    } else {
        try {
            if ($avaliacao_id) { // UPDATE
                $sql = "UPDATE avaliacoes_produto SET
                            produto_id = :pid,
                            nome_avaliador = :nome,
                            classificacao = :classificacao,
                            comentario = :comentario,
                            foto_url = :foto_url,
                            aprovado = :aprovado
                        WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $avaliacao_id, PDO::PARAM_INT);
            } else { // INSERT
                // Se for inserção manual, o status 'aprovado' virá do checkbox, mas garantimos o NOW()
                $sql = "INSERT INTO avaliacoes_produto (
                            produto_id, nome_avaliador, classificacao, comentario, foto_url, aprovado, data_avaliacao
                        ) VALUES (
                            :pid, :nome, :classificacao, :comentario, :foto_url, :aprovado, NOW()
                        )";
                $stmt = $pdo->prepare($sql);
            }

            $stmt->bindParam(':pid', $produto_id, PDO::PARAM_INT);
            $stmt->bindParam(':nome', $nome_avaliador, PDO::PARAM_STR);
            $stmt->bindParam(':classificacao', $classificacao, PDO::PARAM_INT);
            $stmt->bindParam(':comentario', $comentario, PDO::PARAM_STR);
            $stmt->bindParam(':foto_url', $foto_url, PDO::PARAM_STR);
            $stmt->bindParam(':aprovado', $aprovado, PDO::PARAM_BOOL);
            $stmt->execute();

            $message = $avaliacao_id ? "Avaliação atualizada com sucesso!" : "Avaliação adicionada com sucesso!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Erro ao salvar avaliação: " . $e->getMessage();
            $message_type = "error";
        }
    }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $message_type;
    // Redireciona para evitar reenvio de formulário e limpa URL de edição
    $redirect_hash = isset($_POST['avaliacao_id']) ? "#tab-adicionar" : "#tab-lista";
    header("Location: avaliacoes.php" . $redirect_hash);
    exit;
}

// 2A. APROVAR / REPROVAR
// AÇÃO REMOVIDA para simplificar a moderação (sem toggle)
// Os links na tabela também serão removidos/ajustados.


// 3A. DELETAR
if (isset($_GET['delete_avaliacao'])) {
    $avaliacao_id = (int)$_GET['delete_avaliacao'];
    try {
        $sql = "DELETE FROM avaliacoes_produto WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $avaliacao_id]);
        $message = "Avaliação removida permanentemente!"; $message_type = "success";
    } catch (PDOException $e) {
        $message = "Erro ao remover avaliação: " . $e->getMessage(); $message_type = "error";
    }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $message_type;
    header("Location: avaliacoes.php#tab-lista"); exit;
}

// 4A. MODO DE EDIÇÃO
if (isset($_GET['edit_avaliacao'])) {
    $avaliacao_id = (int)$_GET['edit_avaliacao'];
    $is_editing_avaliacao = true;
    $stmt_edit_avaliacao = $pdo->prepare("SELECT * FROM avaliacoes_produto WHERE id = :id");
    $stmt_edit_avaliacao->execute(['id' => $avaliacao_id]);
    $edit_avaliacao = $stmt_edit_avaliacao->fetch(PDO::FETCH_ASSOC);
    if (!$edit_avaliacao) $is_editing_avaliacao = false;
}

// ==========================================================
// LÓGICA DE LEITURA (LISTAGEM)
// ==========================================================

// Pega mensagens flash da sessão
if (isset($_SESSION['flash_message']) && empty($message)) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// --- FILTROS ---
$filter_produto = $_GET['filter_produto'] ?? '';
$filter_marca = $_GET['filter_marca'] ?? '';
$filter_status = $_GET['filter_status'] ?? 'pending';

$where_clauses = ["TRUE"];
$params = [];

// Filtro por STATUS - Mantido para visualização, mas a moderação foi simplificada
if ($filter_status === 'approved') {
    $where_clauses[] = "ap.aprovado = TRUE";
} elseif ($filter_status === 'pending') {
    $where_clauses[] = "ap.aprovado = FALSE";
}

// Filtro por PRODUTO
if (!empty($filter_produto)) {
    $where_clauses[] = "ap.produto_id = :pid_filter";
    $params[':pid_filter'] = (int)$filter_produto;
}

// Filtro por MARCA
if (!empty($filter_marca)) {
    $where_clauses[] = "p.marca_id = :mid_filter";
    $params[':mid_filter'] = (int)$filter_marca;
}


// --- QUERY PRINCIPAL ---
$sql_avaliacoes = "
    SELECT
        ap.id, ap.nome_avaliador, ap.classificacao, ap.comentario,
        ap.data_avaliacao, ap.aprovado, ap.foto_url,
        p.nome AS produto_nome, p.imagem_url AS produto_imagem,
        m.nome AS marca_nome
    FROM avaliacoes_produto ap
    JOIN produtos p ON ap.produto_id = p.id
    LEFT JOIN marcas m ON p.marca_id = m.id
    WHERE " . implode(' AND ', $where_clauses) . "
    ORDER BY ap.data_avaliacao DESC
";

try {
    $stmt_listagem = $pdo->prepare($sql_avaliacoes);
    $stmt_listagem->execute($params);
    $all_avaliacoes = $stmt_listagem->fetchAll(PDO::FETCH_ASSOC);

    // Dados para os Filtros
    $all_produtos_options = $pdo->query("SELECT id, nome FROM produtos ORDER BY nome ASC")->fetchAll(PDO::FETCH_KEY_PAIR);
    $all_marcas_options = $pdo->query("SELECT id, nome FROM marcas ORDER BY nome ASC")->fetchAll(PDO::FETCH_KEY_PAIR);

    // Contagens para os filtros
    $count_pending = $pdo->query("SELECT COUNT(*) FROM avaliacoes_produto WHERE aprovado = FALSE")->fetchColumn();
    $count_approved = $pdo->query("SELECT COUNT(*) FROM avaliacoes_produto WHERE aprovado = TRUE")->fetchColumn();
} catch (PDOException $e) {
    if (empty($message)) {
        $message .= " Erro ao carregar dados: " . $e->getMessage();
        if ($e->getCode() == '42703') {
             $message .= ". Por favor, execute o SQL 'ALTER TABLE public.avaliacoes_produto ADD COLUMN nome_avaliador VARCHAR(100) NOT NULL DEFAULT 'Admin';' no seu DB para corrigir o problema de coluna ausente.";
        }
        $message_type = "error";
    }
    $all_avaliacoes = [];
    $all_produtos_options = [];
    $all_marcas_options = [];
    $count_pending = 0;
    $count_approved = 0;
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Avaliações - Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>

    <style>
        /* ==========================================================
           CSS PADRÃO DO PAINEL ADMIN (Dark/Glass Theme)
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
            --info-color: #5bc0de;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--background-color); color: var(--text-color); display: flex; min-height: 100vh; overflow-x: hidden; }
        #particles-js { position: fixed; width: 100%; height: 100%; top: 0; left: 0; z-index: -1; opacity: 0.6; }

        /* --- Sidebar --- */
        .sidebar { width: var(--sidebar-width); background-color: var(--sidebar-color); height: 100vh; position: fixed; left: 0; top: 0; padding: 1.5rem; display: flex; flex-direction: column; border-right: 1px solid var(--border-color); z-index: 1000; transition: transform 0.3s ease; box-shadow: var(--box-shadow); }
        .sidebar .logo-area { display: flex; flex-direction: column; align-items: center; margin-bottom: 1rem; } .sidebar .logo-circle { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 0.5rem; box-shadow: 0 0 10px rgba(74, 105, 189, 0.6); overflow: hidden; background-color: #fff; } .sidebar .logo-circle svg { color: var(--primary-color); width: 24px; height: 24px; } .sidebar .logo-text { font-size: 1rem; font-weight: 600; color: var(--text-color); text-align: center; } .sidebar .divider { width: 100%; height: 1px; background-color: var(--border-color); margin: 1rem 0; } .sidebar nav { flex-grow: 1; } .sidebar nav a { display: flex; align-items: center; gap: 0.8rem; padding: 0.8rem 1rem; color: var(--light-text-color); text-decoration: none; border-radius: var(--border-radius); margin-bottom: 0.5rem; transition: all 0.3s ease; border: 1px solid transparent; background-color: transparent; } .sidebar nav a:hover, .sidebar nav a.active { background-color: var(--glass-background); color: var(--text-color); border-color: var(--primary-color); box-shadow: 0 2px 8px rgba(74, 105, 189, 0.4); } .sidebar nav a svg { width: 20px; height: 20px; flex-shrink: 0; } .user-profile { position: relative; margin-top: auto; background-color: var(--glass-background); padding: 0.75rem; border-radius: var(--border-radius); display: flex; align-items: center; gap: 1rem; cursor: pointer; border: 1px solid var(--border-color); transition: all 0.3s ease; } .user-profile:hover { border-color: var(--primary-color); } .avatar { width: 35px; height: 35px; border-radius: 50%; background-color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.9rem; } .user-info .user-name { font-weight: 600; font-size: 0.85rem; line-height: 1.2; } .user-info .user-level { font-size: 0.7rem; color: var(--light-text-color); } .profile-dropdown { position: absolute; bottom: calc(100% + 10px); left: 0; width: 100%; background-color: #2c3a4f; border-radius: var(--border-radius); border: 1px solid var(--border-color); padding: 0.5rem; z-index: 20; visibility: hidden; opacity: 0; transform: translateY(10px); transition: all 0.3s ease; } .profile-dropdown.show { visibility: visible; opacity: 1; transform: translateY(0); } .profile-dropdown a { display: flex; gap: 0.75rem; padding: 0.75rem; color: var(--light-text-color); font-size: 0.85rem; border-radius: 6px; } .profile-dropdown a:hover { background-color: var(--glass-background); color: var(--text-color); }
        .sidebar nav .sidebar-submenu { padding-left: 20px; margin-top: -5px; margin-bottom: 5px; overflow: hidden; transition: max-height 0.3s ease-out; max-height: 0; } .sidebar nav a.open .menu-chevron { transform: rotate(90deg); } .sidebar-submenu a { font-size: 0.9em; padding: 0.7rem 1rem 0.7rem 1.5rem; color: var(--light-text-color); position: relative; } .sidebar-submenu a::before { content: ''; position: absolute; left: 0.5rem; top: 50%; transform: translateY(-50%); width: 4px; height: 4px; border-radius: 50%; background-color: var(--light-text-color); transition: all 0.3s ease; } .sidebar-submenu a:hover { color: var(--text-color); background-color: transparent; border-color: transparent; box-shadow: none; } .sidebar-submenu a.active-child { color: #fff; font-weight: 600; } .sidebar-submenu a.active-child::before { background-color: var(--primary-color); transform: translateY(-50%) scale(1.5); }

        /* --- Conteúdo Principal --- */
        .main-content { margin-left: var(--sidebar-width); flex-grow: 1; padding: 2rem 2.5rem; min-height: 100vh; overflow-y: auto; transition: margin-left 0.3s ease, width 0.3s ease; width: calc(100% - var(--sidebar-width)); }
        .content-header { margin-bottom: 2rem; background: var(--glass-background); padding: 1.5rem 2rem; border-radius: var(--border-radius); border: 1px solid var(--border-color); backdrop-filter: blur(5px); }
        .content-header h1 { font-size: 1.8rem; font-weight: 600; color: var(--primary-color); margin: 0 0 0.25rem 0; }
        .content-header p { font-size: 1rem; color: var(--light-text-color); margin: 0; }
        .message { padding: 1rem; border-radius: var(--border-radius); margin-bottom: 1.5rem; font-weight: 600; border: 1px solid transparent; backdrop-filter: blur(3px); }
        .message.error { background-color: var(--error-bg); color: var(--error-text); border-color: rgba(114, 28, 36, 0.5); }
        .message.warning { background-color: var(--warning-bg); color: var(--warning-text); border-color: rgba(133, 100, 4, 0.5); }
        .message.success { background-color: var(--success-bg); color: var(--success-text); border-color: rgba(21, 87, 36, 0.5); }

        /* --- Tabs --- */
        .tab-navigation { display: flex; gap: 0.5rem; margin-bottom: -1px; position: relative; z-index: 10; padding-left: 1rem; }
        .tab-button { padding: 0.8rem 1.5rem; border: 1px solid var(--border-color); background-color: var(--sidebar-color); color: var(--light-text-color); border-radius: var(--border-radius) var(--border-radius) 0 0; cursor: pointer; font-weight: 500; transition: all 0.2s ease-in-out; border-bottom-color: var(--border-color); }
        .tab-button.active { background-color: var(--glass-background); color: var(--primary-color); font-weight: 600; border-bottom-color: var(--glass-background); box-shadow: 0 2px 8px rgba(0,0,0,0.3); }
        .tab-pane { display: none; padding: 0; background: transparent; }
        .tab-pane.active { display: block; animation: fadeIn 0.4s ease-in-out; }
        .crud-section { margin-bottom: 2.5rem; }
        .form-container, .list-container { background: var(--glass-background); padding: 1.5rem 2rem; border-radius: var(--border-radius); border: 1px solid var(--border-color); box-shadow: var(--box-shadow); margin-bottom: 2rem; backdrop-filter: blur(5px); }
        .crud-section h3 { font-size: 1.25rem; color: var(--text-color); margin-bottom: 1.25rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color); font-weight: 600; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem 1.5rem; }

        /* --- Formulários --- */
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--light-text-color); font-size: 0.8rem; text-transform: uppercase; }
        .form-group input[type="text"], .form-group input[type="number"], .form-group textarea, .form-group select { width: 100%; padding: 0.65rem 0.8rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); background-color: rgba(0, 0, 0, 0.3); color: var(--text-color); box-sizing: border-box; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color: var(--primary-color); box-shadow: 0 0 8px rgba(74, 105, 189, 0.5); outline: none; }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .form-group-check label { font-weight: 400; color: var(--text-color); display: inline; margin-left: 0.5rem; text-transform: none; }
        button[type="submit"] { padding: 0.65rem 1.2rem; background-color: var(--primary-color); color: #fff; border: none; border-radius: var(--border-radius); cursor: pointer; font-weight: 600; }
        button[type="submit"].update { background-color: #28a745; }
        .form-container a.cancel { color: var(--light-text-color); margin-left: 1rem; font-size: 0.9em; }

        /* --- Tabelas --- */
        .list-container table { width: 100%; border-collapse: collapse; min-width: 900px; }
        .list-container th, .list-container td { border-bottom: 1px solid var(--border-color); padding: 10px 12px; text-align: left; vertical-align: top; }
        .list-container th { background-color: rgba(0, 0, 0, 0.4); color: var(--text-color); font-weight: 600; text-transform: uppercase; font-size: 0.75em; }
        .list-container tbody tr:hover { background-color: rgba(255, 255, 255, 0.05); }
        .list-container td img { max-height: 40px; max-width: 40px; border-radius: 4px; object-fit: cover; }
        .list-container .actions { white-space: nowrap; text-align: right; }
        .list-container .actions a { margin-left: 0.5rem; }
        .list-container .actions a.delete { color: var(--danger-color); }

        /* --- ESTILOS ESPECÍFICOS DE AVALIAÇÕES --- */
        .rating-stars { color: gold; font-size: 1.2em; letter-spacing: 0.1em; }
        .rating-stars .empty { opacity: 0.3; }
        .status-approved { color: var(--success-text); }
        .status-pending { color: var(--warning-text); }
        .status-rejected { color: var(--error-text); }

        .filter-bar { background: rgba(255, 255, 255, 0.05); padding: 1rem; border-radius: var(--border-radius); margin-bottom: 1rem; display: flex; flex-wrap: wrap; gap: 1rem; }
        .filter-bar .form-group { margin: 0; flex-basis: 200px; }
        .filter-bar .form-group label { margin-bottom: 0.2rem; font-size: 0.75rem; }
        .filter-bar button[type="submit"] { flex-basis: 120px; align-self: flex-end; }

        /* --- Responsividade (Padrão) --- */
        .menu-toggle { display: none; position: fixed; top: 1rem; left: 1rem; z-index: 1003; cursor: pointer; padding: 8px; background-color: var(--sidebar-color); border-radius: var(--border-radius); border: 1px solid var(--border-color); box-shadow: var(--box-shadow);}
        .menu-toggle svg { width: 20px; height: 20px; color: var(--text-color); display: block; }
        @media (max-width: 1024px) {
            body { position: relative; }
            .sidebar { transform: translateX(-280px); }
            .menu-toggle { display: flex; }
            .main-content { margin-left: 0; width: 100%; padding: 1.5rem; padding-top: 5rem; }
            body.sidebar-open .sidebar { transform: translateX(0); }
            body.sidebar-open::after { content: ''; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.7); z-index: 1001; backdrop-filter: blur(2px);}
        }
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .filter-bar { flex-direction: column; }
            .filter-bar .form-group, .filter-bar button[type="submit"] { flex-basis: 100%; }
            .list-container thead { display: none; }
            .list-container table { min-width: 0; }
            .list-container tr { display: block; margin-bottom: 1rem; padding: 1rem; background: rgba(0,0,0,0.1); }
            .list-container td { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: none; text-align: right; }
            .list-container td::before { content: attr(data-label); font-weight: 600; color: var(--light-text-color); text-align: left; margin-right: 1rem; flex-basis: 40%;}
            .list-container td.actions { justify-content: flex-end; }
            .list-container td.actions::before { display: none; }
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
            <h1>Gerenciar Avaliações</h1>
            <p>Modere, filtre e adicione manualmente as avaliações dos produtos.</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo nl2br(htmlspecialchars($message)); ?>
            </div>
        <?php endif; ?>

        <div class="tab-navigation">
            <button class="tab-button active" data-tab-target="#tab-lista">Moderação / Lista</button>
            <button class="tab-button" data-tab-target="#tab-adicionar">Adicionar Manualmente</button>
        </div>

        <div class="tab-content-wrapper">

            <div class="tab-pane active" id="tab-lista">
                <div class="crud-section">
                    <h3>Filtros</h3>
                    <form action="avaliacoes.php#tab-lista" method="GET" class="filter-bar">
                        <div class="form-group">
                            <label for="filter_status">Status:</label>
                            <select name="filter_status" id="filter_status">
                                <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pendente (<?php echo $count_pending; ?>)</option>
                                <option value="approved" <?php echo $filter_status == 'approved' ? 'selected' : ''; ?>>Aprovado (<?php echo $count_approved; ?>)</option>
                                <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>Todos</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="filter_produto">Produto:</label>
                            <select name="filter_produto" id="filter_produto">
                                <option value="">-- Todos --</option>
                                <?php foreach ($all_produtos_options as $id => $nome): ?>
                                    <option value="<?php echo $id; ?>" <?php echo $filter_produto == $id ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($nome); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="filter_marca">Marca:</label>
                            <select name="filter_marca" id="filter_marca">
                                <option value="">-- Todas --</option>
                                <?php foreach ($all_marcas_options as $id => $nome): ?>
                                    <option value="<?php echo $id; ?>" <?php echo $filter_marca == $id ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($nome); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit">Filtrar</button>
                    </form>
                </div>

                <div class="crud-section">
                    <h3>Avaliações Encontradas (<?php echo count($all_avaliacoes); ?>)</h3>
                    <div class="list-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Nota</th>
                                    <th>Produto / Marca</th>
                                    <th>Avaliador / Data</th>
                                    <th>Comentário / Foto</th>
                                    <th class="actions">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($all_avaliacoes)): ?>
                                    <tr><td colspan="6" style="text-align: center; color: var(--light-text-color);">Nenhuma avaliação encontrada com os filtros atuais.</td></tr>
                                <?php endif; ?>

                                <?php foreach ($all_avaliacoes as $avaliacao):
                                    $is_approved = filter_var($avaliacao['aprovado'], FILTER_VALIDATE_BOOLEAN);
                                    $status_class = $is_approved ? 'status-approved' : 'status-pending';
                                    $status_text = $is_approved ? 'APROVADA' : 'PENDENTE';
                                ?>
                                    <tr class="<?php echo $status_class; ?>">
                                        <td data-label="Status">
                                            <span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                        </td>
                                        <td data-label="Nota">
                                            <span class="rating-stars">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <span class="<?php echo $i <= $avaliacao['classificacao'] ? '' : 'empty'; ?>">★</span>
                                                <?php endfor; ?>
                                            </span>
                                            (<?php echo $avaliacao['classificacao']; ?>)
                                        </td>
                                        <td data-label="Produto / Marca">
                                            **<?php echo htmlspecialchars($avaliacao['produto_nome']); ?>**<br>
                                            <span style="color: var(--light-text-color);"><?php echo htmlspecialchars($avaliacao['marca_nome'] ?? 'Sem Marca'); ?></span>
                                            <?php if (!empty($avaliacao['produto_imagem'])): ?>
                                                <img src="<?php echo htmlspecialchars($avaliacao['produto_imagem']); ?>" alt="Img" style="max-height: 20px; max-width: 40px; margin-left: 5px;">
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Avaliador / Data">
                                            **<?php echo htmlspecialchars($avaliacao['nome_avaliador']); ?>**<br>
                                            <span style="font-size: 0.8em; color: var(--light-text-color);"><?php echo (new DateTime($avaliacao['data_avaliacao']))->format('d/m/Y H:i'); ?></span>
                                        </td>
                                        <td data-label="Comentário / Foto">
                                            <?php echo !empty($avaliacao['comentario']) ? htmlspecialchars(substr($avaliacao['comentario'], 0, 50)) . (strlen($avaliacao['comentario']) > 50 ? '...' : '') : 'N/A'; ?>
                                            <?php if (!empty($avaliacao['foto_url'])): ?>
                                                <br><img src="<?php echo htmlspecialchars($avaliacao['foto_url']); ?>" alt="Foto" style="max-height: 40px; max-width: 40px;">
                                            <?php endif; ?>
                                        </td>
                                        <td class="actions">
                                            <a href="avaliacoes.php?edit_avaliacao=<?php echo $avaliacao['id']; ?>#tab-adicionar">Editar</a>
                                            <?php if (!$is_approved): ?>
                                                <?php else: ?>
                                                <?php endif; ?>
                                            <a href="avaliacoes.php?delete_avaliacao=<?php echo $avaliacao['id']; ?>" onclick="return confirm('Tem certeza que deseja DELETAR esta avaliação?');" class="delete">Deletar</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="tab-pane" id="tab-adicionar">
                <div class="crud-section">
                    <h3><?php echo $is_editing_avaliacao ? 'Editar Avaliação ID: ' . $edit_avaliacao['id'] : 'Adicionar Nova Avaliação Manual'; ?></h3>
                    <div class="form-container">
                        <form action="avaliacoes.php#tab-adicionar" method="POST">
                            <?php if ($is_editing_avaliacao): ?>
                                <input type="hidden" name="avaliacao_id" value="<?php echo $edit_avaliacao['id']; ?>">
                            <?php endif; ?>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="produto_id">Produto:</label>
                                    <select id="produto_id" name="produto_id" required>
                                        <option value="">-- Selecione um Produto --</option>
                                        <?php
                                        $produto_options = $all_produtos_options;
                                        if ($is_editing_avaliacao && !isset($produto_options[$edit_avaliacao['produto_id']])) {
                                            $produto_options[$edit_avaliacao['produto_id']] = "(ID {$edit_avaliacao['produto_id']})";
                                        }
                                        ?>
                                        <?php foreach ($produto_options as $id => $nome): ?>
                                            <option value="<?php echo $id; ?>"
                                                <?php echo ($is_editing_avaliacao && $edit_avaliacao['produto_id'] == $id) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($nome); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="nome_avaliador">Nome do Avaliador:</label>
                                    <input type="text" id="nome_avaliador" name="nome_avaliador"
                                           value="<?php echo htmlspecialchars($edit_avaliacao['nome_avaliador'] ?? ''); ?>" required
                                           placeholder="Ex: João Silva ou Admin">
                                </div>
                                <div class="form-group">
                                    <label for="classificacao">Classificação (1 a 5):</label>
                                    <input type="number" id="classificacao" name="classificacao" min="1" max="5" required
                                           value="<?php echo htmlspecialchars($edit_avaliacao['classificacao'] ?? '5'); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="comentario">Comentário:</label>
                                <textarea id="comentario" name="comentario" placeholder="Comentário do cliente ou admin."><?php echo htmlspecialchars($edit_avaliacao['comentario'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="foto_url">URL da Foto (Opcional):</label>
                                <input type="text" id="foto_url" name="foto_url"
                                       value="<?php echo htmlspecialchars($edit_avaliacao['foto_url'] ?? ''); ?>"
                                       placeholder="URL da imagem (ex: /uploads/review_foto.jpg)">
                            </div>

                            <div class="form-group-check">
                                <input type="checkbox" id="aprovado" name="aprovado" value="1"
                                    <?php echo ($is_editing_avaliacao && filter_var($edit_avaliacao['aprovado'], FILTER_VALIDATE_BOOLEAN)) ? 'checked' : (!$is_editing_avaliacao ? 'checked' : ''); ?>>
                                <label for="aprovado">Aprovar Imediatamente (Visível no site)</label>
                            </div>

                            <button type="submit" name="salvar_avaliacao" class="<?php echo $is_editing_avaliacao ? 'update' : ''; ?>" style="margin-top: 1.5rem;">
                                <?php echo $is_editing_avaliacao ? 'Salvar Edição' : 'Adicionar Avaliação'; ?>
                            </button>
                            <?php if ($is_editing_avaliacao): ?>
                                <a href="avaliacoes.php#tab-lista" class="cancel">Cancelar Edição</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>
        // --- JavaScript para Partículas ---
        particlesJS('particles-js', {"particles":{"number":{"value":60,"density":{"enable":true,"value_area":800}},"color":{"value":"#4a69bd"},"shape":{"type":"circle"},"opacity":{"value":0.4,"random":false},"size":{"value":3,"random":true},"line_linked":{"enable":true,"distance":150,"color":"#ffffff","opacity":0.1,"width":1},"move":{"enable":true,"speed":1.5,"direction":"none","random":false,"straight":false,"out_mode":"out","bounce":false}},"interactivity":{"detect_on":"canvas","events":{"onhover":{"enable":true,"mode":"repulse"},"onclick":{"enable":true,"mode":"push"},"resize":true}},"retina_detect":true});

        // --- LÓGICA ESPECÍFICA DA PÁGINA (TABS E ANCORAGEM) ---
        document.addEventListener('DOMContentLoaded', () => {

            // NOTE: A lógica de controle do Sidebar (menuToggle, acordeão) é assumida como estando
            // no admin_sidebar.php e, portanto, não é duplicada aqui.

            const urlParams = new URLSearchParams(window.location.search);
            const urlHash = window.location.hash;
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

            function activateTabFromHash(hash) {
                let targetPane = null;
                let elementToScroll = null;

                // 1. Se estiver editando, prioriza a aba de Adicionar/Editar
                if (urlParams.has('edit_avaliacao')) {
                    hash = '#tab-adicionar';
                }

                if (hash.startsWith('#tab-')) {
                    targetPane = document.querySelector(hash);
                    elementToScroll = targetPane;
                }

                if (targetPane) {
                    const targetTabButton = document.querySelector(`.tab-button[data-tab-target="#${targetPane.id}"]`);
                    if (targetTabButton) {
                        tabButtons.forEach(btn => btn.classList.remove('active'));
                        tabPanes.forEach(pane => pane.classList.remove('active'));
                        targetTabButton.classList.add('active');
                        targetPane.classList.add('active');
                    }
                } else {
                    // Padrão: Ativa a primeira aba (Moderação / Lista)
                    const firstTabButton = document.querySelector('.tab-navigation .tab-button:first-child');
                    const firstPane = document.querySelector('.tab-content-wrapper .tab-pane:first-child');
                    if (firstTabButton && firstPane) {
                        firstTabButton.classList.add('active');
                        firstPane.classList.add('active');
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

        });
    </script>
</body>
</html>