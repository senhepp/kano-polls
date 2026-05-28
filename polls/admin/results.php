<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../includes/db.php';

$poll_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$poll_id) die('Не указан ID опроса');

$stmt = $pdo->prepare("SELECT title FROM polls WHERE id = ?");
$stmt->execute([$poll_id]);
$poll = $stmt->fetch();
if (!$poll) die('Опрос не найден');

$questions = $pdo->prepare("SELECT id, function_title FROM functions WHERE poll_id = ? ORDER BY id");
$questions->execute([$poll_id]);
$questions = $questions->fetchAll();

$results = [];
$x_points = [];
$y_points = [];
$point_labels = [];

foreach ($questions as $q) {
    $qid = $q['id'];

    $stmt = $pdo->prepare("
        SELECT
            r_present.importance,
            ao_present.weight AS present_weight,
            ao_absent.weight AS absent_weight
        FROM responses r_present
        JOIN answer_options ao_present ON r_present.answer_option_id = ao_present.id
        JOIN responses r_absent
            ON r_present.poll_id = r_absent.poll_id
            AND r_present.question_id = r_absent.question_id
            AND r_present.session_id = r_absent.session_id
            AND r_absent.answer_type = 'absent'
        JOIN answer_options ao_absent ON r_absent.answer_option_id = ao_absent.id
        WHERE r_present.answer_type = 'present'
          AND r_present.question_id = ?
    ");
    $stmt->execute([$qid]);
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        $results[] = [
            'question' => $q['function_title'],
            'avg_x' => '—',
            'avg_y' => '—',
            'avg_importance' => '—'
        ];
        continue;
    }

    $sum_x_weighted = 0;
    $sum_y_weighted = 0;
    $sum_weights = 0;
    $importance_sum = 0;
    $importance_count = 0;

    foreach ($rows as $row) {
        $present_weight = (float)$row['present_weight'];
        $absent_weight  = (float)$row['absent_weight'];
        $imp_db = (float)$row['importance'];

        $user_choice = (int)round($imp_db * 5);
        if ($user_choice < 1) $user_choice = 1;
        if ($user_choice > 5) $user_choice = 5;

        $Y_raw = ($present_weight + 1) / 2.0;
        $X_raw = (1 - $absent_weight) / 2.0;

        $importance_weight = $imp_db;

        $sum_x_weighted += $X_raw * $importance_weight;
        $sum_y_weighted += $Y_raw * $importance_weight;
        $sum_weights += $importance_weight;

        $importance_sum += $user_choice;
        $importance_count++;
    }

    $avg_x_raw = $sum_weights > 0 ? $sum_x_weighted / $sum_weights : 0.5;
    $avg_y_raw = $sum_weights > 0 ? $sum_y_weighted / $sum_weights : 0.5;
    $avg_importance = $importance_count > 0 ? round($importance_sum / $importance_count, 2) : '—';

    $results[] = [
        'question' => $q['function_title'],
        'avg_x' => round($avg_x_raw, 3),
        'avg_y' => round($avg_y_raw, 3),
        'avg_importance' => $avg_importance
    ];

    $x_points[] = $avg_x_raw;
    $y_points[] = $avg_y_raw;
    $point_labels[] = htmlspecialchars($q['function_title']);
}

$t = [];
for ($i = -0.5; $i <= 1.5; $i += 0.01) $t[] = $i;

$axis_h_x = [-0.5, 1.5];
$axis_h_y = [0.5, 0.5];
$axis_v_x = [0.5, 0.5];
$axis_v_y = [-0.5, 1.5];

$diag_x = $t;
$diag_y = $t;

// Преобразование гипербол из диапазона -10..10 в 0..1
$x_hyper1_old = []; $y_hyper1_old = [];
for ($x = -100; $x <= 0.99; $x += 0.05) { $x_hyper1_old[] = $x; $y_hyper1_old[] = -3 / ($x - 1); }
$x_hyper2_old = []; $y_hyper2_old = [];
for ($x = -0.99; $x <= 100; $x += 0.05) { $x_hyper2_old[] = $x; $y_hyper2_old[] = -3 / ($x + 1); }

$hyper1_x = array_map(function($x) { return ($x + 10) / 20; }, $x_hyper1_old);
$hyper1_y = array_map(function($y) { return ($y + 10) / 20; }, $y_hyper1_old);
$hyper2_x = array_map(function($x) { return ($x + 10) / 20; }, $x_hyper2_old);
$hyper2_y = array_map(function($y) { return ($y + 10) / 20; }, $y_hyper2_old);

$square_x_old = [-3, 3, 3, -3, -3];
$square_y_old = [-3, -3, 3, 3, -3];
$square_x = array_map(function($x) { return ($x + 10) / 20; }, $square_x_old);
$square_y = array_map(function($y) { return ($y + 10) / 20; }, $square_y_old);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Результаты: <?= htmlspecialchars($poll['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat&display=swap" rel="stylesheet">
    <script src="https://cdn.plot.ly/plotly-2.27.1.min.js" charset="utf-8"></script>
    <style>
        body { font-family: 'Montserrat', sans-serif; background: #f5f7fa; }
        .chart-container { background: white; border-radius: 12px; padding: 20px; margin-bottom: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); width: 100%; max-width: 800px; margin: 0 auto 30px auto; }
        #kansoChart { width: 100%; aspect-ratio: 1 / 1; }
        @media print { .btn-print, .btn-secondary, .no-print { display: none; } .chart-container { box-shadow: none; padding: 0; } }
    </style>
</head>
<body class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><?= htmlspecialchars($poll['title']) ?></h2>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-primary btn-print">Сохранить в PDF</button>
            <a href="index.php" class="btn btn-secondary">Вернуться к опросам</a>
        </div>
    </div>
    <div class="chart-container"><div id="kansoChart"></div></div>
    <h3>Результаты</h3>
    <table class="table table-bordered table-striped">
        <thead>
            <tr><th>Функция</th><th>Неудовлетворённость отсутствием (X)</th><th>Удовлетворённость наличием (Y)</th><th>Средняя важность (1–5)</th></tr>
        </thead>
        <tbody>
        <?php foreach ($results as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['question']) ?></td>
                <td><?= $row['avg_x'] ?></td>
                <td><?= $row['avg_y'] ?></td>
                <td><?= $row['avg_importance'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <script>
        var traceAxisH = { x: [-0.5,1.5], y: [0.5,0.5], mode: 'lines', line: { color: 'grey', width: 1}, showlegend: false };
        var traceAxisV = { x: [0.5,0.5], y: [-0.5,1.5], mode: 'lines', line: { color: 'grey', width: 1}, showlegend: false };
        var traceDiagonal = { x: <?= json_encode($diag_x) ?>, y: <?= json_encode($diag_y) ?>, mode: 'lines', line: { color: 'green', width: 2 }, name: 'Одномерные' };
        var traceHyper1 = { x: <?= json_encode($hyper1_x) ?>, y: <?= json_encode($hyper1_y) ?>, mode: 'lines', line: { color: 'orange', width: 2 }, name: 'Желательные' };
        var traceHyper2 = { x: <?= json_encode($hyper2_x) ?>, y: <?= json_encode($hyper2_y) ?>, mode: 'lines', line: { color: 'red', width: 2 }, name: 'Обязательные' };
        var traceSquare = { x: <?= json_encode($square_x) ?>, y: <?= json_encode($square_y) ?>, mode: 'lines', line: { color: 'grey', width: 1, dash: 'dash' }, name: 'Нейтральные', fill: 'none' };
        var tracePoints = { x: <?= json_encode($x_points) ?>, y: <?= json_encode($y_points) ?>, mode: 'markers', type: 'scatter', marker: { size: 12, color: 'blue', symbol: 'circle', line: { color: 'darkblue', width: 1 } }, text: <?= json_encode($point_labels) ?>, hoverinfo: 'text+x+y', name: 'Результаты' };

        var layout = {
            title: { text: "График по Кано", font: { size: 14, family: 'Montserrat' }, x: 0.05, xanchor: 'left' },
            font: { family: 'Montserrat, sans-serif', size: 12 },
            xaxis: { title: "Неудовлетворённость отсутствием", range: [-0.05,1.05], zeroline: false, scaleanchor: 'y', scaleratio: 1, fixedrange: true },
            yaxis: { title: "Удовлетворённость наличием", range: [-0.05,1.05], zeroline: false, scaleanchor: 'x', scaleratio: 1, fixedrange: true },
            autosize: true, showlegend: true, legend: { orientation: 'h', y: -0.2, x: 0, xanchor: 'left', font: { size: 10 } },
            hovermode: 'closest', plot_bgcolor: 'white', paper_bgcolor: 'white', margin: { l: 50, r: 20, t: 80, b: 50 }
        };

        var config = {
            responsive: true,
            displayModeBar: true,
            modeBarButtonsToRemove: ['zoomIn2d', 'zoomOut2d', 'pan2d', 'lasso2d', 'select2d'],
            scrollZoom: false,
            editable: false
        };

        Plotly.newPlot('kansoChart', [traceAxisH, traceAxisV, traceDiagonal, traceHyper1, traceHyper2, traceSquare, tracePoints], layout, config);
    </script>
</body>
</html>