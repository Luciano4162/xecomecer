<?php
include('../config/db.php'); // ajusta o caminho conforme seu arquivo de conexão

// --- Cria a tabela automaticamente se não existir ---
$sql = "CREATE TABLE IF NOT EXISTS tamanhos (
  id SERIAL PRIMARY KEY,
  nome VARCHAR(10) NOT NULL
)";
$pdo->exec($sql);

// --- Cadastrar novo tamanho ---
if (isset($_POST['salvar'])) {
    $nome = trim($_POST['nome']);
    if ($nome != '') {
        $stmt = $pdo->prepare("INSERT INTO tamanhos (nome) VALUES (?)");
        $stmt->execute([$nome]);
    }
}

// --- Listar tamanhos ---
$tamanhos = $pdo->query("SELECT * FROM tamanhos ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Gerenciar Tamanhos</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light p-4">
  <div class="container">
    <h2 class="mb-4 text-center">Gerenciar Tamanhos</h2>

    <form method="post" class="d-flex mb-3">
      <input type="text" name="nome" class="form-control me-2" placeholder="Ex: P, M, G, 38, 40..." required>
      <button class="btn btn-success" name="salvar">Adicionar</button>
    </form>

    <table class="table table-dark table-striped">
      <thead>
        <tr>
          <th>ID</th>
          <th>Tamanho</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tamanhos as $t): ?>
          <tr>
            <td><?= $t['id']; ?></td>
            <td><?= htmlspecialchars($t['nome']); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body>
</html>