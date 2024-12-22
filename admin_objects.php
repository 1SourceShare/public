<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("Доступ запрещен.");
}

// Подключение к базе данных
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "work_tracking_app";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}

// Добавление нового объекта
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['object_name'])) {
    $objectName = $_POST['object_name'];
    if (!empty($objectName)) {
        $stmt = $conn->prepare("INSERT INTO objects (name) VALUES (?)");
        $stmt->bind_param("s", $objectName);
        if ($stmt->execute()) {
            $message = "Объект успешно добавлен.";
        } else {
            $message = "Ошибка: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $message = "Название объекта не может быть пустым.";
    }
}

// Удаление объекта
if (isset($_POST['delete_object_id'])) {
    $objectId = $_POST['delete_object_id'];
    $stmt = $conn->prepare("DELETE FROM objects WHERE id = ?");
    $stmt->bind_param("i", $objectId);
    if ($stmt->execute()) {
        $message = "Объект успешно удален.";
    } else {
        $message = "Ошибка удаления объекта.";
    }
    $stmt->close();
}

// Получение списка объектов
$result = $conn->query("SELECT * FROM objects ORDER BY name");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Объекты</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container mt-5">
    <h1 class="text-center">Управление объектами</h1>

    <!-- Сообщение -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <!-- Форма добавления объекта -->
    <form method="post" action="admin_objects.php" class="mt-4">
        <div class="mb-3">
            <label for="object_name" class="form-label">Название объекта</label>
            <input type="text" class="form-control" id="object_name" name="object_name" placeholder="Введите название объекта" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Добавить объект</button>
    </form>

    <!-- Список объектов -->
    <h2 class="mt-5">Список объектов</h2>
    <table class="table table-bordered">
        <thead>
        <tr>
            <th>ID</th>
            <th>Название</th>
            <th>Действия</th>
        </tr>
        </thead>
        <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['name']); ?></td>
                <td>
                    <form method="post" action="admin_objects.php" style="display:inline-block;">
                        <input type="hidden" name="delete_object_id" value="<?php echo $row['id']; ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Удалить</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>