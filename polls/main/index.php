<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';


// Получаем список активных опросов из БД
$polls = [];
try {
    $stmt = $pdo->query("SELECT id, title, description FROM polls WHERE is_active = true ORDER BY created_at DESC");
    $polls = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Если таблицы polls ещё нет, покажем заглушку
    $polls = [];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Все опросы</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="container my-4">
    <h1>Доступные опросы</h1>
    <?php if (empty($polls)): ?>
        <div class="alert alert-info">Пока нет ни одного опроса. <a href="../admin/create_poll.php">Создать первый</a></div>
    <?php else: ?>
        <div class="list-group">
            <?php foreach ($polls as $poll): ?>
                <a href="poll.php?id=<?= $poll['id'] ?>" class="list-group-item list-group-item-action">
                    <h5><?= htmlspecialchars($poll['title']) ?></h5>
                    <p><?= htmlspecialchars($poll['description']) ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="mt-3">
        <a href="../admin/index.php" class="btn btn-secondary">Админ-панель</a>
    </div>
</body>
</html>