<?php
// Sempre inicie a sessão
session_start();

// 1. Apaga todas as variáveis da sessão
$_SESSION = array();

// 2. Destrói a sessão
session_destroy();

// 3. Redireciona de volta para a página de login
header("Location: admin_login.php");
exit;
?>