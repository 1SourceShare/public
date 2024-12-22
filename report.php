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
$totalEmployeesQuery = "SELECT COUNT(*) AS total FROM users";
$totalEmployees = $conn->query($totalEmployeesQuery)->fetch_assoc()['total'];

$workingEmployeesQuery = "SELECT COUNT(DISTINCT user_id) AS working FROM work_entries WHERE date = ?";
$stmt = $conn->prepare($workingEmployeesQuery);
$stmt->bind_param("s", $currentDate);
$stmt->execute();
$workingEmployees = $stmt->get_result()->fetch_assoc()['working'];

$totalObjectsQuery = "SELECT COUNT(DISTINCT object) AS total_objects FROM work_entries WHERE date = ?";
$stmt = $conn->prepare($totalObjectsQuery);
$stmt->bind_param("s", $currentDate);
$stmt->execute();
$totalObjects = $stmt->get_result()->fetch_assoc()['total_objects'];

$onVacationQuery = "SELECT COUNT(*) AS on_vacation FROM users WHERE status = 'В отпуске'";
$onVacation = $conn->query($onVacationQuery)->fetch_assoc()['on_vacation'];

$onSickLeaveQuery = "SELECT COUNT(*) AS on_sick_leave FROM users WHERE status = 'На больничном'";
$onSickLeave = $conn->query($onSickLeaveQuery)->fetch_assoc()['on_sick_leave'];

// Получение списка сотрудников по объектам
$objectsQuery = "SELECT w.object, COUNT(DISTINCT w.user_id) AS total, GROUP_CONCAT(CONCAT(u.name, ' (', u.position, ')') SEPARATOR ', ') AS employees
                 FROM work_entries w
                 JOIN users u ON w.user_id = u.id
                 WHERE w.date = ?
                 GROUP BY w.object";
$stmt = $conn->prepare($objectsQuery);
$stmt->bind_param("s", $currentDate);
$stmt->execute();
$objectsResult = $stmt->get_result();

// Получение сотрудников на больничном
$sickEmployeesQuery = "SELECT name, position FROM users WHERE status = 'На больничном'";
$sickEmployees = $conn->query($sickEmployeesQuery)->fetch_all(MYSQLI_ASSOC);

// Получение сотрудников в отпуске
$vacationEmployeesQuery = "SELECT name, position FROM users WHERE status = 'В отпуске'";
$vacationEmployees = $conn->query($vacationEmployeesQuery)->fetch_all(MYSQLI_ASSOC);

// Закрытие соединения
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отчет</title>
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
                            <?php
                            $employees = explode(', ', $row['employees']);
                            foreach ($employees as $employee): ?>
                                <li><?php echo htmlspecialchars($employee); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="table-container">
    <h2 class="mb-4">Сотрудники на больничном</h2>
    <p><strong>Всего:</strong> <?php echo count($sickEmployees); ?></p>
    <ul class="employee-list">
        <?php foreach ($sickEmployees as $employee): ?>
            <li><span><?php echo htmlspecialchars($employee['name']); ?></span> — <?php echo htmlspecialchars($employee['position']); ?></li>
        <?php endforeach; ?>
    </ul>
</div>

<div class="table-container">
    <h2 class="mb-4">Сотрудники в отпуске</h2>
    <p><strong>Всего:</strong> <?php echo count($vacationEmployees); ?></p>
    <ul class="employee-list">
        <?php foreach ($vacationEmployees as $employee): ?>
            <li><span><?php echo htmlspecialchars($employee['name']); ?></span> — <?php echo htmlspecialchars($employee['position']); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
</body>
</html>