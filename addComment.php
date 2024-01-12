<?php
include 'dbconnect.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Assurez-vous que l'utilisateur est connecté
    if (!isset($_SESSION['user'])) {
        // Gérer le cas où l'utilisateur n'est pas connecté
        die("Vous devez être connecté pour commenter.");
    }

    $videoId = $_POST['video_id'];
    $commentContent = $_POST['comment_content'];
    $userId = $_SESSION['user']['user_id']; 

    // Préparez et exécutez la requête pour insérer le commentaire
    $insertComment = $pdo->prepare("INSERT INTO comments (video_id, user_id, content) VALUES (?, ?, ?)");
    $insertComment->execute([$videoId, $userId, $commentContent]);

    // Redirection vers la page de la vidéo
    header("Location: watch.php?id=$videoId"); 
    exit;
}
?>
