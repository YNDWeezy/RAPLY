<?php
require 'dbconnect.php';

$response = ["success" => false];

if (isset($_GET['id'])) {
    $videoId = intval($_GET['id']);
    
    try {
        $query = $pdo->prepare("UPDATE videos SET view_count = view_count + 1 WHERE video_id = ?");
        $query->execute([$videoId]);

        if ($query->rowCount() > 0) {
            $response["success"] = true;
        }
    } catch (Exception $e) {
        $response["error"] = $e->getMessage();
    }
}

echo json_encode($response);
?>
