<?php
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/config.php'; // для WEIGHT_MAP и др.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Метод не разрешён']);
    exit;
}

$poll_id = isset($_POST['poll_id']) ? (int)$_POST['poll_id'] : 0;
$answers = $_POST['answers'] ?? [];

if ($poll_id <= 0 || empty($answers)) {
    echo json_encode(['success' => false, 'message' => 'Некорректные данные']);
    exit;
}

// Проверяем существование опроса
$stmt = $pdo->prepare("SELECT id FROM polls WHERE id = :id AND is_active = true");
$stmt->execute([':id' => $poll_id]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Опрос не найден или неактивен']);
    exit;
}

// Получаем все вопросы этого опроса
$stmt = $pdo->prepare("SELECT id FROM functions WHERE poll_id = :poll_id");
$stmt->execute([':poll_id' => $poll_id]);
$validQuestionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (empty($validQuestionIds)) {
    echo json_encode(['success' => false, 'message' => 'В опросе нет вопросов']);
    exit;
}

// Валидация: для каждого вопроса должны быть present и absent
$missing = [];
foreach ($validQuestionIds as $qid) {
    if (!isset($answers[$qid]['present']) || !isset($answers[$qid]['absent'])) {
        $missing[] = "Вопрос ID $qid";
    }
}
if (!empty($missing)) {
    echo json_encode(['success' => false, 'message' => 'Ответьте на все вопросы: ' . implode(', ', $missing)]);
    exit;
}

// Загружаем карту option_code -> id
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
        INSERT INTO responses (poll_id, question_id, answer_type, answer_option_id, session_id, ip_address)
        VALUES (:poll_id, :question_id, :answer_type, :answer_option_id, :session_id, :ip_address)
    ");

    foreach ($answers as $questionId => $types) {
        if (!in_array($questionId, $validQuestionIds)) continue;
        foreach (['present', 'absent'] as $type) {
            $code = $types[$type];
            if (!isset($optionMap[$code])) {
                throw new Exception("Неизвестный вариант ответа: $code");
            }
            $insertStmt->execute([
                ':poll_id' => $poll_id,
                ':question_id' => $questionId,
                ':answer_type' => $type,
                ':answer_option_id' => $optionMap[$code],
                ':session_id' => $sessionId,
                ':ip_address' => $ip
            ]);
        }
    }
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Спасибо! Ответы сохранены.']);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка при сохранении']);
}