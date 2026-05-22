<?php
/**
 * Рендеринг блока темы (для старого варианта без polls)
 */
function renderTopicBlock($topicId, $topicTitle) {
    $html = '<div class="poll mb-4" data-function-id="' . $topicId . '" data-function-title="' . htmlspecialchars($topicTitle) . '">';
    $html .= '<div class="margins p-3 border rounded">';
    $html .= '<h4 class="title">' . htmlspecialchars($topicTitle) . '</h4>';
    $html .= renderQuestionBlock($topicId, 'present', 'Как вы отнесетесь к нашему проекту <strong>при наличии</strong> этой функции?');
    $html .= renderQuestionBlock($topicId, 'absent', 'Как вы отнесетесь к нашему проекту <strong>при отсутствии</strong> этой функции?');
    $html .= '</div></div>';
    return $html;
}

function renderQuestionBlock($topicId, $type, $questionText) {
    $options = [
        'positive' => 'положительно',
        'neutral'  => 'нейтрально',
        'negative' => 'негативно'
    ];
    $html = '<div class="function mt-3">';
    $html .= '<p class="question-text">' . $questionText . '</p>';
    foreach ($options as $value => $labelText) {
        $id = "q{$topicId}_{$type}_{$value}";
        $html .= '<div class="form-check">';
        $html .= '<input class="form-check-input" type="radio" ';
        $html .= 'name="answers[' . $topicId . '][' . $type . ']" ';
        $html .= 'value="' . $value . '" id="' . $id . '">';
        $html .= '<label class="form-check-label" for="' . $id . '">' . $labelText . '</label>';
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}
?>