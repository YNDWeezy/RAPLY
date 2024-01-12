<?php
include 'dbconnect.php';
session_start();

// Vérification du jeton CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die('CSRF token mismatch.');
}

// Récupérer l'action et l'ID de la chaîne à partir du formulaire
$action = $_POST['action'] ?? '';
$channel_id = $_POST['channel_id'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

// Vérifiez si les paramètres nécessaires sont présents
if (!$channel_id || !$user_id) {
    die('Les paramètres nécessaires sont manquant.');
}

try {
    $pdo->beginTransaction();

    if ($action === 'subscribe') {
        // Logique pour s'abonner
        $subscriptionQuery = $pdo->prepare("INSERT INTO subscriptions (user_id, channel_id) VALUES (?, ?)");
        $subscriptionQuery->execute([$user_id, $channel_id]);

        // Mettre à jour le compte des abonnements pour l'utilisateur
        $updateSubCountQuery = $pdo->prepare("UPDATE users SET subscriptions_count = subscriptions_count + 1 WHERE user_id = ?");
        $updateSubCountQuery->execute([$user_id]);

        // Mettre à jour le compte des abonnés pour la chaîne
        $updateSubscribersCountQuery = $pdo->prepare("UPDATE users SET subscribers_count = subscribers_count + 1 WHERE user_id = ?");
        $updateSubscribersCountQuery->execute([$channel_id]);
    } elseif ($action === 'unsubscribe') {
        // Logique pour se désabonner
        $unsubscriptionQuery = $pdo->prepare("DELETE FROM subscriptions WHERE user_id = ? AND channel_id = ?");
        $unsubscriptionQuery->execute([$user_id, $channel_id]);

        // Mettre à jour le compte des abonnements pour l'utilisateur
        $updateSubCountQuery = $pdo->prepare("UPDATE users SET subscriptions_count = subscriptions_count - 1 WHERE user_id = ?");
        $updateSubCountQuery->execute([$user_id]);

        // Mettre à jour le compte des abonnés pour la chaîne
        $updateSubscribersCountQuery = $pdo->prepare("UPDATE users SET subscribers_count = subscribers_count - 1 WHERE user_id = ?");
        $updateSubscribersCountQuery->execute([$channel_id]);
    } else {
        $pdo->rollBack();
        die('Invalid action.');
    }

    // Obtenez le nom d'utilisateur de la chaîne pour la redirection
    $channelUsernameQuery = $pdo->prepare("SELECT username FROM users WHERE user_id = ?");
    $channelUsernameQuery->execute([$channel_id]);
    $channelUsernameResult = $channelUsernameQuery->fetch(PDO::FETCH_ASSOC);
    $channelUsername = $channelUsernameResult['username'] ?? null;

    if (!$channelUsername) {
        throw new Exception("Channel username not found.");
    }

    $pdo->commit();

    // Redirection vers la page du profil de la chaîne
    header('Location: @' . urlencode($channelUsername));
    exit();
    
} catch (PDOException $e) {
    $pdo->rollBack();
    die("Database error occurred: " . $e->getMessage());
} catch (Exception $e) {
    $pdo->rollBack();
    die($e->getMessage());
}
?>
