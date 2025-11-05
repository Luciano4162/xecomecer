<?php
// admin_panel/tickets.php
// MODELO 'suporte.php' - TOTALMENTE REFEITO COM AJAX E ARMAZENAMENTO BLOB
declare(strict_types=1);
session_start();

// Inclui config e funcoes
require_once '../config/db.php';
require_once '../funcoes.php'; // Para formatarDataHoraBR
date_default_timezone_set('America/Sao_Paulo');


// ===================================================================
// === MANIPULADOR DE REQUISIÇÕES AJAX
// ===================================================================
function handleAjaxRequest($pdo) {
    if (isset($_REQUEST['ajax_action'])) {
        // Validação de sessão
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
             header('Content-Type: application/json');
             http_response_code(403); // Forbidden
             echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
             exit;
        }

        header('Content-Type: application/json');
        $action = $_REQUEST['ajax_action'];
        $response = ['success' => false, 'message' => 'Ação inválida.'];

        // ID do Admin logado. Assumindo 'admin_user_id' da sessão, ou 1 como fallback.
        $admin_id = (int)($_SESSION['admin_user_id'] ?? 1);

        try {
            // --- AÇÃO: Admin envia uma nova resposta (POST) ---
            if ($action === 'admin_reply' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $post_ticket_id = (int)($_POST['ticket_id'] ?? 0);
                $mensagem = trim((string)($_POST['mensagem'] ?? ''));

                // --- INÍCIO DA LÓGICA DE UPLOAD (ARMAZENAMENTO DB) ---
                $anexo_dados = null;
                $anexo_nome = null;
                $anexo_tipo = null;

                if ($post_ticket_id <= 0) throw new Exception("ID do ticket inválido.");

                if (isset($_FILES['anexo']) && $_FILES['anexo']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['anexo'];
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt'];
                    $max_size = 5 * 1024 * 1024; // 5 MB
                    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                    if ($file['size'] > $max_size) throw new Exception("Arquivo muito grande (Max 5MB).");
                    if (!in_array($file_extension, $allowed_extensions)) {
                        throw new Exception("Formato de arquivo inválido. Permitidos: JPG, PNG, GIF, PDF, TXT.");
                    }

                    $anexo_dados = file_get_contents($file['tmp_name']);
                    $anexo_nome = $file['name'];
                    $anexo_tipo = $file['type'];
                }

                if (empty($mensagem) && $anexo_dados === null) {
                    throw new Exception("A mensagem ou anexo não pode estar vazio.");
                }
                // --- FIM DA LÓGICA DE UPLOAD ---

                // Busca o ID do status 'AGUARDANDO RESPOSTA'
                $stmt_status_id = $pdo->prepare("SELECT id FROM ticket_status WHERE nome = 'AGUARDANDO RESPOSTA'");
                $stmt_status_id->execute();
                $status_aguardando_id = $stmt_status_id->fetchColumn();
                if (!$status_aguardando_id) {
                    $stmt_status_id = $pdo->prepare("SELECT id FROM ticket_status WHERE nome = 'ABERTO'");
                    $stmt_status_id->execute();
                    $status_aguardando_id = $stmt_status_id->fetchColumn();
                }

                $pdo->beginTransaction();
                // 1. Salva a mensagem (com anexo BLOB)
                $stmt_msg = $pdo->prepare("
                    INSERT INTO ticket_respostas
                        (ticket_id, usuario_id, mensagem, anexo_nome, anexo_tipo, anexo_dados, data_resposta)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");

                $stmt_msg->bindParam(1, $post_ticket_id, PDO::PARAM_INT);
                $stmt_msg->bindParam(2, $admin_id, PDO::PARAM_INT);
                $stmt_msg->bindParam(3, $mensagem, $mensagem ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt_msg->bindParam(4, $anexo_nome, $anexo_nome ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt_msg->bindParam(5, $anexo_tipo, $anexo_tipo ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt_msg->bindParam(6, $anexo_dados, $anexo_dados ? PDO::PARAM_LOB : PDO::PARAM_NULL);
                $stmt_msg->execute();

                $new_message_id = $pdo->lastInsertId();

                // 2. Atualiza o ticket
                $stmt_ticket = $pdo->prepare("UPDATE tickets SET status_id = ?, admin_ultima_visualizacao = NOW(), ultima_atualizacao = NOW() WHERE id = ?");
                $stmt_ticket->execute([$status_aguardando_id, $post_ticket_id]);
                $pdo->commit();

                // 3. Retorna a nova mensagem formatada para o chat (com base64)
                $stmt_new = $pdo->prepare("
                    SELECT m.id, m.ticket_id, m.usuario_id, m.mensagem, m.data_resposta,
                           m.anexo_nome, m.anexo_tipo, m.anexo_dados,
                           u.nome AS remetente_nome, u.tipo AS tipo_remetente
                    FROM ticket_respostas m
                    JOIN usuarios u ON m.usuario_id = u.id
                    WHERE m.id = ?
                ");
                $stmt_new->execute([$new_message_id]);
                $new_message_data = $stmt_new->fetch(PDO::FETCH_ASSOC);

                if ($new_message_data) {
                    $new_message_data['data_resposta_br'] = formatarDataHoraBR($new_message_data['data_resposta']);
                    // Trata o dado que vem do DB (pode ser string ou resource)
                    $blob_data = $new_message_data['anexo_dados'];
                    if (is_resource($blob_data)) {
                         $blob_data = stream_get_contents($blob_data);
                    }
                    if ($blob_data) {
                        $new_message_data['anexo_dados'] = base64_encode($blob_data);
                    }
                }
                $response = ['success' => true, 'message' => $new_message_data];
            }

            // --- AÇÃO: Admin atualiza APENAS o status (POST) ---
            elseif ($action === 'update_status_only' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $post_ticket_id = (int)($_POST['ticket_id'] ?? 0);
                $new_status_id = (int)($_POST['new_status_id'] ?? 0);

                if ($post_ticket_id <= 0 || $new_status_id <= 0) throw new Exception("IDs inválidos.");

                $stmt_status = $pdo->prepare("SELECT nome, cor_badge FROM ticket_status WHERE id = ?");
                $stmt_status->execute([$new_status_id]);
                $status_info = $stmt_status->fetch(PDO::FETCH_ASSOC);

                if (!$status_info) throw new Exception("Status não encontrado.");

                $stmt_ticket = $pdo->prepare("UPDATE tickets SET status_id = ?, ultima_atualizacao = NOW() WHERE id = ?");
                $stmt_ticket->execute([$new_status_id, $post_ticket_id]);

                $response = [
                    'success' => true,
                    'message' => 'Status atualizado!',
                    'new_status_nome' => $status_info['nome'],
                    'new_status_cor' => $status_info['cor_badge']
                ];
            }

            // --- AÇÃO: Buscar Novas Mensagens (Polling - GET) ---
            elseif ($action === 'get_new_messages') {
                $ticket_id = (int)($_GET['ticket_id'] ?? 0);
                $last_message_id = (int)($_GET['last_message_id'] ?? 0);
                if ($ticket_id <= 0) throw new Exception("ID do ticket inválido.");

                $stmt_msg = $pdo->prepare("
                    SELECT m.id, m.ticket_id, m.usuario_id, m.mensagem, m.data_resposta,
                           m.anexo_nome, m.anexo_tipo, m.anexo_dados,
                           u.nome AS remetente_nome, u.tipo AS tipo_remetente
                    FROM ticket_respostas m JOIN usuarios u ON m.usuario_id = u.id
                    WHERE m.ticket_id = ? AND m.id > ? AND u.tipo != 'admin'
                    ORDER BY m.data_resposta ASC");
                $stmt_msg->execute([$ticket_id, $last_message_id]);

                $new_messages = [];
                while ($row = $stmt_msg->fetch(PDO::FETCH_ASSOC)) {
                    if (is_resource($row['anexo_dados'])) {
                        $row['anexo_dados'] = stream_get_contents($row['anexo_dados']);
                    }
                    if ($row['anexo_dados']) {
                        $row['anexo_dados'] = base64_encode($row['anexo_dados']);
                    }
                    $new_messages[] = $row;
                }

                $status_changed = false;
                $new_status_info = null;

                if (count($new_messages) > 0) {
                    $last_new_msg_time = end($new_messages)['data_resposta'];

                    $stmt_status_id = $pdo->prepare("SELECT id, nome, cor_badge FROM ticket_status WHERE nome = 'ABERTO'");
                    $stmt_status_id->execute();
                    $aberto_status_info = $stmt_status_id->fetch(PDO::FETCH_ASSOC);

                    if ($aberto_status_info) {
                        $stmt_update = $pdo->prepare("UPDATE tickets SET admin_ultima_visualizacao = NULL, status_id = ?, ultima_atualizacao = ? WHERE id = ?");
                        $stmt_update->execute([$aberto_status_info['id'], $last_new_msg_time, $ticket_id]);
                        $status_changed = true;
                        $new_status_info = $aberto_status_info;
                    }

                    foreach ($new_messages as &$msg) {
                        $msg['data_resposta_br'] = formatarDataHoraBR($msg['data_resposta']);
                    }
                    unset($msg);
                }
                $response = ['success' => true, 'messages' => $new_messages, 'status_changed' => $status_changed, 'new_status_info' => $new_status_info];
            }

            // --- AÇÃO: Listar tickets (GET) ---
            elseif ($action === 'get_tickets') {
                $filter_status = $_GET['filter_status'] ?? 'PENDENTES';
                $sql_where = "WHERE 1=1 ";
                $params = [];

                if ($filter_status === 'PENDENTES') {
                    $sql_where .= " AND ts.nome IN ('ABERTO', 'AGUARDANDO RESPOSTA') ";
                } elseif ($filter_status === 'FECHADOS') {
                    $sql_where .= " AND ts.nome = 'FECHADO' ";
                } else {
                    throw new Exception("Filtro de status inválido.");
                }

                $sql_last_message = "
                    SELECT DISTINCT ON (sm.ticket_id)
                        sm.ticket_id, sm.data_resposta AS last_message_time,
                        u_msg.tipo AS last_message_sender_tipo
                    FROM ticket_respostas sm
                    JOIN usuarios u_msg ON sm.usuario_id = u_msg.id
                    ORDER BY sm.ticket_id, sm.data_resposta DESC
                ";

                $stmt_list = $pdo->prepare("
                    SELECT
                        t.id, t.assunto, t.status_id, t.ultima_atualizacao, t.atendimento_rating,
                        COALESCE(u.nome, t.guest_nome, 'N/A') AS usuario_nome,
                        ts.nome AS status_nome, ts.cor_badge,
                        lm.last_message_time, lm.last_message_sender_tipo,

                        (ts.nome = 'ABERTO' AND (t.admin_ultima_visualizacao IS NULL OR t.ultima_atualizacao > t.admin_ultima_visualizacao)) AS flag_new,
                        (t.atendimento_rating IS NOT NULL AND t.atendimento_rating <= 2) AS flag_bad_rating,
                        (ts.nome = 'ABERTO' AND t.ultima_atualizacao < NOW() - INTERVAL '1 day') AS flag_long_wait,
                        (ts.nome = 'ABERTO' AND (lm.last_message_sender_tipo IS NULL OR lm.last_message_sender_tipo != 'admin') AND (lm.last_message_time IS NULL OR lm.last_message_time < NOW() - INTERVAL '5 minutes')) AS flag_admin_delay,

                        CASE
                            WHEN ts.nome = 'ABERTO' AND (lm.last_message_sender_tipo IS NULL OR lm.last_message_sender_tipo != 'admin')
                            THEN EXTRACT(EPOCH FROM (NOW() - COALESCE(lm.last_message_time, t.data_criacao)))
                            ELSE 0
                        END AS seconds_since_user_msg

                    FROM tickets t
                    JOIN ticket_status ts ON t.status_id = ts.id
                    LEFT JOIN usuarios u ON t.usuario_id = u.id
                    LEFT JOIN LATERAL ($sql_last_message) lm ON lm.ticket_id = t.id
                    $sql_where
                    ORDER BY
                        CASE
                            WHEN ts.nome = 'ABERTO' AND (lm.last_message_sender_tipo IS NULL OR lm.last_message_sender_tipo != 'admin') AND (lm.last_message_time IS NULL OR lm.last_message_time < NOW() - INTERVAL '5 minutes') THEN 1
                            WHEN ts.nome = 'ABERTO' AND (t.admin_ultima_visualizacao IS NULL OR t.ultima_atualizacao > t.admin_ultima_visualizacao) THEN 2
                            WHEN ts.nome = 'ABERTO' AND t.ultima_atualizacao < NOW() - INTERVAL '1 day' THEN 3
                            WHEN ts.nome = 'ABERTO' THEN 4
                            WHEN ts.nome = 'AGUARDANDO RESPOSTA' THEN 5
                            ELSE 6
                        END,
                        t.ultima_atualizacao DESC
                ");
                $stmt_list->execute($params);
                $tickets = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

                foreach ($tickets as &$ticket) {
                    $ticket['data_ultima_atualizacao_br'] = formatarDataHoraBR($ticket['ultima_atualizacao']);
                }
                unset($ticket);

                $response = ['success' => true, 'tickets' => $tickets];
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $response['message'] = $e->getMessage();
            http_response_code(500);
        }

        echo json_encode($response);
        exit;
    }
}
// -------------------------------------------------------------------
// FIM DO MANIPULADOR AJAX
// -------------------------------------------------------------------
handleAjaxRequest($pdo);

// ===================================================================
// === EXECUÇÃO NORMAL DA PÁGINA (CARGA INICIAL)
// ===================================================================
$admin_id = (int)($_SESSION['admin_user_id'] ?? 1);
$ticket_id_view = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;
$initial_filter = $_GET['filter'] ?? 'PENDENTES';

$successMessage = null;
$errorMessage = null;

if (isset($_SESSION['flash_success'])) {
    $successMessage = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// ===================================================================
// === LÓGICA DE POST (Fechar Ticket - Não AJAX)
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_action'])) {
    try {
        $pdo->beginTransaction();
        $action = $_POST['action'] ?? '';
        $post_ticket_id = (int)($_POST['ticket_id'] ?? 0);

        if ($post_ticket_id <= 0) throw new Exception("ID do ticket inválido.");

        if ($action === 'close_ticket') {
            $stmt_status_id = $pdo->prepare("SELECT id FROM ticket_status WHERE nome = 'FECHADO'");
            $stmt_status_id->execute();
            $status_fechado_id = $stmt_status_id->fetchColumn();

            if (!$status_fechado_id) throw new Exception("Status 'FECHADO' não encontrado.");

            $stmt_ticket = $pdo->prepare("UPDATE tickets SET status_id = ?, ultima_atualizacao = NOW() WHERE id = ?");
            $stmt_ticket->execute([$status_fechado_id, $post_ticket_id]);

            $mensagem_sistema = "[TICKET FECHADO PELO SUPORTE]";
            $stmt_msg = $pdo->prepare("INSERT INTO ticket_respostas (ticket_id, usuario_id, mensagem, data_resposta) VALUES (?, ?, ?, NOW())");
            $stmt_msg->execute([$post_ticket_id, $admin_id, $mensagem_sistema]);

            $pdo->commit();
            $_SESSION['flash_success'] = "Ticket #" . $post_ticket_id . " fechado com sucesso!";
            header("Location: tickets.php?filter=PENDENTES");
            exit;
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errorMessage = "Ocorreu um erro: " . $e->getMessage();
    }
}


// ===================================================================
// === LÓGICA DE EXIBIÇÃO (GET - BUSCA DADOS DO TICKET)
// ===================================================================
$view_data = [
    'ticket_info' => null,
    'mensagens' => [],
    'usuario_ticket' => null,
    'usuario_pedidos' => [],
    'last_message_id' => 0,
    'seconds_since_user_msg' => 0,
    'status_options' => []
];
try {
    $stmt_status_all = $pdo->query("SELECT id, nome FROM ticket_status ORDER BY id");
    $view_data['status_options'] = $stmt_status_all->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
     $errorMessage = "Erro fatal ao carregar status de tickets.";
}


if ($ticket_id_view > 0) {
    try {
        // 1. Marca o ticket como lido pelo admin
        $stmt_mark_read = $pdo->prepare("
            UPDATE tickets SET admin_ultima_visualizacao = NOW()
            WHERE id = ? AND (admin_ultima_visualizacao IS NULL OR ultima_atualizacao > admin_ultima_visualizacao)
        ");
        $stmt_mark_read->execute([$ticket_id_view]);

        // 2. Busca dados do ticket
        $stmt_ticket = $pdo->prepare("
            SELECT t.*, ts.nome AS status_nome, ts.cor_badge
            FROM tickets t
            JOIN ticket_status ts ON t.status_id = ts.id
            WHERE t.id = ?
        ");
        $stmt_ticket->execute([$ticket_id_view]);
        $view_data['ticket_info'] = $stmt_ticket->fetch(PDO::FETCH_ASSOC);

        if ($view_data['ticket_info']) {
            $usuario_id_do_ticket = $view_data['ticket_info']['usuario_id'];

            // 3. Busca dados do usuário (se for cliente registrado)
            if ($usuario_id_do_ticket) {
                $stmt_user = $pdo->prepare("SELECT id, nome, email, tipo FROM usuarios WHERE id = ?");
                $stmt_user->execute([$usuario_id_do_ticket]);
                $view_data['usuario_ticket'] = $stmt_user->fetch(PDO::FETCH_ASSOC);

                // 4. Busca o histórico de pedidos do usuário
                if($view_data['usuario_ticket']) {
                    $stmt_pedidos = $pdo->prepare("
                        SELECT id, valor_total, status, criado_em
                        FROM pedidos
                        WHERE usuario_id = ?
                        ORDER BY criado_em DESC LIMIT 5
                    ");
                    $stmt_pedidos->execute([$usuario_id_do_ticket]);
                    $view_data['usuario_pedidos'] = $stmt_pedidos->fetchAll(PDO::FETCH_ASSOC);
                }
            } else {
                $view_data['usuario_ticket'] = [
                    'id' => null,
                    'nome' => $view_data['ticket_info']['guest_nome'] . ' (Visitante)',
                    'email' => $view_data['ticket_info']['guest_email'],
                    'tipo' => 'cliente' // Guest é tratado como cliente
                ];
            }

            // 5. Busca todas as mensagens (incluindo a original)
            $stmt_msgs = $pdo->prepare("
                SELECT m.id, m.ticket_id, m.usuario_id, m.mensagem, m.data_resposta,
                       m.anexo_nome, m.anexo_tipo, m.anexo_dados,
                       u.nome AS remetente_nome, u.tipo AS tipo_remetente
                FROM ticket_respostas m
                JOIN usuarios u ON m.usuario_id = u.id
                WHERE m.ticket_id = ?
                ORDER BY m.data_resposta ASC
            ");
            $stmt_msgs->execute([$ticket_id_view]);

            $mensagens = [];
            while ($row = $stmt_msgs->fetch(PDO::FETCH_ASSOC)) {
                // CORREÇÃO: Lógica para ler o BYTEA/BLOB corretamente
                if (is_resource($row['anexo_dados'])) {
                    $row['anexo_dados'] = stream_get_contents($row['anexo_dados']);
                }
                $mensagens[] = $row;
            }
            $view_data['mensagens'] = $mensagens;


            // Adiciona a mensagem ORIGINAL (Abertura do Ticket)
            array_unshift($view_data['mensagens'], [
                'id' => 0,
                'ticket_id' => $ticket_id_view,
                'usuario_id' => $usuario_id_do_ticket,
                'mensagem' => $view_data['ticket_info']['mensagem'],
                'anexo_nome' => null, 'anexo_tipo' => null, 'anexo_dados' => null,
                'data_resposta' => $view_data['ticket_info']['data_criacao'],
                'remetente_nome' => $view_data['usuario_ticket']['nome'],
                'tipo_remetente' => $usuario_id_do_ticket ? $view_data['usuario_ticket']['tipo'] : 'cliente'
            ]);

            // 6. Calcula o timer de atraso
            if (!empty($view_data['mensagens'])) {
                $last_msg = end($view_data['mensagens']);
                $view_data['last_message_id'] = (int)$last_msg['id'];
                $last_msg_sender_tipo = $last_msg['tipo_remetente'] ?? null;
                $last_msg_time = $last_msg['data_resposta'] ?? null;

                if ($view_data['ticket_info']['status_nome'] === 'ABERTO' && $last_msg_sender_tipo !== 'admin' && $last_msg_time) {
                    $now = new DateTime();
                    $lastTime = new DateTime($last_msg_time);
                    $view_data['seconds_since_user_msg'] = $now->getTimestamp() - $lastTime->getTimestamp();
                }
            }
        } else {
             $errorMessage = "Ticket #" . $ticket_id_view ." não encontrado.";
             $ticket_id_view = 0;
        }
    } catch (Exception $e) {
        $errorMessage = "Erro ao carregar ticket: " . $e->getMessage();
        $ticket_id_view = 0;
    }
}


// ==========================================================
// === FUNÇÕES HELPER (Para o HTML)
// ==========================================================

if (!function_exists('formatCurrency')) {
    function formatCurrency($value) {
        $numericValue = filter_var($value, FILTER_VALIDATE_FLOAT);
        if ($numericValue === false) { $numericValue = 0.00; }
        return 'R$ ' . number_format($numericValue, 2, ',', '.');
    }
}

// Função helper para badges de status (para o chat)
function getStatusBadge($status_nome, $cor_badge) {
    if (!$cor_badge) $cor_badge = '#888';
    $r = hexdec(substr($cor_badge,1,2));
    $g = hexdec(substr($cor_badge,3,2));
    $b = hexdec(substr($cor_badge,5,2));
    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    $text_color = $luminance > 0.5 ? '#000' : '#fff';

    $style = "background-color: {$cor_badge}; color: {$text_color}; padding: 0.3em 0.7em; border-radius: 1em; font-size: 0.8em; font-weight: 600; text-transform: uppercase;";
    return "<span style='{$style}'>" . htmlspecialchars($status_nome) . "</span>";
}

// Função helper para estrelas
function renderStars($rating) {
    if ($rating === null || $rating < 1 || $rating > 5) {
        return '<span style="font-size: 0.8rem; color: var(--text-muted);">(Não avaliado)</span>';
    }
    $starsHtml = '';
    for ($i = 1; $i <= 5; $i++) {
        $starsHtml .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 16px; height: 16px; display: inline-block; color: ' . ($i <= $rating ? 'var(--warning-color)' : 'var(--text-muted)') . ';"><path fill-rule="evenodd" d="M10.868 2.884c.321-.772 1.415-.772 1.736 0l1.83 4.426 4.873.708c.848.123 1.186 1.161.573 1.751l-3.526 3.436.833 4.852c.144.843-.74 1.485-1.49.957L10 15.487l-4.35 2.287c-.75.394-1.635-.114-1.49-.957l.833-4.852L1.467 9.77c-.613-.59-.275-1.628.573-1.751l4.873-.708 1.83-4.426Z" clip-rule="evenodd" /></svg>';
    }
    return $starsHtml;
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Tickets - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* ==========================================================
           CSS COMPLETO (Baseado no 'suporte.php' e adaptado ao 'tickets.php')
           ========================================================== */
        @keyframes pulse-red {
            0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); background-color: rgba(220, 53, 69, 0.1); }
            70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); background-color: rgba(220, 53, 69, 0.05); }
            100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); background-color: rgba(220, 53, 69, 0.1); }
        }

        :root {
            --primary-color: #4a69bd;
            --background-color: #111827;
            --sidebar-color: #1f2937;
            --secondary-bg: #1f2937;
            --glass-background: rgba(31, 41, 55, 0.5);
            --text-color: #f9fafb;
            --text-muted: #9ca3af;
            --border-color: rgba(255, 255, 255, 0.1);
            --success-color: #22c55e;
            --error-color: #f87171;
            --info-color: #3b82f6;
            --warning-color: #f59e0b;
            --danger-color: #dc3545;

            --flag-rating-color: var(--danger-color);
            --flag-wait-color: var(--warning-color);
            --flag-delay-color: var(--danger-color);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scrollbar-width: thin; scrollbar-color: var(--primary-color) transparent; }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background-color: var(--primary-color); border-radius: 10px; border: 3px solid var(--background-color); }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* --- SIDEBAR --- */
        .sidebar {
            width: 240px;
            background-color: var(--sidebar-color);
            height: 100%;
            position: fixed; left:0; top:0;
            padding: 1.5rem;
            display: flex; flex-direction: column;
            border-right: 1px solid var(--border-color);
            z-index: 1000;
            transition: transform 0.3s ease;
        }
        .sidebar .logo-area { display: flex; flex-direction: column; align-items: center; margin-bottom: 1rem; } .sidebar .logo-circle { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 0.5rem; box-shadow: 0 0 10px rgba(74, 105, 189, 0.6); overflow: hidden; background-color: #fff; } .sidebar .logo-circle svg { color: var(--primary-color); width: 24px; height: 24px; } .sidebar .logo-text { font-size: 1rem; font-weight: 600; color: var(--text-color); text-align: center; } .sidebar .divider { width: 100%; height: 1px; background-color: var(--border-color); margin: 1rem 0; } .sidebar nav { flex-grow: 1; overflow-y: auto; scrollbar-width: none;} .sidebar nav::-webkit-scrollbar { display: none; } .sidebar nav a { display: flex; align-items: center; gap: 0.8rem; padding: 0.8rem 1rem; color: var(--text-muted); text-decoration: none; border-radius: 8px; margin-bottom: 0.5rem; transition: all 0.3s ease; border: 1px solid transparent; } .sidebar nav a:hover, .sidebar nav a.active { background-color: var(--glass-background); color: var(--text-color); border-color: var(--primary-color); box-shadow: 0 2px 8px rgba(74, 105, 189, 0.4); } .sidebar nav a svg { width: 20px; height: 20px; flex-shrink: 0; } .user-profile { position: relative; margin-top: auto; background-color: var(--glass-background); padding: 0.75rem; border-radius: 8px; display: flex; align-items: center; gap: 1rem; cursor: pointer; border: 1px solid transparent; transition: all 0.3s ease; flex-shrink: 0; } .user-profile:hover { border-color: var(--border-color); } .avatar { width: 35px; height: 35px; border-radius: 50%; background-color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.9rem; flex-shrink: 0; } .user-info { overflow: hidden; } .user-info .user-name { font-weight: 600; font-size: 0.85rem; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; } .user-info .user-level { font-size: 0.7rem; color: var(--text-muted); } .profile-dropdown { position: absolute; bottom: calc(100% + 10px); left: 0; width: 100%; background-color: #2c3a4f; border-radius: 8px; border: 1px solid var(--border-color); padding: 0.5rem; z-index: 20; visibility: hidden; opacity: 0; transform: translateY(10px); transition: all 0.3s ease; } .profile-dropdown.show { visibility: visible; opacity: 1; transform: translateY(0); } .profile-dropdown a { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; color: var(--text-muted); text-decoration: none; font-size: 0.9rem; border-radius: 6px; } .profile-dropdown a:hover { background-color: var(--glass-background); color: var(--text-color); }
        .sidebar nav .sidebar-submenu { padding-left: 20px; margin-top: -5px; margin-bottom: 5px; overflow: hidden; transition: max-height 0.3s ease-out; max-height: 0; } .sidebar nav .sidebar-submenu.open { max-height: 500px; } .sidebar nav a.has-children { display: flex; justify-content: space-between; align-items: center; } .sidebar nav a .menu-chevron { width: 16px; height: 16px; color: var(--light-text-color); transition: transform 0.3s ease; } .sidebar nav a.open .menu-chevron { transform: rotate(90deg); } .sidebar-submenu a { font-size: 0.9em; padding: 0.7rem 1rem 0.7rem 1.5rem; color: var(--light-text-color); position: relative; } .sidebar-submenu a::before { content: ''; position: absolute; left: 0.5rem; top: 50%; transform: translateY(-50%); width: 4px; height: 4px; border-radius: 50%; background-color: var(--light-text-color); transition: all 0.3s ease; } .sidebar-submenu a:hover { color: var(--text-color); background-color: transparent; border-color: transparent; box-shadow: none; } .sidebar-submenu a:hover::before { background-color: var(--primary-color); } .sidebar-submenu a.active-child { color: #fff; font-weight: 600; } .sidebar-submenu a.active-child::before { background-color: var(--primary-color); transform: translateY(-50%) scale(1.5); }


        /* --- MAIN CONTENT --- */
        .main-content {
            margin-left: 240px;
            flex-grow: 1;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            padding: 1.5rem 2.5rem;
            transition: margin-left 0.3s ease;
        }
        .menu-toggle { display: none; position: fixed; top: 1rem; left: 1rem; z-index: 1001; cursor: pointer; padding: 8px; background-color: var(--sidebar-color); border-radius: 8px; border: 1px solid var(--border-color); }
        .menu-toggle svg { width: 24px; height: 24px; color: var(--text-color); }

        .main-content header {
            margin-bottom: 1rem;
            flex-shrink: 0;
            background: var(--glass-background);
            padding: 1rem 2rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            backdrop-filter: blur(5px);
        }
        .main-content header h1 { font-size: 1.8rem; font-weight: 600; color: var(--primary-color); margin: 0;}
        .main-content header p { font-size: 1rem; color: var(--text-muted); margin: 0; }
        .main-content header a.back-to-list-btn { display: none; } /* Escondido em desktop */

        /* --- COMPONENTES --- */
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; font-weight: 500; font-size: 0.9rem; }
        .alert-success { background-color: rgba(34, 197, 94, 0.2); color: var(--success-color); }
        .alert-error { background-color: rgba(248, 113, 113, 0.2); color: var(--error-color); }

        .btn { padding: 0.65rem 1.2rem; background-color: var(--primary-color); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s ease; font-size: 0.9em; text-decoration: none; }
        .btn-sm { font-size: 0.8rem; padding: 0.4rem 0.8rem; }
        .btn:hover:not(:disabled) { background-color: #3b5998; }
        .btn:disabled { background-color: #4b5563; opacity: 0.7; cursor: not-allowed; }
        .btn-info { background-color: var(--info-color); }
        .btn-info:hover:not(:disabled) { background-color: #2563eb; }
        .btn-secondary { background-color: #4b5563; }
        .btn-secondary:hover:not(:disabled) { background-color: #374151; }

        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 0.3rem; font-size: 0.9rem; }
        .form-group input, .form-group textarea, .form-group select {
             width: 100%; padding: 0.65rem 0.8rem; border: 1px solid var(--border-color);
             border-radius: 8px; background-color: rgba(0, 0, 0, 0.3);
             color: var(--text-color); box-sizing: border-box;
             transition: border-color 0.3s ease, box-shadow 0.3s ease; font-size: 0.9em;
             font-family: 'Poppins', sans-serif;
        }
        .form-group input[type="file"] { padding: 0.5rem; background-color: var(--glass-background); }
        .form-group input[type="file"]::file-selector-button { background-color: var(--primary-color); color: white; border: none; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer; margin-right: 1rem; font-family: 'Poppins', sans-serif; font-weight: 500; }
        .form-group textarea { resize: vertical; min-height: 80px; font-size: 0.9rem; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color: var(--primary-color); box-shadow: 0 0 8px rgba(74, 105, 189, 0.5); outline: none; }

        .badge { padding: 0.25rem 0.6rem; font-size: 0.75rem; font-weight: 600; border-radius: 20px; }
        .badge-warning { background-color: rgba(245, 158, 11, 0.2); color: var(--warning-color); }
        .badge-info { background-color: rgba(59, 130, 246, 0.2); color: var(--info-color); }
        .badge-muted { background-color: rgba(156, 163, 175, 0.2); color: var(--text-muted); }
        .badge-new { margin-left: 8px; background-color: var(--primary-color); color: white; font-size: 0.65rem; padding: 0.15rem 0.4rem; vertical-align: middle; }

        /* ======================================= */
        /* ESTRUTURA GRID PARA CHAT VIEW */
        /* ======================================= */
        .chat-grid-container {
            display: grid;
            grid-template-columns: minmax(300px, 1fr) 3fr;
            flex-grow: 1;
            min-height: 0;
            max-height: 100%;
            gap: 1.5rem;
        }

        /* Coluna de Lista de Tickets */
        .tickets-list-column { background-color: var(--secondary-bg); border-radius: 12px; overflow: hidden; padding: 0; border: 1px solid var(--border-color); min-width: 300px; height: 100%; display: flex; flex-direction: column; }
        .ticket-list-header { flex-shrink: 0; position: sticky; top: 0; z-index: 10; background-color: var(--secondary-bg); padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); font-size: 1.1rem; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
        .ticket-list-filters { display: flex; gap: 0.5rem; }
        .ticket-list-filters button { background: none; border: none; color: var(--text-muted); padding: 0.3rem 0.6rem; font-size: 0.8rem; font-weight: 500; cursor: pointer; border-radius: 6px; transition: all 0.2s; }
        .ticket-list-filters button:hover { background-color: var(--glass-background); color: var(--text-color); }
        .ticket-list-filters button.active { background-color: var(--primary-color); color: white; }

        .tickets-list-scroll { flex-grow: 1; overflow-y: auto; scrollbar-width: thin; scrollbar-color: var(--primary-color) transparent; }
        .tickets-list-scroll::-webkit-scrollbar { width: 5px; }
        .tickets-list-scroll::-webkit-scrollbar-thumb { background-color: var(--primary-color); border-radius: 10px; }

        .ticket-list-item { display: block; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); text-decoration: none; color: var(--text-color); transition: background-color 0.15s; }
        .ticket-list-item:last-child { border-bottom: none; }
        .ticket-list-item:hover { background-color: rgba(255, 255, 255, 0.05); }
        .ticket-list-item.active { background-color: var(--glass-background); border-left: 4px solid var(--primary-color); padding-left: calc(1.25rem - 4px); }
        .ticket-list-item h4 { font-size: 0.95rem; font-weight: 500; margin: 0; line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; gap: 0.5rem; }
        .ticket-list-item p { font-size: 0.8rem; color: var(--text-muted); margin: 0; line-height: 1.2; }
        .ticket-list-item-meta { display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem; }
        .ticket-list-item .badge { font-size: 0.7rem; }
        .ticket-list-item.new-message h4 { font-weight: 700; color: var(--primary-color); }

        .flags-container { display: flex; align-items: center; gap: 2px; flex-shrink: 0; }
        .flag { display: inline-block; width: 10px; height: 10px; border-radius: 50%; vertical-align: middle; }
        .flag-rating { background-color: var(--flag-rating-color); }
        .flag-wait { background-color: var(--flag-wait-color); }
        .flag-delay { background-color: var(--flag-delay-color); }

        /* Coluna de Visualização do Chat */
        .chat-view-column { background-color: var(--secondary-bg); border-radius: 12px; padding: 0; display: flex; flex-direction: column; border: 1px solid var(--border-color); height: 100%; overflow: hidden; }

        /* Chat Header */
        .chat-header { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: flex-start; flex-shrink: 0; flex-wrap: wrap; gap: 1rem;}
        .chat-header .info h2 { font-size: 1.1rem; margin: 0 0 0.2rem 0; font-weight: 600; }
        .chat-header .info p { font-size: 0.8rem; color: var(--text-muted); margin: 0; line-height: 1.3; }
        .chat-actions { display: flex; gap: 0.5rem; align-items: center; flex-shrink: 0; flex-wrap: wrap; }

        /* NOVO: Dropdown de Status (para atender o pedido) */
        .chat-actions .form-group { margin-bottom: 0; }
        .chat-actions .form-group select {
             padding: 0.4rem 0.8rem;
             font-size: 0.8rem;
             background-color: var(--background-color);
        }

        /* Detalhes do Usuário/Avaliação */
        .user-details-section { margin-top: 0.5rem; border-top: 1px dashed var(--border-color); padding-top: 0.5rem; }
        .user-details-section summary { cursor: pointer; font-size: 0.8rem; color: var(--info-color); font-weight: 500; list-style: none; }
        .user-details-section summary::-webkit-details-marker { display: none; }
        .user-details-section summary::before { content: '▶'; display: inline-block; font-size: 0.6em; margin-right: 0.4em; transition: transform 0.2s; }
        .user-details-section[open] summary::before { transform: rotate(90deg); }
        .user-details-section ul { list-style: none; padding: 0.5rem 0 0 1rem; margin: 0; }
        .user-details-section li { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.25rem; }
        .user-details-section li::before { content: "•"; color: var(--primary-color); display: inline-block; width: 1em; margin-left: -1em; }
        .user-details-section li a { color: var(--text-muted); } .user-details-section li a:hover { color: var(--info-color); }
        .ticket-rating { margin-top: 0.5rem; font-size: 0.85rem; color: var(--text-muted); }
        .ticket-rating strong { color: var(--text-color); font-weight: 500; }
        .ticket-rating span { vertical-align: middle; }
        .ticket-rating .rating-comment { font-style: italic; font-size: 0.8rem; margin-top: 0.3rem; padding-left: 0.5rem; border-left: 2px solid var(--border-color); }

        /* Barra de Alertas (Tempo/Status) */
        .chat-alerts-bar {
            background: var(--background-color); padding: 0.5rem 1.5rem; color: var(--text-color);
            display: flex; justify-content: space-between; align-items: center;
            font-size: 0.9rem; border-bottom: 1px solid var(--border-color);
            flex-shrink: 0; min-height: 40px;
        }
        .chat-alerts-bar .alert-text { font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .alert-text.critical {
            color: var(--flag-delay-color); animation: pulse-red 1.5s infinite;
            padding: 2px 5px; border-radius: 5px;
        }
        .alert-text.warning { color: var(--flag-wait-color); }
        .alert-text.satisfied { color: var(--success-color); }

        /* --- Estilos do Chat (Alinhamento Direita/Esquerda) --- */
        .chat-history { flex-grow: 1; overflow-y: auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; scrollbar-width: thin; scrollbar-color: var(--primary-color) transparent; }
        .message-wrapper { display: flex; gap: 0.75rem; max-width: 85%; }
        .message-bubble { padding: 0.75rem 1rem; border-radius: 12px; line-height: 1.5; word-wrap: break-word; width: fit-content; max-width: 100%; text-align: left; display: flex; flex-direction: column; }
        .message-meta { font-size: 0.7rem; color: var(--text-muted); margin-top: 0.5rem; align-self: flex-end; white-space: nowrap; }

        /* CORREÇÃO CHAT: Admin (Direita) */
        .message-wrapper.admin { align-self: flex-end; }
        .message-wrapper.admin .message-content { display: flex; flex-direction: column; align-items: flex-end; }
        .message-wrapper.admin .message-bubble { background-color: var(--primary-color); color: white; border-top-right-radius: 0; }
        .message-wrapper.admin .message-meta { color: rgba(255, 255, 255, 0.7); }

        /* CORREÇÃO CHAT: Usuário (Esquerda) */
        .message-wrapper.user { align-self: flex-start; flex-direction: row-reverse; }
        .message-wrapper.user .message-bubble { background-color: #2c3a4f; border-top-left-radius: 0; }

        .message-icon { flex-shrink: 0; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background-color: var(--glass-background); }
        .message-wrapper.admin .message-icon { background-color: var(--primary-color); }
        .message-wrapper.admin .message-icon svg { color: white; }
        .message-icon svg { width: 18px; height: 18px; color: var(--text-muted); }
        .chat-image-anexo { max-width: 100%; width: 250px; height: auto; border-radius: 8px; margin-top: 10px; cursor: pointer; display: block; }
        .chat-file-link { color: inherit; text-decoration: underline; margin-top: 5px; font-size: 0.85rem; display: block; }
        .message-system { align-self: center; text-align: center; font-size: 0.8rem; color: var(--text-muted); background-color: var(--glass-background); padding: 0.4rem 0.8rem; border-radius: 20px; margin: 0.5rem 0; }
        .chat-reply-form { border-top: 1px solid var(--border-color); padding: 1rem 1.5rem; flex-shrink: 0; }
        .no-ticket-selected { flex-grow: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; color: var(--text-muted); font-size: 1.1rem; padding: 2rem; }
        .no-ticket-selected svg { width: 40px; height: 40px; margin-bottom: 1rem; }

        /* ======================================= */
        /* RESPONSIVIDADE (Mobile) */
        /* ======================================= */
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-240px); }
            .main-content { margin-left: 0; width: 100%; padding: 1rem; padding-top: 5rem; height: 100vh; overflow-y: auto; }
            .menu-toggle { display: block; position: fixed; top: 1rem; left: 1rem; z-index: 1003;}
            body.sidebar-open .sidebar { transform: translateX(0); }
            body.sidebar-open::after { content: ''; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.7); z-index: 999; }

            /* Em mobile, o grid vira 1 coluna e JS controla a visão */
            .chat-grid-container { grid-template-columns: 1fr; height: auto; max-height: none; }
            /* Esconde a coluna de chat se não estiver ativa */
            body.list-view-active .chat-view-column { display: none; }
            /* Esconde a lista de tickets se o chat estiver ativo */
            body.chat-view-active .tickets-list-column { display: none; }
            body.chat-view-active .chat-view-column { display: flex !important; }
            /* Ajusta header para mobile quando no chat */
            body.chat-view-active .main-content header { margin-bottom: 0.5rem; padding: 0.75rem 1rem; }
            body.chat-view-active .main-content { padding-top: 4.5rem; }
            body.chat-view-active .menu-toggle { top: 0.75rem; left: 0.75rem; }
            .back-to-list-btn { display: block !important; } /* Força a exibição do botão 'Voltar' */

            .chat-header { padding: 1rem; }
            .chat-history { padding: 1rem; }
            .chat-reply-form { padding: 1rem; }
            .message-wrapper { max-width: 95%; }
        }
    </style>
</head>
<body class="<?php echo $ticket_id_view > 0 ? 'chat-view-active' : 'list-view-active'; ?>">

    <div class="menu-toggle" id="menu-toggle">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="24" height="24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
    </div>

    <?php include 'admin_sidebar.php'; ?>

    <main class="main-content">
        <header>
            <?php if ($ticket_id_view > 0): ?>
            <a href="tickets.php?filter=<?php echo $initial_filter; ?>" class="back-to-list-btn" style="text-decoration: none; color: var(--text-muted); display: none; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.9rem;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                Voltar à Lista
            </a>
            <?php endif; ?>

            <h1>Gerenciamento de Tickets</h1>
            <p>Gerencie as solicitações de suporte abertas pelos clientes.</p>
        </header>

        <?php if ($successMessage): ?><div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div><?php endif; ?>
        <?php if ($errorMessage && $ticket_id_view === 0): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <div class="chat-grid-container">

            <div class="tickets-list-column">
                <div class="ticket-list-header">
                    <span>Chats</span>
                    <div class="ticket-list-filters">
                        <button type="button" class="filter-btn <?php echo ($initial_filter === 'PENDENTES') ? 'active' : ''; ?>" data-filter="PENDENTES">Pendentes</button>
                        <button type="button" class="filter-btn <?php echo ($initial_filter === 'FECHADOS') ? 'active' : ''; ?>" data-filter="FECHADOS">Fechados</button>
                    </div>
                </div>
                <div class="tickets-list-scroll" id="tickets-list-area">
                    <div style="text-align: center; padding: 2rem; color: var(--text-muted);">Carregando tickets...</div>
                </div>
            </div>

            <div class="chat-view-column" id="chat-view-column">

            <?php if ($ticket_id_view > 0 && $view_data['ticket_info']):
                $ticket = $view_data['ticket_info'];
                $usuario = $view_data['usuario_ticket'];
                $pedidos_usuario = $view_data['usuario_pedidos'];
                $avaliacao = $ticket['atendimento_rating'];
                $comentario_avaliacao = $ticket['atendimento_comentario'];
                $status_options_list = $view_data['status_options'];
            ?>
                <div class="chat-header">
                    <div class="info">
                        <h2>#<?php echo $ticket['id']; ?>: <?php echo htmlspecialchars($ticket['assunto']); ?></h2>
                        <p>Aberto por: <strong><?php echo htmlspecialchars($usuario['nome'] ?? 'Usuário Deletado'); ?></strong> (<?php echo htmlspecialchars($usuario['email'] ?? 'N/A'); ?>)</p>

                        <div class="user-details-section">
                            <details>
                                <summary>Detalhes do Usuário</summary>
                                <p style="margin-top: 0.5rem; font-size: 0.8rem; font-weight: 500;">Últimos 5 Pedidos:</p>
                                <?php if (count($pedidos_usuario) > 0): ?>
                                    <ul>
                                        <?php foreach ($pedidos_usuario as $pedido): ?>
                                            <li>
                                                <a href="pedidos.php?view_pedido=<?php echo $pedido['id']; ?>" target="_blank">
                                                    Pedido #<?php echo $pedido['id']; ?>
                                                </a>
                                                (<?php echo formatCurrency($pedido['valor_total']); ?> - <?php echo htmlspecialchars($pedido['status']); ?>)
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p style="font-size: 0.8rem; color: var(--text-muted); padding-left: 1rem;">Nenhum pedido encontrado.</p>
                                <?php endif; ?>

                                <?php if ($ticket['status_nome'] === 'FECHADO' || $avaliacao !== null): ?>
                                <div class="ticket-rating">
                                    <strong>Avaliação:</strong> <span><?php echo renderStars($avaliacao); ?></span>
                                    <?php if (!empty($comentario_avaliacao)): ?>
                                        <p class="rating-comment">"<?php echo nl2br(htmlspecialchars($comentario_avaliacao)); ?>"</p>
                                    <?php elseif($avaliacao !== null): ?>
                                        <p class="rating-comment">(Sem comentário)</p>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </details>
                        </div>
                    </div>

                    <div class="chat-actions">
                        <div class="form-group">
                            <select id="ticket-status-select" data-ticket-id="<?php echo $ticket['id']; ?>" <?php echo ($ticket['status_nome'] === 'FECHADO') ? 'disabled' : ''; ?>>
                                <?php foreach ($status_options_list as $status): ?>
                                <option
                                    value="<?php echo $status['id']; ?>"
                                    <?php echo ($ticket['status_id'] == $status['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($status['nome']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <?php if ($ticket['status_nome'] !== 'FECHADO'): ?>
                            <form method="POST" action="tickets.php" onsubmit="return confirm('Tem certeza que deseja fechar este ticket?');" style="margin: 0;">
                                <input type="hidden" name="action" value="close_ticket">
                                <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                <button type="submit" class="btn btn-secondary btn-sm">Fechar Ticket</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="chat-alerts-bar">
                    <span class="alert-text" id="admin-delay-alert"></span>
                    <span id="delay-timer" style="font-weight: 700;"></span>
                </div>

                <div class="chat-history" id="chat-history"
                     data-ticket-id="<?php echo $ticket['id']; ?>"
                     data-last-message-id="<?php echo $view_data['last_message_id']; ?>">

                    <?php foreach ($view_data['mensagens'] as $msg): ?>
                        <?php
                            // CORREÇÃO: Usa 'admin' (tipo do seu DB)
                            $is_admin = ($msg['tipo_remetente'] === 'admin');
                            $is_system = (!empty($msg['mensagem']) && strpos($msg['mensagem'], '[TICKET') === 0);

                            if ($is_system):
                        ?>
                            <div class="message-system">
                                <?php echo htmlspecialchars($msg['mensagem']); ?> - <?php echo formatarDataHoraBR($msg['data_resposta']); ?>
                            </div>
                        <?php else:
                                $wrapper_class = $is_admin ? 'admin' : 'user';
                                $icon_svg = $is_admin ?
                                    '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"></path></svg>' :
                                    '<svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"></path></svg>';
                        ?>
                            <div class="message-wrapper <?php echo $wrapper_class; ?>">
                                <div class="message-content">
                                    <div class="message-bubble <?php echo $wrapper_class; ?>">
                                        <?php if (!empty($msg['mensagem'])) echo nl2br(htmlspecialchars($msg['mensagem'])); ?>

                                        <?php if (!empty($msg['anexo_dados'])):
                                            // Lendo o BLOB (agora é string binária)
                                            $anexo_data_base64 = base64_encode($msg['anexo_dados']);
                                            $anexo_tipo = htmlspecialchars($msg['anexo_tipo']);
                                            $anexo_nome = htmlspecialchars($msg['anexo_nome']);
                                            $dataUri = "data:$anexo_tipo;base64,$anexo_data_base64";
                                            $isImage = strpos($anexo_tipo, 'image/') === 0;
                                        ?>
                                            <?php if ($isImage): ?>
                                                <a href="<?php echo $dataUri; ?>" target="_blank"><img src="<?php echo $dataUri; ?>" alt="Anexo" class="chat-image-anexo"></a>
                                            <?php else: ?>
                                                <a href="<?php echo $dataUri; ?>" download="<?php echo $anexo_nome; ?>" class="chat-file-link">Anexo: <?php echo $anexo_nome; ?></a>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <div class="message-meta">
                                            <?php echo formatarDataHoraBR($msg['data_resposta']); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="message-icon"><?php echo $icon_svg; ?></div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <?php if ($ticket['status_nome'] !== 'FECHADO'): ?>
                    <div class="chat-reply-form">
                        <form method="POST" action="tickets.php?ajax_action=admin_reply" id="chat-reply-form" enctype="multipart/form-data">
                            <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                            <div class="form-group">
                                <textarea id="mensagem" name="mensagem" rows="3" placeholder="Sua Resposta..."></textarea>
                            </div>
                            <div class="form-group" style="margin-bottom: 0.5rem;">
                                <label for="anexo" style="font-size: 0.8rem; color: var(--text-muted);">Anexar Arquivo (Opcional - Max 5MB: JPG, PNG, GIF, PDF, TXT)</label>
                                <input type="file" name="anexo" id="anexo" accept=".jpg, .jpeg, .png, .gif, .pdf, .txt">
                            </div>
                            <button type="submit" class="btn btn-info">Enviar Resposta</button>
                        </form>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="no-ticket-selected">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-3.03 8.25-7.5 8.25a9.75 9.75 0 0 1-3.132-.549l-5.877 1.572a.75.75 0 0 1-.94-.94l1.572-5.877A9.75 9.75 0 0 1 3 12c0-4.556 3.03-8.25 7.5-8.25s7.5 3.694 7.5 8.25Z" /></svg>
                    <?php echo $errorMessage ?: 'Selecione um chat na coluna ao lado.'; ?>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </main>

 <script>
    document.addEventListener('DOMContentLoaded', () => {
        // --- Lógica Específica (suporte.php) ---
        const ticketsListArea = document.getElementById('tickets-list-area');
        const filterButtons = document.querySelectorAll('.ticket-list-filters button');
        const body = document.body;

        // Variável PHP está ok, assume-se que ela está definida no arquivo principal
        let currentFilter = '<?php echo $initial_filter; ?>';
        let loadTicketsTimeout;
        let pollingInterval;

        // --- Helpers JS ---
        const formatDate_JS = (dateStr) => {
             if (!dateStr) return 'N/A';
             try {
                 const date = new Date(dateStr);
                 if (isNaN(date.getTime())) return 'Data inválida';
                 return date.toLocaleString('pt-BR', {day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'});
             } catch (e) { return 'Data inválida'; }
        };
        const getBadgeHTML = (status, cor_badge) => {
             if (!cor_badge) cor_badge = '#888';
             let r = parseInt(cor_badge.substr(1, 2), 16);
             let g = parseInt(cor_badge.substr(3, 2), 16);
             let b = parseInt(cor_badge.substr(5, 2), 16);
             let luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
             let textColor = luminance > 0.5 ? '#000' : '#fff';
             return `<span style="background-color: ${cor_badge}; color: ${textColor}; padding: 0.3em 0.7em; border-radius: 1em; font-size: 0.75em; font-weight: 600; text-transform: uppercase;">${sanitizeHTML(status)}</span>`;
        };
        const sanitizeHTML = (str) => {
             if (!str) return '';
             return String(str).replace(/</g, "&lt;").replace(/>/g, "&gt;");
        }
        const formatTime = (totalSeconds) => {
             const absSeconds = Math.abs(totalSeconds);
             const minutes = Math.floor(absSeconds / 60);
             const seconds = absSeconds % 60;
             const sign = totalSeconds < 0 ? "-" : "";
             return `${sign}${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
        };

        // --- Função para criar HTML da mensagem (para AJAX/Polling) ---
        const createMessageHTML = (msg) => {
             const is_admin = msg.tipo_remetente === 'admin';
             const wrapper_class = is_admin ? 'admin' : 'user';

             let anexoHTML = '';
             if (msg.anexo_dados && msg.anexo_tipo && msg.anexo_nome) {
                 const dataUri = `data:${sanitizeHTML(msg.anexo_tipo)};base64,${msg.anexo_dados}`;
                 const isImage = msg.anexo_tipo.startsWith('image/');

                 if (isImage) {
                     anexoHTML = `<a href="${dataUri}" target="_blank"><img src="${dataUri}" alt="Anexo" class="chat-image-anexo"></a>`;
                 } else {
                     anexoHTML = `<a href="${dataUri}" download="${sanitizeHTML(msg.anexo_nome)}" class="chat-file-link">Anexo: ${sanitizeHTML(msg.anexo_nome)}</a>`;
                 }
             }

             let iconHTML = is_admin ?
                 '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"></path></svg>' :
                 '<svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"></path></svg>';

             const formattedDate = msg.data_resposta_br || formatDate_JS(msg.data_resposta);

             return `
                 <div class="message-wrapper ${wrapper_class}">
                     <div class="message-content">
                         <div class="message-bubble ${wrapper_class}">
                             ${msg.mensagem ? sanitizeHTML(msg.mensagem).replace(/\n/g, '<br>') : ''}
                             ${anexoHTML}
                             <div class="message-meta">
                                 ${formattedDate}
                             </div>
                         </div>
                     </div>
                     <div class="message-icon">${iconHTML}</div>
                 </div>`;
        };

        // --- Função para carregar a lista de tickets (Pendentes ou Fechados) ---
        let isFirstTicketLoad = true;
        const loadTickets = async (statusFilter = 'PENDENTES') => {
             clearTimeout(loadTicketsTimeout);

             if (isFirstTicketLoad && ticketsListArea) {
                 ticketsListArea.innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--text-muted);">Carregando...</div>';
                 isFirstTicketLoad = false;
             }

             try {
                 const response = await fetch(`tickets.php?ajax_action=get_tickets&filter_status=${statusFilter}`);
                 if (!response.ok) {
                     const errorText = await response.text();
                     throw new Error(`HTTP error! status: ${response.status} | Response: ${errorText}`);
                 }

                 const data = await response.json();

                 if (ticketsListArea) {
                     if (data.success) {
                         ticketsListArea.innerHTML = '';
                         // Variável PHP está ok, assume-se que ela está definida no arquivo principal
                         const currentTicketId = <?php echo $ticket_id_view; ?>;

                         if (data.tickets.length > 0) {
                             data.tickets.forEach(ticket => {
                                 const is_active = currentTicketId == ticket.id;

                                 // Flags
                                 let flagsHTML = '<span class="flags-container">';
                                 let flagText = '';
                                 if (ticket.flag_bad_rating == 1) {
                                     flagsHTML += '<span class="flag flag-rating" title="Usuário Insatisfeito"></span>';
                                     flagText += '(Insatisfeito) ';
                                 }
                                 if (ticket.flag_admin_delay == 1) {
                                     flagsHTML += '<span class="flag flag-delay" title="Atraso >5min"></span>';
                                     flagText += '(Usuário aguardando!) ';
                                 } else if (ticket.flag_long_wait == 1) {
                                     flagsHTML += '<span class="flag flag-wait" title="Espera >24h"></span>';
                                     flagText += '(Espera >24h) ';
                                 }
                                 flagsHTML += '</span>';

                                 const statusBadgeHTML = getBadgeHTML(ticket.status_nome, ticket.cor_badge);

                                 let itemHTML = `
                                     <a href="tickets.php?ticket_id=${ticket.id}&filter=${statusFilter}"
                                        class="ticket-list-item ${is_active ? 'active' : ''} ${ticket.flag_new == 1 ? 'new-message' : ''}"
                                        onclick="handleTicketClick(event, this)">
                                         <h4>
                                             ${flagsHTML}
                                             #${ticket.id} ${sanitizeHTML(ticket.assunto)}
                                             ${ticket.flag_new == 1 ? '<span class="badge badge-new">Novo</span>' : ''}
                                         </h4>
                                         ${flagText ? `<p style="font-weight: 600; color: ${ticket.flag_admin_delay == 1 ? 'var(--flag-delay-color)' : (ticket.flag_long_wait == 1 ? 'var(--flag-wait-color)' : 'var(--flag-rating-color)')};">${flagText.trim()}</p>` : ''}
                                         <div class="ticket-list-item-meta">
                                             <p>${sanitizeHTML(ticket.usuario_nome)}</p>
                                             <p>${ticket.data_ultima_atualizacao_br}</p>
                                         </div>
                                         <div style="margin-top: 5px;">
                                            ${statusBadgeHTML}
                                         </div>
                                     </a>
                                     `;
                                 ticketsListArea.insertAdjacentHTML('beforeend', itemHTML);
                             });
                         } else {
                             ticketsListArea.innerHTML = `<div style="text-align: center; padding: 2rem; color: var(--text-muted);">Nenhum chat ${statusFilter === 'PENDENTES' ? 'pendente' : 'fechado'}.</div>`;
                         }
                     } else {
                         ticketsListArea.innerHTML = `<div style="text-align: center; padding: 2rem; color: var(--error-color);">Falha: ${data.message || 'Erro'}</div>`;
                     }
                 }
             } catch (error) {
                 console.error('Erro ao carregar lista de tickets:', error);
                 if (ticketsListArea) {
                     ticketsListArea.innerHTML = `<div style="text-align: center; padding: 2rem; color: var(--error-color);">Falha ao carregar lista. Verifique o console.</div>`;
                 }
             } finally {
                 // Agenda próxima atualização só para pendentes
                 if (statusFilter === 'PENDENTES') {
                     const randomDelay = Math.random() * 5000 + 10000; // 10-15 segundos
                     loadTicketsTimeout = setTimeout(() => loadTickets('PENDENTES'), randomDelay);
                 }
             }
        };

        // --- Lógica de filtros e botões ---
        filterButtons.forEach(button => {
             button.addEventListener('click', () => {
                 const newFilter = button.dataset.filter;
                 if (newFilter !== currentFilter) {
                     currentFilter = newFilter;
                     filterButtons.forEach(btn => btn.classList.remove('active'));
                     button.classList.add('active');
                     clearTimeout(loadTicketsTimeout);
                     isFirstTicketLoad = true;
                     loadTickets(currentFilter);

                     const url = new URL(window.location);
                     url.searchParams.set('filter', currentFilter);
                     url.searchParams.delete('ticket_id');
                     window.history.pushState({}, '', url);
                     body.classList.remove('chat-view-active');
                     body.classList.add('list-view-active');
                     handleResize();
                 }
             });
        });

        // Inicia o carregamento da lista com o filtro inicial
        loadTickets(currentFilter);


        // ==========================================================
        // SÓ EXECUTA O JAVASCRIPT DO CHAT SE ESTIVER NA TELA DE CHAT
        // ==========================================================
        // Variável PHP está ok, assume-se que ela está definida no arquivo principal
        <?php if ($ticket_id_view > 0 && $view_data['ticket_info']): ?>

        const chatHistory = document.getElementById('chat-history');
        const statusSelect = document.getElementById('ticket-status-select');
        const delayTimerEl = document.getElementById('delay-timer');
        const adminDelayAlertEl = document.getElementById('admin-delay-alert');

        // --- Função de Cronômetro/Contador ---
        let timerInterval;
        let delayStartTime;
        const ADMIN_DELAY_LIMIT_S = 300; // 5 minutos

        const startDelayTimer = (secondsSinceUserMsg) => {
             if (!delayTimerEl || !adminDelayAlertEl) return;
             clearInterval(timerInterval);

             const currentStatusText = statusSelect.options[statusSelect.selectedIndex].text;

             if (secondsSinceUserMsg <= 0 || currentStatusText === 'FECHADO' || currentStatusText === 'AGUARDANDO RESPOSTA') {
                 adminDelayAlertEl.textContent = 'Status OK';
                 delayTimerEl.textContent = '';
                 adminDelayAlertEl.className = 'alert-text';

                 if (currentStatusText === 'AGUARDANDO RESPOSTA') {
                      adminDelayAlertEl.textContent = 'Aguardando resposta do usuário';
                 }
                 else if (currentStatusText === 'FECHADO') {
                     // Variável PHP está ok, assume-se que ela está definida no arquivo principal
                     const rating = <?php echo $view_data['ticket_info']['atendimento_rating'] ?? 'null'; ?>;
                     if (rating !== null && rating <= 2) {
                          adminDelayAlertEl.textContent = 'ATENÇÃO: Usuário Insatisfeito!';
                          adminDelayAlertEl.classList.add('critical');
                     } else if (rating !== null && rating > 2) {
                          adminDelayAlertEl.textContent = 'Atendimento Avaliado Positivamente';
                          adminDelayAlertEl.classList.add('satisfied');
                     } else {
                         adminDelayAlertEl.textContent = 'Ticket Fechado (Não avaliado)';
                     }
                 }
                 return;
             }

             delayStartTime = Date.now() - secondsSinceUserMsg * 1000;

             const updateTimer = () => {
                 if (!delayTimerEl || !adminDelayAlertEl) return;
                 const now = Date.now();
                 const elapsedSeconds = Math.floor((now - delayStartTime) / 1000);
                 const remainingToFlag = ADMIN_DELAY_LIMIT_S - elapsedSeconds;

                 if (remainingToFlag > 0) {
                     const displayTime = formatTime(remainingToFlag);
                     adminDelayAlertEl.textContent = 'Tempo para Responder:';
                     delayTimerEl.textContent = displayTime;
                     adminDelayAlertEl.className = 'alert-text';
                     if (remainingToFlag <= 60) {
                         adminDelayAlertEl.classList.add('warning');
                     }
                 } else {
                     const overdueSeconds = elapsedSeconds - ADMIN_DELAY_LIMIT_S;
                     const displayTime = formatTime(overdueSeconds);
                     adminDelayAlertEl.textContent = 'ATRASO CRÍTICO:';
                     delayTimerEl.textContent = `+${displayTime}`;
                     adminDelayAlertEl.className = 'alert-text critical';
                 }
             };
             updateTimer();
             timerInterval = setInterval(updateTimer, 1000);
        };

        // --- Lógica de Polling e Envio de Formulário ---
        const ticketId = chatHistory.dataset.ticketId;
        let lastMessageId = parseInt(chatHistory.dataset.lastMessageId || '0');

        // 1. Scroll inicial para o fim
        chatHistory.scrollTop = chatHistory.scrollHeight;

        // 2. Função de Polling (Check Novas Mensagens do Usuário)
        const checkNewMessages = async () => {
             try {
                 const params = new URLSearchParams({ajax_action: 'get_new_messages', ticket_id: ticketId, last_message_id: lastMessageId });
                 const response = await fetch(`tickets.php?${params.toString()}`);

                 if (!response.ok) {
                     const errorText = await response.text();
                     throw new Error(`Resposta não-JSON: ${errorText}`);
                 }

                 const data = await response.json();

                 if (data.success && data.messages.length > 0) {
                     let newLastId = lastMessageId;
                     data.messages.forEach(msg => {
                         chatHistory.insertAdjacentHTML('beforeend', createMessageHTML(msg));
                         newLastId = msg.id;
                     });

                     lastMessageId = newLastId;
                     chatHistory.dataset.lastMessageId = lastMessageId;

                     const isScrolledToBottom = chatHistory.scrollHeight - chatHistory.clientHeight <= chatHistory.scrollTop + 50;
                     if(isScrolledToBottom) {
                         chatHistory.scrollTop = chatHistory.scrollHeight;
                     }

                     startDelayTimer(0);
                     loadTickets(currentFilter);
                 }

                 if (data.success && data.status_changed && statusSelect && data.new_status_info) {
                      statusSelect.value = data.new_status_info.id;
                 }

             } catch (error) {
                 console.error('Erro ao buscar novas mensagens:', error);
                 clearInterval(pollingInterval);
             }
        };

        // 3. Inicia o Polling de mensagens no chat
        // Variável PHP está ok, assume-se que ela está definida no arquivo principal
        <?php if ($view_data['ticket_info']['status_nome'] !== 'FECHADO'): ?>
            pollingInterval = setInterval(checkNewMessages, 5000); // 5 segundos
        <?php endif; ?>

        // 4. Intercepta o envio do formulário de resposta (AJAX)
        const replyForm = document.getElementById('chat-reply-form');
        if (replyForm) {
             replyForm.addEventListener('submit', async function(e) {
                 e.preventDefault();

                 const formData = new FormData(replyForm);
                 const submitButton = replyForm.querySelector('button[type="submit"]');
                 const messageInput = replyForm.querySelector('#mensagem');
                 const fileInput = replyForm.querySelector('#anexo');

                 if (!messageInput.value.trim() && (!fileInput.files || fileInput.files.length === 0)) {
                     alert('Digite uma mensagem ou anexe um arquivo.');
                     return;
                 }

                 submitButton.disabled = true;
                 submitButton.textContent = 'Enviando...';

                 clearInterval(timerInterval);
                 startDelayTimer(0); // Reseta a barra de alerta

                 try {
                     const response = await fetch('tickets.php?ajax_action=admin_reply', {method: 'POST', body: formData});

                     if (!response.ok) {
                          const errorText = await response.text();
                          throw new Error(`Resposta não-JSON: ${errorText}`);
                     }

                     const data = await response.json();

                     if (data.success && data.message) {
                         chatHistory.insertAdjacentHTML('beforeend', createMessageHTML(data.message));
                         lastMessageId = data.message.id;
                         chatHistory.dataset.lastMessageId = lastMessageId;
                         chatHistory.scrollTop = chatHistory.scrollHeight;
                         replyForm.reset();

                         if (statusSelect) {
                             const statusAguardando = Array.from(statusSelect.options).find(opt => opt.text === 'AGUARDANDO RESPOSTA');
                             if (statusAguardando) {
                                 statusSelect.value = statusAguardando.value;
                             }
                         }
                         loadTickets(currentFilter);

                     } else {
                         alert('Erro ao enviar: ' + (data.message || 'Erro desconhecido.'));
                     }

                 } catch (error) {
                     console.error('Erro no fetch:', error);
                     alert('Erro de conexão ao enviar mensagem. Verifique o console.');
                 } finally {
                     submitButton.disabled = false;
                     submitButton.textContent = 'Enviar Resposta';
                 }
             });
        }

        // 5. Inicia o Timer de Resposta
        // Variável PHP está ok, assume-se que ela está definida no arquivo principal
        startDelayTimer(<?php echo $view_data['seconds_since_user_msg']; ?>);

        // 6. Handler para o dropdown de status (Independente)
        if (statusSelect) {
             // Variável PHP está ok, assume-se que ela está definida no arquivo principal
             let originalStatusId = '<?php echo $view_data['ticket_info']['status_id']; ?>';

             statusSelect.addEventListener('change', async function() {
                 const newStatusId = this.value;
                 const ticketId = this.dataset.ticketId;
                 const selectedOption = this.options[this.selectedIndex];

                 if (!confirm(`Tem certeza que deseja alterar o status deste ticket para "${selectedOption.text}"?`)) {
                      this.value = originalStatusId;
                      return;
                 }

                 try {
                     const formData = new FormData();
                     formData.append('ajax_action', 'update_status_only');
                     formData.append('ticket_id', ticketId);
                     formData.append('new_status_id', newStatusId);

                     const response = await fetch('tickets.php', { method: 'POST', body: formData });

                     if (!response.ok) {
                          const errorText = await response.text();
                          throw new Error(`Resposta não-JSON: ${errorText}`);
                     }

                     const data = await response.json();

                     if (data.success) {
                         originalStatusId = newStatusId;
                         loadTickets(currentFilter);

                         if (data.new_status_nome !== 'ABERTO') {
                              startDelayTimer(0);
                         } else {
                             // Se voltou para 'ABERTO', o timer deve reiniciar (ou seja, carregar o tempo original)
                              // Variável PHP está ok, assume-se que ela está definida no arquivo principal
                              startDelayTimer(<?php echo $view_data['seconds_since_user_msg']; ?>);
                         }
                     } else {
                         alert('Falha ao atualizar status: ' + (data.message || "Erro desconhecido"));
                         this.value = originalStatusId;
                     }
                 } catch (error) {
                     console.error('Erro ao atualizar status:', error);
                     alert('Falha ao atualizar status: ' + error.message);
                     this.value = originalStatusId;
                 }
             });
        }

        // Limpa o polling e timer ao fechar
        window.addEventListener('beforeunload', () => {
             if (pollingInterval) clearInterval(pollingInterval);
             clearInterval(timerInterval);
        });

        <?php endif; // --- FIM DO WRAPPER CRÍTICO (is_viewing_details) --- ?>

        // --- Lógica de Responsividade (Sempre executa) ---
        const handleResize = () => {
             // Variável PHP está ok, assume-se que ela está definida no arquivo principal
             const currentTicketId = <?php echo $ticket_id_view; ?>;
             if (window.innerWidth <= 1024) {
                 if (currentTicketId > 0) {
                     body.classList.add('chat-view-active');
                     body.classList.remove('list-view-active');
                 } else {
                     body.classList.add('list-view-active');
                     body.classList.remove('chat-view-active');
                 }
                 const backBtn = document.querySelector('.back-to-list-btn');
                 if (backBtn) {
                      backBtn.style.display = (currentTicketId > 0) ? 'inline-flex' : 'none';
                 }
             } else {
                 body.classList.remove('chat-view-active', 'list-view-active');
                 const backBtn = document.querySelector('.back-to-list-btn');
                 if (backBtn) backBtn.style.display = 'none';
             }
        };

        window.handleTicketClick = (event, element) => {
             if (window.innerWidth <= 1024) {
                 event.preventDefault();
                 window.location.href = element.href;
             }
        };

        handleResize();
        window.addEventListener('resize', handleResize);

        // --- Lógica do Menu (Fallback) REMOVIDA ---
        // A lógica do Menu Hamburguer/Sidebar foi removida
        // pois é controlada pelo script centralizado em admin_sidebar.php.

    });
    </script>
</body>
</html>