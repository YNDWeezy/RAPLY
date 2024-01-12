<?php
include 'dbconnect.php';
session_start();

// Vérifie si l'utilisateur est connecté
$isUserLoggedIn = isset($_SESSION['user']);



$query = $pdo->prepare("
    SELECT videos.*, users.username
    FROM videos 
    LEFT JOIN users ON videos.user_id = users.user_id 
    ORDER BY videos.view_count DESC, upload_date DESC
    LIMIT 3
");


$query->execute();
$videos = $query->fetchAll(PDO::FETCH_ASSOC);

$latest_video = $videos[0] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['email'], $_POST['password'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validation des entrées
    if (empty($username) || empty($email) || empty($password)) {
        $error = "Tous les champs sont requis.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Adresse e-mail invalide.";
    } elseif (strlen($password) < 8) {
        $error = "Le mot de passe doit comporter au moins 8 caractères.";
    } elseif (!preg_match("/[A-Z]/", $password) || !preg_match("/[a-z]/", $password) || !preg_match("/[0-9]/", $password)) {
        $error = "Le mot de passe doit contenir au moins une lettre majuscule, une lettre minuscule et un chiffre.";
    } else {
        // Vérifier si le nom d'utilisateur ou l'e-mail existent déjà
        $checkQuery = $pdo->prepare("SELECT username, email FROM users WHERE username = ? OR email = ?");
        $checkQuery->execute([$username, $email]);
        $existingUser = $checkQuery->fetch(PDO::FETCH_ASSOC);

        if (!$existingUser) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertQuery = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
            $insertQuery->execute([$username, $email, $hashedPassword]);
        } else {
            if ($existingUser['username'] === $username) {
                $error = "Nom d'utilisateur déjà utilisé.";
            } elseif ($existingUser['email'] === $email) {
                $error = "Adresse e-mail déjà utilisée.";
            }
        }
    }
}




// Fonction pour se connecter
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['loginEmail'], $_POST['loginPassword'])) {
    $login = $_POST['loginEmail'];
    $password = $_POST['loginPassword'];

    // Vérifier si l'utilisateur utilise l'email ou le nom d'utilisateur pour se connecter
    $loginType = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

    $checkQuery = $pdo->prepare("SELECT * FROM users WHERE $loginType = ?");
    $checkQuery->execute([$login]);
    $user = $checkQuery->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
		$_SESSION['user_id'] = $user['user_id'];
        $_SESSION['user'] = $user;
        $isUserLoggedIn = true;
    } else {
        $error = "Adresse e-mail, nom d'utilisateur ou mot de passe incorrect.";
    }
}



function timeAgo($date) {
    $timestamp = strtotime($date);
    $strTime = array("seconde", "minute", "heure", "jour", "mois", "année");
    $length = array("60","60","24","30","12","10");

    $currentTime = time();
    if($currentTime >= $timestamp) {
        $diff = time()- $timestamp;
        for($i = 0; $diff >= $length[$i] && $i < count($length)-1; $i++) {
            $diff = $diff / $length[$i];
        }
        
        $diff = round($diff);
        return "Il y a ".$diff." ".$strTime[$i].($diff > 1 ? "s" : "");
    }
}

$query_recent = $pdo->prepare("
    SELECT videos.*, users.username
    FROM videos 
    LEFT JOIN users ON videos.user_id = users.user_id 
    ORDER BY videos.upload_date DESC
    LIMIT 10
");
$query_recent->execute();
$recent_videos = $query_recent->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RAPLY</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
	<link rel="stylesheet" href="style2.css">
	<link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css">


</head>

<body class="bg-gray-900 text-white">
<nav class="bg-gradient-to-r from-gray-800 via-blue-900 to-black p-2 flex justify-between items-center sticky top-0">


    <!-- Section gauche : logo et bouton de menu -->
    <div class="flex items-center">
        <button id="openNav" class="mr-4">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-menu">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>
        <img src="logo.png" alt="Logo" width="80" class="mr-4">
    </div>

    <!-- Section centrale : barre de recherche -->
    <div class="flex items-center bg-gray-700 rounded-full p-2 w-1/2"> <!-- Largeur définie à 50% du conteneur parent -->
        <input type="text" placeholder="Rechercher..." class="bg-transparent w-full px-4 py-2 rounded-full outline-none"> <!-- Largeur définie à 100% de son conteneur parent -->
        <button class="ml-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-search">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
        </button>
    </div>

	<!-- Section droite : boutons de connexion et d'inscription OU photo de profil -->
<div class="flex items-center relative">
	<?php if ($isUserLoggedIn): ?>			
		<button onclick="toggleProfileMenu()" class="relative h-14 w-14 overflow-hidden rounded-full border-2 border-green-500"> 
			<?php 
			$profilePicture = isset($_SESSION['user']['profile_picture']) 
							  ? $_SESSION['user']['profile_picture'] 
							  : 'users/defaut.png';
			?>
			<img src="<?= $profilePicture . '?t=' . time() ?>" alt="Profil" class="absolute top-0 left-0 w-full h-full object-cover">
		</button>
		
		<!-- Menu déroulant -->
		<div id="profileMenu" class="absolute right-0 top-full mt-2 w-64 bg-gray-900 border border-gray-700 rounded shadow-lg z-10 hidden">
			<a href="/@<?= $_SESSION['user']['username'] ?>" class="flex items-center px-4 py-2 hover:bg-gray-800">
			<!-- Icone SVG pour "Voir mon profil" -->
			<svg class="h-5 w-5 mr-2" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
				<path d="M12 14C15.3137 14 18 11.3137 18 8C18 4.68629 15.3137 2 12 2C8.68629 2 6 4.68629 6 8C6 11.3137 8.68629 14 12 14ZM12 16C7.58172 16 4 18.5817 4 22V24H20V22C20 18.5817 16.4183 16 12 16Z" fill="currentColor"></path>
			</svg>
				Voir mon profil
			</a>
			<a href="logout.php" class="flex items-center px-4 py-2 hover:bg-gray-800">
			<!-- Icone SVG pour "Se déconnecter" -->
			<svg class="h-5 w-5 mr-2" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
				<path d="M16 11H4V13H16V16L20 12L16 8V11ZM14 19H4V5H14V7H6V17H14V19Z" fill="currentColor"></path>
			</svg>
				Se déconnecter
			</a>
			<div class="border-t border-gray-700"></div>
			<a href="upload.php" class="flex items-center px-4 py-2 hover:bg-gray-800">
			<!-- Icone SVG pour "Mettre en ligne une vidéo" -->
			<svg class="h-5 w-5 mr-2" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
				<path d="M12 2L8 6h3v9h2V6h3L12 2z"></path>
				<path d="M2 20v2h20v-2H2z"></path>
			</svg>

				Mettre en ligne une vidéo
			</a>
			<a href="settings.php" class="flex items-center px-4 py-2 hover:bg-gray-800">
			<!-- Icone SVG pour "Paramètre" -->
			<svg class="h-5 w-5 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
				<!-- Cercle extérieur -->
				<circle cx="12" cy="12" r="10" fill="none" stroke-width="2"></circle>
				
				<!-- Trois points à l'intérieur, alignés horizontalement -->
				<circle cx="8" cy="12" r="1" fill="currentColor"></circle>
				<circle cx="12" cy="12" r="1" fill="currentColor"></circle>
				<circle cx="16" cy="12" r="1" fill="currentColor"></circle>
			</svg>
				Paramètre
			</a>
			<a href="help.php" class="flex items-center px-4 py-2 hover:bg-gray-800">
				<!-- Icone SVG pour "Aide" -->
			<svg class="h-5 w-5 mr-2" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
				<path d="M12 3c-2.21 0-4 1.79-4 4h2c0-1.1.9-2 2-2s2 .9 2 2c0 2-3 1.75-3 5h2c0-2.25 3-2.5 3-5 0-2.21-1.79-4-4-4zm-1 11v2h2v-2h-2z"></path>
			</svg>

				Aide
			</a>
		</div>
	<?php else: ?>
		<button class="mr-4" onclick="openSigninModal()">Se connecter</button>
		<button class="bg-green-500 text-white px-6 py-2 rounded-full" onclick="openSignupModal()">S'inscrire</button>
	<?php endif; ?>
</div>

<script>

let isMenuOpen = false;

function toggleProfileMenu() {
    const menu = document.getElementById('profileMenu');
    menu.classList.toggle('hidden');
    isMenuOpen = !isMenuOpen; // basculer l'état
}

document.addEventListener('click', function(event) {
    const menu = document.getElementById('profileMenu');
    const profilePic = document.querySelector('.h-14.w-14'); // Sélectionner la photo de profil

    // Vérifie si le menu est ouvert, et si le clic n'était ni dans le menu ni sur la photo de profil
    if (isMenuOpen && !menu.contains(event.target) && !profilePic.contains(event.target)) {
        menu.classList.add('hidden');
        isMenuOpen = false;
    }
});

</script>
</nav>

<div id="sideNavbar" class="fixed top-0 left-0 h-full w-64 bg-gray-800 z-20 transform -translate-x-full transition-transform duration-300">
    <button id="closeNav" class="absolute top-2 right-2 text-gray-400">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-x">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
    </button>
    <!-- Ajoutez les éléments de votre menu ici -->
</div>

<div id="modal" class="fixed z-10 inset-0 overflow-y-auto" style="display: none;">
    <div class="fixed inset-0 bg-gray-900 opacity-50" onclick="hideModal()"></div>
    <div class="flex items-center justify-center min-h-screen">
        <div class="bg-gray-800 p-4 max-w-xl w-full space-y-8 rounded-lg z-20">
            <div class="flex items-center justify-center">
                <div class="text-center flex-grow">
                    <span id="signupTab" class="cursor-pointer px-4 border-b-2 border-green-500" onclick="showTab('signup')">S'inscrire</span>
                    <span id="signinTab" class="cursor-pointer px-4" onclick="showTab('signin')">Se connecter</span>
                </div>
            </div>
            <div id="signup">
                <div>
                    <form action="" method="POST" class="space-y-4">
                        <div>
                            <label class="block mb-2" for="username">Nom d'utilisateur</label>
                            <input class="w-full p-2 text-black rounded-md" type="text" name="username" required>
                        </div>
                        <div>
                            <label class="block mb-2" for="email">Email</label>
                            <input class="w-full p-2 text-black rounded-md" type="email" name="email" required>
                        </div>
                        <div>
                            <label class="block mb-2" for="password">Mot de passe</label>
                            <input class="w-full p-2 text-black rounded-md" type="password" name="password" required>
                        </div>
						<div>
							<label class="block mb-2" for="passwordConfirm">Confirmez le mot de passe</label>
							<input class="w-full p-2 text-black rounded-md" type="password" name="passwordConfirm" required>
						</div>
                        <button type="submit" class="bg-green-500 text-white px-6 py-2 rounded-full">S'inscrire</button>
                    </form>
                </div>
            </div>

			<div id="signin" style="display: none;">
				<div>
					<form action="" method="POST" class="space-y-4">
						<div>
							<label class="block mb-2" for="loginEmail">Email ou Nom d'utilisateur</label>
							<input class="w-full p-2 text-black rounded-md" type="text" name="loginEmail" required>
						</div>
						<div>
							<label class="block mb-2" for="loginPassword">Mot de passe</label>
							<input class="w-full p-2 text-black rounded-md" type="password" name="loginPassword" required>
						</div>
						<button type="submit" class="bg-green-500 text-white px-4 py-2 rounded-full">Se connecter</button>
					</form>
				</div>
			</div>
        </div>
    </div>
</div>

<script>
function showTab(tab) {
    document.getElementById('modal').style.display = 'block'; // On check que le modal est bien affiché

    if (tab === 'signup') {
        document.getElementById('signup').style.display = 'block';
        document.getElementById('signin').style.display = 'none';
        document.getElementById('signupTab').classList.add("border-green-500");
        document.getElementById('signinTab').classList.remove("border-green-500");
    } else {
        document.getElementById('signup').style.display = 'none';
        document.getElementById('signin').style.display = 'block';
        document.getElementById('signupTab').classList.remove("border-green-500");
        document.getElementById('signinTab').classList.add("border-green-500");
    }
}

function hideModal() {
    document.getElementById('modal').style.display = 'none';
    document.getElementById('signupTab').classList.remove("border-green-500");
    document.getElementById('signinTab').classList.remove("border-green-500");
}

// Fonctions pour ouvrir directement le modal sur l'onglet souhaité
function openSignupModal() {
    showTab('signup');
}

function openSigninModal() {
    showTab('signin');
}

</script>

<!-- Section Tendances -->
<div class="mt-16 mx-auto max-w-6xl flex justify-start items-center mb-8">
    <h2 class="text-2xl font-extrabold text-green-500 bg-gray-900 p-2 rounded-md shadow-lg">Tendances 🔥</h2>
</div>


<!--Vidéo les plus vues-->
<div class="mt-12 mx-auto max-w-6xl flex justify-center items-center relative">
    <?php foreach ($videos as $index => $video): ?>
        <div class="slider-video <?= $index == 0 ? 'center-video z-10 transform scale-105' : ($index == 2 ? 'right-video opacity-70 absolute -right-24 transform scale-100' : 'left-video opacity-70 absolute -left-24 transform scale-100') ?> video-wrapper transition-transform duration-300">
            <a href="watch/<?= $video['video_id'] ?>" class="block hover:scale-110 transition-transform duration-300">
                <img src="<?= $video['thumbnail'] ?>" alt="<?= $video['title'] ?>" class="thumbnail w-full h-64 rounded-lg shadow-md border-4 border-transparent">
            </a>
            <div class="mt-2 text-center">
                <div class="font-bold"><a href="watch/<?= $video['video_id'] ?>" class="hover:text-green-500 transition-colors"><?= $index + 1 ?>. <?= $video['title'] ?></a></div>
                <div class="text-sm text-gray-400"><a href="/@<?= $video['username'] ?>"><?= $video['username'] ?></a></div>
				
				
				<div class="flex justify-center items-center mt-1">
					<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500 mr-2" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
						<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17a5 5 0 01-5-5 5 5 0 015-5 5 5 0 015 5 5 5 0 01-5 5z"></path>
					</svg>
					<?= $video['view_count'] . ' ' . ($video['view_count'] <= 1 ? 'vue' : 'vues') ?> · <?= timeAgo($video['upload_date']) ?>
				</div>
            </div>
        </div>
    <?php endforeach; ?>
</div>




<div class="mt-8">
    <h2 class="text-xl font-bold ml-8">Ajoutés récemment</h2>
    <div class="relative mt-6 swiper-container">
        <div class="swiper-wrapper">
            <?php foreach ($recent_videos as $video): ?>
                <div class="swiper-slide flex flex-col items-center justify-center p-0 m-0 mb-4"> <!-- Ajout d'une marge en bas (mb-4) -->
                    <a href="watch/<?= $video['video_id'] ?>" class="flex items-center justify-center w-full h-full">
                        <img src="<?= $video['thumbnail'] ?>" alt="<?= $video['title'] ?>" class="w-full h-full rounded-lg shadow-md border-4 border-transparent hover:border-green-500 object-cover">
                    </a>
                    <div class="mt-2 flex justify-between w-full px-4">
                        <div class="text-left">
                            <div class="font-bold truncate w-64"><a href="watch/=<?= $video['video_id'] ?>" class="hover:text-green-500 transition-colors"><?= $video['title'] ?></a></div>
                            <div class="text-sm text-gray-400"><?= $video['username'] ?></div>
                            <div class="text-xs text-gray-400"><?= timeAgo($video['upload_date']) ?></div>
                        </div>
                        <div class="flex items-center text-right text-xs text-gray-400"> <!-- Encapsulation dans un conteneur flex -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500 mr-2" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17a5 5 0 01-5-5 5 5 0 015-5 5 5 0 015 5 5 5 0 01-5 5z"></path>
                            </svg>
                            <?= $video['view_count'] . ' ' . ($video['view_count'] <= 1 ? 'vue' : 'vues') ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <!-- Add Arrows -->
        <div class="swiper-button-next text-green-500"></div>
        <div class="swiper-button-prev text-green-500"></div>
    </div>
</div>



<script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
<script>
    const swiper = new Swiper('.swiper-container', {
        slidesPerView: 4,
        spaceBetween: 10,
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
        },
    });
</script>




<script>

document.getElementById('openNav').onclick = function() {
    document.getElementById('sideNavbar').style.transform = 'translateX(0)';
}
document.getElementById('closeNav').onclick = function() {
    document.getElementById('sideNavbar').style.transform = 'translateX(-100%)';
}




</script>
</body>
</html>