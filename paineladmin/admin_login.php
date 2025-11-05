<?php
// admin_panel/admin_login.php - Lógica de Autenticação via DB
session_start();

// Inclui a conexão com o banco de dados
require_once '../config/db.php';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? ''); // Assumimos que o username é o email
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = 'Preencha todos os campos.';
    } else {
        try {
            // 1. Busca o usuário pelo e-mail
            $stmt = $pdo->prepare("
                SELECT id, nome, email, senha, tipo
                FROM usuarios
                WHERE email = :email AND tipo = 'admin'
            ");
            $stmt->execute(['email' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // 2. Verifica se o usuário existe, se é admin e se a senha está correta
            if ($user && $user['tipo'] === 'admin' && password_verify($password, $user['senha'])) {

                // SUCESSO NA AUTENTICAÇÃO
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $user['nome']; // Armazena o nome
                $_SESSION['admin_user_id'] = $user['id'];   // ⭐ CRÍTICO: Armazena o ID do usuário
                $_SESSION['admin_user_email'] = $user['email']; // Armazena o email

                header('Location: index.php');
                exit;
            } else {
                $error_message = 'Usuário ou senha inválidos. Certifique-se de que a conta é de Administrador.';
            }

        } catch (PDOException $e) {
            error_log("Erro de login no DB: " . $e->getMessage());
            $error_message = 'Erro interno do servidor. Tente novamente mais tarde.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Painel Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet">


<style>
    /* O CSS (estilização) é mantido igual ao seu original */
    /* ==========================================================
        1. ESTILOS BASE
        ========================================================== */
    :root {
        --font-family: 'Montserrat', sans-serif;
        --text-color: #ffffff;
        --bg-color: #101010;
        --input-bg: #222;
        --input-border: #444;
        --error-color: #ff5c7a;
        --grad-start: #0033a0;
        --grad-mid: #0033a0;
        --grad-end: #00abff;

        --orb-color-1: #0033a0;
        --orb-color-2: #ffffff;
        --orb-color-3: #00abff;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: var(--font-family);
        background-color: var(--bg-color);
        color: var(--text-color);
        display: grid;
        place-items: center;
        min-height: 100vh;
        margin: 0;
        overflow: hidden;
    }

    .login-wrapper { position: relative; z-index: 2; display: grid; place-items: center; width: 100%; }

    /* ==========================================================
        2. O ORBITAL ANIMADO (3 ANÉIS)
        ========================================================== */

    .orb {
        position: absolute; top: 50%; left: 50%; width: 500px; height: 500px; z-index: 1;
        border: 4px solid var(--orb-color-1);
        border-radius: 45% 55% 60% 40% / 40% 60% 55% 45%;
        animation: rotate-main 10s linear infinite;
        transition: width 0.3s ease, height 0.3s ease;
    }
    .orb::before, .orb::after {
        content: ''; position: absolute; inset: 0; border: 4px solid;
        border-radius: 45% 55% 60% 40% / 40% 60% 55% 45%;
        animation: rotate-pseudo 10s linear infinite;
    }

    .orb::before { border-color: var(--orb-color-2); animation-delay: -3.3s; }
    .orb::after { border-color: var(--orb-color-3); animation-delay: -6.6s; }

    @keyframes rotate-main {
        0% { transform: translate(-50%, -50%) rotate(0deg); border-radius: 45% 55% 60% 40% / 40% 60% 55% 45%; }
        50% { border-radius: 40% 60% 55% 45% / 45% 55% 60% 40%; }
        100% { transform: translate(-50%, -50%) rotate(360deg); border-radius: 45% 55% 60% 40% / 40% 60% 55% 45%; }
    }

    @keyframes rotate-pseudo {
        0% { transform: rotate(0deg); border-radius: 45% 55% 60% 40% / 40% 60% 55% 45%; }
        50% { border-radius: 40% 60% 55% 45% / 45% 55% 60% 40%; }
        100% { transform: rotate(360deg); border-radius: 45% 55% 60% 40% / 40% 60% 55% 45%; }
    }


    /* ==========================================================
        3. FORMULÁRIO DE LOGIN (TAMANHO BASE)
        ========================================================== */

    .login-container {
        position: relative; z-index: 2; width: 321px; min-height: 268px; height: auto; padding: 35px 25px;
        background: var(--bg-color); border-radius: 15px; text-align: center; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        display: flex; flex-direction: column; justify-content: center; transition: width 0.3s ease, padding 0.3s ease, opacity 0.5s ease-out, transform 0.5s ease-out;

        opacity: 0;
        transform: scale(0.8);
    }

    .login-container h2 { font-size: 1.8rem; font-weight: 700; margin-bottom: 25px; text-shadow: 0 0 10px rgba(255, 255, 255, 0.1); }

    .form-group { margin-bottom: 15px; text-align: left; }
    .form-group label { display: none; }

    .form-group input {
        width: 100%; padding: 12px 20px; background: var(--input-bg); border: 1px solid var(--input-border);
        border-radius: 25px; color: var(--text-color); font-family: var(--font-family); font-size: 0.9rem;
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }

    .form-group input::placeholder { color: #888; }

    .form-group input:focus {
        outline: none; border-color: var(--orb-color-3); box-shadow: 0 0 10px rgba(0, 204, 255, 0.3);
    }

    button[type="submit"] {
        width: 100%; padding: 12px 20px; border: none; border-radius: 25px;
        background: linear-gradient(90deg, var(--grad-start), var(--grad-mid), var(--grad-end));
        color: #ffffffff; font-family: var(--font-family); font-size: 1rem; font-weight: 700;
        cursor: pointer; transition: transform 0.2s ease, box-shadow 0.2s ease; margin-top: 15px;
    }

    button[type="submit"]:hover { transform: scale(1.03); box-shadow: 0 4px 15px rgba(255, 140, 64, 0.3); }

    .error-message {
        color: var(--error-color); text-align: center; margin-top: 15px; font-size: 0.85rem; font-weight: 500; padding: 8px;
        background-color: rgba(255, 92, 122, 0.1); border: 1px solid rgba(255, 92, 122, 0.3); border-radius: 8px;
    }

    /* ==========================================================
        4. RESPONSIVIDADE (ABORDAGEM FLUIDA E MODERNA)
        ========================================================== */

    /* PEQUENOS CELULARES (TUDO < 500px) */
    @media (max-width: 500px) {
        .orb { width: 90vmin; height: 90vmin; }
        .galaxy-center { width: 20vmin; height: 20vmin; }

        .login-container {
            width: 65vmin; padding: 20px 15px; min-height: auto; border-radius: 10px;
        }

        .login-container h2 { font-size: 1.5rem; margin-bottom: 20px; }
        .form-group input, button[type="submit"] { padding: 11px 18px; font-size: 0.9rem; }
        .form-group { margin-bottom: 12px; }
        button[type="submit"] { margin-top: 10px; }
        .error-message { font-size: 0.8rem; padding: 5px; }
    }

    /* TABLETS (A PARTIR DE 501px) */
    @media (min-width: 501px) and (max-width: 768px) {
        .orb { width: 420px; height: 420px; }
        .galaxy-center { width: 100px; height: 100px; }

        .login-container { width: 321px; max-width: 321px; padding: 35px 25px; min-height: 268px; border-radius: 15px; }

        .login-container h2 { font-size: 1.8rem; margin-bottom: 25px; }
        .form-group { margin-bottom: 15px; }
        .form-group input { padding: 12px 20px; font-size: 0.9rem; }
        button[type="submit"] { padding: 12px 20px; font-size: 1rem; margin-top: 15px; }
        .error-message { font-size: 0.85rem; padding: 8px; }
    }

    /* LAPTOPS/DESKTOPS PEQUENOS: (min-width: 769px) and (max-width: 1024px) */
    @media (min-width: 769px) and (max-width: 1024px) {
        .orb { width: 450px; height: 450px; }
        .galaxy-center { width: 100px; height: 100px; }
        .login-container { width: 321px; padding: 35px 25px; }
    }

    /* DESKTOPS GRANDES: (min-width: 1025px) */
    @media (min-width: 1025px) {
        .orb { width: 500px; height: 500px; }
        .galaxy-center { width: 100px; height: 100px; }
        .login-container { width: 321px; padding: 40px 30px; }
    }

    /* Telas Ultra-Wide (Ajustes de Escala) */
    @media (min-width: 2000px) {
        .orb { width: 700px; height: 700px; }
        .galaxy-center { width: 150px; height: 150px; }
        .login-container { width: 400px; padding: 50px 40px; border-radius: 20px; }
        .login-container h2 { font-size: 2.2rem; }
        .form-group input { padding: 16px 25px; font-size: 1rem; }
        button[type="submit"] { padding: 16px 25px; font-size: 1.1rem; }
    }

    /* ==========================================================
        5. ANIMAÇÃO DE ENTRADA DO LOGIN (ESTADO ATIVO)
        ========================================================== */
    .login-container.is-visible { opacity: 1; transform: scale(1); }

    /* ==========================================================
        6. CENTRO GALÁCTICO
        ========================================================== */
    .galaxy-center {
        position: absolute; z-index: 1; width: 100px; height: 100px; top: 50%; left: 50%;
        transform: translate(-50%, -50%); border-radius: 50%;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.5) 0%, var(--orb-color-3) 50%, rgba(0, 0, 0, 0) 100%);
        box-shadow: 0 0 30px 10px rgba(0, 171, 255, 0.7), 0 0 60px 20px rgba(255, 255, 255, 0.2);

        opacity: 0; transition: opacity 1s ease-in-out, transform 0.5s ease-out;
    }

    .galaxy-center.is-active { opacity: 1; }
    .galaxy-center.is-fading { opacity: 0; transform: translate(-50%, -50%) scale(0.5); }
</style>
</head>
<body>

    <div class="login-wrapper">
        <div class="orb"></div>
        <div class="galaxy-center"></div>

        <div class="login-container">
            <h2>Login Admin</h2>

            <form action="admin_login.php" method="POST">
                <div class="form-group">
                    <label for="username">E-mail:</label>
                    <input type="text" id="username" name="username" placeholder="E-mail (usuário)" required
                                    value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="password">Senha:</label>
                    <input type="password" id="password" name="password" placeholder="Senha" required>
                </div>

                <button type="submit">Entrar</button>
            </form>

            <?php if (!empty($error_message)): ?>
                <p class="error-message"><?php echo $error_message; ?></p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginContainer = document.querySelector('.login-container');
            const galaxyCenter = document.querySelector('.galaxy-center');

            setTimeout(() => {
                galaxyCenter.classList.add('is-active');
            }, 500);

            setTimeout(() => {
                galaxyCenter.classList.add('is-fading');
                galaxyCenter.classList.remove('is-active');
            }, 1500);

            setTimeout(() => {
                loginContainer.classList.add('is-visible');
            }, 2000);
        });
    </script>
</body>
</html>