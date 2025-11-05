<?php
// admin_panel/usuarios.php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

require_once '../config/db.php'; // Conexão com o DB

$message = '';
$message_type = ''; // success, error, info, warning

// --- INICIALIZAÇÃO DE VARIÁVEIS ---
$edit_usuario = null;
$user_enderecos = [];
$edit_endereco = null;
$is_viewing_details = false;
$is_editing_endereco = false;
$perfil_completo = false;
$motivos_incompleto = [];

// ==========================================================
// LÓGICA DE AÇÕES (DELETE, TOGGLE, SAVE)
// ==========================================================

// 1. DELETAR USUÁRIO (Ação da Lista)
if (isset($_GET['delete_usuario'])) {
    $id = (int)$_GET['delete_usuario'];
    $pdo->beginTransaction();
    try {
        $stmt_del_end = $pdo->prepare("DELETE FROM enderecos WHERE usuario_id = :id");
        $stmt_del_end->execute(['id' => $id]);
        $sql = "DELETE FROM usuarios WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $pdo->commit();
        $message = "Usuário e seus endereços foram removidos com sucesso!"; $message_type = "success";
        header("Location: usuarios.php?deleted=true"); exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() == '23503') {
            $message = "Erro: Não é possível remover este usuário, pois ele possui pedidos registrados. Considere bloquear o usuário.";
        } else {
            $message = "Erro ao remover usuário: " . $e->getMessage();
        }
        $message_type = "error";
    }
}

// 2. BLOQUEAR/DESBLOQUEAR USUÁRIO (Ação da Lista) - *** CORRIGIDO ***
if (isset($_GET['toggle_block'])) {
    $id = (int)$_GET['toggle_block'];
    try {
        $stmt_check = $pdo->prepare("SELECT is_bloqueado FROM usuarios WHERE id = :id");
        $stmt_check->execute(['id' => $id]);
        $is_bloqueado_atual = $stmt_check->fetchColumn();

        $novo_status = !(bool)$is_bloqueado_atual;
        $novo_motivo = $novo_status ? "Bloqueado pelo administrador." : null;

        $sql = "UPDATE usuarios SET is_bloqueado = :status, motivo_bloqueio = :motivo WHERE id = :id";
        $stmt = $pdo->prepare($sql);

        $stmt->bindParam(':status', $novo_status, PDO::PARAM_BOOL);
        $stmt->bindParam(':motivo', $novo_motivo, $novo_motivo === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $message = $novo_status ? "Usuário bloqueado!" : "Usuário desbloqueado!";
        $message_type = "success";
        header("Location: usuarios.php?status_changed=true"); exit;

    } catch (PDOException $e) {
        $message = "Erro ao alterar status do usuário: " . $e->getMessage();
        $message_type = "error";
    }
}

// 3. DELETAR ENDEREÇO (Ação da Página de Detalhes)
if (isset($_GET['delete_endereco'])) {
    $endereco_id = (int)$_GET['delete_endereco'];
    $usuario_id_redirect = (int)$_GET['view_user'];

    try {
        $sql = "DELETE FROM enderecos WHERE id = :id AND usuario_id = :uid";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $endereco_id, 'uid' => $usuario_id_redirect]);

        $message = "Endereço removido com sucesso!";
        $message_type = "success";
        header("Location: usuarios.php?view_user=" . $usuario_id_redirect . "&endereco_deleted=true#section-enderecos");
        exit;

    } catch (PDOException $e) {
        $message = "Erro ao remover endereço: ". $e->getMessage();
        $message_type = "error";
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $message_type;
        header("Location: usuarios.php?view_user=" . $usuario_id_redirect . "#section-enderecos");
        exit;
    }
}

// 4. ATUALIZAR USUÁRIO (Formulário de Detalhes)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_usuario'])) {
    $id = (int)$_POST['id'];
    $nome = trim($_POST['nome']);
    $email = trim(strtolower($_POST['email']));
    $cpf = preg_replace('/[^0-9]/', '', trim($_POST['cpf']));
    $data_nascimento = !empty($_POST['data_nascimento']) ? $_POST['data_nascimento'] : null;
    $telefone_celular = trim($_POST['telefone_celular']);
    $telefone_fixo = trim($_POST['telefone_fixo']);
    $nova_senha = trim($_POST['nova_senha']);
    $is_bloqueado = isset($_POST['is_bloqueado']);
    $motivo_bloqueio = trim($_POST['motivo_bloqueio']);
    $receber_promocoes = isset($_POST['receber_promocoes']);

    if (empty($nome) || empty($email)) {
        $message = "Nome e E-mail são obrigatórios.";
        $message_type = "error";
    } else {
        try {
            $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE (email = :email OR (cpf = :cpf AND cpf IS NOT NULL AND cpf <> '')) AND id != :id");
            $stmt_check->execute(['email' => $email, 'cpf' => $cpf, 'id' => $id]);

            if ($stmt_check->fetch()) {
                $message = "Erro: O e-mail ou CPF informado já pertence a outro usuário.";
                $message_type = "error";
            } else {
                $sql_parts = [
                    "nome = :nome", "email = :email", "cpf = :cpf",
                    "data_nascimento = :data_nascimento", "telefone_celular = :telefone_celular",
                    "telefone_fixo = :telefone_fixo",
                    "receber_promocoes = :receber_promocoes",
                    "is_bloqueado = :is_bloqueado", "motivo_bloqueio = :motivo_bloqueio"
                ];
                $params = [
                    ':nome' => $nome,
                    ':email' => $email,
                    ':cpf' => !empty($cpf) ? $cpf : null,
                    ':data_nascimento' => $data_nascimento,
                    ':telefone_celular' => !empty($telefone_celular) ? $telefone_celular : null,
                    ':telefone_fixo' => !empty($telefone_fixo) ? $telefone_fixo : null,
                    ':receber_promocoes' => $receber_promocoes,
                    ':is_bloqueado' => $is_bloqueado,
                    ':motivo_bloqueio' => $is_bloqueado ? $motivo_bloqueio : null,
                    ':id' => $id
                ];

                if (!empty($nova_senha)) {
                    if (strlen($nova_senha) < 6) {
                        $message = "A nova senha deve ter pelo menos 6 caracteres.";
                        $message_type = "error";
                    } else {
                        $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                        $sql_parts[] = "senha = :senha";
                        $params[':senha'] = $senha_hash;
                    }
                }

                if (empty($message)) {
                    $sql = "UPDATE usuarios SET " . implode(", ", $sql_parts) . " WHERE id = :id";
                    $stmt = $pdo->prepare($sql);

                    $stmt->bindParam(':receber_promocoes', $receber_promocoes, PDO::PARAM_BOOL);
                    $stmt->bindParam(':is_bloqueado', $is_bloqueado, PDO::PARAM_BOOL);

                    foreach ($params as $key => &$val) {
                         if ($key !== ':receber_promocoes' && $key !== ':is_bloqueado') {
                             $type = PDO::PARAM_STR;
                             if (is_null($val)) $type = PDO::PARAM_NULL;
                             if (is_int($val)) $type = PDO::PARAM_INT;
                             $stmt->bindParam($key, $val, $type);
                         }
                    }
                    unset($val);

                    $stmt->execute();
                    $message = "Usuário atualizado com sucesso!";
                    $message_type = "success";
                    header("Location: usuarios.php?view_user=" . $id . "&saved=true");
                    exit;
                }
            }
        } catch (PDOException $e) {
            $message = "Erro ao atualizar usuário: " . $e->getMessage();
            $message_type = "error";
        }
    }
    $is_viewing_details = true;
    $edit_usuario = $_POST;
    $edit_usuario['id'] = $id;
}

// 5. SALVAR/ADICIONAR ENDEREÇO (LÓGICA UNIFICADA)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_endereco'])) {
    $endereco_id = !empty($_POST['endereco_id']) ? (int)$_POST['endereco_id'] : null;
    $usuario_id = (int)$_POST['usuario_id_redirect'];
    $nome_endereco = trim($_POST['nome_endereco']);
    $cep = trim($_POST['cep']);
    $endereco = trim($_POST['endereco']);
    $numero = trim($_POST['numero']);
    $complemento = trim($_POST['complemento']);
    $bairro = trim($_POST['bairro']);
    $cidade = trim($_POST['cidade']);
    $estado = trim($_POST['estado']);
    $destinatario = trim($_POST['destinatario']);
    $is_principal = isset($_POST['is_principal']);

    if (empty($nome_endereco) || empty($cep) || empty($endereco) || empty($numero) || empty($bairro) || empty($cidade) || empty($estado) || empty($destinatario)) {
         $message = "Erro: Todos os campos do endereço (exceto complemento) são obrigatórios.";
         $message_type = "error";
         $is_viewing_details = true;
         $_GET['view_user'] = $usuario_id;
         $edit_endereco = $_POST; // Recarrega dados do POST no form
         $is_editing_endereco = (bool)$endereco_id; // Mantém o estado de edição/adição
    } else {
        try {
            if ($is_principal) {
                $stmt_clear = $pdo->prepare("UPDATE enderecos SET is_principal = false WHERE usuario_id = :usuario_id");
                $stmt_clear->execute(['usuario_id' => $usuario_id]);
            }

            $params_end = [
                ':nome_endereco' => $nome_endereco,
                ':cep' => $cep,
                ':endereco' => $endereco,
                ':numero' => $numero,
                ':complemento' => !empty($complemento) ? $complemento : null,
                ':bairro' => $bairro,
                ':cidade' => $cidade,
                ':estado' => $estado,
                ':destinatario' => $destinatario,
                ':is_principal' => $is_principal,
                ':usuario_id' => $usuario_id
            ];

            if ($endereco_id) { // UPDATE
                $sql = "UPDATE enderecos SET
                            nome_endereco = :nome_endereco, cep = :cep, endereco = :endereco, numero = :numero,
                            complemento = :complemento, bairro = :bairro, cidade = :cidade, estado = :estado,
                            destinatario = :destinatario, is_principal = :is_principal
                        WHERE id = :id AND usuario_id = :usuario_id";
                $params_end[':id'] = $endereco_id;
                $message = "Endereço atualizado com sucesso!";
            } else { // INSERT
                 $sql = "INSERT INTO enderecos
                            (usuario_id, nome_endereco, cep, endereco, numero, complemento, bairro, cidade, estado, destinatario, is_principal)
                         VALUES
                            (:usuario_id, :nome_endereco, :cep, :endereco, :numero, :complemento, :bairro, :cidade, :estado, :destinatario, :is_principal)";
                 $message = "Endereço adicionado com sucesso!";
            }

            $stmt = $pdo->prepare($sql);

            // Bind explícito para booleanos
            $stmt->bindParam(':is_principal', $is_principal, PDO::PARAM_BOOL);

            // Bind dos outros parâmetros
            foreach ($params_end as $key => &$val) {
                 if ($key !== ':is_principal') {
                     $type = PDO::PARAM_STR;
                     if (is_null($val)) $type = PDO::PARAM_NULL;
                     if (is_int($val)) $type = PDO::PARAM_INT;
                     $stmt->bindParam($key, $val, $type);
                 }
            }
            unset($val);
            $stmt->execute();

            $message_type = "success";
            header("Location: usuarios.php?view_user=" . $usuario_id . "&endereco_saved=true#section-enderecos");
            exit;

        } catch (PDOException $e) {
            $message = "Erro ao salvar endereço: " . $e->getMessage();
            $message_type = "error";
            $is_viewing_details = true;
            $_GET['view_user'] = $usuario_id;
            $edit_endereco = $_POST;
            $is_editing_endereco = (bool)$endereco_id;
        }
    }
}


// ==========================================================
// LÓGICA DE VISUALIZAÇÃO DE DETALHES / EDIÇÃO DE ENDEREÇO
// ==========================================================

if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

if (isset($_GET['view_user'])) {
    $id = (int)$_GET['view_user'];
    $is_viewing_details = true;

    // Busca dados do usuário (necessário em todos os casos, mesmo com erro de POST)
    $stmt_user = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id");
    $stmt_user->execute(['id' => $id]);
    $usuario_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

    // Se houve erro no POST de usuário, $edit_usuario já está setado. Senão, usa o do banco.
    if (empty($message) || !$edit_usuario) {
        $edit_usuario = $usuario_data;
    }

    if ($edit_usuario) {
        // Busca os endereços do usuário
        try {
             $stmt_enderecos = $pdo->prepare("SELECT * FROM enderecos WHERE usuario_id = :id ORDER BY is_principal DESC, id DESC");
             $stmt_enderecos->execute(['id' => $id]);
             $user_enderecos = $stmt_enderecos->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
             $message .= " Erro ao carregar endereços: " . $e->getMessage();
             $message_type = "error";
        }

        // LÓGICA PERFIL COMPLETO (ATUALIZADA)
        if (empty($edit_usuario['cpf'])) { $motivos_incompleto[] = 'CPF não preenchido'; }
        if (empty($edit_usuario['data_nascimento'])) { $motivos_incompleto[] = 'Data de nascimento não preenchida'; }
        if (empty($edit_usuario['telefone_celular'])) { $motivos_incompleto[] = 'Celular não preenchido'; }
        if (empty($user_enderecos)) { $motivos_incompleto[] = 'Nenhum endereço cadastrado'; }
        if (empty($motivos_incompleto)) { $perfil_completo = true; }

        // MODO DE EDIÇÃO DE ENDEREÇO
        if (isset($_GET['edit_endereco'])) {
             $endereco_id_edit = (int)$_GET['edit_endereco'];
             if (empty($edit_endereco)) { // Se não houve erro no post de endereço
                $stmt_edit_end = $pdo->prepare("SELECT * FROM enderecos WHERE id = :id AND usuario_id = :uid");
                $stmt_edit_end->execute(['id' => $endereco_id_edit, 'uid' => $id]);
                $edit_endereco = $stmt_edit_end->fetch(PDO::FETCH_ASSOC);
             }

             if ($edit_endereco) {
                 $is_editing_endereco = true;
             } else if (empty($message)) {
                 $message = "Endereço não encontrado para edição.";
                 $message_type = "warning";
             }
        }

        // Mensagens de feedback
        if (isset($_GET['saved']) && empty($message)) { $message = "Usuário salvo com sucesso!"; $message_type = "success"; }
        if (isset($_GET['endereco_saved']) && empty($message)) { $message = "Endereço salvo com sucesso!"; $message_type = "success"; }
        if (isset($_GET['endereco_deleted']) && empty($message)) { $message = "Endereço removido com sucesso!"; $message_type = "success"; }

    } else {
         $is_viewing_details = false;
         if (empty($message)) {
            $message = "Usuário não encontrado.";
            $message_type = "warning";
         }
    }
}

// Mensagens de feedback da lista principal
if (isset($_GET['deleted']) && empty($message)) { $message = "Usuário removido com sucesso!"; $message_type = "success"; }
if (isset($_GET['status_changed']) && empty($message)) { $message = "Status do usuário alterado!"; $message_type = "success"; }


// ==========================================================
// LÓGICA DE LEITURA (LISTA E CARDS)
// ==========================================================
$all_usuarios = [];
$stats = ['total' => 0, 'hoje' => 0, 'completos' => 0];
try {
    // Estatísticas
    $stats['total'] = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    $stats['hoje'] = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE DATE(criado_em) = CURRENT_DATE")->fetchColumn();

    // Query Card Perfis Completos (ATUALIZADA)
    $sql_stats_completos = "
        SELECT COUNT(DISTINCT u.id)
        FROM usuarios u
        JOIN enderecos e ON u.id = e.usuario_id
        WHERE u.cpf IS NOT NULL AND u.cpf <> ''
          AND u.telefone_celular IS NOT NULL AND u.telefone_celular <> ''
          AND u.data_nascimento IS NOT NULL
    ";
    $stats['completos'] = $pdo->query($sql_stats_completos)->fetchColumn();

    // Query Principal
    $sql_where = "";
    $params_where = [];
    $search_term = trim($_GET['search'] ?? '');
    if (!empty($search_term)) {
        $search = '%' . $search_term . '%';
        $sql_where = " WHERE (u.nome ILIKE :search OR u.email ILIKE :search)";
        $params_where[':search'] = $search;
    }

    $stmt_usuarios = $pdo->prepare("
        SELECT
            u.id, u.nome, u.email, u.cpf, u.criado_em, u.is_bloqueado,
            COUNT(p.id) AS total_pedidos
        FROM usuarios u
        LEFT JOIN pedidos p ON u.id = p.usuario_id
        $sql_where
        GROUP BY u.id, u.nome, u.email, u.cpf, u.criado_em, u.is_bloqueado
        ORDER BY u.nome ASC
    ");
    $stmt_usuarios->execute($params_where);
    $all_usuarios = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message .= " Erro ao carregar dados da página: " . $e->getMessage();
    $message_type = "error";
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>

    <style>
        /* ==========================================================
           CSS COMPLETO DO PAINEL ADMIN (Com todas as adições)
           ========================================================== */
        :root {
            --primary-color: #4a69bd; --secondary-color: #6a89cc; --text-color: #f9fafb;
            --light-text-color: #9ca3af; --border-color: rgba(255, 255, 255, 0.1); --background-color: #111827;
            --sidebar-color: #1f2937; --glass-background: rgba(31, 41, 55, 0.7);
            --success-bg: rgba(40, 167, 69, 0.3); --success-text: #c3e6cb;
            --error-bg: rgba(220, 53, 69, 0.3); --error-text: #f5c6cb;
            --info-bg: rgba(0, 123, 255, 0.2); --info-text: #bee5eb;
            --warning-bg: rgba(255, 193, 7, 0.2); --warning-text: #ffeeba;
            --danger-color: #e74c3c; --danger-color-hover: #c0392b;
            --sidebar-width: 240px; --border-radius: 8px;
            --box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--background-color); color: var(--text-color); display: flex; min-height: 100vh; overflow-x: hidden; line-height: 1.6; }
        #particles-js { position: fixed; width: 100%; height: 100%; top: 0; left: 0; z-index: -1; opacity: 0.6; }
        a { color: var(--primary-color); text-decoration: none; transition: color 0.2s ease;} a:hover { color: var(--secondary-color); text-decoration: underline;}

        /* --- Sidebar --- */
        .sidebar { width: var(--sidebar-width); background-color: var(--sidebar-color); height: 100vh; position: fixed; left: 0; top: 0; padding: 1.5rem; display: flex; flex-direction: column; border-right: 1px solid var(--border-color); z-index: 1000; transition: transform 0.3s ease; box-shadow: var(--box-shadow); }
        .sidebar .logo-area { display: flex; flex-direction: column; align-items: center; margin-bottom: 1rem; } .sidebar .logo-circle { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 0.5rem; box-shadow: 0 0 10px rgba(74, 105, 189, 0.6); overflow: hidden; background-color: #fff; } .sidebar .logo-circle svg { color: var(--primary-color); width: 24px; height: 24px; } .sidebar .logo-text { font-size: 1rem; font-weight: 600; color: var(--text-color); text-align: center; } .sidebar .divider { width: 100%; height: 1px; background-color: var(--border-color); margin: 1rem 0; } .sidebar nav { flex-grow: 1; } .sidebar nav a { display: flex; align-items: center; gap: 0.8rem; padding: 0.8rem 1rem; color: var(--light-text-color); text-decoration: none; border-radius: var(--border-radius); margin-bottom: 0.5rem; transition: all 0.3s ease; border: 1px solid transparent; background-color: transparent; } .sidebar nav a:hover, .sidebar nav a.active { background-color: var(--glass-background); color: var(--text-color); border-color: var(--primary-color); box-shadow: 0 2px 8px rgba(74, 105, 189, 0.4); } .sidebar nav a svg { width: 20px; height: 20px; flex-shrink: 0; } .user-profile { position: relative; margin-top: auto; background-color: var(--glass-background); padding: 0.75rem; border-radius: var(--border-radius); display: flex; align-items: center; gap: 1rem; cursor: pointer; border: 1px solid var(--border-color); transition: all 0.3s ease; } .user-profile:hover { border-color: var(--primary-color); } .avatar { width: 35px; height: 35px; border-radius: 50%; background-color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.9rem; } .user-info .user-name { font-weight: 600; font-size: 0.85rem; line-height: 1.2; } .user-info .user-level { font-size: 0.7rem; color: var(--light-text-color); } .profile-dropdown { position: absolute; bottom: calc(100% + 10px); left: 0; width: 100%; background-color: #2c3a4f; border-radius: var(--border-radius); border: 1px solid var(--border-color); padding: 0.5rem; z-index: 20; visibility: hidden; opacity: 0; transform: translateY(10px); transition: all 0.3s ease; } .profile-dropdown.show { visibility: visible; opacity: 1; transform: translateY(0); } .profile-dropdown a { display: flex; gap: 0.75rem; padding: 0.75rem; color: var(--light-text-color); font-size: 0.85rem; border-radius: 6px; } .profile-dropdown a:hover { background-color: var(--glass-background); color: var(--text-color); }
        .sidebar nav .sidebar-submenu { padding-left: 20px; margin-top: -5px; margin-bottom: 5px; overflow: hidden; transition: max-height 0.3s ease-out; max-height: 0; } .sidebar nav .sidebar-submenu.open { max-height: 500px; } .sidebar nav a.has-children { display: flex; justify-content: space-between; align-items: center; } .sidebar nav a .menu-chevron { width: 16px; height: 16px; color: var(--light-text-color); transition: transform 0.3s ease; } .sidebar nav a.open .menu-chevron { transform: rotate(90deg); } .sidebar-submenu a { font-size: 0.9em; padding: 0.7rem 1rem 0.7rem 1.5rem; color: var(--light-text-color); position: relative; } .sidebar-submenu a::before { content: ''; position: absolute; left: 0.5rem; top: 50%; transform: translateY(-50%); width: 4px; height: 4px; border-radius: 50%; background-color: var(--light-text-color); transition: all 0.3s ease; } .sidebar-submenu a:hover { color: var(--text-color); background-color: transparent; border-color: transparent; box-shadow: none; } .sidebar-submenu a:hover::before { background-color: var(--primary-color); } .sidebar-submenu a.active-child { color: #fff; font-weight: 600; } .sidebar-submenu a.active-child::before { background-color: var(--primary-color); transform: translateY(-50%) scale(1.5); }

        /* --- Conteúdo Principal --- */
        .main-content { margin-left: var(--sidebar-width); flex-grow: 1; padding: 2rem 2.5rem; min-height: 100vh; overflow-y: auto; transition: margin-left 0.3s ease, width 0.3s ease; width: calc(100% - var(--sidebar-width)); }
        .content-header { margin-bottom: 2rem; background: var(--glass-background); padding: 1.5rem 2rem; border-radius: var(--border-radius); border: 1px solid var(--border-color); backdrop-filter: blur(5px); }
        .content-header h1 { font-size: 1.8rem; font-weight: 600; color: var(--primary-color); margin: 0 0 0.25rem 0; }
        .content-header p { font-size: 1rem; color: var(--light-text-color); margin: 0; }

        /* --- Stat Cards --- */
         .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-bottom: 2.5rem; }
         .stat-card { background: var(--glass-background); padding: 1.5rem; border-radius: var(--border-radius); border: 1px solid var(--border-color); box-shadow: var(--box-shadow); text-align: left; display: flex; flex-direction: column; justify-content: space-between; }
         .stat-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
         .stat-card-header h4 { font-size: 1rem; color: var(--light-text-color); margin: 0; font-weight: 500; }
         .stat-card-header .icon { font-size: 1.5rem; color: var(--primary-color); }
         .stat-card-header .icon svg { width: 24px; height: 24px; }
         .stat-card .value { font-size: 2.5rem; font-weight: 700; color: var(--text-color); line-height: 1.2; }
         .stat-card.highlight .value, .stat-card.highlight .icon { color: var(--warning-text); }
         .stat-card.success .value, .stat-card.success .icon { color: var(--success-text); }

        /* --- Seções CRUD --- */
        .crud-section { margin-bottom: 2.5rem; }
        .crud-section h3 {
            font-size: 1.25rem; color: var(--text-color); margin-bottom: 1.25rem;
            padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color); font-weight: 600;
            display: flex; justify-content: space-between; align-items: center;
        }
        .form-container, .list-container, .filter-container { background: var(--glass-background); padding: 1.5rem 2rem; border-radius: var(--border-radius); border: 1px solid var(--border-color); box-shadow: var(--box-shadow); margin-bottom: 2rem; backdrop-filter: blur(5px); }
        .form-container h4 { font-size: 1.1rem; color: var(--primary-color); margin-bottom: 1.25rem; font-weight: 600; }

        /* --- Formulários --- */
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--light-text-color); font-size: 0.8rem; text-transform: uppercase; }
        .form-group input[type="text"], .form-group input[type="email"], .form-group input[type="password"], .form-group input[type="date"], .form-group textarea, .form-group select { width: 100%; padding: 0.65rem 0.8rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); background-color: rgba(0, 0, 0, 0.3); color: var(--text-color); box-sizing: border-box; transition: border-color 0.3s ease, box-shadow 0.3s ease; font-size: 0.9em; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color: var(--primary-color); box-shadow: 0 0 8px rgba(74, 105, 189, 0.5); outline: none; }
        .form-group input[type="date"] { color-scheme: dark; }
        .form-group textarea { min-height: 80px; resize: vertical; }
        .form-group-check { display: flex; align-items: center; padding-top: 0; margin-bottom: 0.5rem; }
        .form-group-check label { font-weight: 400; color: var(--text-color); display: inline; margin-left: 0.5rem; text-transform: none; cursor: pointer; }
        .form-group-check input[type="checkbox"] { width: auto; vertical-align: middle; accent-color: var(--primary-color); cursor: pointer; margin-right: 0.5rem; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem 1.5rem; }
        .form-actions { display: flex; gap: 1rem; margin-top: 1.5rem; flex-wrap: wrap; }
        button[type="submit"], .btn { padding: 0.65rem 1.2rem; background-color: var(--primary-color); color: #fff; border: none; border-radius: var(--border-radius); cursor: pointer; font-weight: 600; transition: background-color 0.3s ease, transform 0.1s ease; font-size: 0.9em; }
        button[type="submit"]:hover, .btn:hover { background-color: var(--secondary-color); transform: translateY(-1px); }
        button[type="submit"].update, .btn.update { background-color: #28a745; }
        button[type="submit"].update:hover, .btn.update:hover { background-color: #218838; }
        a.cancel, .btn.cancel { color: var(--background-color) !important; background-color: var(--light-text-color); text-decoration: none; display: inline-block; padding: 0.65rem 1.2rem; }
        a.cancel:hover, .btn.cancel:hover { background-color: #bbb; text-decoration: none; color: var(--background-color) !important; }
        .btn.danger { background-color: var(--danger-color); }
        .btn.danger:hover { background-color: var(--danger-color-hover); }
        .cep-loading { /* NOVO: Para feedback do CEP */
            font-size: 0.8em;
            color: var(--warning-text);
            margin-left: 10px;
            display: none; /* Começa escondido */
        }

        /* --- Tabelas --- */
        .list-container { overflow-x: auto; }
        .list-container table { width: 100%; border-collapse: collapse; background-color: transparent; border-radius: 0; overflow: hidden; font-size: 0.85em; border: none; min-width: 800px; }
        .list-container th, .list-container td { border-bottom: 1px solid var(--border-color); padding: 10px 12px; text-align: left; vertical-align: middle; }
        .list-container th { background-color: rgba(0, 0, 0, 0.4); color: var(--light-text-color); font-weight: 600; text-transform: uppercase; font-size: 0.75em; }
        .list-container tbody tr:last-child td { border-bottom: none; }
        .list-container tbody tr:hover { background-color: rgba(255, 255, 255, 0.05); }
        .list-container td.actions { white-space: nowrap; text-align: left; }
        .list-container .actions a, .list-container .actions button { color: var(--primary-color); margin-left: 0.8rem; font-size: 0.85em; transition: color 0.2s ease; background: none; border: none; font-family: 'Poppins', sans-serif; cursor: pointer; padding: 0; }
        .list-container .actions a:hover, .list-container .actions button:hover { color: var(--secondary-color); text-decoration: underline; }
        .list-container .actions .action-icon { color: var(--light-text-color); text-decoration: none; }
        .list-container .actions .action-icon:hover { color: var(--primary-color); }
        .list-container .actions .action-icon.delete:hover { color: var(--danger-color); }
        .list-container .actions .action-icon svg { width: 18px; height: 18px; vertical-align: middle; }
        .list-container .status-icon svg { width: 20px; height: 20px; vertical-align: middle; }
        .status-ativo { color: #82e0aa; }
        .status-inativo { color: var(--danger-color); }
        .list-container a.pedidos-link { color: var(--text-color); font-weight: 600; }
        .list-container a.pedidos-link:hover { color: var(--primary-color); }
        .list-container .no-results-message { text-align: center; color: var(--light-text-color); padding: 1.5rem; }

        /* --- Mensagens --- */
        .message { padding: 1rem; border-radius: var(--border-radius); margin-bottom: 1.5rem; font-weight: 600; border: 1px solid transparent; backdrop-filter: blur(3px); }
        .message.success { background-color: var(--success-bg); color: var(--success-text); border-color: rgba(21, 87, 36, 0.5); }
        .message.error { background-color: var(--error-bg); color: var(--error-text); border-color: rgba(114, 28, 36, 0.5); }
        .message.info { background-color: var(--info-bg); color: var(--info-text); border-color: rgba(8, 66, 152, 0.5); }
        .message.warning { background-color: var(--warning-bg); color: var(--warning-text); border-color: rgba(255,193,7,0.5); }

        /* --- Flag Perfil Completo (NOVO) --- */
        .badge-status-completo, .badge-status-incompleto {
            font-size: 0.6em;
            font-weight: 600;
            padding: 0.3em 0.8em;
            border-radius: 1em;
            text-transform: uppercase;
            vertical-align: middle;
            margin-left: 1rem;
            position: relative;
        }
        .badge-status-completo {
            background-color: var(--success-bg);
            color: var(--success-text);
            border: 1px solid var(--success-text);
        }
        .badge-status-incompleto {
            background-color: var(--warning-bg);
            color: var(--warning-text);
            border: 1px solid var(--warning-text);
            cursor: pointer;
        }

        /* --- Popover de Motivos (NOVO) --- */
        .perfil-popover {
            display: none;
            position: absolute;
            top: 120%; /* Abaixo da flag */
            left: 50%;
            transform: translateX(-50%);
            min-width: 280px;
            background: var(--sidebar-color);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 1rem 1.5rem;
            z-index: 100;
            opacity: 0;
            transform: translate(-50%, 10px);
            transition: all 0.2s ease-out;
            text-align: left;
        }
        .perfil-popover.active {
            display: block;
            opacity: 1;
            transform: translate(-75%, 0);
            margin-top: 7px;
        }
        .perfil-popover h5 {
            font-size: 0.9rem;
            color: var(--primary-color);
            text-transform: uppercase;
            font-weight: 600;
            margin: 0 0 0.75rem 0;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        .perfil-popover ul { margin: 0; padding: 0; list-style: none; }
        .perfil-popover li {
            font-size: 0.9em;
            color: var(--light-text-color);
            margin-bottom: 0.5rem;
            font-weight: 400;
            text-transform: none;
            line-height: 1.5;
        }
         .perfil-popover li:last-child { margin-bottom: 0; }

        /* --- Seção de Endereços --- */
        .endereco-card-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
        .endereco-card { background: rgba(0,0,0,0.2); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 1.5rem; display: flex; flex-direction: column; }
        .endereco-card h5 { font-size: 1rem; color: var(--text-color); margin: 0 0 0.25rem 0; display: flex; justify-content: space-between; align-items: center; }
        .endereco-card h5 .badge-principal { font-size: 0.7em; background: var(--primary-color); color: #fff; padding: 0.2em 0.6em; border-radius: 1em; font-weight: 500; }
        .endereco-card p { font-size: 0.9em; color: var(--light-text-color); line-height: 1.6; margin: 0; flex-grow: 1; }
        .endereco-card strong { color: var(--text-color); font-weight: 500; }
        .endereco-card-actions { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.5rem; }
        .endereco-card-actions button, .endereco-card-actions a { font-size: 0.8em; padding: 0.4rem 0.8rem; }

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
            .dashboard-grid { grid-template-columns: 1fr; }
            .form-grid { grid-template-columns: 1fr; }
            .list-container table { border: none; min-width: auto; display: block; }
            .list-container thead { display: none; }
            .list-container tr { display: block; margin-bottom: 1rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 1rem; background: rgba(0,0,0,0.1); }
            .list-container td { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: none; text-align: right; }
            .list-container td::before { content: attr(data-label); font-weight: 600; color: var(--light-text-color); text-align: left; margin-right: 1rem; flex-basis: 40%;}
            .list-container td.actions { justify-content: flex-end; }
            .list-container td.actions::before { display: none; }
            .endereco-card-list { grid-template-columns: 1fr; }
        }
        @media (max-width: 576px) {
            .main-content { padding: 1rem; padding-top: 4.5rem; }
            .content-header { padding: 1rem 1.5rem;}
            .content-header h1 { font-size: 1.5rem; }
            .content-header p { font-size: 0.9rem;}
            .form-container, .list-container, .filter-container { padding: 1rem 1.5rem;}
            .crud-section h3 { font-size: 1.1rem;}
            .form-container h4 { font-size: 1rem;}
            .list-container td, .list-container td::before { font-size: 0.8em; }
            .modal-container { width: 95%; }
            .modal-header, .modal-body, .modal-footer { padding: 1.25rem 1.5rem; }
            .modal-footer { flex-direction: column-reverse; gap: 0.75rem; }
            .modal-footer .btn { width: 100%; text-align: center; }
            .perfil-popover { left: 10%; transform: translateX(0); min-width: 250px; width: 80%; }
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
    <div class="menu-toggle" id="menu-toggle">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
        </svg>
    </div>

    <div id="particles-js"></div>

    <?php include 'admin_sidebar.php'; ?>

    <main class="main-content">
        <div class="content-header">
            <h1>Gerenciar Usuários</h1>
            <p>Edite, bloqueie ou remova usuários cadastrados na loja.</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo nl2br(htmlspecialchars($message)); ?>
            </div>
        <?php endif; ?>


        <?php if ($is_viewing_details && $edit_usuario): ?>
        <div class="crud-section" id="form-usuario">
            <h3>
                Detalhes do Usuário: <?php echo htmlspecialchars($edit_usuario['nome']); ?>

                <?php if ($perfil_completo): ?>
                    <span class="badge-status-completo">Perfil Completo</span>
                <?php else: ?>
                    <span class="badge-status-incompleto" id="perfil-incompleto-badge" aria-haspopup="true">
                        Perfil Incompleto
                        <div class="perfil-popover" id="perfil-incompleto-popover">
                            <h5>Motivos do Perfil Incompleto:</h5>
                            <ul>
                                <?php foreach ($motivos_incompleto as $motivo): ?>
                                    <li>- <?php echo $motivo; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </span>
                <?php endif; ?>
            </h3>
            <div class="form-container">
                <form action="usuarios.php?view_user=<?php echo $edit_usuario['id']; ?>#form-usuario" method="POST">
                    <input type="hidden" name="id" value="<?php echo $edit_usuario['id']; ?>">

                    <h4>Dados Pessoais</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nome">Nome Completo:</label>
                            <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($edit_usuario['nome'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">E-mail:</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($edit_usuario['email'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="cpf">CPF:</label>
                            <input type="text" id="cpf" name="cpf" value="<?php echo htmlspecialchars($edit_usuario['cpf'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="data_nascimento">Data de Nascimento:</label>
                            <input type="date" id="data_nascimento" name="data_nascimento" value="<?php echo htmlspecialchars($edit_usuario['data_nascimento'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="telefone_celular">Telefone Celular:</label>
                            <input type="text" id="telefone_celular" name="telefone_celular" value="<?php echo htmlspecialchars($edit_usuario['telefone_celular'] ?? ''); ?>">
                        </div>
                         <div class="form-group">
                            <label for="telefone_fixo">Telefone Fixo:</label>
                            <input type="text" id="telefone_fixo" name="telefone_fixo" value="<?php echo htmlspecialchars($edit_usuario['telefone_fixo'] ?? ''); ?>">
                        </div>
                    </div>
                     <div class="form-group-check" style="padding-top: 0;">
                        <input type="checkbox" id="receber_promocoes" name="receber_promocoes" value="1"
                                <?php echo (!empty($edit_usuario['receber_promocoes'])) ? 'checked' : ''; ?>>
                        <label for="receber_promocoes">Aceita receber promoções</label>
                    </div>

                    <hr style="border-color: var(--border-color); margin: 1.5rem 0;">

                    <h4>Gerenciamento de Acesso</h4>
                    <div class="form-group">
                        <label for="nova_senha">Alterar Senha</label>
                        <input type="password" id="nova_senha" name="nova_senha" placeholder="Deixe em branco para não alterar" autocomplete="new-password">
                    </div>
                    <div class="form-group-check">
                        <input type="checkbox" id="is_bloqueado" name="is_bloqueado" value="1"
                                <?php echo (!empty($edit_usuario['is_bloqueado'])) ? 'checked' : ''; ?>>
                        <label for="is_bloqueado">Bloquear acesso deste usuário</label>
                    </div>
                    <div class="form-group" style="margin-top: 1rem;">
                        <label for="motivo_bloqueio">Motivo do Bloqueio (visível para o usuário se bloqueado)</label>
                        <textarea id="motivo_bloqueio" name="motivo_bloqueio" placeholder="Ex: Atividade suspeita detectada."><?php echo htmlspecialchars($edit_usuario['motivo_bloqueio'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="salvar_usuario" class="btn update">Salvar Alterações</button>
                        <a href="usuarios.php" class="btn cancel">Voltar para Lista</a>
                    </div>
                </form>
            </div>

            <div class="crud-section" id="section-enderecos" style="margin-top: 2.5rem;">
                <h3>Endereços Cadastrados (<?php echo count($user_enderecos); ?>)</h3>
                <div class="endereco-card-list">
                    <?php if (empty($user_enderecos)): ?>
                        <div class="message info" style="grid-column: 1 / -1;">Este usuário não possui endereços cadastrados.</div>
                    <?php else: ?>
                        <?php foreach ($user_enderecos as $endereco): ?>
                            <div class="endereco-card">
                                <h5>
                                    <?php echo htmlspecialchars($endereco['nome_endereco']); ?>
                                    <?php if ($endereco['is_principal']): ?>
                                        <span class="badge-principal">Principal</span>
                                    <?php endif; ?>
                                </h5>
                                <p>
                                    <strong>Destinatário:</strong> <?php echo htmlspecialchars($endereco['destinatario'] ?? $edit_usuario['nome']); ?><br>
                                    <?php echo htmlspecialchars($endereco['endereco']); ?>, <?php echo htmlspecialchars($endereco['numero']); ?>
                                    <?php echo !empty($endereco['complemento']) ? ' - ' . htmlspecialchars($endereco['complemento']) : ''; ?><br>
                                    <?php echo htmlspecialchars($endereco['bairro']); ?>, <?php echo htmlspecialchars($endereco['cidade']); ?> - <?php echo htmlspecialchars($endereco['estado']); ?><br>
                                    <strong>CEP:</strong> <?php echo htmlspecialchars($endereco['cep']); ?>
                                </p>
                                <div class="endereco-card-actions">
                                    <a href="usuarios.php?view_user=<?php echo $edit_usuario['id']; ?>&edit_endereco=<?php echo $endereco['id']; ?>#form-endereco" class="btn update">Editar</a>

                                    <button class="btn danger"
                                            onclick="openModal(
                                                'Excluir Endereço',
                                                'Tem certeza que deseja excluir o endereço \'<?php echo htmlspecialchars(addslashes($endereco['nome_endereco'])); ?>\'?<br><br>Esta ação não pode ser desfeita.',
                                                'usuarios.php?delete_endereco=<?php echo $endereco['id']; ?>&view_user=<?php echo $edit_usuario['id']; ?>'
                                            )">
                                        Excluir
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="crud-section" id="form-endereco" style="margin-top: 2.5rem;">
                 <h3><?php echo $is_editing_endereco ? 'Editar Endereço' : 'Adicionar Novo Endereço'; ?></h3>
                 <div class="form-container" <?php if($is_editing_endereco) echo 'style="border-color: var(--primary-color);"'; ?>>
                     <form action="usuarios.php?view_user=<?php echo $edit_usuario['id']; ?>#form-endereco" method="POST">
                        <?php if ($is_editing_endereco): ?>
                            <input type="hidden" name="endereco_id" value="<?php echo $edit_endereco['id']; ?>">
                        <?php endif; ?>
                        <input type="hidden" name="usuario_id_redirect" value="<?php echo $edit_usuario['id']; ?>">

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nome_endereco">Nome do Endereço (Ex: Casa, Trabalho)</label>
                                <input type="text" id="nome_endereco" name="nome_endereco" value="<?php echo htmlspecialchars($edit_endereco['nome_endereco'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="destinatario">Nome do Destinatário</label>
                                <input type="text" id="destinatario" name="destinatario" value="<?php echo htmlspecialchars($edit_endereco['destinatario'] ?? $edit_usuario['nome']); ?>" required>
                            </div>
                        </div>
                        <div class="form-grid" style="grid-template-columns: 1fr 2fr;">
                             <div class="form-group">
                                <label for="cep_endereco">CEP <span class="cep-loading" id="cep-loading-status"></span></label>
                                <input type="text" id="cep_endereco" name="cep" value="<?php echo htmlspecialchars($edit_endereco['cep'] ?? ''); ?>" required maxlength="9" placeholder="00000-000">
                            </div>
                             <div class="form-group">
                                <label for="rua_endereco">Endereço (Rua/Avenida)</label>
                                <input type="text" id="rua_endereco" name="endereco" value="<?php echo htmlspecialchars($edit_endereco['endereco'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="form-grid">
                             <div class="form-group">
                                <label for="numero_endereco">Número</label>
                                <input type="text" id="numero_endereco" name="numero" value="<?php echo htmlspecialchars($edit_endereco['numero'] ?? ''); ?>" required>
                            </div>
                             <div class="form-group">
                                <label for="complemento_endereco">Complemento (Opcional)</label>
                                <input type="text" id="complemento_endereco" name="complemento" value="<?php echo htmlspecialchars($edit_endereco['complemento'] ?? ''); ?>">
                            </div>
                             <div class="form-group">
                                <label for="bairro_endereco">Bairro</label>
                                <input type="text" id="bairro_endereco" name="bairro" value="<?php echo htmlspecialchars($edit_endereco['bairro'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="form-grid" style="grid-template-columns: 2fr 1fr;">
                             <div class="form-group">
                                <label for="cidade_endereco">Cidade</label>
                                <input type="text" id="cidade_endereco" name="cidade" value="<?php echo htmlspecialchars($edit_endereco['cidade'] ?? ''); ?>" required>
                            </div>
                             <div class="form-group">
                                <label for="estado_endereco">Estado (UF)</label>
                                <input type="text" id="estado_endereco" name="estado" value="<?php echo htmlspecialchars($edit_endereco['estado'] ?? ''); ?>" required maxlength="2">
                            </div>
                        </div>
                        <div class="form-group-check">
                            <input type="checkbox" id="is_principal" name="is_principal" value="1"
                                <?php echo (!empty($edit_endereco['is_principal'])) ? 'checked' : ''; ?>>
                            <label for="is_principal">Definir como endereço principal</label>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="salvar_endereco" class="btn <?php echo $is_editing_endereco ? 'update' : 'primary'; ?>">
                                <?php echo $is_editing_endereco ? 'Salvar Alterações' : 'Adicionar Endereço'; ?>
                            </button>
                            <?php if ($is_editing_endereco): ?>
                                <a href="usuarios.php?view_user=<?php echo $edit_usuario['id']; ?>#section-enderecos" class="btn cancel">Cancelar Edição</a>
                            <?php endif; ?>
                        </div>
                     </form>
                 </div>
            </div>

        </div>
        <?php endif; ?>


        <div class="crud-section" id="section-lista-usuarios" <?php if ($is_viewing_details) echo 'style="display: none;"'; ?>>

            <div class="dashboard-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h4>Total de Usuários</h4>
                        <span class="icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
                        </span>
                    </div>
                    <div class="value"><?php echo $stats['total']; ?></div>
                </div>
                <div class="stat-card highlight">
                    <div class="stat-card-header">
                        <h4>Novos Hoje</h4>
                        <span class="icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" /></svg>
                        </span>
                    </div>
                    <div class="value"><?php echo $stats['hoje']; ?></div>
                </div>
                <div class="stat-card success">
                    <div class="stat-card-header">
                        <h4>Perfis Completos</h4>
                        <span class="icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        </span>
                    </div>
                    <div class="value"><?php echo $stats['completos']; ?></div>
                </div>
            </div>

            <div class="filter-container">
                 <div class="form-group">
                    <label for="user-search">Buscar Usuário</label>
                    <input type="text" id="user-search" placeholder="Digite nome ou e-mail para filtrar..."
                           value="<?php echo htmlspecialchars($search_term); ?>">
                 </div>
            </div>

            <h3>Usuários Cadastrados (<?php echo count($all_usuarios); ?>)</h3>
            <div class="list-container">
                 <?php if (empty($all_usuarios) && !empty($search_term)): ?>
                    <p class="no-results-message" style="text-align: center; color: var(--light-text-color); padding: 1.5rem;">Nenhum usuário encontrado para o termo "<?php echo htmlspecialchars($search_term); ?>". <a href="usuarios.php">Limpar busca</a></p>
                 <?php elseif (empty($all_usuarios)): ?>
                    <p class="no-results-message" style="text-align: center; color: var(--light-text-color); padding: 1.5rem;">Nenhum usuário cadastrado.</p>
                 <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>E-mail / CPF</th>
                                <th>Data Cadastro</th>
                                <th>Pedidos</th>
                                <th>Status</th>
                                <th class="actions">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="user-table-body">
                            <?php foreach ($all_usuarios as $usuario): ?>
                                <tr class="user-row" data-search-term="<?php echo strtolower(htmlspecialchars($usuario['nome'] . ' ' . $usuario['email'])); ?>">
                                    <td data-label="Nome">
                                        <a href="usuarios.php?view_user=<?php echo $usuario['id']; ?>" title="Ver Detalhes do Usuário" style="font-weight: 600; color: var(--text-color);">
                                            <?php echo htmlspecialchars($usuario['nome']); ?>
                                        </a>
                                    </td>
                                    <td data-label="E-mail / CPF">
                                        <?php echo htmlspecialchars($usuario['email']); ?>
                                        <br>
                                        <small style="color: var(--light-text-color);"><?php echo htmlspecialchars($usuario['cpf'] ?? 'CPF não informado'); ?></small>
                                    </td>
                                    <td data-label="Data Cadastro"><?php echo date('d/m/Y', strtotime($usuario['criado_em'])); ?></td>
                                    <td data-label="Pedidos">
                                        <a href="pedidos.php?filter_client=<?php echo urlencode($usuario['email']); ?>" title="Ver pedidos deste usuário" class="pedidos-link">
                                            <?php echo $usuario['total_pedidos']; ?>
                                        </a>
                                    </td>
                                    <td data-label="Status" class="status-icon">
                                        <?php if ($usuario['is_bloqueado']): ?>
                                            <span class="status-inativo" title="Bloqueado">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                                </svg>

                                            </span>
                                        <?php else: ?>
                                            <span class="status-ativo" title="Ativo">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 1 1 9 0v3.75M3.75 21.75h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H3.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                                </svg>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions">
                                        <a href="usuarios.php?view_user=<?php echo $usuario['id']; ?>#form-usuario" class="action-icon" title="Ver/Editar Detalhes">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>
                                        </a>
                                        <?php if ($usuario['is_bloqueado']): ?>
                                            <button class="action-icon" title="Desbloquear Usuário"
                                                    onclick="openModal(
                                                        'Desbloquear Usuário',
                                                        'Tem certeza que deseja desbloquear <?php echo htmlspecialchars(addslashes($usuario['nome'])); ?>?',
                                                        'usuarios.php?toggle_block=<?php echo $usuario['id']; ?>'
                                                    )">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 1 1 9 0v3.75M3.75 21.75h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H3.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                                    </svg>
                                            </button>
                                        <?php else: ?>
                                             <button class="action-icon" title="Bloquear Usuário"
                                                    onclick="openModal(
                                                        'Bloquear Usuário',
                                                        'Tem certeza que deseja bloquear <?php echo htmlspecialchars(addslashes($usuario['nome'])); ?>?',
                                                        'usuarios.php?toggle_block=<?php echo $usuario['id']; ?>'
                                                    )">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="status-inativo"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                                            </button>
                                        <?php endif; ?>

                                        <button class="action-icon delete" title="Remover Usuário"
                                                onclick="openModal(
                                                    'Excluir Usuário',
                                                    'Tem certeza que deseja excluir permanentemente <?php echo htmlspecialchars(addslashes($usuario['nome'])); ?>?<br><br><strong>Atenção:</strong> Todos os endereços associados serão removidos. Esta ação não pode ser desfeita e pode falhar se o usuário tiver pedidos.',
                                                    'usuarios.php?delete_usuario=<?php echo $usuario['id']; ?>'
                                                )">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                 <?php endif; ?>
            </div>
        </div>
        </main>

    <script>
        // --- JavaScript para Partículas ---
        particlesJS('particles-js', {"particles":{"number":{"value":60,"density":{"enable":true,"value_area":800}},"color":{"value":"#4a69bd"},"shape":{"type":"circle"},"opacity":{"value":0.4,"random":false},"size":{"value":3,"random":true},"line_linked":{"enable":true,"distance":150,"color":"#ffffff","opacity":0.1,"width":1},"move":{"enable":true,"speed":1.5,"direction":"none","random":false,"straight":false,"out_mode":"out","bounce":false}},"interactivity":{"detect_on":"canvas","events":{"onhover":{"enable":true,"mode":"repulse"},"onclick":{"enable":true,"mode":"push"},"resize":true}},"retina_detect":true});

        // --- JavaScript Específico da Página ---
        document.addEventListener('DOMContentLoaded', () => {
             // --- Lógica de Menu e Perfil REMOVIDA (Assumindo estar em admin_sidebar.php) ---

             // --- Ancoragem suave ---
             const urlParams = new URLSearchParams(window.location.search);
             const viewUserId = urlParams.get('view_user');
             let targetHash = window.location.hash;

             if (viewUserId && !targetHash) {
                  targetHash = '#form-usuario';
             } else if (urlParams.has('saved') || urlParams.has('endereco_deleted') || urlParams.has('endereco_saved') || urlParams.has('edit_endereco')) {
                 if (urlParams.has('edit_endereco') && targetHash !== '#form-endereco') {
                     targetHash = '#form-endereco';
                 } else if ((urlParams.has('endereco_deleted') || urlParams.has('endereco_saved')) && targetHash !== '#section-enderecos') {
                      targetHash = '#section-enderecos';
                 } else if (urlParams.has('saved') && !targetHash) {
                     targetHash = '#form-usuario';
                 }
             } else if (urlParams.has('deleted') || urlParams.has('status_changed')) {
                 targetHash = '#section-lista-usuarios';
             }

             if (targetHash) {
                 const targetElement = document.querySelector(targetHash);
                 if (targetElement) {
                     setTimeout(() => {
                         const headerElement = targetElement.querySelector('h3') || targetElement.querySelector('h4') || targetElement;
                         headerElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
                    modalText.innerHTML = text;
                    modalConfirmLink.href = confirmUrl;

                    modalConfirmLink.classList.remove('update', 'danger');
                    if (title.toLowerCase().includes('excluir') || title.toLowerCase().includes('remover') || title.toLowerCase().includes('bloquear')) {
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

            // --- Lógica do Popover de Perfil Incompleto (NOVO) ---
            const badge = document.getElementById('perfil-incompleto-badge');
            const popover = document.getElementById('perfil-incompleto-popover');

            if (badge && popover) {
                 badge.addEventListener('click', (event) => {
                    event.stopPropagation();
                    popover.classList.toggle('active');
                 });
                 document.addEventListener('click', (event) => {
                     if (!popover.contains(event.target) && event.target !== badge) {
                         popover.classList.remove('active');
                     }
                 });
            }

            // --- Lógica do Filtro de Busca (em tempo real) ---
            const searchInput = document.getElementById('user-search');
            const tableBody = document.getElementById('user-table-body');
            const rows = tableBody ? tableBody.querySelectorAll('tr.user-row') : [];
            const noResultsMessage = document.querySelector('#section-lista-usuarios .list-container > p.no-results-message');

            if (searchInput && tableBody && (rows.length > 0 || urlParams.has('search'))) {

                const filterRowsJS = () => {
                    const searchTerm = searchInput.value.toLowerCase();
                    let visibleRows = 0;

                    rows.forEach(row => {
                        const rowText = row.getAttribute('data-search-term');
                        if (rowText.includes(searchTerm)) {
                            row.style.display = '';
                            visibleRows++;
                        } else {
                            row.style.display = 'none';
                        }
                    });

                    if (noResultsMessage) {
                        noResultsMessage.style.display = (visibleRows === 0) ? '' : 'none';
                        if(visibleRows === 0) {
                             if(searchTerm) {
                                noResultsMessage.innerHTML = `Nenhum usuário encontrado para "<strong>${htmlspecialchars(searchTerm)}</strong>". <a href="usuarios.php">Limpar busca</a>`;
                             } else {
                                noResultsMessage.innerHTML = 'Nenhum usuário cadastrado.';
                             }
                        }
                    }
                };

                function htmlspecialchars(str) {
                    if (typeof str !== 'string') return '';
                    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
                }

                searchInput.addEventListener('keyup', filterRowsJS);

                if(searchInput.value && !urlParams.has('search')) {
                    filterRowsJS();
                }
                // Se ?search=... está na URL, o PHP já filtrou, então o JS não precisa rodar no load.
            }

            // --- Lógica do ViaCEP (NOVO) ---
            const cepInput = document.getElementById('cep_endereco');
            const cepStatus = document.getElementById('cep-loading-status');
            const ruaInput = document.getElementById('rua_endereco');
            const bairroInput = document.getElementById('bairro_endereco');
            const cidadeInput = document.getElementById('cidade_endereco');
            const estadoInput = document.getElementById('estado_endereco');
            const numeroInput = document.getElementById('numero_endereco');

            if (cepInput) {
                cepInput.addEventListener('blur', function() {
                    const cep = cepInput.value.replace(/\D/g, ''); // Remove não-números

                    if (cep.length !== 8) {
                        cepStatus.textContent = '';
                        return;
                    }

                    cepStatus.textContent = '(Buscando...)';
                    cepStatus.style.color = 'var(--warning-text)';

                    fetch(`https://viacep.com.br/ws/${cep}/json/`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.erro) {
                                cepStatus.textContent = '(CEP não encontrado)';
                                cepStatus.style.color = 'var(--error-text)';
                                ruaInput.value = '';
                                bairroInput.value = '';
                                cidadeInput.value = '';
                                estadoInput.value = '';
                            } else {
                                cepStatus.textContent = '(Encontrado!)';
                                cepStatus.style.color = 'var(--success-text)';
                                ruaInput.value = data.logradouro;
                                bairroInput.value = data.bairro;
                                cidadeInput.value = data.localidade;
                                estadoInput.value = data.uf;
                                numeroInput.focus(); // Pula para o campo de número
                            }
                        })
                        .catch(error => {
                            console.error('Erro ao buscar CEP:', error);
                            cepStatus.textContent = '(Erro na busca)';
                            cepStatus.style.color = 'var(--error-text)';
                        });
                });
            }

        });
    </script>
</body>
</html>