<?php
session_start();
include 'dbconnect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['user'])) {
    $userId = $_SESSION['user']['user_id'];
    $replyId = $_POST['replyId']; // Obtenez l'ID de la réponse

    // Vérifier si l'utilisateur a déjà aimé cette réponse
    $checkLike = $pdo->prepare("SELECT * FROM reply_likes WHERE user_id = ? AND reply_id = ?");
    $checkLike->execute([$userId, $replyId]);
    $likeStatus = false;

    if ($checkLike->fetch()) {
        // Si déjà aimé, supprimer le like
        $deleteLike = $pdo->prepare("DELETE FROM reply_likes WHERE user_id = ? AND reply_id = ?");
        $deleteLike->execute([$userId, $replyId]);
    } else {
        // Sinon, ajouter le like
        $addLike = $pdo->prepare("INSERT INTO reply_likes (reply_id, user_id) VALUES (?, ?)");
        $addLike->execute([$replyId, $userId]);
        $likeStatus = true;
    }

    // Obtenir le nombre actuel de likes pour cette réponse
    $likeCountQuery = $pdo->prepare("SELECT COUNT(*) as count FROM reply_likes WHERE reply_id = ?");
    $likeCountQuery->execute([$replyId]);
    $likeCount = $likeCountQuery->fetch()['count'];

    // Retourner le résultat avec le nombre de likes
    echo json_encode(['success' => true, 'liked' => $likeStatus, 'likeCount' => $likeCount]);
} else {
    // En cas d'erreur (utilisateur non connecté ou requête invalide)
    echo json_encode(['success' => false, 'error' => 'Utilisateur non connecté ou requête invalide']);
}
?>
