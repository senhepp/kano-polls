<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../includes/db.php';

$poll_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$poll_id) die('Не указан ID опроса');

// Обработка формы обновления опроса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_poll'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE polls SET title = ?, description = ?, is_active = ? WHERE id = ?");
    $stmt->execute([$title, $description, $is_active, $poll_id]);
    header("Location: edit_poll.php?id=$poll_id");
    exit;
}

// Добавление вопроса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    $question_text = trim($_POST['question_text']);
    if ($question_text !== '') {
        $stmt = $pdo->prepare("INSERT INTO functions (poll_id, function_title) VALUES (?, ?)");
        $stmt->execute([$poll_id, $question_text]);
    }
    header("Location: edit_poll.php?id=$poll_id");
    exit;
}

// Редактирование вопроса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_question'])) {
    $question_id = (int)$_POST['question_id'];
    $question_text = trim($_POST['question_text']);
    $stmt = $pdo->prepare("UPDATE functions SET function_title = ? WHERE id = ? AND poll_id = ?");
    $stmt->execute([$question_text, $question_id, $poll_id]);
    header("Location: edit_poll.php?id=$poll_id");
    exit;
}

// Удаление вопроса
if (isset($_GET['del_q'])) {
    $question_id = (int)$_GET['del_q'];
    $stmt = $pdo->prepare("DELETE FROM functions WHERE id = ? AND poll_id = ?");
    $stmt->execute([$question_id, $poll_id]);
    header("Location: edit_poll.php?id=$poll_id");
    exit;
}

// Получаем данные опроса
$stmt = $pdo->prepare("SELECT * FROM polls WHERE id = ?");
$stmt->execute([$poll_id]);
$poll = $stmt->fetch();
if (!$poll) die('Опрос не найден');

// Получаем вопросы
$questions = $pdo->prepare("SELECT * FROM functions WHERE poll_id = ? ORDER BY id");
$questions->execute([$poll_id]);
$questions = $questions->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Редактирование опроса</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"></script>    
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="container mt-4">
    <h1>Редактирование опроса</h1>
    <form method="post">
        <input type="hidden" name="update_poll" value="1">
        <div class="mb-3">
            <label>Название</label>
            <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($poll['title']) ?>" required>
        </div>
        <div class="mb-3">
            <label>Описание</label>
            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($poll['description']) ?></textarea>
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" name="is_active" class="form-check-input" <?= $poll['is_active'] ? 'checked' : '' ?>>
            <label class="form-check-label">Активен</label>
        </div>
        <button type="submit" class="btn btn-primary">Сохранить изменения</button>
        <a href="index.php" class="btn btn-secondary">Назад</a>
    </form>

    <hr>
    <h2>Вопросы (темы)</h2>
    <table class="table table-sm">
        <thead><tr><th>ID</th><th>Текст вопроса</th><th>Действия</th></tr></thead>
        <tbody>
        <?php foreach ($questions as $q): ?>
            <tr>
                <td><?= $q['id'] ?></td>
                <td>
                    <form method="post" style="display:inline-block" class="d-inline">
                        <input type="hidden" name="edit_question" value="1">
                        <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                        <input type="text" name="question_text" value="<?= htmlspecialchars($q['function_title']) ?>" class="form-control form-control-sm d-inline-block w-auto">
                        <button type="submit" class="btn btn-sm btn-primary">Сохранить</button>
                    </form>
                </td>
                <td>
                    <a href="?id=<?= $poll_id ?>&del_q=<?= $q['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Удалить вопрос?')">Удалить</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <h3>Добавить новый вопрос</h3>
    <form method="post">
        <input type="hidden" name="add_question" value="1">
        <div class="input-group">
            <input type="text" name="question_text" class="form-control" placeholder="Текст вопроса" required>
            <button type="submit" class="btn btn-success">Добавить</button>
        </div>
    </form>
</body>
</html>