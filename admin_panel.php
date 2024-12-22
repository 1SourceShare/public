<?php
// Проверка сессии администратора
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

// Получение текущей даты
$currentDate = date("Y-m-d");

// Получение сводной информации
$totalEmployees = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];
$workingEmployeesQuery = $conn->prepare("SELECT COUNT(DISTINCT user_id) AS working FROM work_entries WHERE date = ?");
$workingEmployeesQuery->bind_param("s", $currentDate);
$workingEmployeesQuery->execute();
$workingEmployees = $workingEmployeesQuery->get_result()->fetch_assoc()['working'];

$totalObjectsQuery = $conn->prepare("SELECT COUNT(DISTINCT object) AS total_objects FROM work_entries WHERE date = ?");
$totalObjectsQuery->bind_param("s", $currentDate);
$totalObjectsQuery->execute();
$totalObjects = $totalObjectsQuery->get_result()->fetch_assoc()['total_objects'];

$onVacation = $conn->query("SELECT COUNT(*) AS on_vacation FROM users WHERE status = 'В отпуске'")->fetch_assoc()['on_vacation'];
$onSickLeave = $conn->query("SELECT COUNT(*) AS on_sick_leave FROM users WHERE status = 'На больничном'")->fetch_assoc()['on_sick_leave'];

// Получение списка сотрудников по объектам
$objectsQuery = $conn->prepare("SELECT w.object, COUNT(DISTINCT w.user_id) AS total, GROUP_CONCAT(CONCAT(u.name, ' (', u.position, ')') SEPARATOR ', ') AS employees
                                 FROM work_entries w
                                 JOIN users u ON w.user_id = u.id
                                 WHERE w.date = ?
                                 GROUP BY w.object");
$objectsQuery->bind_param("s", $currentDate);
$objectsQuery->execute();
$objectsResult = $objectsQuery->get_result();

// Получение сотрудников на больничном и в отпуске
$sickEmployees = $conn->query("SELECT name, position FROM users WHERE status = 'На больничном'")->fetch_all(MYSQLI_ASSOC);
$vacationEmployees = $conn->query("SELECT name, position FROM users WHERE status = 'В отпуске'")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-Панель</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .summary {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .table-container {
            margin-top: 40px;
        }
        .employee-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .employee-list li {
            padding: 5px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #ddd;
        }
        .employee-list li:last-child {
            border-bottom: none;
        }
        .employee-list li span {
            font-weight: bold;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">Админ-Панель</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link active" href="admin_panel.php">Отчет</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="admin_dashboard.php">Сотрудники</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="admin_objects.php">Объекты</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-5">
    <h1 class="text-center mb-5">Отчет</h1>

    <!-- Сводка -->
    <div class="summary">
        <h4 class="mb-4">Сводка на <?php echo htmlspecialchars($currentDate); ?></h4>
        <p><strong>Всего сотрудников:</strong> <?php echo $totalEmployees; ?></p>
        <p><strong>На работе:</strong> <?php echo $workingEmployees; ?></p>
        <p><strong>Всего объектов:</strong> <?php echo $totalObjects; ?></p>
        <p><strong>На больничном:</strong> <?php echo $onSickLeave; ?></p>
        <p><strong>В отпуске:</strong> <?php echo $onVacation; ?></p>
    </div>

    <!-- Таблица объектов -->
    <div class="table-container">
        <h2 class="mb-4">Отметки по объектам</h2>
        <table class="table table-bordered">
            <thead>
            <tr>
                <th>Объект</th>
                <th>Общее количество сотрудников</th>
                <th>Сотрудники</th>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = $objectsResult->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['object']); ?></td>
                    <td><?php echo htmlspecialchars($row['total']); ?></td>
                    <td>
                        <ul class="employee-list">
                            <?php foreach (explode(', ', $row['employees']) as $employee): ?>
                                <li><?php echo htmlspecialchars($employee); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Сотрудники на больничном -->
    <div class="table-container">
        <h2 class="mb-4">Сотрудники на больничном</h2>
        <p><strong>Всего:</strong> <?php echo count($sickEmployees); ?></p>
        <ul class="employee-list">
            <?php foreach ($sickEmployees as $employee): ?>
                <li><span><?php echo htmlspecialchars($employee['name']); ?></span> — <?php echo htmlspecialchars($employee['position']); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Сотрудники в отпуске -->
    <div class="table-container">
        <h2 class="mb-4">Сотрудники в отпуске</h2>
        <p><strong>Всего:</strong> <?php echo count($vacationEmployees); ?></p>
        <ul class="employee-list">
            <?php foreach ($vacationEmployees as $employee): ?>
                <li><span><?php echo htmlspecialchars($employee['name']); ?></span> — <?php echo htmlspecialchars($employee['position']); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
</body>
</html>