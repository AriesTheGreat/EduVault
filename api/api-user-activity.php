<?php

header('Content-Type: application/json');
$mysqli = new mysqli("localhost", "root", "", "eduvault");

$query = "
    SELECT DATE_FORMAT(timestamp, '%H:%i') AS time, COUNT(*) AS count
    FROM user_activity
    WHERE timestamp >= NOW() - INTERVAL 1 HOUR
    GROUP BY time
    ORDER BY time ASC
";

$result = $mysqli->query($query);
$labels = [];
$data = [];

while ($row = $result->fetch_assoc()) {
    $labels[] = $row['time'];
    $data[] = (int)$row['count'];
}

echo json_encode(["labels" => $labels, "data" => $data]);
