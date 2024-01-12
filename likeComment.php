<?php
session_start();
include 'dbconnect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['user'])) {
    $userId = $_SESSION['user']['user_id'];
    $commentId = $_POST['commentId'];

    // Vérifier si l'utilisateur a déjà aimé ce commentaire
    $checkLike = $pdo->prepare("SELECT * FROM comment_likes WHERE user_id = ? AND comment_id = ?");
    $checkLike->execute([$userId, $commentId]);
    if ($checkLike->fetch()) {
        // Supprimer le like
        $deleteLike = $pdo->prepare("DELETE FROM comment_likes WHERE user_id = ? AND comment_id = ?");
        $deleteLike->execute([$userId, $commentId]);
        echo json_encode(['success' => true, 'liked' => false]);
    } else {
        // Ajouter le like
        $addLike = $pdo->prepare("INSERT INTO comment_likes (comment_id, user_id) VALUES (?, ?)");
        $addLike->execute([$commentId, $userId]);
        echo json_encode(['success' => true, 'liked' => true]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Utilisateur non connecté ou requête invalide']);
}
?>
