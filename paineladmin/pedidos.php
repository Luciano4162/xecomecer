<?php
// admin_panel/pedidos.php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

require_once '../config/db.php'; // Conexão com o DB
require_once '../funcoes.php'; // Para formatarDataHoraBR (no detalhe do pedido)
date_default_timezone_set('America/Sao_Paulo'); // Garante fuso horário

$message = '';
$message_type = ''; // success, error, info, warning
$edit_pedido = null;
$is_editing_pedido = false;
$is_viewing_details = false;

// Array de Status Permitidos
$status_options = [
    'PENDENTE' => 'Pendente',
    'AGUARDANDO PAGAMENTO' => 'Aguardando Pagamento',
    'APROVADO' => 'Aprovado',
    'PROCESSANDO' => 'Processando',
    'EM TRANSPORTE' => 'Em Transporte',
    'ENTREGUE' => 'Entregue',
    'CANCELADO' => 'Cancelado'
];

// ==========================================================
// LÓGICA CRUD PARA PEDIDOS
// ==========================================================

// 1P. ATUALIZAR STATUS DO PEDIDO (Formulário de Detalhes)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $pedido_id = filter_input(INPUT_POST, 'pedido_id', FILTER_VALIDATE_INT);
    $new_status = trim($_POST['status_novo'] ?? '');
    $new_status_upper = strtoupper($new_status);

    if ($pedido_id && array_key_exists($new_status_upper, $status_options)) {
        try {
            $sql = "UPDATE pedidos SET status = :status WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['status' => $new_status_upper, 'id' => $pedido_id]);

            $_SESSION['flash_message'] = "Status do Pedido #{$pedido_id} atualizado para '{$status_options[$new_status_upper]}'!";
            $_SESSION['flash_type'] = "success";
            header("Location: pedidos.php?view_pedido={$pedido_id}#edit-form"); // Redireciona de volta para a edição
            exit;

        } catch (PDOException $e) {
            $message = "Erro ao atualizar status: Verifique o log para detalhes.";
            $message_type = "error";
            error_log("Erro update_status pedido {$pedido_id}: " . $e->getMessage());
            $is_viewing_details = true;
            $_GET['view_pedido'] = $pedido_id; // Força recarregar a pág de detalhes
        }
    } else {
        $message = "Dados inválidos para atualizar status (ID: {$pedido_id}, Status: {$new_status}).";
        $message_type = "error";
        $is_viewing_details = isset($_POST['pedido_id']);
        if ($is_viewing_details) $_GET['view_pedido'] = $_POST['pedido_id'];
    }
}

// 2P. ATUALIZAR ENDEREÇO DO PEDIDO (Formulário de Detalhes)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_endereco'])) {
    $pedido_id = filter_input(INPUT_POST, 'pedido_id', FILTER_VALIDATE_INT);
    $endereco_id = filter_input(INPUT_POST, 'endereco_id', FILTER_VALIDATE_INT);
    $endereco = trim(filter_input(INPUT_POST, 'endereco', FILTER_SANITIZE_SPECIAL_CHARS));
    $numero = trim(filter_input(INPUT_POST, 'numero', FILTER_SANITIZE_SPECIAL_CHARS));
    $complemento = trim(filter_input(INPUT_POST, 'complemento', FILTER_SANITIZE_SPECIAL_CHARS));
    $bairro = trim(filter_input(INPUT_POST, 'bairro', FILTER_SANITIZE_SPECIAL_CHARS));
    $cidade = trim(filter_input(INPUT_POST, 'cidade', FILTER_SANITIZE_SPECIAL_CHARS));
    $estado = trim(filter_input(INPUT_POST, 'estado', FILTER_SANITIZE_SPECIAL_CHARS));
    $cep = trim(filter_input(INPUT_POST, 'cep', FILTER_SANITIZE_SPECIAL_CHARS));
    $destinatario = trim(filter_input(INPUT_POST, 'destinatario', FILTER_SANITIZE_SPECIAL_CHARS));

    if (!$pedido_id || !$endereco_id) {
          $message = "IDs inválidos para atualizar endereço."; $message_type = "error";
          $is_viewing_details = true; $_GET['view_pedido'] = $pedido_id;
    } elseif (empty($endereco) || empty($numero) || empty($cidade) || empty($cep) || empty($destinatario)) {
          $message = "Os campos Destinatário, CEP, Endereço, Número e Cidade são obrigatórios."; $message_type = "error";
          $is_viewing_details = true; $_GET['view_pedido'] = $pedido_id;
    } else {
          try {
              // Edita o endereço na tabela 'enderecos' (que é a base)
              $sql = "UPDATE enderecos SET endereco = :endereco, numero = :numero, complemento = :complemento, bairro = :bairro, cidade = :cidade, estado = :estado, cep = :cep, destinatario = :destinatario WHERE id = :id";
              $stmt = $pdo->prepare($sql);
              $stmt->execute([ 'endereco' => $endereco, 'numero' => $numero, 'complemento' => $complemento ?: null, 'bairro' => $bairro ?: null, 'cidade' => $cidade, 'estado' => $estado ?: null, 'cep' => $cep, 'destinatario' => $destinatario, 'id' => $endereco_id ]);

              // ATUALIZA os dados copiados na tabela 'pedidos' (recomendado)
              $sql_pedido_update = "UPDATE pedidos SET
                  endereco_destinatario = :destinatario, endereco_cep = :cep, endereco_logradouro = :endereco,
                  endereco_numero = :numero, endereco_complemento = :complemento, endereco_bairro = :bairro,
                  endereco_cidade = :cidade, endereco_estado = :estado
                  WHERE id = :pedido_id";
              $stmt_pedido_update = $pdo->prepare($sql_pedido_update);
              $stmt_pedido_update->execute([
                  'destinatario' => $destinatario, 'cep' => $cep, 'endereco' => $endereco,
                  'numero' => $numero, 'complemento' => $complemento ?: null, 'bairro' => $bairro ?: null,
                  'cidade' => $cidade, 'estado' => $estado ?: null, 'pedido_id' => $pedido_id
              ]);

              $_SESSION['flash_message'] = "Endereço do Pedido #{$pedido_id} atualizado!"; $_SESSION['flash_type'] = "success";
              header("Location: pedidos.php?view_pedido={$pedido_id}#edit-form"); exit;
          } catch (PDOException $e) {
              $message = "Erro ao atualizar endereço: Verifique o log."; $message_type = "error"; $is_viewing_details = true; $_GET['view_pedido'] = $pedido_id;
              error_log("Erro update_endereco pedido {$pedido_id}, endereco {$endereco_id}: " . $e->getMessage());
          }
    }
}

// 3P. CANCELAR PEDIDO (Ação da Lista)
if (isset($_GET['cancel_pedido'])) {
    $id = (int)$_GET['cancel_pedido'];
    try {
        $sql = "UPDATE pedidos SET status = 'CANCELADO' WHERE id = :id AND status NOT IN ('ENTREGUE', 'CANCELADO')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['flash_message'] = "Pedido #{$id} foi cancelado.";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Não foi possível cancelar o Pedido #{$id} (pode já estar finalizado ou cancelado).";
            $_SESSION['flash_type'] = "warning";
        }
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = "Erro ao cancelar pedido: " . $e->getMessage();
        $_SESSION['flash_type'] = "error";
    }
    header("Location: pedidos.php"); exit;
}

// ▼▼▼ INSERIR/ATUALIZAR RASTREIO E MUDAR STATUS ▼▼▼
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_rastreio'])) {
    $pedido_id = filter_input(INPUT_POST, 'pedido_id', FILTER_VALIDATE_INT);
    $transportadora = trim($_POST['transportadora_nome'] ?? '');
    $codigo_rastreio = trim($_POST['codigo_rastreio'] ?? '');
    $link_rastreio = trim($_POST['link_rastreio'] ?? '');
    $data_envio = trim($_POST['data_envio'] ?? date('Y-m-d')); // Usa a data atual como fallback

    if (!$pedido_id) {
        $message = "ID do pedido inválido para rastreio."; $message_type = "error";
    } elseif (empty($transportadora) || empty($codigo_rastreio)) {
        $message = "Transportadora e Código de Rastreio são obrigatórios para marcar como 'Em Transporte'."; $message_type = "error";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. UPSERT (Insert OR Update) na tabela rastreio
            $sql_rastreio = "INSERT INTO rastreio (pedido_id, transportadora_nome, codigo_rastreio, link_rastreio, data_envio)
                             VALUES (:pedido_id, :transportadora, :codigo, :link, :data_envio)
                             ON CONFLICT (pedido_id) DO UPDATE
                             SET transportadora_nome = EXCLUDED.transportadora_nome,
                                 codigo_rastreio = EXCLUDED.codigo_rastreio,
                                 link_rastreio = EXCLUDED.link_rastreio,
                                 data_envio = EXCLUDED.data_envio";
            $stmt_rastreio = $pdo->prepare($sql_rastreio);
            $stmt_rastreio->execute([
                ':pedido_id' => $pedido_id,
                ':transportadora' => $transportadora,
                ':codigo' => $codigo_rastreio,
                ':link' => !empty($link_rastreio) ? $link_rastreio : null,
                ':data_envio' => $data_envio // Salva a data informada
            ]);

            // 2. ATUALIZAR STATUS do pedido para 'EM TRANSPORTE'
            $sql_status = "UPDATE pedidos SET status = 'EM TRANSPORTE' WHERE id = :id AND status NOT IN ('ENTREGUE', 'CANCELADO')";
            $stmt_status = $pdo->prepare($sql_status);
            $stmt_status->execute([':id' => $pedido_id]);

            $pdo->commit();
            $_SESSION['flash_message'] = "Rastreio cadastrado e Pedido #{$pedido_id} movido para 'EM TRANSPORTE'!";
            $_SESSION['flash_type'] = "success";
            header("Location: pedidos.php?view_pedido={$pedido_id}#rastreio-form");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "Erro ao salvar rastreio: Verifique o log."; $message_type = "error";
            error_log("Erro salvar_rastreio pedido {$pedido_id}: " . $e->getMessage());
        }
    }
    // Força recarregar os detalhes em caso de erro
    $is_viewing_details = true; $_GET['view_pedido'] = $pedido_id;
}
// ▲▲▲ FIM DO NOVO BLOCO PHP DE RASTREIO ▲▲▲


// Verifica mensagens flash da sessão (após redirecionamentos)
if (isset($_SESSION['flash_message']) && empty($message)) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// 4P. MODO DE VISUALIZAÇÃO DE DETALHES
if (isset($_GET['view_pedido'])) {
    $id = filter_input(INPUT_GET, 'view_pedido', FILTER_VALIDATE_INT);
    if ($id) {
        $is_viewing_details = true; // Flag para mostrar a view de detalhes
        try {
            $stmt = $pdo->prepare("
                SELECT
                    p.*, u.nome AS nome_usuario, u.email AS email_usuario,
                    r.transportadora_nome, r.codigo_rastreio, r.link_rastreio, r.data_envio
                FROM pedidos p
                LEFT JOIN usuarios u ON p.usuario_id = u.id
                LEFT JOIN rastreio r ON p.id = r.pedido_id
                WHERE p.id = :id
            ");
            $stmt->execute(['id' => $id]);
            $edit_pedido = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($edit_pedido) {
                // Busca Itens
                $stmt_itens = $pdo->prepare("
                    SELECT ip.quantidade, ip.preco_unitario, (ip.preco_unitario * ip.quantidade) AS preco_total_calculado,
                            prod.nome AS nome_produto, prod.imagem_url
                    FROM pedidos_itens ip
                    JOIN produtos prod ON ip.produto_id = prod.id
                    WHERE ip.pedido_id = :pedido_id
                ");
                $stmt_itens->execute(['pedido_id' => $id]);
                $itens_pedido = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);
                $edit_pedido['itens'] = $itens_pedido;
            } else {
                $is_viewing_details = false;
                $message = "Pedido #{$id} não encontrado.";
                $message_type = "warning";
            }
        } catch (PDOException $e) {
              $is_viewing_details = false;
              $message = "Erro ao carregar detalhes do pedido: Verifique o log.";
              $message_type = "error";
              error_log("Erro view_pedido {$id}: " . $e->getMessage());
        }
    } else {
        header("Location: pedidos.php");
        exit;
    }
}


// --- LEITURA DE DADOS (LISTA PRINCIPAL E CARDS) ---
$all_pedidos = [];
$total_records = 0; // CORREÇÃO: Inicializa a variável para evitar Warning em caso de erro.
$stats = [ // Inicializa stats para evitar erros se a query falhar
    'pendentes' => 0,
    'aprovados_hoje' => 0,
    'em_transporte' => 0,
    'cancelados' => 0,
    'faturamento_mes' => 0.00
];

try {
    // Estatísticas ATUALIZADAS (Funil)
    $hoje = date('Y-m-d');
    $primeiro_dia_mes = date('Y-m-01');

    // Consulta única para otimizar e incluir Cancelados e EM TRANSPORTE
    $stmt_stats = $pdo->prepare("
        SELECT
            SUM(CASE WHEN status IN ('PENDENTE', 'AGUARDANDO PAGAMENTO') THEN 1 ELSE 0 END) AS pendentes,
            SUM(CASE WHEN status IN ('APROVADO', 'ENTREGUE') AND DATE(criado_em) = :hoje THEN 1 ELSE 0 END) AS aprovados_hoje,
            SUM(CASE WHEN status = 'EM TRANSPORTE' THEN 1 ELSE 0 END) AS em_transporte,
            SUM(CASE WHEN status = 'CANCELADO' THEN 1 ELSE 0 END) AS cancelados,
            SUM(CASE WHEN status IN ('APROVADO', 'PROCESSANDO', 'EM TRANSPORTE', 'ENTREGUE') AND criado_em >= :primeiro_dia_mes THEN valor_total ELSE 0 END) AS faturamento_mes
        FROM pedidos
    ");
    $stmt_stats->execute([':hoje' => $hoje, ':primeiro_dia_mes' => $primeiro_dia_mes]);
    // Sobrescreve $stats apenas se a query for bem-sucedida
    $stats_data = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    if($stats_data) {
        $stats = $stats_data;
    }

    // Filtros
    $filter_id = preg_replace('/[^0-9]/', '', $_GET['filter_id'] ?? '');
    $filter_client = filter_input(INPUT_GET, 'filter_client', FILTER_SANITIZE_SPECIAL_CHARS);
    $filter_status = filter_input(INPUT_GET, 'filter_status', FILTER_SANITIZE_SPECIAL_CHARS);
    $filter_produto = filter_input(INPUT_GET, 'filter_produto', FILTER_SANITIZE_SPECIAL_CHARS);
    $filter_marca = filter_input(INPUT_GET, 'filter_marca', FILTER_SANITIZE_SPECIAL_CHARS);

    // PAGINAÇÃO
    $pedidos_per_page = 20;
    $current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
    $offset = ($current_page - 1) * $pedidos_per_page;

    $sql_where = [];
    $sql_params = [];

    if (!empty($filter_id)) { $sql_where[] = "p.id = :id"; $sql_params[':id'] = $filter_id; }
    if (!empty($filter_client)) { $sql_where[] = "(u.nome ILIKE :client OR u.email ILIKE :client)"; $sql_params[':client'] = '%' . $filter_client . '%'; }
    if (!empty($filter_status) && array_key_exists(strtoupper($filter_status), $status_options)) { $sql_where[] = "p.status = :status"; $sql_params[':status'] = strtoupper($filter_status); }

    // ==========================================================
    // CORREÇÃO: Filtro Produto/Marca com JOIN na tabela MARCAS
    // ==========================================================
    if (!empty($filter_produto) || !empty($filter_marca)) {

        $sub_query_where = [];
        $sub_query_sql = "p.id IN (SELECT ip.pedido_id FROM pedidos_itens ip
                                JOIN produtos pr ON ip.produto_id = pr.id
                                LEFT JOIN marcas m ON pr.marca_id = m.id";

        if (!empty($filter_produto)) {
            $sub_query_where[] = "pr.nome ILIKE :produto_nome";
            $sql_params[':produto_nome'] = '%' . $filter_produto . '%';
        }
        if (!empty($filter_marca)) {
            // CORRIGIDO: de pr.marca para m.nome
            $sub_query_where[] = "m.nome ILIKE :marca_nome";
            $sql_params[':marca_nome'] = '%' . $filter_marca . '%';
        }

        $sub_query_sql .= " WHERE " . implode(" AND ", $sub_query_where) . ")";
        $sql_where[] = $sub_query_sql;
    }
    // ==========================================================
    // FIM DA CORREÇÃO
    // ==========================================================


    $where_clause = !empty($sql_where) ? "WHERE " . implode(" AND ", $sql_where) : '';

    // Conta o total de registros para a paginação (com os filtros aplicados)
    $sql_count = "SELECT COUNT(p.id)
                  FROM pedidos p
                  LEFT JOIN usuarios u ON p.usuario_id = u.id
                  {$where_clause}";

    $stmt_count = $pdo->prepare($sql_count);

    $final_count_params = [];
    foreach($sql_params as $key => $value) {
        if ($key !== ':limit' && $key !== ':offset') {
            $final_count_params[$key] = $value;
        }
    }

    $stmt_count->execute($final_count_params);
    $total_records = $stmt_count->fetchColumn();
    $total_pages = ceil($total_records / $pedidos_per_page);


    // Query da Lista Principal com PAGINAÇÃO
    $sql = "SELECT p.id, p.valor_total, p.status, p.criado_em, COALESCE(u.nome, 'N/A') AS nome_usuario, p.valor_frete, p.valor_subtotal
             FROM pedidos p LEFT JOIN usuarios u ON p.usuario_id = u.id
             {$where_clause}
             ORDER BY p.criado_em DESC
             LIMIT :limit OFFSET :offset";

    $sql_params[':limit'] = $pedidos_per_page;
    $sql_params[':offset'] = $offset;

    $stmt_pedidos = $pdo->prepare($sql);
    $stmt_pedidos->execute($sql_params);
    $all_pedidos = $stmt_pedidos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    if (empty($message)) {
        $message .= " Erro ao carregar dados: Verifique o log.";
        $message_type = "error";
    }
    error_log("Erro listagem/stats pedidos: " . $e->getMessage());
}

// --- Funções Helper ---
function get_status_class($status) { $s = strtoupper($status ?? ''); if (in_array($s, ['APROVADO', 'ENTREGUE'])) return 'status-aprovado'; if (in_array($s, ['PENDENTE', 'AGUARDANDO PAGAMENTO'])) return 'status-pendente'; if (in_array($s, ['EM TRANSPORTE', 'PROCESSANDO'])) return 'status-processando'; if ($s === 'CANCELADO') return 'status-cancelado'; return 'status-inativo'; }
function formatCurrency($value) { $numericValue = filter_var($value, FILTER_VALIDATE_FLOAT); if ($numericValue === false) { $numericValue = 0.00; } return 'R$ ' . number_format($numericValue, 2, ',', '.'); }
// --- Fim Funções Helper ---

$current_page_file = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Pedidos - Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <style>
        /* ==========================================================
            CSS COMPLETO DO PAINEL ADMIN (Base no Produtos.php)
            + Estilos de pedidos.php
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
              /* Status Específicos */
              --status-aprovado-rgb: 40, 167, 69; --status-pendente-rgb: 255, 193, 7;
              --status-cancelado-rgb: 220, 53, 69; --status-processando-rgb: 23, 162, 184; --status-inativo-rgb: 108, 117, 125;
              --status-aprovado: #28a745; --status-pendente: #ffc107; --status-cancelado: #dc3545; --status-processando: #17a2b8; --status-inativo: #6c757d;
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
          .main-content {
              margin-left: var(--sidebar-width);
              flex-grow: 1;
              padding: 2rem 2.5rem;
              min-height: 100vh;
              overflow-y: auto;
              transition: margin-left 0.3s ease, width 0.3s ease;
              width: calc(100% - var(--sidebar-width));
          }
          .content-header { margin-bottom: 2rem; background: var(--glass-background); padding: 1.5rem 2rem; border-radius: var(--border-radius); border: 1px solid var(--border-color); backdrop-filter: blur(5px); }
          .content-header h1 { font-size: 1.8rem; font-weight: 600; color: var(--primary-color); margin: 0 0 0.25rem 0; }
          .content-header p { font-size: 1rem; color: var(--light-text-color); margin: 0; }

          /* --- Seções CRUD --- */
          .crud-section { margin-bottom: 2.5rem; }
          .crud-section h3 { font-size: 1.25rem; color: var(--text-color); margin-bottom: 1.25rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color); font-weight: 600; }
          .form-container, .list-container, .detail-container, .filter-container { background: var(--glass-background); padding: 1.5rem 2rem; border-radius: var(--border-radius); border: 1px solid var(--border-color); box-shadow: var(--box-shadow); margin-bottom: 2rem; backdrop-filter: blur(5px); }

          /* --- Estilos Detalhes do Pedido (Específico pedidos.php) --- */
          .detail-container h4 { font-size: 1.1rem; color: var(--primary-color); margin: 1.5rem 0 1rem 0; font-weight: 600; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; }
          .detail-container h4:first-of-type { margin-top: 0; }
          .info-card { background-color: rgba(0,0,0, 0.15); padding: 1rem 1.5rem; border-radius: var(--border-radius); border: 1px solid rgba(255, 255, 255, 0.08); height: 100%; display: flex; flex-direction: column;}
          .detail-info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem; }
          .detail-block h5 { color: var(--primary-color); font-size: 0.9rem; margin-bottom: 0.5rem; text-transform: uppercase; font-weight: 600;}
          .detail-block p { font-size: 0.9rem; color: var(--light-text-color); margin: 0; line-height: 1.6;}
          .detail-block p strong { color: var(--text-color); font-weight: 500;}
          .detail-block span[class^="status-"] { display: inline-block; padding: 0.2em 0.6em; border-radius: 1em; font-size: 0.8em; font-weight: 600; }
          .detail-total { color: var(--status-aprovado); font-weight: 600; font-size: 1rem;}
          .address-card { background-color: rgba(0,0,0, 0.15); padding: 0; border-radius: var(--border-radius); border: 1px solid rgba(255, 255, 255, 0.08); margin-bottom: 1.5rem; overflow: hidden; }
          .address-display { padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; border-bottom: 1px solid transparent; transition: border-color 0.4s ease-out; }
          .address-card.editing .address-display { border-bottom-color: var(--border-color); }
          .address-display p { font-size: 0.9rem; line-height: 1.6; color: var(--light-text-color); margin: 0;}
          .address-display p strong { color: var(--text-color); font-weight: 600; display: block; margin-bottom: 0.25rem;}
          .address-display .btn-toggle-edit { background-color: var(--secondary-color); color: var(--text-color); padding: 0.4rem 0.8rem; border-radius: var(--border-radius); font-size: 0.8em; cursor: pointer; border: none; transition: background-color 0.2s; white-space: nowrap;}
          .address-display .btn-toggle-edit:hover { background-color: var(--primary-color); }
          #endereco-edit-form { max-height: 0; overflow: hidden; transition: max-height 0.4s ease-out, padding 0.4s ease-out; padding: 0 1.5rem; background: rgba(0, 0, 0, 0.1); }
          #endereco-edit-form.open { max-height: 700px; padding: 1.5rem; }
          #rastreio-edit-form { background: rgba(0, 0, 0, 0.1); padding: 1.5rem; border-radius: var(--border-radius); margin-top: 1.5rem;}
          .rastreio-info p { margin-bottom: 0.5rem; }
          .rastreio-info a { font-weight: 600; text-decoration: underline; }
          .rastreio-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1rem;}
          .item-list-table { margin: 0; width: 100%; border: none; min-width: auto; }
          .item-list-table img { width: 40px; height: 40px; object-fit: contain; vertical-align: middle; background-color: rgba(255,255,255,0.1); border-radius: 4px; margin-right: 10px;}
          .item-list-table td:first-child { display: flex; align-items: center; gap: 10px; }
          .item-list-table th, .item-list-table td { padding: 0.75rem 1rem; border-radius: 0; }
          .item-list-table tbody tr:last-child td { border-bottom: none;}


          /* --- Formulários --- */
          .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem 1.5rem; }
          .form-group { margin-bottom: 1rem; }
          .form-group label { display: block; margin-bottom: 0.5rem; font-size: 0.85em; font-weight: 500; color: var(--light-text-color); text-transform: uppercase; }
          .form-group input[type="text"], .form-group input[type="email"], .form-group input[type="date"], .form-group input[type="number"], .form-group textarea, .form-group input[type="url"], .form-group select { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); background-color: rgba(0, 0, 0, 0.2); color: var(--text-color); box-sizing: border-box; font-size: 0.9em; transition: border-color 0.3s ease, box-shadow 0.3s ease; }
          .form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 2px rgba(74, 105, 189, 0.4); }
          .form-group textarea { resize: vertical; min-height: 80px; }
          .form-group input[type="date"] { color-scheme: dark; }

          .form-actions { display: flex; gap: 1rem; margin-top: 1.5rem; justify-content: flex-end; flex-wrap: wrap; }
          .btn { padding: 0.65rem 1.2rem; background-color: var(--primary-color); color: #fff; border: none; border-radius: var(--border-radius); cursor: pointer; font-weight: 600; transition: all 0.3s ease; font-size: 0.9em; display: inline-block; text-decoration: none; }
          .btn:hover { background-color: var(--secondary-color); transform: translateY(-1px); text-decoration: none; color: #fff;}
          .btn.update { background-color: #28a745; }
          .btn.update:hover { background-color: #218838; }
          .btn.cancel { background-color: var(--light-text-color); color: var(--background-color) !important; }
          .btn.cancel:hover { background-color: #bbb; }
          .btn.danger { background-color: var(--danger-color); }
          .btn.danger:hover { background-color: var(--danger-color-hover); }

          .filter-container .form-grid { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); align-items: flex-end; }
          .filter-container .form-actions { margin-top: 0; }

          /* --- Tabelas --- */
          .list-container { overflow-x: auto; -webkit-overflow-scrolling: touch; }
          .list-container table { width: 100%; border-collapse: collapse; margin-top: 1rem; background-color: var(--glass-background); border-radius: var(--border-radius); overflow: hidden; border: 1px solid var(--border-color); min-width: 800px; }
          .list-container th, .list-container td { padding: 0.9rem 1rem; text-align: left; border-bottom: 1px solid var(--border-color); font-size: 0.85em; }
          .list-container th { background-color: rgba(0,0,0,0.2); font-weight: 600; color: var(--light-text-color); text-transform: uppercase; letter-spacing: 0.5px; }
          .list-container td { color: var(--text-color); }
          .list-container tbody tr:last-child td { border-bottom: none; }
          .list-container tbody tr:hover { background-color: rgba(255, 255, 255, 0.03); }
          .list-container td.actions { white-space: nowrap; text-align: right; }
          .list-container .actions a, .list-container .actions button { color: var(--primary-color); text-decoration: none; margin-left: 1rem; font-weight: 500; background: none; border: none; font-size: 1em; font-family: inherit; cursor: pointer; }
          .list-container .actions a:hover, .list-container .actions button:hover { color: var(--secondary-color); text-decoration: none;}
          .list-container .actions .action-icon { color: var(--light-text-color); text-decoration: none; font-size: 0.9em;}
          .list-container .actions .action-icon:hover { color: var(--primary-color); }
          .list-container .actions .action-icon.delete:hover, .list-container .actions .action-icon.danger:hover { color: var(--danger-color); }
          .list-container .actions .action-icon svg { width: 18px; height: 18px; vertical-align: middle; }

          span[class^="status-"] { padding: 0.3em 0.7em; border-radius: 1em; font-size: 0.75em; font-weight: 600; text-transform: uppercase; white-space: nowrap; }
          .status-aprovado { background-color: rgba(var(--status-aprovado-rgb), 0.15); color: var(--status-aprovado); }
          .status-pendente { background-color: rgba(var(--status-pendente-rgb), 0.15); color: var(--status-pendente); }
          .status-cancelado { background-color: rgba(var(--status-cancelado-rgb), 0.15); color: var(--status-cancelado); }
          .status-processando { background-color: rgba(var(--status-processando-rgb), 0.15); color: var(--status-processando); }
          .status-inativo { background-color: rgba(var(--status-inativo-rgb), 0.15); color: var(--status-inativo); }

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

          /* --- NOVO: Funil de Vendas Visual --- */
          .funnel-report-wrapper { display: flex; gap: 2.5rem; margin-bottom: 2.5rem; }
          .funnel-visual-container { display: flex; flex-direction: column; align-items: center; position: relative; padding-top: 50px; flex-basis: 50%; min-width: 350px; }
          .funnel-stage-visual {
              width: 90%; max-width: 600px; height: 60px; margin-top: -10px; position: relative;
              display: flex; justify-content: center; align-items: center;
              color: var(--text-color); font-weight: 600; font-size: 1rem;
              padding: 0 1rem; text-align: center; cursor: default;
              transition: transform 0.3s ease, box-shadow 0.3s ease;
              border-left: 2px solid rgba(255, 255, 255, 0.1); border-right: 2px solid rgba(255, 255, 255, 0.1);
              clip-path: polygon(0% 0%, 100% 0%, 90% 100%, 10% 100%);
              background-color: var(--glass-background);
          }
          /* 5 Fatias */
          .funnel-stage-visual:nth-child(1) { width: 95%; max-width: 700px; background-color: rgba(76, 175, 80, 0.8); z-index: 5;}
          .funnel-stage-visual:nth-child(2) { width: 85%; max-width: 600px; background-color: rgba(23, 162, 184, 0.8); z-index: 4;}
          .funnel-stage-visual:nth-child(3) { width: 75%; max-width: 500px; background-color: rgba(0, 150, 136, 0.8); z-index: 3;}
          .funnel-stage-visual:nth-child(4) { width: 65%; max-width: 400px; background-color: rgba(255, 152, 0, 0.8); z-index: 2;}
          .funnel-stage-visual:nth-child(5) { width: 55%; max-width: 300px; background-color: rgba(244, 67, 54, 0.8); z-index: 1;}

          .funnel-stage-visual:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3); z-index: 6; }
          .funnel-label-top {
              position: absolute; top: -30px; font-size: 0.9em; font-weight: 500;
              color: var(--light-text-color); text-transform: uppercase; white-space: nowrap;
          }
          .funnel-stage-visual:nth-child(1) .funnel-label-top { color: var(--status-aprovado); }
          .funnel-stage-visual:nth-child(2) .funnel-label-top { color: var(--status-processando); }
          .funnel-stage-visual:nth-child(3) .funnel-label-top { color: var(--status-pendente); }
          .funnel-stage-visual:nth-child(5) .funnel-label-top { color: var(--status-cancelado); }
          .funnel-value { font-size: 1.5rem; font-weight: 700; margin-right: 10px; }

          /* Legenda do Funil */
          .funnel-legend { flex-basis: 50%; background: var(--glass-background); padding: 1.5rem; border-radius: var(--border-radius); border: 1px solid var(--border-color); }
          .funnel-legend h4 { color: var(--primary-color); font-size: 1.1rem; margin-bottom: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; }
          .funnel-legend ul { list-style: none; padding-left: 0; }
          .funnel-legend ul li { margin-bottom: 0.75rem; font-size: 0.9em; color: var(--light-text-color); display: flex; align-items: flex-start; }
          .legend-dot { width: 10px; height: 10px; border-radius: 50%; margin-right: 10px; flex-shrink: 0; margin-top: 5px; }
          .legend-dot.aprovado { background-color: rgba(76, 175, 80, 1); }
          .legend-dot.transporte { background-color: rgba(23, 162, 184, 1); }
          .legend-dot.pendente { background-color: rgba(0, 150, 136, 1); }
          .legend-dot.faturamento { background-color: rgba(255, 152, 0, 1); }
          .legend-dot.cancelado { background-color: rgba(244, 67, 54, 1); }


          /* --- Mobile / Responsivo (Layout Base do produtos.php) --- */
          .menu-toggle { display: none; position: fixed; top: 1rem; left: 1rem; z-index: 1003; cursor: pointer; padding: 8px; background-color: var(--sidebar-color); border-radius: var(--border-radius); border: 1px solid var(--border-color); box-shadow: var(--box-shadow);}
          .menu-toggle svg { width: 20px; height: 20px; color: var(--text-color); display: block; }

          @media (max-width: 1024px) {
              body { position: relative; }
              .sidebar { width: 280px; transform: translateX(-280px); box-shadow: 5px 0 15px rgba(0, 0, 0, 0.5); z-index: 1002; height: 100%; overflow-y: auto; }
              .menu-toggle { display: flex; }
              .main-content { margin-left: 0; width: 100%; padding: 1.5rem; padding-top: 5rem; min-width: unset; }
              body.sidebar-open .sidebar { transform: translateX(0); }
              body.sidebar-open::after { content: ''; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.7); z-index: 1001; backdrop-filter: blur(2px);}

              /* Responsividade do Funil */
              .funnel-report-wrapper { flex-direction: column; gap: 1rem; }
              .funnel-visual-container { flex-basis: 100%; min-width: unset; padding-top: 0; }
              .funnel-legend { flex-basis: 100%; }
              /* Transforma fatias em blocos retangulares */
              .funnel-stage-visual {
                  clip-path: none;
                  width: 100% !important;
                  max-width: 100% !important;
                  margin-top: 10px;
                  height: auto; /* Altura automática */
                  min-height: 50px;
                  border-radius: var(--border-radius);
                  border: 1px solid var(--border-color);
                  justify-content: space-between;
                  padding: 0.75rem 1rem;
              }
              .funnel-stage-visual:first-child { margin-top: 0; }
              .funnel-label-top { display: none; }
              /* Exibe a etiqueta antes do valor */
              .funnel-stage-visual::before {
                  content: attr(data-label);
                  font-size: 0.85em;
                  font-weight: 500;
                  color: var(--text-color);
                  text-transform: uppercase;
              }
              .funnel-stage-visual .funnel-value { font-size: 1.2rem; margin-right: 0; }
              .funnel-stage-visual > div { display: flex; align-items: center; } /* Agrupa valor e "Pedidos" */
              .funnel-stage-visual > div > span:last-child { margin-left: 5px; font-size: 0.8em; }
          }

          @media (max-width: 768px) {
              /* Responsividade da Lista */
              .list-container table { border: none; min-width: 100%; display: block; }
              .list-container thead { display: none; }
              .list-container tr { display: block; margin-bottom: 1rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 1rem; background: rgba(0,0,0,0.1); }
              .list-container td { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: none; text-align: right; }
              .list-container td::before { content: attr(data-label); font-weight: 600; color: var(--light-text-color); text-align: left; margin-right: 1rem; flex-basis: 40%;}
              .list-container td.actions { justify-content: flex-end; }
              .list-container td.actions::before { display: none; }

              /* Responsividade dos Detalhes */
              .detail-info-grid { grid-template-columns: 1fr; }
              .filter-container .form-grid { grid-template-columns: 1fr; }
              .filter-container .form-actions { justify-content: flex-start; }
              .rastreio-info-grid { grid-template-columns: 1fr; }
          }

          @media (max-width: 576px) {
              .main-content { padding: 1rem; padding-top: 4.5rem; }
              .content-header { padding: 1rem 1.5rem;}
              .content-header h1 { font-size: 1.5rem; }
              .content-header p { font-size: 0.9rem;}
              .form-container, .list-container, .detail-container, .filter-container { padding: 1rem 1.5rem;}
              .crud-section h3 { font-size: 1.1rem;}
              .form-container h4, .detail-container h4 { font-size: 1rem;}
              .list-container td, .list-container td::before { font-size: 0.8em; }

              /* Responsividade Detalhes */
              .address-display { flex-direction: column; gap: 0.75rem; align-items: flex-start;}
              .address-display button { margin-top: 0.5rem;}
              .item-list-table th, .item-list-table td { padding: 0.5rem 1rem;}

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
                <h4 id="modal-title">Confirmar Ação</h4>
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
            <h1>Gerenciar Pedidos</h1>
            <p>Visualize, filtre e edite o status e endereço dos pedidos.</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo nl2br(htmlspecialchars($message)); ?></div>
        <?php endif; ?>

        <?php if ($is_viewing_details && $edit_pedido): ?>
        <div class="crud-section" id="edit-form">
            <h3>Detalhes do Pedido #<?php echo $edit_pedido['id']; ?></h3>
            <div class="detail-container">
                <h4>Informações Gerais</h4>
                <div class="detail-info-grid">
                    <div class="info-card">
                        <div class="detail-block">
                            <h5>Cliente</h5>
                            <p><strong>Nome:</strong> <a href="usuarios.php?view_user=<?php echo $edit_pedido['usuario_id']; ?>" title="Ver perfil do usuário"><?php echo htmlspecialchars($edit_pedido['nome_usuario'] ?? 'N/A'); ?></a><br>
                                <strong>Email:</strong> <?php echo htmlspecialchars($edit_pedido['email_usuario'] ?? 'N/A'); ?><br>
                                <strong>Data:</strong> <?php echo date('d/m/Y H:i', strtotime($edit_pedido['criado_em'])); ?>
                            </p>
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="detail-block">
                            <h5>Valores</h5>
                            <p><strong>Subtotal:</strong> <?php echo formatCurrency($edit_pedido['valor_subtotal']); ?><br>
                                <strong>Frete:</strong> <?php echo formatCurrency($edit_pedido['valor_frete']); ?> (<?php echo htmlspecialchars($edit_pedido['envio_nome'] ?? 'N/A'); ?>)<br>
                               <strong class="detail-total">Total: <?php echo formatCurrency($edit_pedido['valor_total']); ?></strong>
                            </p>
                        </div>
                    </div>
                    <div class="info-card">
                           <div class="detail-block">
                                <h5>Pagamento / Status</h5>
                                <p><strong>Método:</strong> <?php echo htmlspecialchars($edit_pedido['pag_nome'] ?? 'N/A'); ?><br>
                                   <strong>Status Atual:</strong> <span class="<?php echo get_status_class($edit_pedido['status']); ?>"><?php echo htmlspecialchars($edit_pedido['status'] ?? 'N/A'); ?></span>
                                </p>
                           </div>
                    </div>
                </div>

                <h4>Endereço de Entrega</h4>
                <div class="address-card" id="address-card">
                    <div class="address-display">
                        <p>
                            <strong><?php echo htmlspecialchars($edit_pedido['endereco_destinatario'] ?? 'N/A'); ?></strong><br>
                            <?php echo htmlspecialchars($edit_pedido['endereco_logradouro'] ?? 'N/A'); ?>, <?php echo htmlspecialchars($edit_pedido['endereco_numero'] ?? 'S/N'); ?> <?php echo $edit_pedido['endereco_complemento'] ? ' - '.htmlspecialchars($edit_pedido['endereco_complemento']) : ''; ?><br>
                            <?php echo htmlspecialchars($edit_pedido['endereco_bairro'] ?? 'N/A'); ?>, <?php echo htmlspecialchars($edit_pedido['endereco_cidade'] ?? 'N/A'); ?> - <?php echo htmlspecialchars($edit_pedido['endereco_estado'] ?? 'N/A'); ?><br>
                            CEP: <?php echo htmlspecialchars($edit_pedido['endereco_cep'] ?? 'N/A'); ?>
                        </p>
                        <button type="button" class="btn-toggle-edit" onclick="toggleEditAddress()">Editar Endereço</button>
                    </div>
                    <div id="endereco-edit-form">
                        <?php
                            $endereco_original_para_edicao = null;
                            if ($edit_pedido['endereco_id']) {
                                $stmt_addr_orig = $pdo->prepare("SELECT * FROM enderecos WHERE id = ?");
                                $stmt_addr_orig->execute([$edit_pedido['endereco_id']]);
                                $endereco_original_para_edicao = $stmt_addr_orig->fetch(PDO::FETCH_ASSOC);
                            }
                        ?>
                        <?php if($endereco_original_para_edicao): ?>
                        <form action="pedidos.php?view_pedido=<?php echo $edit_pedido['id']; ?>#edit-form" method="POST">
                            <input type="hidden" name="update_endereco" value="1">
                            <input type="hidden" name="pedido_id" value="<?php echo $edit_pedido['id']; ?>">
                            <input type="hidden" name="endereco_id" value="<?php echo $edit_pedido['endereco_id']; ?>">

                            <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
                                <div class="form-group" style="grid-column: 1 / -1;"> <label for="destinatario">Destinatário</label> <input type="text" id="destinatario" name="destinatario" value="<?php echo htmlspecialchars($endereco_original_para_edicao['destinatario'] ?? ''); ?>" required> </div>
                                <div class="form-group"> <label for="cep">CEP</label> <input type="text" id="cep" name="cep" value="<?php echo htmlspecialchars($endereco_original_para_edicao['cep'] ?? ''); ?>" required> </div>
                                <div class="form-group" style="grid-column: span 2;"> <label for="endereco">Rua/Avenida</label> <input type="text" id="endereco" name="endereco" value="<?php echo htmlspecialchars($endereco_original_para_edicao['endereco'] ?? ''); ?>" required> </div>
                                <div class="form-group"> <label for="numero">Número</label> <input type="text" id="numero" name="numero" value="<?php echo htmlspecialchars($endereco_original_para_edicao['numero'] ?? ''); ?>" required> </div>
                                <div class="form-group"> <label for="complemento">Complemento</label> <input type="text" id="complemento" name="complemento" value="<?php echo htmlspecialchars($endereco_original_para_edicao['complemento'] ?? ''); ?>"> </div>
                                <div class="form-group"> <label for="bairro">Bairro</label> <input type="text" id="bairro" name="bairro" value="<?php echo htmlspecialchars($endereco_original_para_edicao['bairro'] ?? ''); ?>"> </div>
                                <div class="form-group"> <label for="cidade">Cidade</label> <input type="text" id="cidade" name="cidade" value="<?php echo htmlspecialchars($endereco_original_para_edicao['cidade'] ?? ''); ?>" required> </div>
                                <div class="form-group"> <label for="estado">Estado (UF)</label> <input type="text" id="estado" name="estado" value="<?php echo htmlspecialchars($endereco_original_para_edicao['estado'] ?? ''); ?>" required maxlength="2"> </div>
                            </div>
                            <div class="form-actions"> <button type="submit" class="btn update">Salvar Endereço</button> <button type="button" class="btn cancel" onclick="toggleEditAddress()">Cancelar</button> </div>
                        </form>
                        <?php else: ?>
                            <p style="padding: 1.5rem; color: var(--warning-text);">O endereço original (ID: <?php echo $edit_pedido['endereco_id']; ?>) não foi encontrado na tabela 'enderecos' e não pode ser editado.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <h4>Rastreio e Envio</h4>
                <div class="form-container" id="rastreio-form">

                    <?php if ($edit_pedido['status'] === 'PENDENTE' || $edit_pedido['status'] === 'AGUARDANDO PAGAMENTO'): ?>
                        <div class="message warning" style="margin-bottom: 0;">O envio só pode ser cadastrado após o status ser alterado para **APROVADO** ou **PROCESSANDO**.</div>
                    <?php else: ?>

                        <?php
                            $is_sent = in_array(strtoupper($edit_pedido['status'] ?? ''), ['EM TRANSPORTE', 'ENTREGUE']);
                            $rastreio_exists = !empty($edit_pedido['codigo_rastreio']);
                        ?>

                        <?php if ($rastreio_exists): ?>
                            <div class="rastreio-info" style="margin-bottom: 1.5rem;">
                                <p><strong>Cadastrado:</strong> <?php echo date('d/m/Y', strtotime($edit_pedido['data_envio'])); ?></p>
                                <p><strong>Transportadora:</strong> <?php echo htmlspecialchars($edit_pedido['transportadora_nome']); ?></p>
                                <p><strong>Código:</strong> <?php echo htmlspecialchars($edit_pedido['codigo_rastreio']); ?></p>
                                <?php if (!empty($edit_pedido['link_rastreio'])): ?>
                                    <p><strong>Link:</strong> <a href="<?php echo htmlspecialchars($edit_pedido['link_rastreio']); ?>" target="_blank"><?php echo htmlspecialchars($edit_pedido['link_rastreio']); ?></a></p>
                                <?php endif; ?>
                                <div class="message info" style="margin-top: 1rem;">O status do pedido está como <strong><?php echo htmlspecialchars($edit_pedido['status']); ?></strong>. Se os dados de rastreio estiverem corretos, não é necessário atualizar.</div>
                            </div>
                        <?php endif; ?>

                        <form action="pedidos.php?view_pedido=<?php echo $edit_pedido['id']; ?>#rastreio-form" method="POST">
                            <input type="hidden" name="salvar_rastreio" value="1">
                            <input type="hidden" name="pedido_id" value="<?php echo $edit_pedido['id']; ?>">

                            <div class="rastreio-info-grid">
                                <div class="form-group">
                                    <label for="transportadora_nome">Transportadora (Ex: Correios, Jadlog)</label>
                                    <input type="text" id="transportadora_nome" name="transportadora_nome"
                                            value="<?php echo htmlspecialchars($edit_pedido['transportadora_nome'] ?? ''); ?>"
                                            required>
                                </div>
                                <div class="form-group">
                                    <label for="codigo_rastreio">Código de Rastreio</label>
                                    <input type="text" id="codigo_rastreio" name="codigo_rastreio"
                                            value="<?php echo htmlspecialchars($edit_pedido['codigo_rastreio'] ?? ''); ?>"
                                            required>
                                </div>
                                <div class="form-group">
                                    <label for="link_rastreio">Link Rastreio (Opcional)</label>
                                    <input type="url" id="link_rastreio" name="link_rastreio"
                                            value="<?php echo htmlspecialchars($edit_pedido['link_rastreio'] ?? ''); ?>"
                                            placeholder="https://rastreio.com/codigo">
                                </div>
                                <div class="form-group">
                                    <label for="data_envio">Data de Envio</label>
                                    <input type="date" id="data_envio" name="data_envio"
                                            value="<?php
                                                         if (!empty($edit_pedido['data_envio'])) {
                                                             echo date('Y-m-d', strtotime($edit_pedido['data_envio']));
                                                         } else {
                                                             echo date('Y-m-d'); // Data atual por padrão
                                                         }
                                                       ?>"
                                            required>
                                </div>
                            </div>
                            <div class="form-actions" style="justify-content: flex-start;">
                                <button type="submit" class="btn update">
                                    <?php echo $rastreio_exists ? 'Atualizar Rastreio' : 'Salvar e Mover para EM TRANSPORTE'; ?>
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>

                </div>
                <h4>Itens Comprados</h4>
                <div class="info-card" style="padding: 0; margin-bottom: 1.5rem; overflow: hidden;">
                    <div style="overflow-x: auto;">
                        <table class="item-list-table">
                            <thead><tr><th>Produto</th><th>Qtd</th><th>Preço Unit.</th><th>Total Item</th></tr></thead>
                            <tbody>
                                <?php if(empty($edit_pedido['itens'])): ?> <tr><td colspan="4" style="text-align: center; color: var(--light-text-color);">Nenhum item encontrado.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($edit_pedido['itens'] as $item): ?>
                                    <tr>
                                        <td>
                                            <?php $imageUrl = $item['imagem_url'] ?? null; $imageSrc = '../uploads/placeholder.png'; if ($imageUrl) { if (strpos($imageUrl, 'http://') === 0 || strpos($imageUrl, 'https://') === 0) { $imageSrc = $imageUrl; } elseif (strpos($imageUrl, 'uploads/') === 0) { $imageSrc = '../' . $imageUrl; } } ?>
                                            <img src="<?php echo htmlspecialchars($imageSrc); ?>" alt="Img">
                                            <?php echo htmlspecialchars($item['nome_produto']); ?>
                                        </td>
                                        <td><?php echo $item['quantidade']; ?></td>
                                        <td><?php echo formatCurrency($item['preco_unitario']); ?></td>
                                        <td><?php echo formatCurrency($item['preco_total_calculado']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <h4>Atualizar Status</h4>
                <div class="form-container" style="margin-bottom: 0; background: rgba(0,0,0, 0.1);">
                    <form action="pedidos.php?view_pedido=<?php echo $edit_pedido['id']; ?>#edit-form" method="POST">
                        <input type="hidden" name="update_status" value="1">
                        <input type="hidden" name="pedido_id" value="<?php echo $edit_pedido['id']; ?>">
                        <div class="form-grid" style="grid-template-columns: auto 1fr auto; align-items: center; gap: 1.5rem;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Status Atual:</label>
                                <span class="<?php echo get_status_class($edit_pedido['status']); ?>" style="display: inline-block; font-size: 1em; padding: 0.5em 1em;"><?php echo htmlspecialchars($edit_pedido['status'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="status_novo">Mudar Para:</label>
                                <select id="status_novo" name="status_novo" required>
                                    <?php foreach ($status_options as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo (strtoupper($edit_pedido['status'] ?? '') == $key) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-actions" style="margin-top: 0; margin-bottom: 0;">
                                <button type="submit" name="update_status_btn" class="btn update">Salvar Status</button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="form-actions" style="justify-content: flex-start; padding: 1.5rem 0 0 0; border-top: 1px solid var(--border-color); margin-top: 2rem;">
                    <a href="pedidos.php" class="btn cancel">Voltar para Lista</a>
                </div>
            </div>
        </div>

        <?php else: ?>

        <div class="funnel-report-wrapper">

            <div class="funnel-visual-container">
                <div class="funnel-stage-visual" data-label="Aprovados/Entregues HOJE">
                    <span class="funnel-label-top">Aprovados/Entregues HOJE</span>
                    <div>
                        <span class="funnel-value"><?php echo htmlspecialchars($stats['aprovados_hoje'] ?? 0); ?></span>
                        <span style="font-size: 0.85em;">Pedidos</span>
                    </div>
                </div>

                <div class="funnel-stage-visual" data-label="Em Transporte">
                    <span class="funnel-label-top">Em Transporte</span>
                    <div>
                        <span class="funnel-value"><?php echo htmlspecialchars($stats['em_transporte'] ?? 0); ?></span>
                        <span style="font-size: 0.85em;">Pedidos</span>
                    </div>
                </div>

                <div class="funnel-stage-visual" data-label="Pendentes/Aguardando">
                    <span class="funnel-label-top">Pendentes / Aguardando</span>
                    <div>
                        <span class="funnel-value"><?php echo htmlspecialchars($stats['pendentes'] ?? 0); ?></span>
                        <span style="font-size: 0.85em;">Pedidos</span>
                    </div>
                </div>

                <div class="funnel-stage-visual" data-label="Faturamento (Mês)">
                    <span class="funnel-label-top">Faturamento (Mês)</span>
                    <div>
                        <span class="funnel-value"><?php echo formatCurrency($stats['faturamento_mes'] ?? 0.00); ?></span>
                        <span style="font-size: 0.85em;">Total</span>
                    </div>
                </div>

                <div class="funnel-stage-visual" data-label="Cancelados">
                    <span class="funnel-label-top">Cancelados</span>
                    <div>
                        <span class="funnel-value"><?php echo htmlspecialchars($stats['cancelados'] ?? 0); ?></span>
                        <span style="font-size: 0.85em;">Pedidos</span>
                    </div>
                </div>
            </div>

            <div class="funnel-legend">
                <h4>Legenda do Funil de Pedidos</h4>
                <ul>
                    <li>
                        <span class="legend-dot aprovado"></span>
                        <strong>Aprovados/Entregues HOJE:</strong> Pedidos com status 'APROVADO' ou 'ENTREGUE' criados na data atual.
                    </li>
                    <li>
                        <span class="legend-dot transporte"></span>
                        <strong>Em Transporte:</strong> Pedidos que foram enviados e aguardam a entrega ao cliente.
                    </li>
                    <li>
                        <span class="legend-dot pendente"></span>
                        <strong>Pendentes / Aguardando:</strong> Pedidos que estão no início do processo e precisam de aprovação de pagamento.
                    </li>
                    <li>
                        <span class="legend-dot faturamento"></span>
                        <strong>Faturamento (Mês):</strong> Soma do valor total de pedidos pagos (Aprovado, Processando, Em Transporte, Entregue) no mês corrente.
                    </li>
                    <li>
                        <span class="legend-dot cancelado"></span>
                        <strong>Cancelados:</strong> Número total de pedidos cancelados.
                    </li>
                </ul>
            </div>

        </div>
        <div class="filter-container">
            <h3>Filtros de Pedidos</h3>
            <form action="pedidos.php#section-pedidos" method="GET">
                <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); align-items: flex-end;">
                    <div class="form-group">
                           <label for="filter_id">ID do Pedido:</label>
                           <input type="text" id="filter_id" name="filter_id" value="<?php echo htmlspecialchars($filter_id ?? ''); ?>" placeholder="#123">
                    </div>
                    <div class="form-group">
                        <label for="filter_client">Cliente/Email:</label>
                        <input type="text" id="filter_client" name="filter_client" value="<?php echo htmlspecialchars($filter_client ?? ''); ?>" placeholder="Nome ou email">
                    </div>
                    <div class="form-group">
                        <label for="filter_produto">Produto (Nome):</label>
                        <input type="text" id="filter_produto" name="filter_produto" value="<?php echo htmlspecialchars($filter_produto ?? ''); ?>" placeholder="Nome do produto">
                    </div>
                    <div class="form-group">
                        <label for="filter_marca">Marca:</label>
                        <input type="text" id="filter_marca" name="filter_marca" value="<?php echo htmlspecialchars($filter_marca ?? ''); ?>" placeholder="Nome da marca">
                    </div>
                    <div class="form-group">
                        <label for="filter_status">Status:</label>
                        <select id="filter_status" name="filter_status">
                            <option value="">-- Todos --</option>
                            <?php foreach ($status_options as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo (isset($filter_status) && strtoupper($filter_status) == $key) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-actions" style="margin-top: 0;">
                        <button type="submit" class="btn update">Filtrar</button>
                        <a href="pedidos.php" class="btn cancel" style="padding: 0.65rem 1rem; line-height: 1.5;">Limpar</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="crud-section" id="section-pedidos">
            <h3>Lista de Pedidos (Total Encontrado: <?php echo $total_records; ?>)</h3>
            <div class="list-container">
                <?php if (empty($all_pedidos)): ?>
                    <p style="text-align: center; color: var(--light-text-color); padding: 1.5rem 0;">Nenhum pedido encontrado<?php echo (!empty($filter_id) || !empty($filter_client) || !empty($filter_status) || !empty($filter_produto) || !empty($filter_marca)) ? ' para os filtros aplicados' : ''; ?>.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Data</th>
                                <th>Cliente</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th class="actions">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="pedidos-table-body">
                            <?php foreach ($all_pedidos as $pedido): ?>
                            <tr>
                                <td data-label="ID"><strong>#<?php echo $pedido['id']; ?></strong></td>
                                <td data-label="Data"><?php echo date('d/m/y H:i', strtotime($pedido['criado_em'])); ?></td>
                                <td data-label="Cliente"><?php echo htmlspecialchars($pedido['nome_usuario'] ?? 'N/A'); ?></td>
                                <td data-label="Total"><?php echo formatCurrency($pedido['valor_total']); ?></td>
                                <td data-label="Status"><span class="<?php echo get_status_class($pedido['status']); ?>"><?php echo htmlspecialchars($pedido['status']); ?></span></td>
                                <td class="actions">
                                    <a href="pedidos.php?view_pedido=<?php echo $pedido['id']; ?>#edit-form" class="btn update" style="padding: 0.4rem 0.8rem; font-size: 0.8em; margin-left: 0;">Ver Detalhes</a>

                                    <?php if ($pedido['status'] !== 'CANCELADO' && $pedido['status'] !== 'ENTREGUE'): ?>
                                    <button class="action-icon danger" title="Cancelar Pedido"
                                            onclick="openModal(
                                                'Cancelar Pedido',
                                                'Tem certeza que deseja cancelar o Pedido #<?php echo $pedido['id']; ?>?<br><br>Esta ação não pode ser desfeita.',
                                                'pedidos.php?cancel_pedido=<?php echo $pedido['id']; ?>'
                                            )">
                                             <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                           </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if ($total_pages > 1): ?>
                    <div class="pagination" style="display: flex; justify-content: center; align-items: center; gap: 0.5rem; margin-top: 1.5rem;">
                        <?php
                            // Cria a URL base mantendo todos os filtros ativos
                            $base_url = "pedidos.php?" . http_build_query(array_filter([
                                'filter_id' => $filter_id, 'filter_client' => $filter_client,
                                'filter_status' => $filter_status, 'filter_produto' => $filter_produto,
                                'filter_marca' => $filter_marca
                            ]));
                        ?>

                        <?php if ($current_page > 1): ?>
                            <a href="<?php echo $base_url . "&page=" . ($current_page - 1); ?>#section-pedidos" class="btn cancel" style="padding: 0.5rem 1rem;">&laquo; Anterior</a>
                        <?php else: ?>
                             <span class="btn cancel" style="padding: 0.5rem 1rem; opacity: 0.5; cursor: not-allowed;">&laquo; Anterior</span>
                        <?php endif; ?>

                        <span style="color: var(--light-text-color); font-size: 0.9em;">Página <?php echo $current_page; ?> de <?php echo $total_pages; ?></span>

                        <?php if ($current_page < $total_pages): ?>
                            <a href="<?php echo $base_url . "&page=" . ($current_page + 1); ?>#section-pedidos" class="btn update" style="padding: 0.5rem 1rem;">Próxima &raquo;</a>
                        <?php else: ?>
                            <span class="btn update" style="padding: 0.5rem 1rem; opacity: 0.5; cursor: not-allowed;">Próxima &raquo;</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </main>

    <script>
        // --- JavaScript para Partículas ---
        particlesJS('particles-js', {"particles":{"number":{"value":60,"density":{"enable":true,"value_area":800}},"color":{"value":"#4a69bd"},"shape":{"type":"circle"},"opacity":{"value":0.4,"random":false},"size":{"value":3,"random":true},"line_linked":{"enable":true,"distance":150,"color":"#ffffff","opacity":0.1,"width":1},"move":{"enable":true,"speed":1.5,"direction":"none","random":false,"straight":false,"out_mode":"out","bounce":false}},"interactivity":{"detect_on":"canvas","events":{"onhover":{"enable":true,"mode":"repulse"},"onclick":{"enable":true,"mode":"push"},"resize":true}},"retina_detect":true});

        // --- JavaScript para Abrir/Fechar Edição de Endereço ---
        function toggleEditAddress() {
            const formWrapper = document.getElementById('endereco-edit-form');
            const cardWrapper = document.getElementById('address-card');
            if (formWrapper && cardWrapper) {
                const isOpen = formWrapper.classList.contains('open');
                if (isOpen) {
                    formWrapper.style.maxHeight = null;
                } else {
                    formWrapper.style.maxHeight = formWrapper.scrollHeight + 40 + "px";
                    setTimeout(() => { formWrapper.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 400);
                }
                formWrapper.classList.toggle('open');
                cardWrapper.classList.toggle('editing');
            }
        }

        // --- JavaScript Geral (DOMContentLoaded) ---
        document.addEventListener('DOMContentLoaded', () => {
            // --- Ancoragem suave ---
             const urlParams = new URLSearchParams(window.location.search);
             const viewPedidoId = urlParams.get('view_pedido');
             let targetHash = window.location.hash;

             if (viewPedidoId && !targetHash) {
                 targetHash = '#edit-form'; // Ancora no formulário de edição
             } else if (urlParams.has('view_pedido') && !targetHash) { // Após salvar rastreio ou status
                 targetHash = '#edit-form';
             } else if (!viewPedidoId && !targetHash) { // Só ancora na lista se NÃO estiver editando
                 targetHash = '#section-pedidos'; // Padrão é a lista
             }

             if (targetHash) {
                 const targetElement = document.querySelector(targetHash);
                 if (targetElement) {
                     setTimeout(() => {
                         const headerElement = targetElement.querySelector('h3') || targetElement;
                         if(headerElement) {
                             headerElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                         }
                     }, 150);
                 }
             }

            // --- Lógica do Modal de Confirmação ---
            const modalOverlay = document.getElementById('confirmation-modal');
            const modalTitle = document.getElementById('modal-title');
            const modalText = document.getElementById('modal-text');
            const modalConfirmLink = document.getElementById('modal-confirm-link');
            const modalCancel = document.getElementById('modal-cancel');

            window.openModal = function(title, text, confirmUrl) {
                if (modalOverlay) {
                    modalTitle.innerText = title;
                    modalText.innerHTML = text; // innerHTML para aceitar <br>
                    modalConfirmLink.href = confirmUrl;

                    modalConfirmLink.classList.remove('update', 'danger');
                    if (title.toLowerCase().includes('cancelar') || title.toLowerCase().includes('excluir')) {
                        modalConfirmLink.classList.add('danger');
                    } else {
                        modalConfirmLink.classList.add('update');
                    }
                    modalOverlay.classList.add('active');
                }
            }
            window.closeModal = function() {
                if (modalOverlay) { modalOverlay.classList.remove('active'); }
            }
            if (modalOverlay) {
                modalCancel.addEventListener('click', closeModal);
                modalOverlay.addEventListener('click', function(e) {
                    if (e.target === modalOverlay) { closeModal(); }
                });
            }

            // --- Reabre form de endereço se houver erro nele ---
            const messageDivError = document.querySelector('.message.error');
            if (messageDivError && document.getElementById('endereco-edit-form')) {
                const formWrapper = document.getElementById('endereco-edit-form');
                if (formWrapper && messageDivError.textContent.toLowerCase().includes('endere')) {
                    if (!formWrapper.classList.contains('open')) { toggleEditAddress(); }
                }
            }

        });
    </script>
</body>
</html>