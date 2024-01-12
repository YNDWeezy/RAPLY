<?php
include 'dbconnect.php';
session_start();

$isUserLoggedIn = isset($_SESSION['user']);



// Génération d'un jeton CSRF qu'on stock dans la session
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$username = $_GET['username'] ?? null;

if (!$username) {
    die('Nom d\'utilisateur manquant.');
}

// On sécurise les requêtes avec des prepared statements pour éviter les injections SQL
$query = $pdo->prepare("SELECT * FROM users WHERE username = :username");
$query->execute(['username' => $username]);
$userProfile = $query->fetch(PDO::FETCH_ASSOC);


// Récupérer les détails de l'utilisateur, le nombre d'abonnés, d'abonnements, le total des "j'aime" reçus sur ses vidéos, et la bannière de profil
$query = $pdo->prepare("
    SELECT users.*, 
    (SELECT COUNT(*) FROM subscriptions WHERE channel_id = users.user_id) AS subscribers_count, 
    (SELECT COUNT(*) FROM subscriptions WHERE user_id = users.user_id) AS subscriptions_count,
    (SELECT COUNT(*) FROM likes 
        INNER JOIN videos ON videos.video_id = likes.video_id 
        WHERE videos.user_id = users.user_id) AS total_likes
    FROM users 
    WHERE username = ?
");
$query->execute([$username]);
$userProfile = $query->fetch(PDO::FETCH_ASSOC);

if (!$userProfile) {
    die('Utilisateur non trouvé.');
}

// Fonction pour formater le temps écoulé
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'an',
        'm' => 'mois',
        'w' => 'semaine',
        'd' => 'jour',
        'h' => 'heure',
        'i' => 'minute',
        's' => 'seconde',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? 'Il y a ' . implode(', ', $string) : 'à l\'instant';
}

// Récupérer les vidéos de l'utilisateur avec le nombre de vues directement de la table `videos`
$query = $pdo->prepare("
    SELECT videos.*, IFNULL(videos.view_count, 0) as views 
    FROM videos 
    WHERE videos.user_id = ? 
    ORDER BY videos.upload_date DESC
");
$query->execute([$userProfile['user_id']]);
$videos = $query->fetchAll(PDO::FETCH_ASSOC);

$isSubscribed = false;
if ($isUserLoggedIn) {
    $subscriberQuery = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = ? AND channel_id = ?");
    $subscriberQuery->execute([$_SESSION['user_id'], $userProfile['user_id']]);
    $isSubscribed = $subscriberQuery->fetch(PDO::FETCH_ASSOC) ? true : false;
}

$bannerPath = $userProfile['profile_banner'] ?? 'banner/defaut.png'; // Bannière par défaut si c'est = Null

// Fonctions de validation d'image
function is_image($file) {
    $image_types = ['image/jpeg', 'image/png', 'image/gif'];
    return in_array($file['type'], $image_types);
}

function save_image($file, $folder = 'uploads/') {
    $path = $folder . bin2hex(random_bytes(10)) . '_' . basename($file['name']);
    if (move_uploaded_file($file['tmp_name'], $path)) {
        return $path;
    }
    return null;
}

// Traitement de l'upload d'image de profil
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_image'], $_POST['csrf_token']) && $isUserLoggedIn && $_SESSION['csrf_token'] === $_POST['csrf_token']) {
    // Vérifiez le jeton CSRF
    if ($_SESSION['csrf_token'] !== $_POST['csrf_token']) {
        die('CSRF token mismatch.');
    }

    $image = $_FILES['profile_image'];
    
    if (is_image($image)) {
        // Avant de mettre à jour la nouvelle image de profil, on supprime l'ancienne sauf si c'est celle par défaut
        if ($userProfile['profile_picture'] && $userProfile['profile_picture'] != 'users/defaut.png') {
            unlink($userProfile['profile_picture']);
        }

        // Sauvegarder la nouvelle image
        $newImagePath = save_image($image, 'users/');
        if ($newImagePath) {
            $updateQuery = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
            $success = $updateQuery->execute([$newImagePath, $userProfile['user_id']]);
            if ($success) {
                // Redirection pour éviter de poster les données à nouveau
					$_SESSION['user']['profile_picture'] = $newImagePath;
					header('Location: @' . $username);

                exit;
            } else {
                $errorBanner = "Erreur lors de la mise à jour de l'image de profil.";
            }
        } else {
            $errorBanner = "Erreur lors de la sauvegarde de l'image.";
        }
    } else {
        $errorBanner = "Veuillez télécharger une image valide.";
    }
}


$minWidth = 2048; // Largeur minimale en pixels
$minHeight = 1152; // Hauteur minimale en pixels

// Fonction de validation et de sauvegarde de la bannière
function save_banner($file) {
    $maxFileSize = 6 * 1024 * 1024; // 6 Mo
    $validDimensions = ['width' => 2048, 'height' => 1152];
    $path = null;

    if ($file['size'] <= $maxFileSize) {
        list($width, $height) = getimagesize($file['tmp_name']);
        if ($width >= $validDimensions['width'] && $height >= $validDimensions['height']) {
            $path = save_image($file, 'banner/');
        }
    }

    return $path;
}




// Traitement de l'upload de la bannière de profil
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['banner_image'], $_POST['csrf_token']) && $isUserLoggedIn && $_SESSION['csrf_token'] === $_POST['csrf_token']) {
    
    $banner = $_FILES['banner_image'];

    if (is_image($banner)) {
        // Sauvegardez d'abord la nouvelle image sans supprimer l'ancienne
        $newBannerPath = save_banner($banner);

        if ($newBannerPath) {
            // Tentative de mise à jour de la bannière dans la base de données
            $updateBannerQuery = $pdo->prepare("UPDATE users SET profile_banner = ? WHERE user_id = ?");
            $successBannerUpdate = $updateBannerQuery->execute([$newBannerPath, $userProfile['user_id']]);
            
            if ($successBannerUpdate) {
                // La mise à jour a réussi, nous pouvons maintenant supprimer l'ancienne image
                $oldBannerPath = $userProfile['profile_banner'];
                if ($oldBannerPath && $oldBannerPath != 'banner/defaut.png' && file_exists($oldBannerPath)) {
                    unlink($oldBannerPath);
                }
                
                // Mettre à jour les informations de l'utilisateur pour refléter la nouvelle bannière
                $userProfile['profile_banner'] = $newBannerPath;

                // Redirection après la mise à jour réussie
                header('Location: @' . $username);
                exit;
            } else {
                // La mise à jour a échoué, on ne supprime pas l'ancienne image, et on affiche une erreur
                $errorBanner = "Une erreur est survenue.";
                if (file_exists($newBannerPath)) {
                    unlink($newBannerPath);
                }
            }
        } else {
            $errorBanner = "Assurez-vous que la bannière respecte les dimensions et la taille minimum autorisées. (2048x1152 - 6MO max)";
        }
    } else {
        $errorBanner = "Veuillez télécharger une image valide pour la bannière.";
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $errorBanner = "Une erreur inattendue est survenue lors de la soumission du formulaire.";
}



?>



<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil de <?= htmlspecialchars($username) ?> - RAPLY</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="style2.css">
	<script src="//unpkg.com/alpinejs" defer></script>

</head>
<body class="bg-gray-900 text-white">

<div class="container mx-auto mt-20">
    <!-- Début du profil -->
    <div class="w-full max-w-6xl mx-auto">
        <!-- Bannière avec photo de profil coupant dans la bannière -->
			<div class="relative">
	<!-- Bannière de profil -->
	<div class="profile-banner h-48 rounded-t-lg bg-cover bg-center relative" style="background-image: url('<?= htmlspecialchars($bannerPath) ?>')">
		<?php if ($isUserLoggedIn && $_SESSION['user_id'] == $userProfile['user_id']): ?>
			<!-- Formulaire pour changer la bannière -->
			<form action="@<?= htmlspecialchars($username) ?>" method="post" enctype="multipart/form-data" class="hidden" id="banner-form">
				<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
				<input type="file" name="banner_image" id="banner-image-upload" onchange="document.getElementById('banner-form').submit();">
			</form>
			<label for="banner-image-upload" class="cursor-pointer absolute bottom-0 left-0 right-0 top-0">
				<div class="banner-hover-text text-white text-center font-bold py-20 bg-black bg-opacity-50 hover:bg-opacity-75 transition duration-300 ease-in-out">
					Cliquez pour modifier la bannière
				</div>
			</label>
		<?php endif; ?>
	</div>

				
				<!-- Image de profil -->
    <?php if ($isUserLoggedIn && $_SESSION['user_id'] == $userProfile['user_id']): ?>
        <form action="profile.php?username=<?= htmlspecialchars($username) ?>" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
            <label for="profile-image-upload" class="cursor-pointer">
                <!-- Utilisez un div pour créer l'effet de survol -->
                <div class="rounded-full absolute -bottom-16 left-10 border-4 border-gray-900 profile-image overflow-hidden">
                    <img src="<?= htmlspecialchars($userProfile['profile_picture'] ?? 'users/defaut.png') ?>" alt="Profil" class="rounded-full">
                    <!-- Effet de survol avec icône Font Awesome -->
                    <div class="absolute inset-0 bg-black bg-opacity-50 flex justify-center items-center opacity-0 hover:opacity-100 transition-opacity duration-300">
                        <i class="fas fa-camera fa-3x text-white"></i> <!-- Icône Font Awesome -->
                    </div>
                </div>
            </label>
            <input type="file" name="profile_image" id="profile-image-upload" class="hidden" onchange="this.form.submit()">
        </form>
    <?php else: ?>
        <img src="<?= htmlspecialchars($userProfile['profile_picture'] ?? 'users/defaut.png') ?>" alt="Profil" class="rounded-full absolute -bottom-16 left-10 border-4 border-gray-900 profile-image">
    <?php endif; ?>
		
			</div>
		<!-- Afficher le nombre d'abonnés et d'abonnements-->
		<div class="text-center mt-4 space-x-4">
			<h2 class="text-white text-2xl font-semibold">@<?= htmlspecialchars($userProfile['username']) ?></h2>
			
			<div class="flex justify-center items-center space-x-2 mt-2">
				<span class="text-white font-bold text-lg"><?= $userProfile['subscribers_count'] ?></span>
				<span class="text-gray-400">Abonnés</span>
				<span>|</span>
				<span class="text-white font-bold text-lg"><?= $userProfile['subscriptions_count'] ?></span>
				<span class="text-gray-400">Abonnements</span>
				<span>|</span>
				<span class="text-white font-bold text-lg"><?= $userProfile['total_likes'] ?></span>
				<span class="text-gray-400">J'aime</span>
			</div>

			<!-- Le bouton s'abonner / se désabonner -->
			<?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $userProfile['user_id']): ?>
				<div class="mt-4">
					<form action="subscription_handler.php" method="post">
						<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
						<input type="hidden" name="action" value="<?= $isSubscribed ? 'unsubscribe' : 'subscribe' ?>">
						<input type="hidden" name="channel_id" value="<?= $userProfile['user_id'] ?>">
						<button type="submit" class="transition duration-300 ease-in-out text-white font-bold py-2 px-4 rounded-full border border-green-500 hover:bg-green-500 <?= $isSubscribed ? 'bg-green-500' : 'bg-transparent' ?> hover:text-white">
							<?= $isSubscribed ? 'Abonné' : 'S\'abonner' ?>
						</button>
					</form>
				</div>
			<?php endif; ?>
		</div>
</div>


        </div>
<!-- Navigation -->

<div x-data="{ activeTab: 'accueil' }">
    <!-- Tab navigation -->
    <div class="border-b-2 border-gray-700">
        <div class="flex justify-center space-x-4 mt-4">
			<button @click="activeTab = 'accueil'" :class="{ 'text-green-500 border-b-2 border-green-500 tab-button': activeTab === 'accueil', 'text-white hover:text-green-500': activeTab !== 'accueil' }" class="pb-3 focus:outline-none">Accueil</button>
			<button @click="activeTab = 'videos'" :class="{ 'text-green-500 border-b-2 border-green-500 tab-button': activeTab === 'videos', 'text-white hover:text-green-500': activeTab !== 'videos' }" class="pb-3 focus:outline-none">Vidéos</button>
			<button @click="activeTab = 'favoris'" :class="{ 'text-green-500 border-b-2 border-green-500 tab-button': activeTab === 'favoris', 'text-white hover:text-green-500': activeTab !== 'favoris' }" class="pb-3 focus:outline-none">Favoris</button>

        </div>
    </div>

    <div class="mt-4 px-4 md:px-16 lg:px-32">
        <div x-show="activeTab === 'accueil'">
            <!-- Contenu de l'accueil -->
        </div>

        <div x-show="activeTab === 'videos'" x-cloak>
            <!-- Section Vidéos avec animations -->
            <div class="w-full max-w-6xl mx-auto">
                <h3 class="text-xl font-bold mb-4 text-green-500 text-center">Vidéos</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($videos as $video): ?>
                        <!-- Video card -->
                        <div class="bg-gray-800 rounded-lg overflow-hidden shadow-lg transition duration-300 ease-in-out mb-4 group">
                            <a href="watch/<?= $video['video_id'] ?>" class="block relative">
                                <img src="<?= htmlspecialchars($video['thumbnail']) ?>" alt="<?= htmlspecialchars($video['title']) ?>" class="w-full h-48 object-cover group-hover:scale-105 transition-transform duration-300 ease-in-out">
                                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-50 transition-opacity duration-300 ease-in-out flex items-center justify-center opacity-0 group-hover:opacity-100">
                                </div>
                            </a>
                            <div class="p-4">
                                <a href="watch/<?= $video['video_id'] ?>" class="block text-lg font-semibold text-white transition-colors duration-300 truncate hover:text-green-400">
                                    <?= htmlspecialchars($video['title']) ?>
                                </a>
                                <p class="text-gray-400 text-sm mt-2 flex items-center group-hover:opacity-100 transition-opacity duration-500 ease-in-out">
                                    <i class="fas fa-eye mr-2 group-hover:text-green-400 transition-colors duration-300"></i> <?= $video['views'] ?> vues
                                    <span class="mx-2">·</span>
                                    <?= time_elapsed_string($video['upload_date']) ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div x-show="activeTab === 'favoris'" x-cloak>
            <!-- Contenu de la section favoris -->
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.2/dist/cdn.min.js"></script>





<!-- Container de notification qui sera initialement caché -->
<div id="notification-container" class="fixed bottom-4 right-4 transition-opacity duration-500 ease-out opacity-0 z-50">
    <div id="notification" class="bg-gray-800 border border-green-500 text-white-400 px-4 py-3 rounded-lg shadow-lg relative" role="alert">
        <strong class="font-bold">Erreur :</strong>
        <span class="block sm:inline" id="notification-message"></span>
    </div>
</div>

<script>
// Fonction pour afficher la notification avec le message d'erreur
function showNotification(message) {
    const notificationContainer = document.getElementById('notification-container');
    const notificationMessage = document.getElementById('notification-message');

    notificationMessage.textContent = message;

    // Affiche la notification
    notificationContainer.style.opacity = '1';

    // Cache la notification après 5 secondes
    setTimeout(() => {
        notificationContainer.style.opacity = '0';
    }, 5000);
}

// Fonction pour fermer la notification manuellement
function closeNotification() {
    const notificationContainer = document.getElementById('notification-container');
    notificationContainer.style.opacity = '0';
}

// À appeler avec le message d'erreur quand il y en a un
<?php if (isset($errorBanner) && $errorBanner != ''): ?>
showNotification("<?= $errorBanner; ?>");
<?php endif; ?>
</script>


</body>
</html>