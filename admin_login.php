<?php
session_start();

// Логиним администратора (это упрощённая версия)
$_SESSION['is_admin'] = true;

// Перенаправляем на панель администратора
header("Location: admin_dashboard.php");
exit;
?>