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

$importanceFactors = [
    0 => 1, 1 => 1.5, 2 => 2, 3 => 2.5, 4 => 3,
    5 => 3.5, 6 => 4, 7 => 4.5, 8 => 5, 9 => 5.5, 10 => 6
];

$results = [];
$x_points = [];   // будет содержать absent (неудовлетворённость отсутствием)
$y_points = [];   // будет содержать present (удовлетворённость наличием)
$point_labels = [];

foreach ($questions as $q) {
    $qid = $q['id'];
    $stmt = $pdo->prepare("SELECT answer_type, ao.weight, r.importance FROM responses r JOIN answer_options ao ON r.answer_option_id = ao.id WHERE r.question_id = ?");
    $stmt->execute([$qid]);
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        $results[] = ['question' => $q['function_title'], 'present_avg' => '—', 'absent_avg' => '—', 'importance_avg' => '—'];
        continue;
    }

    $present_sum = $present_count = $absent_sum = $absent_count = $importance_sum = $importance_count = 0;
    foreach ($rows as $row) {
        $weight = (float)$row['weight'];
        $imp = (int)$row['importance'];
        $factor = $importanceFactors[$imp];
        $adjusted = $weight * $factor;
        if ($row['answer_type'] === 'present') {
            $present_sum += $adjusted;
            $present_count++;
        } else {
            $absent_sum += $adjusted;
            $absent_count++;
        }
        $importance_sum += $imp;
        $importance_count++;
    }

    $present_avg = $present_count ? $present_sum / $present_count : null;
    $absent_avg = $absent_count ? $absent_sum / $absent_count : null;
    $importance_avg = $importance_count ? $importance_sum / $importance_count : null;

    $present_clipped = ($present_avg !== null) ? max(-10, min(10, $present_avg)) : null;
    $absent_clipped = ($absent_avg !== null) ? max(-10, min(10, $absent_avg)) : null;

    $results[] = [
        'question' => $q['function_title'],
        'present_avg' => $present_avg !== null ? round($present_avg, 2) : '—',
        'absent_avg' => $absent_avg !== null ? round($absent_avg, 2) : '—',
        'importance_avg' => $importance_avg !== null ? round($importance_avg, 2) : '—'
    ];

    // Теперь по оси X — absent, по оси Y — present
    if ($absent_clipped !== null && $present_clipped !== null) {
        $x_points[] = $absent_clipped;
        $y_points[] = $present_clipped;
        $point_labels[] = htmlspecialchars($q['function_title']);
    }
}

// математические линии (без изменений)
$x_vals = [];
for ($i = -10; $i <= 10; $i += 0.1) $x_vals[] = $i;
$y_zero = array_fill(0, count($x_vals), 0);
$x_zero = array_fill(0, count($x_vals), 0);
$diagonal = $x_vals;

$x_hyper1 = $y_hyper1 = [];
for ($x = -10; $x <= 0.99; $x += 0.05) { $x_hyper1[] = $x; $y_hyper1[] = -3 / ($x - 1); }
$x_hyper2 = $y_hyper2 = [];
for ($x = -0.99; $x <= 10; $x += 0.05) { $x_hyper2[] = $x; $y_hyper2[] = -3 / ($x + 1); }
?>
<!DOCTYPE html>
<html>
<head>
    <title>Результаты опроса: <?= htmlspecialchars($poll['title']) ?></title>
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
        <h1>Результаты: <?= htmlspecialchars($poll['title']) ?></h1>
        <div><button onclick="window.print()" class="btn btn-primary btn-print">Печать</button><a href="index.php" class="btn btn-secondary">Назад</a></div>
    </div>
    <div class="chart-container"><div id="kansoChart"></div></div>
    <h2>Результаты</h2>
    <table class="table table-bordered table-striped">
        <thead><tr><th>Функция</th><th>Удовлетворенность наличием</th><th>Неудовлетворенность отсутствием</th><th>Средняя важность (0–10)</th></tr></thead>
        <tbody>
        <?php foreach ($results as $row): ?>
            <tr><td><?= htmlspecialchars($row['question']) ?></td><td><?= $row['present_avg'] ?></td><td><?= $row['absent_avg'] ?></td><td><?= $row['importance_avg'] ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <script>
        // Оси (без изменений)
        var traceAxisX = { x: <?= json_encode($x_vals) ?>, y: <?= json_encode($y_zero) ?>, mode: 'lines', line: { color: 'black', width: 1 }, showlegend: false };
        var traceAxisY = { x: <?= json_encode($x_zero) ?>, y: <?= json_encode($x_vals) ?>, mode: 'lines', line: { color: 'black', width: 1 }, showlegend: false };
        var traceDiagonal = { x: <?= json_encode($x_vals) ?>, y: <?= json_encode($diagonal) ?>, mode: 'lines', line: { color: 'limegreen', width: 2 }, name: 'Диагональ (равновесие)' };
        var traceHyper1 = { x: <?= json_encode($x_hyper1) ?>, y: <?= json_encode($y_hyper1) ?>, mode: 'lines', line: { color: 'orange', width: 2 }, name: 'Гипербола -3/(x-1)' };
        var traceHyper2 = { x: <?= json_encode($x_hyper2) ?>, y: <?= json_encode($y_hyper2) ?>, mode: 'lines', line: { color: 'red', width: 2 }, name: 'Гипербола -3/(x+1)' };
        var traceSquare = { x: [-3, 3, 3, -3, -3], y: [-3, -3, 3, 3, -3], mode: 'lines', line: { color: 'grey', width: 2, dash: 'dash' }, name: 'Центральная область (-3..3)', fill: 'none' };
        
        // Точки данных: X = неудовлетворённость отсутствием, Y = удовлетворённость наличием
        var tracePoints = {
            x: <?= json_encode($x_points) ?>,
            y: <?= json_encode($y_points) ?>,
            mode: 'markers',
            type: 'scatter',
            marker: { size: 12, color: 'blue', symbol: 'circle', line: { color: 'darkblue', width: 1 } },
            text: <?= json_encode($point_labels) ?>,
            hoverinfo: 'text+x+y',
            name: 'Функции'
        };
        
        var layout = {
            title: { text: "Результаты", font: { size: 14, family: 'Montserrat' }, x: 0.05, xanchor: 'left' },
            font: { family: 'Montserrat, sans-serif', size: 12 },
            xaxis: { title: "Неудовлетворенность отсутствием", range: [-10, 10], zeroline: false, scaleanchor: 'y', scaleratio: 1 },
            yaxis: { title: "Удовлетворенность наличием", range: [-10, 10], zeroline: false, scaleanchor: 'x', scaleratio: 1 },
            autosize: true, showlegend: true, legend: { orientation: 'h', y: -0.2, x: 0, xanchor: 'left', font: { size: 10 } },
            hovermode: 'closest', plot_bgcolor: 'white', paper_bgcolor: 'white', margin: { l: 50, r: 20, t: 80, b: 50 }
        };
        
        var config = { responsive: true, displayModeBar: true, modeBarButtonsToRemove: ['lasso2d', 'select2d'] };
        
        Plotly.newPlot('kansoChart', [traceAxisX, traceAxisY, traceDiagonal, traceHyper1, traceHyper2, traceSquare, tracePoints], layout, config);
        
        window.addEventListener('resize', () => Plotly.relayout('kansoChart', { 'xaxis.range': [-10, 10], 'yaxis.range': [-10, 10] }));
    </script>
</body>
</html>