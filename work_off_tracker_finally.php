<?php
class work_off_tracker_finally {
    private $pdo;

    public function __construct($servername, $username, $password, $dbname) {
        date_default_timezone_set("Asia/Tashkent");
        $this->pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    }

    private function calculate_seconds_to_hour($sec) {
        return floor($sec / 3600);
    }

    public function addEntry($arrived_at, $leaved_at) {
        if (!empty($arrived_at) && !empty($leaved_at)) {
            $arrivedat = new DateTime($arrived_at);
            $leavedat = new DateTime($leaved_at);

            $work_off_time_sum = 0;
            $entitled_time_sum = 0;

            $arrivedatFormatted = $arrivedat->format("Y-m-d H:i:s");
            $leavedatFormatted = $leavedat->format("Y-m-d H:i:s");

            $interval = $arrivedat->diff($leavedat);
            $workingDurationSeconds = ($interval->h * 3600) + ($interval->i * 60) + $interval->s;

            $const_work_time = 32400;

            if ($workingDurationSeconds > $const_work_time){
                $debted_time = $workingDurationSeconds - $const_work_time;
                $req_work_off_timee = $this->calculate_seconds_to_hour($debted_time);
                $work_off_time_sum += $req_work_off_timee;
            } else if ($workingDurationSeconds < $const_work_time){
                $debted_time = $const_work_time - $workingDurationSeconds;
                $entitled = $this->calculate_seconds_to_hour($debted_time);
                $entitled_time_sum += $entitled;
            }

            $query = $this->pdo->query("SELECT * FROM Daily")->fetchAll();

            $new_entitled_time_sum = 0;
            $new_work_off_time_sum = 0;
            foreach ($query as $row) {
                $new_entitled_time_sum += $row['req_work_off_time_sum'];
                $new_work_off_time_sum += $row['entitled_time_sum'];
            }

            if($work_off_time_sum > $entitled_time_sum){
                $new_work_off_time_sum = $work_off_time_sum - $entitled_time_sum;
            }elseif ($work_off_time_sum < $entitled_time_sum) {
                $new_entitled_time_sum = $entitled_time_sum - $work_off_time_sum;
            }else{
                $new_work_off_time_sum = 0;
                $new_entitled_time_sum = 0;
            }

            $workingDurationSeconds = $this->calculate_seconds_to_hour($workingDurationSeconds);

            $sql = "INSERT INTO Daily (arrived_at, leaved_at, working_duration, req_work_off_time, entitled, req_work_off_time_sum, entitled_time_sum) 
                    VALUES (:arrived_at, :leaved_at, :working_duration, :req_work_off_time, :entitled, :req_work_off_time_sum, :entitled_time_sum)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':arrived_at', $arrivedatFormatted);
            $stmt->bindParam(':leaved_at', $leavedatFormatted);
            $stmt->bindParam(':working_duration', $workingDurationSeconds);
            $stmt->bindParam(':req_work_off_time', $entitled);
            $stmt->bindParam(':entitled', $req_work_off_timee);
            $stmt->bindParam(':req_work_off_time_sum', $new_entitled_time_sum);
            $stmt->bindParam(':entitled_time_sum', $new_work_off_time_sum);
            $stmt->execute();
            return "Dates successfully added.";
        } else {
            return "Please fill the gaps!";
        }
    }

    public function displayEntries() {
        $query = $this->pdo->query("SELECT * FROM Daily")->fetchAll();
        foreach ($query as $row) {
            $doneClass = $row["status"] === 'done' ? 'done' : '';
            echo "<tr id='row-{$row["id"]}' class='$doneClass'>
                    <td>{$row["id"]}</td>
                    <td>{$row['arrived_at']}</td>
                    <td>{$row["leaved_at"]}</td>
                    <td>{$row["working_duration"]} Hours</td>
                    <td>{$row["req_work_off_time"]}</td>
                    <td>{$row["entitled"]}</td>
                    <td>{$row["req_work_off_time_sum"]}</td>
                    <td>{$row["entitled_time_sum"]}</td>
                    <td><button onclick='markAsDone({$row["id"]})'>Done</button></td>
                  </tr>";
        }
    }

    public function markAsDone($id) {
        $sql = "UPDATE Daily SET status = 'done' WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
    }

    public function exportToCSV() {
        $filename = "work_off_tracker.csv";
        $fp = fopen($filename, 'w');

        $query = $this->pdo->query("SELECT * FROM Daily");
        $rows = $query->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            fputcsv($fp, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($fp, $row);
            }
        }

        fclose($fp);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename=' . $filename);
        readfile($filename);
        exit;
    }
}

$servername = "localhost";
$username = "root";
$password = "1234";
$dbname = "work_off_tracker";

$workOffTracker = new work_off_tracker_finally($servername, $username, $password, $dbname);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST["arrived_at"]) && isset($_POST["leaved_at"])) {
        $message = $workOffTracker->addEntry($_POST["arrived_at"], $_POST["leaved_at"]);
        echo "<script>
                document.getElementById('message').innerText = '$message';
                document.getElementById('message').classList.add('$message === 'Dates successfully added.' ? 'success' : 'error');
                document.getElementById('message').style.display = 'block';
            </script>";
    } else if (isset($_POST['markAsDone'])) {
        $workOffTracker->markAsDone($_POST['id']);
        echo 'Success';
        exit;
    } else if (isset($_POST['exportToCSV'])) {
        $workOffTracker->exportToCSV();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work Off Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 0;
            transition: background-color 0.3s, color 0.3s;
        }
        .container {
            width: 80%;
            margin: 20px auto;
            background: var(--container-bg-color);
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            transition: background-color 0.3s;
        }
        h1 {
            text-align: center;
        }
        .mode-toggle {
            position: absolute;
            top: 20px;
            right: 20px;
            cursor: pointer;
            font-size: 24px;
            transition: color 0.3s;
        }
        .message {
            margin: 10px 0;
            padding: 10px;
            border-radius: 4px;
            display: none;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
        }
        form {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        input[type="text"] {
            flex-grow: 1;
            padding: 5px;
            margin-right: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .done {
            text-decoration: line-through;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid #ccc;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
    </style>
</head>
<body>
<div class="mode-toggle" onclick="toggleTheme()"><i class="fas fa-moon"></i></div>
<div class="container">
    <h1>Work Off Tracker</h1>
    <div id="message" class="message"></div>
    <form id="workOffForm" method="POST" action="work_off_tracker_finally.php">
        <input type="datetime-local" name="arrived_at" placeholder="Arrived At (YYYY-MM-DD HH:MM:SS)">
        <input type="datetime-local" name="leaved_at" placeholder="Leaved At (YYYY-MM-DD HH:MM:SS)">
        <button type="submit">Add Entry</button>
    </form>
    <button onclick="exportToCSV()">Export to CSV</button>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Arrived At</th>
            <th>Leaved At</th>
            <th>Working Duration</th>
            <th>Requested Work Off Time</th>
            <th>Entitled</th>
            <th>Requested Work Off Time Sum</th>
            <th>Entitled Time Sum</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <?php $workOffTracker->displayEntries(); ?>
        </tbody>
    </table>
</div>
<script>
    function toggleTheme() {
        const body = document.body;
        const icon = document.querySelector('.mode-toggle');
        const isDark = body.getAttribute('data-theme') === 'dark';
        body.setAttribute('data-theme', isDark ? 'light' : 'dark');
        icon.classList.toggle('fa-sun', isDark);
        icon.classList.toggle('fa-moon', !isDark);
    }

    document.getElementById('workOffForm').addEventListener('submit', function (event) {
        const arrivedAt = document.querySelector('input[name="arrived_at"]').value;
        const leavedAt = document.querySelector('input[name="leaved_at"]').value;
        const messageDiv = document.getElementById('message');

        if (!arrivedAt || !leavedAt) {
            event.preventDefault();
            messageDiv.innerText = 'Please fill the gaps!';
            messageDiv.classList.remove('success');
            messageDiv.classList.add('error');
            messageDiv.style.display = 'block';
        }
    });

    function markAsDone(id) {
        const formData = new FormData();
        formData.append('id', id);
        formData.append('markAsDone', true);

        fetch('work_off_tracker_finally.php', {
            method: 'POST',
            body: formData
        }).then(response => response.text()).then(data => {
            if (data.trim() === 'Success') {
                document.getElementById(`row-${id}`).classList.add('done');
            } else {
                console.error('Failed to mark as done');
            }
        });
    }

    function exportToCSV() {
        const formData = new FormData();
        formData.append('exportToCSV', true);

        fetch('work_off_tracker_finally.php', {
            method: 'POST',
            body: formData
        }).then(response => response.blob()).then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = 'work_off_tracker.csv';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
        }).catch(error => console.error('Failed to export CSV:', error));
    }
</script>
</body>
</html>
