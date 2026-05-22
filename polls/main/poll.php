<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

$poll_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($poll_id <= 0) {
    die("Не указан ID опроса");
}

// Загружаем данные опроса
$stmt = $pdo->prepare("SELECT id, title, description, is_active FROM polls WHERE id = :id");
$stmt->execute([':id' => $poll_id]);
$poll = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$poll) {
    die("Опрос не найден");
}

// Если опрос неактивен – показываем заглушку
if (!$poll['is_active']) {
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <title>Опрос временно недоступен</title>
        <link rel="stylesheet" href="assets/css/style.css">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"></script>
    </head>
    <body>
        <div class="container-unavailable-card">
            <div class="unavailable-card">
                <h1>⛔ Опрос недоступен</h1>
                <p>Сейчас опрос «<?= htmlspecialchars($poll['title']) ?>» временно закрыт для прохождения.</p>
                <p>Пожалуйста, зайдите позже.</p>
                <a href="index.php" class="btn btn-primary">← К списку опросов</a>
            </div>
        </div>
    </body>
    </html>

<?php
    exit;
}

// Если активен – показываем форму

// Загружаем вопросы (темы)
$stmt = $pdo->prepare("SELECT id, function_title FROM functions WHERE poll_id = :poll_id ORDER BY id");
$stmt->execute([':poll_id' => $poll_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($questions)) {
    die("В этом опросе нет вопросов");
}

// Варианты ответов
$options = $pdo->query("SELECT option_code, label_ru FROM answer_options ORDER BY id")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($poll['title']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body class="container">
    <form id="pollForm" method="POST" action="submit.php">
        <input type="hidden" name="poll_id" value="<?= $poll_id ?>">
        <div class="poll mb-4">
            <h1><?= htmlspecialchars($poll['title']) ?></h1>
            <?php if ($poll['description']): ?>
                <p><?= nl2br(htmlspecialchars($poll['description'])) ?></p>
            <?php endif; ?>
        </div>

        <?php foreach ($questions as $q): ?>
            <div class="poll mb-4" data-question-id="<?= $q['id'] ?>" data-question-title="<?= htmlspecialchars($q['function_title']) ?>">
                <div class="margins p-3 border rounded">
                    <h4><?= htmlspecialchars($q['function_title']) ?></h4>

                    <!-- при наличии -->
                    <div class="function mt-3">
                        <p class="question-text">Как вы отнесетесь к нашему проекту <strong>при наличии</strong> этой функции?</p>
                        <?php foreach ($options as $code => $label): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio"
                                       name="answers[<?= $q['id'] ?>][present]"
                                       value="<?= $code ?>" id="q<?= $q['id'] ?>_present_<?= $code ?>">
                                <label for="q<?= $q['id'] ?>_present_<?= $code ?>"><?= htmlspecialchars($label) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- при отсутствии -->
                    <div class="function mt-3">
                        <p class="question-text">Как вы отнесетесь к нашему проекту <strong>при отсутствии</strong> этой функции?</p>
                        <?php foreach ($options as $code => $label): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio"
                                       name="answers[<?= $q['id'] ?>][absent]"
                                       value="<?= $code ?>" id="q<?= $q['id'] ?>_absent_<?= $code ?>">
                                <label for="q<?= $q['id'] ?>_absent_<?= $code ?>"><?= htmlspecialchars($label) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <button type="submit" class="btn btn-primary">Отправить ответы</button>
        <div id="statusMessage" class="mt-3"></div>
    </form>

    <script src="assets/js/script.js"></script>
</body>
</html>