<?php
header('Content-Type: application/json');
$mysqli = new mysqli("localhost", "root", "", "eduvault");

$query = "
    SELECT status, COUNT(*) AS count
    FROM requests
    GROUP BY status
";

$result = $mysqli->query($query);
$labels = [];
$data = [];

while ($row = $result->fetch_assoc()) {
    $labels[] = $row['status'];
    $data[] = (int)$row['count'];
}

echo json_encode(["labels" => $labels, "data" => $data]);
