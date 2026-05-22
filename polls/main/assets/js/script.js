document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('pollForm');
    if (!form) {
        console.error('Форма не найдена');
        return;
    }

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const statusDiv = document.getElementById('statusMessage');
        if (!statusDiv) {
            console.error('Нет элемента #statusMessage');
            return;
        }
        statusDiv.innerHTML = '<div class="spinner-border text-primary"></div> Отправка...';

        const formData = new FormData(this);
        const questionBlocks = document.querySelectorAll('[data-question-id]');
        let missing = [];
        questionBlocks.forEach(block => {
            const title = block.dataset.questionTitle;
            const qid = block.dataset.questionId;
            const presentSelected = block.querySelector(`input[name="answers[${qid}][present]"]:checked`);
            const absentSelected = block.querySelector(`input[name="answers[${qid}][absent]"]:checked`);
            if (!presentSelected) missing.push(`«${title}» (при наличии)`);
            if (!absentSelected) missing.push(`«${title}» (при отсутствии)`);
        });

        if (missing.length > 0) {
            statusDiv.innerHTML = `<div class="alert alert-danger">Пожалуйста, ответьте на все вопросы:<br>` + `${missing.join('<br>')}</div>`;
            return;
        }

        try {
            const response = await fetch('submit.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();


            if (result.success) {
                statusDiv.innerHTML = `<div class="alert alert-success">${result.message}</div>`;
                setTimeout(() => {
                    window.location.href = '../main/index.php';
                }, 1000);
            } else {
                statusDiv.innerHTML = `<div class="alert alert-danger">Ошибка: ${result.message}</div>`;
            }

        } catch (error) {
            console.error('Fetch error:', error);
            statusDiv.innerHTML = `<div class="alert alert-danger">Ошибка соединения: ${error.message}</div>`;
        }
    });
});