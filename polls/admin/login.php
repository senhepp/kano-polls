<?php
require_once '../includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if (adminLogin($password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'Неверный пароль';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Вход в админку</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <h3>Вход в панель управления</h3>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="mb-3">
                    <label>Пароль</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">Войти</button>
            </form>
        </div>
    </div>
</body>
</html>