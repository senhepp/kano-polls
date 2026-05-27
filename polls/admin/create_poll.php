<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $questions_text = trim($_POST['questions']); // строки вопросов, разделённые переносом

    if ($title === '') {
        $error = "Название опроса обязательно";
    } else {
        try {
            $pdo->beginTransaction();
            // Создаём опрос
            $stmt = $pdo->prepare("INSERT INTO polls (title, description, is_active) VALUES (?, ?, ?) RETURNING id");
            $stmt->execute([$title, $description, $is_active]);
            $poll_id = $stmt->fetchColumn();

            // Добавляем вопросы, если есть
            if ($questions_text !== '') {
                $question_lines = explode("\n", $questions_text);
                $question_lines = array_map('trim', $question_lines);
                $question_lines = array_filter($question_lines, function($line) { return $line !== ''; });
                $stmt_q = $pdo->prepare("INSERT INTO functions (poll_id, function_title) VALUES (?, ?)");
                foreach ($question_lines as $q_text) {
                    $stmt_q->execute([$poll_id, $q_text]);
                }
            }
            $pdo->commit();
            header('Location: ../main/index.php');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Ошибка: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Создать опрос</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"></script>    
</head>
<body class="container mt-4">
    <h1>Создать опрос</h1>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="mb-3">
            <label>Название опроса</label>
            <input type="text" name="title" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Описание</label>
            <textarea name="description" class="form-control" rows="3"></textarea>
        </div>
        <div class="mb-3">
            <label>Функция (каждая с новой строки)</label>
            <textarea name="questions" class="form-control" rows="5" placeholder="Добавление контактных данных на страницу&#10;Внедрение системы уведомлений&#10;Доступ к материалам офлайн"></textarea>
            <div class="form-text">Введите список функций, которые будут оцениваться по шкале "при наличии / при отсутствии".</div>
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" name="is_active" class="form-check-input" checked>
            <label class="form-check-label">Активен</label>
        </div>
        <button type="submit" class="btn btn-primary">Создать опрос</button>
        <a href="index.php" class="btn btn-secondary">Отмена</a>
    </form>
</body>
</html>