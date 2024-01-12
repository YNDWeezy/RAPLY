<?php
include 'dbconnect.php';
session_start();

$response = ['liked' => false, 'error' => ''];

if (!isset($_SESSION['user'])) {
    $response['error'] = 'Utilisateur non connecté.';
    echo json_encode($response);
    exit;
}

if (!isset($_POST['videoId'])) {
    $response['error'] = 'ID vidéo manquant.';
    echo json_encode($response);
    exit;
}

$userId = $_SESSION['user']['user_id'];
$videoId = intval($_POST['videoId']);

// Vérifiez si l'utilisateur a déjà liké la vidéo
$checkLike = $pdo->prepare("SELECT * FROM likes WHERE user_id = ? AND video_id = ?");
$checkLike->execute([$userId, $videoId]);
$like = $checkLike->fetch();

if ($like) {
    // L'utilisateur a déjà liké la vidéo, retirez le like
    $deleteLike = $pdo->prepare("DELETE FROM likes WHERE like_id = ?");
    $deleteLike->execute([$like['like_id']]);
    $response['liked'] = false;
} else {
    // L'utilisateur n'a pas encore liké la vidéo, ajoutez un like
    $addLike = $pdo->prepare("INSERT INTO likes (user_id, video_id) VALUES (?, ?)");
    $addLike->execute([$userId, $videoId]);
    $response['liked'] = true;
}

echo json_encode($response);
?>
