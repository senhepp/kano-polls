<?php
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Метод не разрешён']);
    exit;
}

$poll_id = isset($_POST['poll_id']) ? (int)$_POST['poll_id'] : 0;
$answers = $_POST['answers'] ?? [];
$importance = $_POST['importance'] ?? [];

if ($poll_id <= 0 || empty($answers)) {
    echo json_encode(['success' => false, 'message' => 'Некорректные данные']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM polls WHERE id = :id AND is_active = true");
$stmt->execute([':id' => $poll_id]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Опрос не найден или неактивен']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM functions WHERE poll_id = :poll_id");
$stmt->execute([':poll_id' => $poll_id]);
$validQuestionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (empty($validQuestionIds)) {
    echo json_encode(['success' => false, 'message' => 'В опросе не задано функций']);
    exit;
}

// Валидация answers
$missing = [];
foreach ($validQuestionIds as $qid) {
    if (!isset($answers[$qid]['present']) || !isset($answers[$qid]['absent'])) {
        $missing[] = "Вопрос ID $qid";
    }
}
if (!empty($missing)) {
    echo json_encode(['success' => false, 'message' => 'Расскажите про отношение к каждой функции: ' . implode(', ', $missing)]);
    exit;
}

// Валидация importance (допустимые значения 1..5)
if (!is_array($importance)) {
    echo json_encode(['success' => false, 'message' => 'Не указана важность функций']);
    exit;
}
$importanceErrors = [];
foreach ($validQuestionIds as $qid) {
    if (!isset($importance[$qid])) {
        $importanceErrors[] = "Вопрос ID $qid (важность не выбрана)";
    } else {
        $imp = (int)$importance[$qid];
        if ($imp < 1 || $imp > 5) {
            $importanceErrors[] = "Вопрос ID $qid (некорректное значение важности: $imp)";
        }
    }
}
if (!empty($importanceErrors)) {
    echo json_encode(['success' => false, 'message' => 'Ошибка при задании важности: ' . implode(', ', $importanceErrors)]);
    exit;
}

$stmt = $pdo->query("SELECT id, option_code FROM answer_options");
$optionMap = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $optionMap[$row['option_code']] = $row['id'];
}

$sessionId = session_id();
$ip = $_SERVER['REMOTE_ADDR'] ?? null;

try {
    $pdo->beginTransaction();
    $insertStmt = $pdo->prepare("
        INSERT INTO responses (poll_id, question_id, answer_type, answer_option_id, session_id, ip_address, importance)
        VALUES (:poll_id, :question_id, :answer_type, :answer_option_id, :session_id, :ip_address, :importance)
    ");

    foreach ($validQuestionIds as $questionId) {
        $presentCode = $answers[$questionId]['present'];
        $absentCode = $answers[$questionId]['absent'];
        $imp = (int)$importance[$questionId];
        $imp_db = $imp / 5.0;  // преобразуем для хранения (0.2, 0.4, ..., 1.0)

        if (!isset($optionMap[$presentCode]) || !isset($optionMap[$absentCode])) {
            throw new Exception("Некорректный код ответа для функции $questionId");
        }

        $insertStmt->execute([
            ':poll_id' => $poll_id,
            ':question_id' => $questionId,
            ':answer_type' => 'present',
            ':answer_option_id' => $optionMap[$presentCode],
            ':session_id' => $sessionId,
            ':ip_address' => $ip,
            ':importance' => $imp_db
        ]);
        $insertStmt->execute([
            ':poll_id' => $poll_id,
            ':question_id' => $questionId,
            ':answer_type' => 'absent',
            ':answer_option_id' => $optionMap[$absentCode],
            ':session_id' => $sessionId,
            ':ip_address' => $ip,
            ':importance' => $imp_db
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Спасибо! Ответы сохранены.']);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка при сохранении: ' . $e->getMessage()]);
}