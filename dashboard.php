<?php
// Подключение к базе данных
$servername = "localhost"; // Сервер базы данных
$username = "root";        // Имя пользователя MySQL
$password = "";            // Пароль MySQL (пустой, если не задавался)
$dbname = "work_tracking_app"; // Имя базы данных

// Создание подключения
$conn = new mysqli($servername, $username, $password, $dbname);

// Проверка подключения
if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}

// Получение данных из формы
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

// Проверка пользователя в базе
$sql = "SELECT * FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    if ($user['password'] === $password) {
        echo "Добро пожаловать, " . htmlspecialchars($user['name']) . "!";
    } else {
        echo "Неверный пароль.";
    }
} else {
    echo "Пользователь с таким email не найден.";
}

// Закрытие подключения
$stmt->close();
$conn->close();
?>
