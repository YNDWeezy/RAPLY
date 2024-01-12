<?php
session_start();
include 'dbconnect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Assurez-vous que l'utilisateur est connecté
    if (!isset($_SESSION['user'])) {
        die("Vous devez être connecté pour répondre.");
    }

    $commentId = $_POST['comment_id'];
    $replyContent = $_POST['reply_content'];
    $userId = $_SESSION['user']['user_id'];
    $videoId = $_POST['video_id']; // Récupérer l'ID de la vidéo


    // Préparez et exécutez la requête pour insérer la réponse
    $insertReply = $pdo->prepare("INSERT INTO comment_replies (comment_id, user_id, content) VALUES (?, ?, ?)");
    $insertReply->execute([$commentId, $userId, $replyContent]);

    // Redirection vers la page de la vidéo
    header("Location: watch.php?id=$videoId");
    exit;
}
?>
