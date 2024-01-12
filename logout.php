<?php
session_start();
session_destroy(); // Détruit toutes les données associées à la session en cours
header("Location: index.php"); // Redirige vers la page d'accueil après la déconnexion
exit;
?>
