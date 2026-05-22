<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../includes/db.php';

// Удаление опроса (с каскадным удалением вопросов и ответов)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM polls WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: index.php');
    exit;
}

$polls = $pdo->query("SELECT id, title, is_active FROM polls ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Админ-панель</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"></script>    
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="container mt-4">
    <h1>Опросы</h1>
    <a href="create_poll.php" class="btn btn-success mb-3">Создать опрос</a>
    <a href="logout.php" class="btn btn-secondary mb-3 float-end">Выйти</a>
    <table class="table table-bordered">
        <thead>
            <tr><th>ID</th><th>Название</th><th>Статус</th><th>Действия</th></tr>
        </thead>
        <tbody>
        <?php foreach ($polls as $poll): ?>
            <tr>
                <td><?= $poll['id'] ?></td>
                <td><?= htmlspecialchars($poll['title']) ?></td>
                <td><?= $poll['is_active'] ? 'Активен' : 'Неактивен' ?></td>
                <td>
                    <a href="edit_poll.php?id=<?= $poll['id'] ?>" class="btn btn-sm btn-primary">Редактировать</a>
                    <a href="results.php?id=<?= $poll['id'] ?>" class="btn btn-sm btn-info">Результаты</a>
                    <a href="../main/poll.php?id=<?= $poll['id'] ?>" class="btn btn-sm btn-secondary" target="_blank">Пройти опрос</a>
                    <a href="?delete=<?= $poll['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Удалить опрос? Все вопросы и ответы будут удалены')">Удалить</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>