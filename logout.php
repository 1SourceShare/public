<?php
session_start();
session_destroy();

// Перенаправляем на страницу логина
header("Location: admin_login.php");
exit;
?>
