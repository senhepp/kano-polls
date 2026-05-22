<?php

// Параметры подключения к БД
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'poll_db');
define('DB_USER', 'postgres');
define('DB_PASS', '5432');  // замените на свой пароль

// Сохранение хэша для авторизации
define('ADMIN_PASSWORD_HASH', password_hash('admin123', PASSWORD_DEFAULT));

?>