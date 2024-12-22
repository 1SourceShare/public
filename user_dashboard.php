<?php
session_start();
if (!isset($_SESSION['user_id'])) {
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

$message = '';
$user_id = $_SESSION['user_id'];

// Получение списка объектов
$objectsResult = $conn->query("SELECT name FROM objects ORDER BY name");

// Обработка данных формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? '';
    $object = $_POST['object'] ?? null;
    $work_done = $_POST['work_done'] ?? null;
    $photo = $_FILES['photo'] ?? null;

    $current_date = date("Y-m-d");

    if (isset($_POST['time_in'])) {
        // Обработка "Пришел"
        $time_in = date("Y-m-d H:i:s");
        $photo_path_in = null;

        if ($photo && $photo['tmp_name']) {
            $target_dir = "../uploads/";
            $photo_path_in = $target_dir . "in_" . basename($photo["name"]);
            if (!move_uploaded_file($photo["tmp_name"], $photo_path_in)) {
                $message = "Ошибка загрузки фото.";
            }
        }

        $stmt = $conn->prepare("INSERT INTO work_entries (user_id, status, object, time_in, photo, date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $user_id, $status, $object, $time_in, $photo_path_in, $current_date);

        if ($stmt->execute()) {
            $message = "Отметка прихода успешно добавлена!";
        } else {
            $message = "Ошибка: " . $stmt->error;
        }
        $stmt->close();
    }

    if (isset($_POST['time_out'])) {
        // Обработка "Ушел"
        $time_out = date("Y-m-d H:i:s");
        $photo_path_out = null;

        if ($photo && $photo['tmp_name']) {
            $target_dir = "../uploads/";
            $photo_path_out = $target_dir . "out_" . basename($photo["name"]);
            if (!move_uploaded_file($photo["tmp_name"], $photo_path_out)) {
                $message = "Ошибка загрузки фото при выходе.";
            }
        }

        $stmt = $conn->prepare("UPDATE work_entries SET time_out = ?, photo_out = ?, work_done = ? WHERE user_id = ? AND date = ?");
        $stmt->bind_param("sssis", $time_out, $photo_path_out, $work_done, $user_id, $current_date);

        if ($stmt->execute()) {
            $message = "Отметка ухода успешно добавлена!";
        } else {
            $message = "Ошибка: " . $stmt->error;
        }
        $stmt->close();

        // Генерация отчета
        require_once '../vendor/autoload.php';
        $pdf = new TCPDF();
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Система учета');
        $pdf->SetTitle("Ежедневный отчет");
        $pdf->AddPage();
        $pdf->SetFont('dejavusans', '', 12);

        $stmt = $conn->prepare("SELECT time_in, time_out, photo, photo_out FROM work_entries WHERE user_id = ? AND date = ?");
        $stmt->bind_param("is", $user_id, $current_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $entry = $result->fetch_assoc();
        $stmt->close();

        $time_in = new DateTime($entry['time_in']);
        $time_out = new DateTime($entry['time_out']);
        $interval = $time_in->diff($time_out);
        $hours = $interval->h;
        $minutes = $interval->i;

        $pdf->Write(0, "Ежедневный отчет", '', 0, 'C', true, 0, false, false, 0);
        $pdf->Ln(10);
        $pdf->Write(0, "Сотрудник: $user_id", '', 0, 'L', true, 0, false, false, 0);
        $pdf->Write(0, "Дата: $current_date", '', 0, 'L', true, 0, false, false, 0);
        $pdf->Write(0, "Время на работе: $hours ч. $minutes мин.", '', 0, 'L', true, 0, false, false, 0);

        if ($entry['photo']) {
            $pdf->Write(0, "Фото при входе:", '', 0, 'L', true, 0, false, false, 0);
            $pdf->Image($entry['photo'], '', '', 50, 50, '', '', 'T', false, 300, '', false, false, 1, false, false, false);
        }

        if ($entry['photo_out']) {
            $pdf->Ln(10);
            $pdf->Write(0, "Фото при выходе:", '', 0, 'L', true, 0, false, false, 0);
            $pdf->Image($entry['photo_out'], '', '', 50, 50, '', '', 'T', false, 300, '', false, false, 1, false, false, false);
        }

        $base_dir = realpath(__DIR__ . '/../reports');
        if (!is_dir($base_dir)) {
            mkdir($base_dir, 0777, true);
        }
        $filename = "$base_dir/Отчет_" . date("Y-m-d") . ".pdf";
        $pdf->Output($filename, 'F');

        $message .= " Ежедневный отчет сохранен: $filename";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        function toggleFields() {
            const timeOutChecked = document.getElementById('time-out').checked;
            document.getElementById('work-done').style.display = timeOutChecked ? 'block' : 'none';
        }

        document.addEventListener('DOMContentLoaded', toggleFields);
    </script>
</head>
<body>
<div class="container mt-5">
    <h1 class="text-center">Личный кабинет</h1>
    <?php if (!empty($message)): ?>
        <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="mt-4">
        <div class="mb-3">
            <label for="status" class="form-label">Статус</label>
            <select id="status" name="status" class="form-select" required>
                <option value="На работе">На работе</option>
                <option value="На больничном">На больничном</option>
                <option value="В отпуске">В отпуске</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="object" class="form-label">Объект</label>
            <select id="object" name="object" class="form-select">
                <?php while ($row = $objectsResult->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($row['name']); ?>"><?php echo htmlspecialchars($row['name']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="photo" class="form-label">Фото</label>
            <input type="file" id="photo" name="photo" class="form-control">
        </div>
        <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" id="time-in" name="time_in">
            <label class="form-check-label" for="time-in">Пришел</label>
        </div>
        <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" id="time-out" name="time_out" onchange="toggleFields()">
            <label class="form-check-label" for="time-out">Ушел</label>
        </div>
        <div id="work-done" style="display: none;" class="mt-3">
            <label for="work_done" class="form-label">Что было сделано</label>
            <textarea id="work_done" name="work_done" class="form-control" rows="3"></textarea>
        </div>
        <button type="submit" class="btn btn-primary w-100 mt-3">Отправить</button>
    </form>
</div>
</body>
</html>