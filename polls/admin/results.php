<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../includes/db.php';

$poll_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$poll_id) die('Не указан ID опроса');

// Получаем опрос
$stmt = $pdo->prepare("SELECT title FROM polls WHERE id = ?");
$stmt->execute([$poll_id]);
$poll = $stmt->fetch();
if (!$poll) die('Опрос не найден');

// Получаем все вопросы этого опроса
$questions = $pdo->prepare("SELECT id, function_title FROM functions WHERE poll_id = ? ORDER BY id");
$questions->execute([$poll_id]);
$questions = $questions->fetchAll();

// Для каждого вопроса вычисляем средний вес для present и absent
$results = [];
$x_points = [];
$y_points = [];
$point_labels = [];

foreach ($questions as $q) {
    $qid = $q['id'];
    // Средний вес для present, умножаем на 10, округляем до сотых
    $stmt = $pdo->prepare("
        SELECT AVG(ao.weight) as avg_weight
        FROM responses r
        JOIN answer_options ao ON r.answer_option_id = ao.id
        WHERE r.question_id = ? AND r.answer_type = 'present'
    ");
    $stmt->execute([$qid]);
    $present_avg = $stmt->fetchColumn();
    if ($present_avg !== null) {
        $present_avg = round($present_avg * 10, 2);
    }
    $present_display = ($present_avg === null) ? '—' : $present_avg;
    
    // Средний вес для absent, умножаем на 10, округляем до сотых
    $stmt = $pdo->prepare("
        SELECT AVG(ao.weight) as avg_weight
        FROM responses r
        JOIN answer_options ao ON r.answer_option_id = ao.id
        WHERE r.question_id = ? AND r.answer_type = 'absent'
    ");
    $stmt->execute([$qid]);
    $absent_avg = $stmt->fetchColumn();
    if ($absent_avg !== null) {
        $absent_avg = round($absent_avg * 10, 2);
    }
    $absent_display = ($absent_avg === null) ? '—' : $absent_avg;
    
    $results[] = [
        'question' => $q['function_title'],
        'present_avg' => $present_display,
        'absent_avg' => $absent_display
    ];
    
    // Для графика используем только числа, null пропускаем
    if ($present_avg !== null && $absent_avg !== null) {
        $x_points[] = (float)$present_avg;
        $y_points[] = (float)$absent_avg;
        $point_labels[] = htmlspecialchars($q['function_title']);
    }
}

// Генерация данных для математических линий (масштаб от -10 до 10)
$x_vals = [];
for ($i = -10; $i <= 10; $i += 0.1) {
    $x_vals[] = $i;
}
$y_zero = array_fill(0, count($x_vals), 0);
$x_zero = array_fill(0, count($x_vals), 0);

$diagonal = $x_vals;

$x_hyper1 = [];
$y_hyper1 = [];
for ($x = -10; $x <= 0.99; $x += 0.05) {
    $x_hyper1[] = $x;
    $y_hyper1[] = -30 / ($x - 1); // -3 * 10 = -30, т.к. весы увеличены в 10 раз
}

$x_hyper2 = [];
$y_hyper2 = [];
for ($x = -0.99; $x <= 10; $x += 0.05) {
    $x_hyper2[] = $x;
    $y_hyper2[] = -30 / ($x + 1);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Результаты опроса: <?= htmlspecialchars($poll['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat&display=swap" rel="stylesheet">
    <script src="https://cdn.plot.ly/plotly-2.27.1.min.js" charset="utf-8"></script>
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background: #f5f7fa;
        }
        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        #kansoChart {
            width: 100%;
            aspect-ratio: 1 / 1; /* квадратный блок */
        }
        .btn-print {
            margin-left: 10px;
        }
        @media print {
            .btn-print, .btn-secondary, .no-print {
                display: none;
            }
            .chart-container {
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
</head>
<body class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1>Результаты: <?= htmlspecialchars($poll['title']) ?></h1>
        <div>
            <button onclick="window.print()" class="btn btn-primary btn-print">Печать</button>
            <a href="index.php" class="btn btn-secondary">Назад к опросам</a>
        </div>
    </div>

    <!-- График Oxy с гиперболами -->
    <div class="chart-container">
        <div id="kansoChart"></div>
    </div>

    <!-- Таблица со средними баллами -->
    <h2>Числовые значения (баллы ×10)</h2>
    <table class="table table-bordered table-striped">
        <thead>
            <tr><th>Вопрос</th><th>Удовлетворенность наличием (×10)</th><th>Неудовлетворенность отсутствием (×10)</th></tr>
        </thead>
        <tbody>
        <?php foreach ($results as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['question']) ?></td>
                <td><?= $row['present_avg'] ?></td>
                <td><?= $row['absent_avg'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <script>
        var traceAxisX = {
            x: <?= json_encode($x_vals) ?>,
            y: <?= json_encode($y_zero) ?>,
            mode: 'lines',
            line: { color: 'black', width: 1 },
            name: 'Удовлетворенность наличием',
            showlegend: false
        };
        var traceAxisY = {
            x: <?= json_encode($x_zero) ?>,
            y: <?= json_encode($x_vals) ?>,
            mode: 'lines',
            line: { color: 'black', width: 1 },
            name: 'Неудовлетворенность отсутствием',
            showlegend: false
        };
        
        // y = x
        var traceDiagonal = {
            x: <?= json_encode($x_vals) ?>,
            y: <?= json_encode($diagonal) ?>,
            mode: 'lines',
            line: { color: 'limegreen', width: 2, dash: 'solid' },
            name: 'Диагональ (равновесие)'
        };
        
        // Гиперболы (с учётом масштаба ×10)
        var traceHyper1 = {
            x: <?= json_encode($x_hyper1) ?>,
            y: <?= json_encode($y_hyper1) ?>,
            mode: 'lines',
            line: { color: 'orange', width: 2 },
            name: 'Гипербола -30/(x-1)'
        };
        var traceHyper2 = {
            x: <?= json_encode($x_hyper2) ?>,
            y: <?= json_encode($y_hyper2) ?>,
            mode: 'lines',
            line: { color: 'red', width: 2 },
            name: 'Гипербола -30/(x+1)'
        };
        
        // Центральный квадрат [-3,3] x [-3,3] в оригинале, но теперь масштаб ×10 => [-30,30]?
        // Оставим как было, но чтобы соответствовать логике, используем [-30,30] или [-3,3]? 
        // Поскольку веса умножены на 10, то старый диапазон [-3,3] превращается в [-30,30].
        // Однако для наглядности оставим [-3,3] без умножения? Нет, логичнее тоже умножить.
        // Но поскольку пользователи ожидают область от -3 до 3 в исходных единицах, 
        // а в новых единицах это -30..30. Сделаем квадрат от -30 до 30, чтобы он соответствовал масштабу.
        var traceSquare = {
            x: [-30, 30, 30, -30, -30],
            y: [-30, -30, 30, 30, -30],
            mode: 'lines',
            line: { color: 'grey', width: 2, dash: 'dash' },
            name: 'Центральная область (-30..30)',
            fill: 'none'
        };
        
        // Точки данных опроса (present_avg, absent_avg) уже умножены на 10
        var tracePoints = {
            x: <?= json_encode($x_points) ?>,
            y: <?= json_encode($y_points) ?>,
            mode: 'markers',
            type: 'scatter',
            marker: {
                size: 12,
                color: 'blue',
                symbol: 'circle',
                line: { color: 'darkblue', width: 1 }
            },
            text: <?= json_encode($point_labels) ?>,
            hoverinfo: 'text+x+y',
            name: 'Функции (вопросы)'
        };
        
        var layout = {
            title: {
                text: "Проект: Пример<br>Результаты опроса пользователей",
                font: { size: 14, family: 'Montserrat' },
                x: 0.05,
                xanchor: 'left'
            },
            font: {
                family: 'Montserrat, sans-serif',
                size: 12
            },
            xaxis: {
                title: "Средний балл ×10 (удовлетворенность)",
                range: [-10, 10],
                zeroline: false,
                showgrid: true,
                gridcolor: '#e0e0e0',
                tickvals: [-10, -8, -6, -4, -2, 0, 2, 4, 6, 8, 10],
                ticktext: ['-10', '', '-6', '', '-2', '0', '2', '', '6', '', '10'],
                scaleanchor: 'y',   // привязываем масштаб к оси Y
                scaleratio: 1       // одинаковый масштаб
            },
            yaxis: {
                title: "Средний балл ×10 (неудовлетворенность)",
                range: [-10, 10],
                zeroline: false,
                showgrid: true,
                gridcolor: '#e0e0e0',
                tickvals: [-10, -8, -6, -4, -2, 0, 2, 4, 6, 8, 10],
                ticktext: ['-10', '', '-6', '', '-2', '0', '2', '', '6', '', '10'],
                scaleanchor: 'x',
                scaleratio: 1
            },
            autosize: true,
            showlegend: true,
            legend: {
                orientation: 'h',
                y: -0.2,
                x: 0,
                xanchor: 'left',
                font: { size: 10 }
            },
            hovermode: 'closest',
            plot_bgcolor: 'white',
            paper_bgcolor: 'white',
            margin: { l: 50, r: 20, t: 80, b: 50 }
        };
        
        var config = {
            responsive: true,
            displayModeBar: true,
            modeBarButtonsToRemove: ['lasso2d', 'select2d']
        };
        
        Plotly.newPlot('kansoChart', 
            [traceAxisX, traceAxisY, traceDiagonal, traceHyper1, traceHyper2, traceSquare, tracePoints], 
            layout, 
            config
        );
        
        // При изменении размера окна пересчитываем layout (для сохранения квадратности)
        window.addEventListener('resize', function() {
            Plotly.relayout('kansoChart', {
                'xaxis.range': [-10, 10],
                'yaxis.range': [-10, 10]
            });
        });
    </script>
</body>
</html>