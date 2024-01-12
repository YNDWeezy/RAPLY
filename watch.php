<?php
include 'dbconnect.php';
session_start();

// Vérifie si l'utilisateur est connecté
$isUserLoggedIn = isset($_SESSION['user']);

if(!isset($_GET['id']) || empty($_GET['id'])) {
    die("No video ID provided!");
}

$videoId = intval($_GET['id']); 


$query = $pdo->prepare("SELECT video_path, title FROM videos WHERE video_id = ?");
$query->execute([$videoId]);
$video = $query->fetch();

if(!$video) {
    die("Vidéo inconnu!");
}

$videoSrc = $video['video_path'];
$videoTitle = $video['title']; 


    // Longueur maximale du titre
    $maxLength = 78; // Ajustez selon vos besoins

    // Tronquer le titre si nécessaire
    $videoTitle = (strlen($videoTitle) > $maxLength) ? substr($videoTitle, 0, $maxLength - 3) . "..." : $videoTitle;


$isLiked = false;
if ($isUserLoggedIn) {
    $checkLike = $pdo->prepare("SELECT * FROM likes WHERE user_id = ? AND video_id = ?");
    $checkLike->execute([$_SESSION['user']['user_id'], $videoId]);
    $isLiked = $checkLike->fetch() ? true : false;
}


// Pour les mentions d'utilisateurs 
function convertMentionsToLinks($content) {
    $mentionPattern = '/@(\w+)/'; // Regex pour détecter les mentions
    $replaceCallback = function($matches) {
        $username = $matches[1];
        $url = "@" . urlencode($username); 
        return "<a href='{$url}'>@{$username}</a>";
    };
    return preg_replace_callback($mentionPattern, $replaceCallback, $content);
}



$sortOrder = isset($_GET['sort']) ? $_GET['sort'] : 'most_liked'; // Valeur par défaut

switch ($sortOrder) {
    case 'most_recent':
        $orderBy = 'date DESC';
        break;
    case 'oldest':
        $orderBy = 'date ASC';
        break;
    case 'most_liked':
    default:
        $orderBy = 'likes_count DESC';
        break;
}

$commentQuery = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.comment_id) as likes_count FROM comments c WHERE video_id = ? ORDER BY $orderBy");
$commentQuery->execute([$videoId]);



?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <base href="http://localhost/">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">

    <title><?= htmlspecialchars($videoTitle) ?> - RAPLY</title>
	
	<script src="https://cdn.jsdelivr.net/npm/emojione@4.5.0/lib/js/emojione.min.js"></script>
	<link href="https://cdn.jsdelivr.net/npm/emojione@4.5.0/extras/css/emojione.min.css" rel="stylesheet">
	
</head>
<body>

    <div id="appMountPoint">
        <div class="watch-video">
            <div class="watch-video--player-view">
                <div class="video-view">
                    <video id="videoElement" width="100%" height="100%">
                        <source src="<?= $videoSrc ?>" type="video/mp4">
                    </video>
                </div>
					

					<div class="controls back-icon">
						<div class="controls back-icon" onclick="goBack()">
						<svg width="80" height="64" viewBox="0 0 80 64" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M48 56L16 32l32-24" stroke="currentColor" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"></path>
							<line x1="18" y1="32" x2="74" y2="32" stroke="currentColor" stroke-width="6" stroke-linecap="round"></line>
						</svg>
						</div>
					</div>
					
                    <div class="video-controls">
					

                        <div class="toprow">
                            <div class="progress">
                                <span class="progress-bar"><span class="progress-loaded" style="width: 0%;"></span><span class="progress-completed" style="width: 0%;"></span></span>
								                            <div class="timecode"><span id="timecode">0:00</span></div>
                            </div>

                        </div>
                        <div class="lowrow">
                            <div class="left">
                                <div class="controls" id="id_playpause">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="svg-icon-nfplayerPlay">
                                        <path d="M4 2.69127C4 1.93067 4.81547 1.44851 5.48192 1.81506L22.4069 11.1238C23.0977 11.5037 23.0977 12.4963 22.4069 12.8762L5.48192 22.1849C4.81546 22.5515 4 22.0693 4 21.3087V2.69127Z" fill="currentColor"></path>
                                    </svg>
									
																	<!-- Icône de pause -->
								<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="svg-icon-nfplayerPause" style="display: none;">
									<path d="M3 3H10V21H3V3ZM14 3H21V21H14V3Z" fill="currentColor"></path>
								</svg>

                                </div>
                                <div class="controls" id="id_back">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="Hawkins-Icon Hawkins-Icon-Standard">
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M11.0198 2.04817C13.3222 1.8214 15.6321 2.39998 17.5557 3.68532C19.4794 4.97067 20.8978 6.88324 21.5694 9.09718C22.241 11.3111 22.1242 13.6894 21.2388 15.8269C20.3534 17.9643 18.7543 19.7286 16.714 20.8192C14.6736 21.9098 12.3182 22.2592 10.0491 21.8079C7.77999 21.3565 5.73759 20.1323 4.26989 18.3439C2.80219 16.5555 2 14.3136 2 12L0 12C-2.74181e-06 14.7763 0.962627 17.4666 2.72387 19.6127C4.48511 21.7588 6.93599 23.2278 9.65891 23.7694C12.3818 24.3111 15.2083 23.8918 17.6568 22.5831C20.1052 21.2744 22.0241 19.1572 23.0866 16.5922C24.149 14.0273 24.2892 11.1733 23.4833 8.51661C22.6774 5.85989 20.9752 3.56479 18.6668 2.02238C16.3585 0.479975 13.5867 -0.214319 10.8238 0.057802C8.71195 0.2658 6.70517 1.02859 5 2.2532V1H3V5C3 5.55229 3.44772 6 4 6H8V4H5.99999C7.45608 2.90793 9.19066 2.22833 11.0198 2.04817ZM2 4V7H5V9H1C0.447715 9 0 8.55229 0 8V4H2ZM14.125 16C13.5466 16 13.0389 15.8586 12.6018 15.5758C12.1713 15.2865 11.8385 14.8815 11.6031 14.3609C11.3677 13.8338 11.25 13.2135 11.25 12.5C11.25 11.7929 11.3677 11.1759 11.6031 10.6488C11.8385 10.1217 12.1713 9.71671 12.6018 9.43389C13.0389 9.14463 13.5466 9 14.125 9C14.7034 9 15.2077 9.14463 15.6382 9.43389C16.0753 9.71671 16.4116 10.1217 16.6469 10.6488C16.8823 11.1759 17 11.7929 17 12.5C17 13.2135 16.8823 13.8338 16.6469 14.3609C16.4116 14.8815 16.0753 15.2865 15.6382 15.5758C15.2077 15.8586 14.7034 16 14.125 16ZM14.125 14.6501C14.5151 14.6501 14.8211 14.4637 15.043 14.0909C15.2649 13.7117 15.3759 13.1814 15.3759 12.5C15.3759 11.8186 15.2649 11.2916 15.043 10.9187C14.8211 10.5395 14.5151 10.3499 14.125 10.3499C13.7349 10.3499 13.4289 10.5395 13.207 10.9187C12.9851 11.2916 12.8741 11.8186 12.8741 12.5C12.8741 13.1814 12.9851 13.7117 13.207 14.0909C13.4289 14.4637 13.7349 14.6501 14.125 14.6501ZM8.60395 15.8554V10.7163L7 11.1405V9.81956L10.1978 9.01929V15.8554H8.60395Z" fill="currentColor"></path>
                                    </svg>
                                </div>
                                <div class="controls" id="id_next">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="Hawkins-Icon Hawkins-Icon-Standard">
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M6.4443 3.68532C8.36794 2.39998 10.6778 1.8214 12.9802 2.04817C14.8093 2.22833 16.5439 2.90793 18 4H16V6H20C20.5523 6 21 5.55228 21 5V1H19V2.2532C17.2948 1.02858 15.288 0.265799 13.1762 0.0578004C10.4133 -0.214321 7.64153 0.479973 5.33315 2.02238C3.02478 3.56479 1.32262 5.85989 0.516716 8.51661C-0.28919 11.1733 -0.148983 14.0273 0.913448 16.5922C1.97588 19.1572 3.8948 21.2744 6.34325 22.5831C8.79169 23.8918 11.6182 24.3111 14.3411 23.7694C17.064 23.2278 19.5149 21.7588 21.2761 19.6127C23.0374 17.4666 24 14.7763 24 12L22 12C22 14.3136 21.1978 16.5555 19.7301 18.3439C18.2624 20.1323 16.22 21.3565 13.9509 21.8079C11.6818 22.2592 9.32641 21.9098 7.28604 20.8192C5.24567 19.7286 3.64657 17.9643 2.76121 15.8269C1.87585 13.6894 1.75901 11.3111 2.4306 9.09717C3.10218 6.88324 4.52065 4.97066 6.4443 3.68532ZM22 4V7H19V9H23C23.5523 9 24 8.55228 24 8V4H22ZM12.6018 15.5758C13.0389 15.8586 13.5466 16 14.125 16C14.7034 16 15.2077 15.8586 15.6382 15.5758C16.0753 15.2865 16.4116 14.8815 16.6469 14.3609C16.8823 13.8338 17 13.2135 17 12.5C17 11.7929 16.8823 11.1758 16.6469 10.6488C16.4116 10.1217 16.0753 9.71671 15.6382 9.43388C15.2077 9.14463 14.7034 9 14.125 9C13.5466 9 13.0389 9.14463 12.6018 9.43388C12.1713 9.71671 11.8385 10.1217 11.6031 10.6488C11.3677 11.1758 11.25 11.7929 11.25 12.5C11.25 13.2135 11.3677 13.8338 11.6031 14.3609C11.8385 14.8815 12.1713 15.2865 12.6018 15.5758ZM15.043 14.0909C14.8211 14.4637 14.5151 14.6501 14.125 14.6501C13.7349 14.6501 13.4289 14.4637 13.207 14.0909C12.9851 13.7117 12.8741 13.1814 12.8741 12.5C12.8741 11.8186 12.9851 11.2916 13.207 10.9187C13.4289 10.5395 13.7349 10.3499 14.125 10.3499C14.5151 10.3499 14.8211 10.5395 15.043 10.9187C15.2649 11.2916 15.3759 11.8186 15.3759 12.5C15.3759 13.1814 15.2649 13.7117 15.043 14.0909ZM8.60395 10.7163V15.8554H10.1978V9.01928L7 9.81956V11.1405L8.60395 10.7163Z" fill="currentColor"></path>
                                    </svg>
                                </div>
								<div class="controls" id="id_volume">
									<div class="volume-bg"></div> 
									<div class="volume-bar" id="volume-bar">
										<div class="volume-level" id="volume-level"></div>
									</div>
								<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="Hawkins-Icon Hawkins-Icon-Standard">
									<!-- Speaker Base -->
									<path d="M11 4.00001C11 3.59555 10.7564 3.23092 10.3827 3.07613C10.009 2.92135 9.57889 3.00691 9.29289 3.29291L4.58579 8.00001H1C0.447715 8.00001 0 8.44773 0 9.00001V15C0 15.5523 0.447715 16 1 16H4.58579L9.29289 20.7071C9.57889 20.9931 10.009 21.0787 10.3827 20.9239C10.7564 20.7691 11 20.4045 11 20V4.00001Z" fill="currentColor"></path>
									 <path fill-rule="evenodd" clip-rule="evenodd" d="..." fill="currentColor"></path>

									<!-- Bar 1 -->
									<path id="volume-bar-1" d="M16.0001 12C16.0001 10.4087 15.368 8.8826 14.2428 7.75739L12.8285 9.1716C13.5787 9.92174 14.0001 10.9392 14.0001 12C14.0001 13.0609 13.5787 14.0783 12.8285 14.8285L14.2428 16.2427C15.368 15.1174 16.0001 13.5913 16.0001 12Z" fill="currentColor"></path>
									<!-- Bar 2 -->
									<path id="volume-bar-2" d="M17.0709 4.92896C18.9462 6.80432 19.9998 9.34786 19.9998 12C19.9998 14.6522 18.9462 17.1957 17.0709 19.0711L15.6567 17.6569C17.157 16.1566 17.9998 14.1218 17.9998 12C17.9998 9.87829 17.157 7.84346 15.6567 6.34317L17.0709 4.92896Z" fill="currentColor"></path>
									<!-- Bar 3 -->
									<path id="volume-bar-3" d="M24 12C24 8.28699 22.525 4.72603 19.8995 2.10052L18.4853 3.51474C20.7357 5.76517 22 8.81742 22 12C22 15.1826 20.7357 18.2349 18.4853 20.4853L19.8995 21.8995C22.525 19.274 24 15.7131 24 12Z" fill="currentColor"></path>
								</svg>
							</div>
                            </div>
							<div class="middle">
								<div class="video-title"><?= htmlspecialchars($videoTitle) ?></div>
							</div>

                            <div class="right">
								<div class="controls" id="id_heart">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="Hawkins-Icon Hawkins-Icon-Standard">
										<path id="heartPath" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" class="<?= $isLiked ? 'heart-filled' : 'heart-empty' ?>"></path>
									</svg>
								</div>
                                <div class="controls" id="id_caption">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="Hawkins-Icon Hawkins-Icon-Standard">
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M0 4C0 3.44772 0.447715 3 1 3H23C23.5523 3 24 3.44772 24 4V16C24 16.5523 23.5523 17 23 17H19V20C19 20.3688 18.797 20.7077 18.4719 20.8817C18.1467 21.0557 17.7522 21.0366 17.4453 20.8321L11.6972 17H1C0.447715 17 0 16.5523 0 16V4ZM2 5V15H12H12.3028L12.5547 15.1679L17 18.1315V16V15H18H22V5H2ZM10 9H4V7H10V9ZM20 11H14V13H20V11ZM12 13H4V11H12V13ZM20 7H12V9H20V7Z" fill="currentColor"></path>
                                    </svg>
                                </div>
                                <div class="controls" id="id_speed">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="Hawkins-Icon Hawkins-Icon-Standard">
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M17.6427 7.43779C14.5215 4.1874 9.47851 4.1874 6.35734 7.43779C3.21422 10.711 3.21422 16.0341 6.35734 19.3074L4.91474 20.6926C1.02842 16.6454 1.02842 10.0997 4.91474 6.05254C8.823 1.98249 15.177 1.98249 19.0853 6.05254C22.9716 10.0997 22.9716 16.6454 19.0853 20.6926L17.6427 19.3074C20.7858 16.0341 20.7858 10.711 17.6427 7.43779ZM14 14C14 15.1046 13.1046 16 12 16C10.8954 16 10 15.1046 10 14C10 12.8954 10.8954 12 12 12C12.1792 12 12.3528 12.0236 12.518 12.0677L15.7929 8.79289L17.2071 10.2071L13.9323 13.482C13.9764 13.6472 14 13.8208 14 14Z" fill="currentColor"></path>
                                    </svg>

									<div class="speed-selection-box">
										<div class="title">Vitesse de lecture</div>
										<div class="speeds">
											<div class="speed-line"></div>
											<div class="speed-option" data-speed="0.5">
												<div class="indicator"></div>
												<span>0.5x</span>
											</div>
											<div class="speed-option" data-speed="0.75">
												<div class="indicator"></div>
												<span>0.75x</span>
											</div>
											<div class="speed-option" data-speed="1">
												<div class="indicator selected"></div>
												<span>1x (Normal)</span>
											</div>
											<div class="speed-option" data-speed="1.25">
												<div class="indicator"></div>
												<span>1.25x</span>
											</div>
											<div class="speed-option" data-speed="1.50">
												<div class="indicator"></div>
												<span>1.5x</span>
											</div>
										</div>
									</div>
                                </div>
                                <div class="controls" id="id_fullscreen"> <!-- Bouton plein écran -->
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="Hawkins-Icon Hawkins-Icon-Standard" data-uia="control-fullscreen-enter"> 
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M0 5C0 3.89543 0.895431 3 2 3H9V5H2V9H0V5ZM22 5H15V3H22C23.1046 3 24 3.89543 24 5V9H22V5ZM2 15V19H9V21H2C0.895431 21 0 20.1046 0 19V15H2ZM22 19V15H24V19C24 20.1046 23.1046 21 22 21H15V19H22Z" fill="currentColor"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
					<!--Vidéo en Preview-->
					<video id="previewVideo" style="display: none;" preload="auto" src="<?= $videoSrc ?>"></video>

					
					
<script>
document.addEventListener("DOMContentLoaded", function () {
    let video = document.getElementById('videoElement');

    // Fonction pour play/pause
    function togglePlayPause() {
        let playIcon = document.querySelector('.svg-icon-nfplayerPlay');
        let pauseIcon = document.querySelector('.svg-icon-nfplayerPause');

        if (video.paused || video.ended) {
            video.play();
            playIcon.style.display = 'none';
            pauseIcon.style.display = 'block';
        } else {
            video.pause();
            playIcon.style.display = 'block';
            pauseIcon.style.display = 'none';
        }
    }


    let playPauseButton = document.getElementById('id_playpause');
    playPauseButton.addEventListener('click', togglePlayPause);


    video.addEventListener('click', togglePlayPause);

    // Play/Pause quand on appuie sur la touche "Espace"
    document.addEventListener('keydown', function(e) {
        if (e.code === 'Space') {
            togglePlayPause();
        }
    });
    document.getElementById('id_back').addEventListener('click', function () {
        video.currentTime -= 10;
    });

    document.getElementById('id_next').addEventListener('click', function () {
        video.currentTime += 10;
    });

    video.addEventListener('timeupdate', function () {
        let progress = (video.currentTime / video.duration) * 100;
        document.querySelector('.progress-completed').style.width = progress + "%";
        let minutes = Math.floor(video.currentTime / 60);
        let seconds = Math.floor(video.currentTime - minutes * 60);
        document.getElementById('timecode').textContent = minutes + ":" + (seconds < 10 ? '0' + seconds : seconds);
    });
	
	
	// LE PLEIN ECRAN 


document.getElementById('id_fullscreen').addEventListener('click', function() {
   const watchVideo = document.querySelector('.watch-video');
   if (document.fullscreenElement) {
      document.exitFullscreen();
   } else {
      watchVideo.requestFullscreen();
   }
});




    let volumeBar = document.querySelector('.volume-bar');
    let volumeLevel = document.querySelector('.volume-level');
    
    volumeBar.addEventListener('click', function(e) {
        let rect = volumeBar.getBoundingClientRect();
        let height = rect.bottom - rect.top;
        let clickedPosition = rect.bottom - e.clientY;
        let volume = clickedPosition / height;
        
        video.volume = volume;
        volumeLevel.style.height = (volume * 100) + "%";
    });

    // Volume bar drag functionality
    volumeBar.addEventListener('mousedown', function (e) {
        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
        adjustVolume(e);
    });


// POUR AVANCER DANS LA VIDEO 

	let progressBar = document.querySelector('.progress-bar');
	progressBar.addEventListener('click', function(e) {
		let rect = progressBar.getBoundingClientRect();
		let width = rect.right - rect.left;
		let clickedPosition = e.clientX - rect.left;
		let time = (clickedPosition / width) * video.duration;

		video.currentTime = time; // Mettre à jour la position actuelle de la vidéo
	});


progressBar.addEventListener('mousemove', function(e) {
    let rect = progressBar.getBoundingClientRect();
    let width = rect.right - rect.left;
    let hoveredPosition = e.clientX - rect.left;
    let time = (hoveredPosition / width) * video.duration;

    let minutes = Math.floor(time / 60);
    let seconds = Math.floor(time - minutes * 60);
    let timecode = minutes + ":" + (seconds < 10 ? '0' + seconds : seconds);

    // Affichez le timecode à l'endroit où le curseur est placé
    let tooltip = document.getElementById('tooltip'); 
    if (!tooltip) {
        tooltip = document.createElement('div');
        tooltip.id = 'tooltip';
        document.querySelector('.watch-video').appendChild(tooltip);
    }
    tooltip.style.left = e.clientX + 'px';
    tooltip.style.top = (rect.top - 30) + 'px';  // Positionné au-dessus de la barre de progression
    tooltip.textContent = timecode;
    tooltip.style.display = 'block';
});

progressBar.addEventListener('mouseleave', function() {
    let tooltip = document.getElementById('tooltip');
    if (tooltip) {
        tooltip.style.display = 'none';  // Cachez le tooltip lorsque la souris quitte la barre de progression
    }
});

// PREVISUA DE LA VIDEO

let canvas = document.createElement('canvas');
canvas.id = 'previewCanvas';
document.querySelector('.watch-video').appendChild(canvas);
let ctx = canvas.getContext('2d');

progressBar.addEventListener('mousemove', function(e) {
    let rect = progressBar.getBoundingClientRect();
    let width = rect.right - rect.left;
    let hoveredPosition = e.clientX - rect.left;
    let time = (hoveredPosition / width) * video.duration;

    showPreview(time, e.clientX, rect.top);
});

progressBar.addEventListener('mouseleave', function() {
    canvas.style.display = 'none';
});

let previewVideo = document.getElementById('previewVideo');

function showPreview(time, x, y) {
    previewVideo.currentTime = time;
    
    // Attendez que la vidéo soit prête à être jouée, puis on dessine le frame sur le canvas
    previewVideo.addEventListener('canplay', function drawFrameOnce() {
        ctx.drawImage(previewVideo, 0, 0, canvas.width, canvas.height);
        previewVideo.removeEventListener('canplay', drawFrameOnce);
    });

    canvas.style.left = (x - canvas.width / 5) + 'px';
    canvas.style.top = (y - canvas.height / 1.5) + 'px'; // Positionner le canvas au-dessus de la barre de progression
    canvas.style.display = 'block';
}


// VOLUME ICON 
function adjustVolumeIcons(volume) {
    const bar1 = document.getElementById('volume-bar-1');
    const bar2 = document.getElementById('volume-bar-2');
    const bar3 = document.getElementById('volume-bar-3');

    bar1.style.display = 'none';
    bar2.style.display = 'none';
    bar3.style.display = 'none';

    if (volume > 0.66) {
        bar3.style.display = 'block';
    }
    if (volume > 0.33) {
        bar2.style.display = 'block';
    }
    if (volume > 0) {
        bar1.style.display = 'block';
    }
}

    function onMouseMove(e) {
        adjustVolume(e);
    }

	function onMouseUp() {
		const rect = volumeBar.getBoundingClientRect();
		const height = rect.height;
		const currentVolumeHeight = parseFloat(window.getComputedStyle(volumeLevel).height);
		
		video.volume = currentVolumeHeight / height;

		document.removeEventListener('mousemove', onMouseMove);
		document.removeEventListener('mouseup', onMouseUp);
	}

	function adjustVolume(e) {
		const rect = volumeBar.getBoundingClientRect();
		const height = rect.height;
		const y = e.clientY - rect.top;

		// Si la souris est hors de la barre de volume, ne rien faire
		if (e.clientY < rect.top || e.clientY > rect.bottom) {
			return;
		}

		const volume = 1 - (y / height);
		volumeLevel.style.height = `${volume * 100}%`;
		
		video.volume = Math.min(Math.max(volume, 0), 1);  // Ajustez le volume de l'élément vidéo ou audio
		adjustVolumeIcons(video.volume);
	}
	
});

let volumeControl = document.getElementById('id_volume');
let progressBar = document.querySelector('.progress');

// Cachez la barre de progression lorsque la souris entre dans la zone de l'icône de volume
volumeControl.addEventListener('mouseenter', function() {
    progressBar.classList.add('hidden');
});

// Affichez à nouveau la barre de progression lorsque la souris quitte la zone de l'icône de volume
volumeControl.addEventListener('mouseleave', function() {
    progressBar.classList.remove('hidden');
});







// Sélectionnez l'élément de vitesse de lecture et la barre de progression
let speedControl = document.getElementById('id_speed');

// Cachez la barre de progression lorsque la souris entre dans la zone de l'icône de vitesse de lecture
speedControl.addEventListener('mouseenter', function() {
    progressBar.classList.add('hidden');
});

// Affichez à nouveau la barre de progression lorsque la souris quitte la zone de l'icône de vitesse de lecture
speedControl.addEventListener('mouseleave', function() {
    progressBar.classList.remove('hidden');
});



// APRES 3 SECONDES SANS INTERACTION CACHE LA BAR ET LA FLECHE 

document.addEventListener('DOMContentLoaded', function () {
    var timeout;
    var appMountPoint = document.getElementById('appMountPoint');

    // Fonction pour cacher la barre de contrôle
    function hideControls() {
        appMountPoint.classList.add('hide-controls');
    }

    // Fonction pour montrer la barre de contrôle
    function showControls() {
        appMountPoint.classList.remove('hide-controls');
    }

    // Détecter le mouvement de la souris
    appMountPoint.addEventListener('mousemove', function() {
        // Montrer la barre de contrôle à chaque mouvement de la souris
        showControls();

        // Effacer le timeout précédent
        clearTimeout(timeout);

        // Définir un nouveau timeout pour cacher la barre de contrôle après 5 secondes
        timeout = setTimeout(hideControls, 3000);
    });
});
// VITESSE DE LECTURE 

document.getElementById('id_speed').addEventListener('mouseenter', function() {
    document.querySelector('.speed-selection-box').style.display = 'block';
});

document.getElementById('id_speed').addEventListener('mouseleave', function() {
    document.querySelector('.speed-selection-box').style.display = 'none';
});

document.querySelectorAll('.speed-option').forEach(function(option) {
    option.addEventListener('click', function() {
        // Désélectionner toutes les options
        document.querySelectorAll('.speed-option .indicator').forEach(function(indicator) {
            indicator.classList.remove('selected');
        });
        
        // Sélectionner l'option cliquée
        this.querySelector('.indicator').classList.add('selected');
        
        let speed = parseFloat(this.getAttribute('data-speed'));
        let video = document.getElementById('videoElement');
        video.playbackRate = speed;
    });
});


// IMPOSSIBLE DE CLIQUE DROIT 
document.addEventListener('contextmenu', function(e) {
    e.preventDefault();
});

document.addEventListener('selectstart', function(e) {
    e.preventDefault();
});
</script>

<script>
// Système d'ajout de vues
let video = document.getElementById("videoElement");
let hasBeenCounted = false;
let watchingTime = 0;
let watchInterval;

video.addEventListener("play", function() {
    if (watchingTime < 20 && !hasBeenCounted) {
        watchInterval = setInterval(() => {
            watchingTime++;
            if (watchingTime >= 20) {
                clearInterval(watchInterval);

                let lastViewTimestamp = localStorage.getItem(`lastView_${<?= $videoId ?>}`);
                let now = new Date().getTime();

                // Si une vue n'a jamais été ajoutée ou s'est écoulé plus de 3 minutes depuis la dernière vue
                if (!lastViewTimestamp || now - lastViewTimestamp > 3 * 60 * 1000) {
                    incrementView();
                    localStorage.setItem(`lastView_${<?= $videoId ?>}`, now);
                }
            }
        }, 1000);
    }
});

video.addEventListener("pause", function() {
    clearInterval(watchInterval);
});

video.addEventListener("seeked", function() {
    if (video.currentTime < 20) {
        watchingTime = Math.floor(video.currentTime);
    }
});

function incrementView() {
    fetch(`incrementView.php?id=${<?= $videoId ?>}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log("Vue comptabilisée !");
            hasBeenCounted = true;
        } else {
            console.log("Erreur lors de la comptabilisation de la vue !");
        }
    })
    .catch(error => {
        console.log("Erreur:", error);
    });
}
</script>





<script>
// Système like
document.getElementById('id_heart').addEventListener('click', function() {
    let videoId = <?= $videoId ?>;
    let heartPath = document.getElementById('heartPath');

    fetch('like_video.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'videoId=' + videoId + '&_=' + new Date().getTime()
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            console.error(data.error);
        } else {
            setTimeout(() => {
                if (data.liked) {
                    heartPath.classList.add('heart-filled');
                    heartPath.classList.remove('heart-empty');

                    const videoPlayer = document.getElementById('videoElement');
                    const rect = videoPlayer.getBoundingClientRect();
                    for (let i = 0; i < 38; i++) {
                        // Introduire un délai aléatoire pour chaque pouce
                        setTimeout(() => {
                            createAndAnimateThumb(rect);
                        }, i * 100); // Ajustez le délai ici si nécessaire
                    }
                } else {
                    heartPath.classList.add('heart-empty');
                    heartPath.classList.remove('heart-filled');
                }
            }, 10);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
    });
});

function createAndAnimateThumb(rect) {
    const thumb = document.createElement('div');
    thumb.classList.add('thumb-up');
    const thumbWidth = 66;
    const thumbHeight = 66;

    const x = rect.left + Math.random() * (rect.width - thumbWidth);
    const y = rect.top + Math.random() * (rect.height - thumbHeight);
    thumb.style.left = `${x}px`;
    thumb.style.top = `${y}px`;

    document.body.appendChild(thumb);
    thumb.style.animation = 'thumbAnimation 3s linear';

    thumb.addEventListener('animationend', () => {
        thumb.remove();
    });
}


</script>


<script>
function goBack() {
    window.history.back();
}
</script>


<!-- Fenêtre Modale pour la section COMMENTAIRE ! -->
<div id="maModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>


<!-- Section de défilement des commentaires -->

<div class="comment-scroll-section">
    <?php
	// Récupérer les commentaires de la base de données
	$commentQuery = $pdo->prepare("
		SELECT c.*, 
		(SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.comment_id) as likes_count,
		(SELECT COUNT(*) FROM comment_replies WHERE comment_id = c.comment_id) as replies_count 
		FROM comments c WHERE video_id = ?
	");
	$commentQuery->execute([$videoId]);

    while ($comment = $commentQuery->fetch()) {
        // Récupérer les informations de l'utilisateur qui a commenté
        $userQuery = $pdo->prepare("SELECT username, profile_picture FROM users WHERE user_id = ?");
        $userQuery->execute([$comment['user_id']]);
		if ($isUserLoggedIn) {
			$userId = $_SESSION['user']['user_id'];
			$commentId = $comment['comment_id'];
			$checkLike = $pdo->prepare("SELECT * FROM comment_likes WHERE user_id = ? AND comment_id = ?");
			$checkLike->execute([$userId, $commentId]);
			$hasLiked = $checkLike->fetch() ? true : false;
		} else {
			$hasLiked = false;
		}

        $commentUser = $userQuery->fetch();
    ?>
	

        <div class="comment-section">
            <div class="comment-container">
                <!-- Image de profil -->
                <img src="<?= htmlspecialchars($commentUser['profile_picture']) ?>" alt="Profil" class="profile-pic">
                <!-- Détails du commentaire -->
                <div class="comment-details">
                    <h4>@<?= htmlspecialchars($commentUser['username']) ?></h4>
					<p><?= nl2br(convertMentionsToLinks(htmlspecialchars($comment['content']))) ?></p>

					<div class="like-container">
						<span class="like-heart <?= $hasLiked ? 'liked' : '' ?>" data-comment-id="<?= $comment['comment_id'] ?>">
							<?= $hasLiked ? '💚' : '♡' ?>
						</span>
						<div class="like-count"><?= $comment['likes_count'] ?></div>
					</div>

                    <button class="reply-button">Répondre</button>
					<div class="reply-input-section" style="display: none;">
						<form method="post" action="addReply.php"> <!-- Remplacez avec le chemin de votre script PHP -->
						<input type="hidden" name="video_id" value="<?= $videoId ?>">
							<textarea name="reply_content" class="reply-input" placeholder="Votre réponse..." required></textarea>

							<input type="hidden" name="comment_id" value="<?= $comment['comment_id'] ?>">

							<div class="button-container"> 
								<button type="button" class="emoji-picker">😊</button>
								<button type="submit" class="send-reply">Envoyer</button>
								<button type="button" class="cancel-reply">Annuler</button>
							</div>
						</form>
					</div>
                </div>
            </div>
			
			<!-- Bouton pour afficher/masquer les réponses -->
			<!-- Affiche le bouton uniquement s'il y a des réponses -->
			<?php if ($comment['replies_count'] > 0) : ?>
				<button class="reply-toggle">Afficher les Réponses</button>
			<?php endif; ?>

            <!-- Section des réponses -->
            <div class="reply-section" style="display: none;">
                <?php				
                // Récupérer les réponses pour ce commentaire
				$replyQuery = $pdo->prepare("
					SELECT r.*, 
					(SELECT COUNT(*) FROM reply_likes WHERE reply_id = r.reply_id) as reply_likes_count 
					FROM comment_replies r WHERE comment_id = ?
				");
				$replyQuery->execute([$comment['comment_id']]);

                while ($reply = $replyQuery->fetch()) {
                    // Récupérer les informations de l'utilisateur qui a répondu
                    $replyUserQuery = $pdo->prepare("SELECT username, profile_picture FROM users WHERE user_id = ?");
                    $replyUserQuery->execute([$reply['user_id']]);
                    $replyUser = $replyUserQuery->fetch();
					
				// Vérifiez si l'utilisateur a aimé la réponse
				if ($isUserLoggedIn) {
					$userId = $_SESSION['user']['user_id'];
					$replyId = $reply['reply_id'];
					$checkLikeReply = $pdo->prepare("SELECT * FROM reply_likes WHERE user_id = ? AND reply_id = ?");
					$checkLikeReply->execute([$userId, $replyId]);
					$hasLikedReply = $checkLikeReply->fetch() ? true : false;
				} else {
					$hasLikedReply = false;
				}
					
                ?>
                    <div class="comment-section">
                        <div class="comment-container">
                            <img src="<?= htmlspecialchars($replyUser['profile_picture']) ?>" alt="Profil" class="profile-pic">
                            <div class="comment-details">
                                <h4>@<?= htmlspecialchars($replyUser['username']) ?></h4>
								<p><?= nl2br(convertMentionsToLinks(htmlspecialchars($reply['content']))) ?></p>

								
								
								<div class="like-container">
								<span class="like-heart-reply <?= $hasLikedReply ? 'liked' : '' ?>" data-reply-id="<?= $reply['reply_id'] ?>">
									<?= $hasLikedReply ? '💚' : '♡' ?>
								</span>
								<div class="like-count"><?= $reply['reply_likes_count'] ?></div>
								</div>

								
								<button class="reply-button" data-reply-to="@<?= htmlspecialchars($replyUser['username']) ?>">Répondre</button>
									<div class="reply-input-section" style="display: none;">
									<form method="post" action="addReply.php"> <!-- Remplacez avec le chemin de votre script PHP -->
									    <input type="hidden" name="video_id" value="<?= $videoId ?>">
										<textarea name="reply_content" class="reply-input" placeholder="Votre réponse..." required></textarea>

										<input type="hidden" name="comment_id" value="<?= $comment['comment_id'] ?>">

										<div class="button-container">
											<button type="button" class="emoji-picker">😊</button>
											<button type="submit" class="send-reply">Envoyer</button>
											<button type="button" class="cancel-reply">Annuler</button>
										</div>
									</form>
								</div>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    <?php } ?>
</div>

		<!-- Section de saisie du nouveau commentaire -->
		<div class="comment-input-section">
		
			<form method="post" action="addComment.php">
				<!-- Espace de saisie du nouveau message -->
				<textarea name="comment_content" class="modal-input" placeholder="Écrivez votre commentaire..." required></textarea>

				<input type="hidden" name="video_id" value="<?= $videoId ?>">

				<div class="input-and-submit-container">
					<button type="button" class="emoji-picker">😊</button>
					<button type="submit" class="modal-submit">Envoyer</button>
				</div>
			</form>
		</div>
	</div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function () {
    var replyButtons = document.querySelectorAll('.reply-button');

    replyButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            var replyToUsername = this.getAttribute('data-reply-to');
            var replyInputSection = this.parentNode.querySelector('.reply-input-section');
            var replyInput = replyInputSection.querySelector('.reply-input');

            if (replyToUsername) {
                replyInput.value = replyToUsername + ' '; // Pré-remplir avec la mention
            }

            replyInputSection.style.display = 'block';
            replyInput.focus(); // focus sur le champ de saisie
        });
    });

    // Gestion du bouton 'Annuler'
    var cancelReplyButtons = document.querySelectorAll('.cancel-reply');
    cancelReplyButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            var replyInputSection = this.closest('.reply-input-section');
            replyInputSection.style.display = 'none';
        });
    });
});


</script>
<script>
document.querySelectorAll('.like-heart').forEach(function(heart) {
    heart.addEventListener('click', function() {
        var commentId = this.getAttribute('data-comment-id');
        fetch('likeComment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'commentId=' + commentId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                var likeCountDiv = this.nextElementSibling;
                var currentLikes = parseInt(likeCountDiv.textContent);
                likeCountDiv.textContent = data.liked ? currentLikes + 1 : currentLikes - 1;

                this.classList.toggle('liked', data.liked);
                this.textContent = data.liked ? '💚' : '♡';

                // Appliquer l'animation
                this.classList.add('heart-pulse');
                setTimeout(() => {
                    this.classList.remove('heart-pulse');
                }, 600);
            } else {
                console.error(data.error);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
        });
    });
});


document.querySelectorAll('.like-heart-reply').forEach(function(heart) {
    heart.addEventListener('click', function() {
        var replyId = this.getAttribute('data-reply-id');
        fetch('likeReply.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'replyId=' + replyId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mettre à jour le cœur de like
                this.classList.toggle('liked', data.liked);
                this.textContent = data.liked ? '💚' : '♡';

                // Mettre à jour le compteur de likes
                var likeCountDiv = this.nextElementSibling;
                likeCountDiv.textContent = data.likeCount;
            } else {
                console.error(data.error);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
        });
    });
});


</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Récupérer le bouton qui ouvre la modale
    var btn = document.getElementById("id_caption");

    // Récupérer l'élément modale
    var modal = document.getElementById("maModal");

    // Récupérer l'élément qui ferme la modale (le span avec la classe .close)
    var span = document.getElementsByClassName("close")[0];

    // Quand l'utilisateur clique sur le bouton, ouvrir la modale 
    btn.onclick = function() {
        modal.style.display = "block";
    }

    // Quand l'utilisateur clique sur <span> (x), fermer la modale
    span.onclick = function() {
        modal.style.display = "none";
    }

    // Quand l'utilisateur clique n'importe où en dehors de la modale, la fermer
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
});


document.addEventListener('DOMContentLoaded', function () {
    var replyToggles = document.querySelectorAll('.reply-toggle');

    replyToggles.forEach(function(toggle) {
        toggle.addEventListener('click', function() {
            var replySection = this.nextElementSibling;

            if (replySection.style.display === 'none' || !replySection.style.display) {
                // Animation d'ouverture
                var keyframes = [
                    { height: '0', opacity: 0, overflow: 'hidden' },
                    { height: 'auto', opacity: 1, overflow: 'hidden' }
                ];
                replySection.animate(keyframes, { duration: 300, fill: 'forwards' });
                replySection.style.display = 'block';
                this.textContent = 'Masquer les Réponses';
            } else {
                // Animation de fermeture
                var keyframes = [
                    { height: 'auto', opacity: 1, overflow: 'hidden' },
                    { height: '0', opacity: 0, overflow: 'hidden' }
                ];
                replySection.animate(keyframes, { duration: 300, fill: 'forwards' }).onfinish = function() {
                    replySection.style.display = 'none';
                };
                this.textContent = 'Afficher les Réponses';
            }
        });
    });
});

document.addEventListener('DOMContentLoaded', function () {
    var replyButtons = document.querySelectorAll('.reply-button');

    replyButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            var replyInputSection = this.nextElementSibling;
            replyInputSection.style.display = 'block';
        });
    });
});

    var cancelReplyButtons = document.querySelectorAll('.cancel-reply');

    cancelReplyButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            // Trouver le conteneur 'reply-input-section' le plus proche et le masquer
            var replyInputSection = this.closest('.reply-input-section');
            if (replyInputSection) {
                replyInputSection.style.display = 'none';
            }
        });
    });



</script>


<script>
document.addEventListener('DOMContentLoaded', function () {
    var emojiPickers = document.querySelectorAll('.emoji-picker');
    var emojiContainer;
    var activeTextarea;
    var currentPicker; // Pour mémoriser le bouton emoji actuellement utilisé


    var emojiCategories = {
		
		
		faces: [
			"😀", "😃", "😄", "😁", "😆", "😅", "🤣", "😂", "🙂", "🙃", 
			"😉", "😊", "😇", "🥰", "😍", "🤩", "😘", "😗", "😚", "😙", 
			"😋", "😛", "😜", "🤪", "😝", "🤑", "🤗", "🤭", "🤫", "🤔", 
			"🤐", "🤨", "😐", "😑", "😶", "😶‍🌫️", "😏", "😒", "🙄", "😬", 
			"😮‍💨", "🤥", "😌", "😔", "😪", "🤤", "😴", "😷", "🤒", "🤕", 
			"🤢", "🤮", "🤧", "🥵", "🥶", "🥴", "😵", "😵‍💫", "🤯", "🤠", 
			"🥳", "😎", "🤓", "🧐", "😕", "😟", "🙁", "☹️", "😮", "😯", 
			"😲", "😳", "🥺", "😦", "😧", "😨", "😰", "😥", "😢", "😭", 
			"😱", "😖", "😣", "😞", "😓", "😩", "😫", "🥱", "😤", "😡", 
			"😠", "🤬", "😈", "👿", "💀", "☠️", "💩", "🤡", "👹", "👺",
			"✈️", "🚀"
		].join(" ").split(" "),
		
		gestures: [
			"👋", "👋🏻", "👋🏼", "👋🏽", "👋🏾", "👋🏿",
			"🤚", "🤚🏻", "🤚🏼", "🤚🏽", "🤚🏾", "🤚🏿",
			"🖐️", "🖐🏻", "🖐🏼", "🖐🏽", "🖐🏾", "🖐🏿",
			"✋", "✋🏻", "✋🏼", "✋🏽", "✋🏾", "✋🏿",
			"🖖", "🖖🏻", "🖖🏼", "🖖🏽", "🖖🏾", "🖖🏿",
			"👌", "👌🏻", "👌🏼", "👌🏽", "👌🏾", "👌🏿",
			"🤌", "🤌🏻", "🤌🏼", "🤌🏽", "🤌🏾", "🤌🏿",
			"🤏", "🤏🏻", "🤏🏼", "🤏🏽", "🤏🏾", "🤏🏿",
			"👈", "👈🏻", "👈🏼", "👈🏽", "👈🏾", "👈🏿",
			"👉", "👉🏻", "👉🏼", "👉🏽", "👉🏾", "👉🏿",
			"👆", "👆🏻", "👆🏼", "👆🏽", "👆🏾", "👆🏿",
			"👇", "👇🏻", "👇🏼", "👇🏽", "👇🏾", "👇🏿",
			"☝️", "☝🏻", "☝🏼", "☝🏽", "☝🏾", "☝🏿",
			"👍", "👍🏻", "👍🏼", "👍🏽", "👍🏾", "👍🏿",
			"👎", "👎🏻", "👎🏼", "👎🏽", "👎🏾", "👎🏿",
			"✊", "✊🏻", "✊🏼", "✊🏽", "✊🏾", "✊🏿",
			"👊", "👊🏻", "👊🏼", "👊🏽", "👊🏾", "👊🏿",
			"🤛", "🤛🏻", "🤛🏼", "🤛🏽", "🤛🏾", "🤛🏿",
			"🤜", "🤜🏻", "🤜🏼", "🤜🏽", "🤜🏾", "🤜🏿",
			"👏", "👏🏻", "👏🏼", "👏🏽", "👏🏾", "👏🏿",
			"🙌", "🙌🏻", "🙌🏼", "🙌🏽", "🙌🏾", "🙌🏿",
			"👐", "👐🏻", "👐🏼", "👐🏽", "👐🏾", "👐🏿",
			"🤲", "🤲🏻", "🤲🏼", "🤲🏽", "🤲🏾", "🤲🏿",
			"🤝", "🤝🏻", "🤝🏼", "🤝🏽", "🤝🏾", "🤝🏿",
			"🙏", "🙏🏻", "🙏🏼", "🙏🏽", "🙏🏾", "🙏🏿",
			"✍️", "✍🏻", "✍🏼", "✍🏽", "✍🏾", "✍🏿",
			"💅", "💅🏻", "💅🏼", "💅🏽", "💅🏾", "💅🏿",
			"🦵", "🦵🏻", "🦵🏼", "🦵🏽", "🦵🏾", "🦵🏿",
			"🦶", "🦶🏻", "🦶🏼", "🦶🏽", "🦶🏾", "🦶🏿",
			"👂", "👂🏻", "👂🏼", "👂🏽", "👂🏾", "👂🏿",
			"💪", "💪🏻", "💪🏼", "💪🏽", "💪🏾", "💪🏿",
			"🖕", "🖕🏻", "🖕🏼", "🖕🏽", "🖕🏾", "🖕🏿"

		].join(" ").split(" "),
		
		animals: [
			"🐱", "🐶", "🐭", "🐹", "🐰", "🦊", "🐻", "🐼", "🐨", "🐯", 
			"🦁", "🐮", "🐷", "🐸", "🐵", "🦄", "🦓", "🐴", "🦌", "🦬", 
			"🦍", "🦧", "🐘", "🦏", "🐪", "🐫", "🦒", "🦤", "🦚", "🦜", 
			"🦢", "🦩", "🕊️", "🐧", "🐦", "🐤", "🐥", "🦆", "🦅", "🦉", 
			"🦇", "🐺", "🐗", "🦔", "🐾", "🐉", "🐲"
		].join(" ").split(" "),

		nature: [
			"🌲", "🌳", "🌴", "🌱", "🌿", "🍀", "🌷", "🌻", "🌼", "🌸",
			"🌺", "🏵️", "🌹", "🥀", "🌾", "🍁", "🍂", "🍃", "🌪", "🌈",
			"☀️", "🌤️", "🌥️", "🌦️", "🌧️", "🌨️", "🌩️", "⛈️", "🌫️", "🌀",
			"☔", "❄️", "☃️", "⛄", "💨", "💧", "💦", "🌊", "🌋", "🗻",
			"🏜️", "🏖️", "🏝️", "🏕️", "🏞️", "🌅", "🌄", "🌠", "🎇", "🎆",
			"🌇", "🌆", "🌉", "🌌", "🌑", "🌒", "🌓", "🌔", "🌕", "🌖",
			"🌗", "🌘", "🌙", "🌚", "🌛", "🌜", "🌝", "🌞", "🪐", "✨",
			"⭐", "🌟", "🌠", "🌌", "🌁", "⛅", "⛈️", "🌤️", "🌥️", "🌦️"
		].join(" ").split(" "),


		sports: [
			"⚽", "🏀", "🏈", "⚾", "🎾", "🏐", "🏉", "🥏", "🎱", "🏓",
			"🏸", "🏑", "🏒", "🥍", "🏏", "🥅", "🥊", "🥋", "🥌", "🛷",
			"🏂", "🏋️", "🏋️‍♂️", "🏋️‍♀️", "🤼", "🤼‍♂️", "🤼‍♀️", "🤸", "🤸‍♂️", "🤸‍♀️",
			"⛹️", "⛹️‍♂️", "⛹️‍♀️", "🤾", "🤾‍♂️", "🤾‍♀️", "🏌️", "🏌️‍♂️", "🏌️‍♀️", "🏇",
			"🏄", "🏄‍♂️", "🏄‍♀️", "🚣", "🚣‍♂️", "🚣‍♀️", "🧗", "🧗‍♂️", "🧗‍♀️", "🚵",
			"🚵‍♂️", "🚵‍♀️", "🚴", "🚴‍♂️", "🚴‍♀️", "🏆", "🥇", "🥈", "🥉", "🏅",
			"🎖️", "🏵️", "🎗️", "🎫", "🎟️", "🎪", "🎭", "🎨", "🖼️", "🎰",
			"🎲", "🧩", "🧸", "🪀", "🪁", "🎮", "🕹️", "🎳", "🎯", "🪃",
			"🛹", "🛼", "🛶", "⛵", "🚣", "🏊", "🤽", "🚴", "🚵", "🏇",
			"🧗", "🏂", "🏌️", "🏄", "🪂", "🤿", "🤸", "🎽", "🏋️", "🤼",
			"🤽", "🏓", "🏸", "🏒", "🏑", "🥍", "🎱", "🏏", "🥅", "🏹"
		].join(" ").split(" "),

		food: [
			"🍏", "🍎", "🍐", "🍊", "🍋", "🍌", "🍉", "🍇", "🍓", "🍈",
			"🍒", "🍑", "🍍", "🥭", "🍅", "🍆", "🥑", "🥦", "🥬", "🥒",
			"🌶️", "🫑", "🌽", "🥕", "🧄", "🧅", "🥔", "🍠", "🍯", "🍞",
			"🥐", "🥖", "🥨", "🥯", "🥞", "🧇", "🧀", "🍖", "🍗", "🥩",
			"🥓", "🍔", "🍟", "🍕", "🌭", "🥪", "🥙", "🧆", "🌮", "🌯",
			"🫔", "🥗", "🥘", "🥫", "🍝", "🍜", "🍲", "🍛", "🍣", "🍱",
			"🥟", "🦪", "🍤", "🍙", "🍚", "🍘", "🍥", "🥠", "🥮", "🍢",
			"🍡", "🍧", "🍨", "🍦", "🥧", "🧁", "🍰", "🎂", "🍮", "🍭",
			"🍬", "🍫", "🍿", "🍩", "🍪", "🌰", "🥜", "🍯", "🫐", "🫒",
			"🫕", "🧈", "🥯", "🥓", "🥚", "🥞", "🧇", "🥖", "🥨", "🥐"
		].join(" ").split(" "),
		
		
		objects: [
			"⌚", "📱", "📲", "💻", "⌨️", "🖥️", "🖨️", "🖱️", "🖲️", "🕹️",
			"🗜️", "💽", "💾", "💿", "📀", "📼", "📷", "📸", "📹", "🎥",
			"📽️", "🎞️", "📞", "📟", "📠", "📺", "📻", "📡", "🔋", "🔌",
			"💡", "🔦", "🕯️", "🪔", "🧯", "🛢️", "💸", "💵", "💴", "💶",
			"💷", "💰", "💳", "🧾", "💎", "⚖️", "🪜", "🧰", "🪛", "🪚",
			"🪤", "🪒", "🧲", "🪓", "🔨", "🔧", "🔩", "🔪", "🗡️", "⚔️",
			"🛡️", "🔗", "⛓️", "🪝", "🔬", "🔭", "📡", "💉", "💊", "🩸",
			"🩺", "🩹", "🩼", "🚪", "🪞", "🪟", "🛏️", "🛋️", "🪑", "🚽",
			"🚿", "🛁", "🪒", "🧴", "🧷", "🧹", "🧺", "🧻", "🪣", "🧼",
			"🪥", "🧽", "🧮", "🕰️", "🌡️", "🧯", "🪓", "🛠️", "🧲", "🔧",
			"🔨", "⚙️", "🗜️", "⚖️", "🧬", "🔬", "🔭", "📡", "🕳️", "🪤",
			"🧱", "🪵", "🛤️", "🛢️", "⛓️", "🧨", "🪓", "🔪", "🗡️", "⚔️",
			"🛡️", "🔮", "🧿", "🪄", "🧸", "🪅", "🪆", "🧵", "🧶", "🪡",
			"🪢", "🛍️", "🎒", "🧳", "🌂", "☂️", "🧵", "🧶", "👓", "🕶️",
			"🥽", "🥼", "🦺", "👜", "💼", "🎒", "🧰", "🛍️", "📿", "💍",
			"🕳️", "🪁", "🏺", "🔑", "🔓", "🔒", "🔏", "🔐", "🔨", "🪓",
			"🪚", "🪛", "🔪", "🪃", "🏹", "🛡️", "🪚", "🔩", "🪜", "🛠️",
			"🗝️", "🪓", "🪚", "🔨", "🛠️", "🪛", "🪚", "🪒", "🧲", "🪜",
			"🧰", "🔧", "🔨", "🪓", "🗡️", "🪚", "🪛", "🔩", "🔪", "🔧"
		].join(" ").split(" "),

		
		symbols: [
			"❤️", "💔", "❣️", "💕", "💞", "💓", "💗", "💖", "💘", "💝",
			"💟", "☮️", "✝️", "☪️", "🕉️", "☸️", "✡️", "🔯", "🕎", "☯️",
			"☦️", "🛐", "⛎", "♈️", "♉️", "♊️", "♋️", "♌️", "♍️", "♎️",
			"♏️", "♐️", "♑️", "♒️", "♓️", "🆔", "⚛️", "🉑", "☢️", "☣️",
			"📴", "📳", "🈶", "🈚️", "🈸", "🈺", "🈷️", "✴️", "🆚", "💮",
			"🉐", "㊙️", "㊗️", "🈴", "🈵", "🈹", "🈲", "🅰️", "🅱️", "🆎",
			"🆑", "🅾️", "🆘", "❌", "⭕️", "🛑", "⛔️", "📛", "🚫", "💯",
			"💢", "♨️", "🚷", "🚯", "🚳", "🚱", "🔞", "📵", "🚭", "❗️"
		].join(" ").split(" "),


	};
	

	function updateEmojiContainerPosition() {
		if (!currentPicker || !emojiContainer) return;

		var pickerRect = currentPicker.getBoundingClientRect();
		var leftPosition = pickerRect.left + window.scrollX;
		var topPosition = pickerRect.bottom + window.scrollY;
		var emojiContainerHeight = 350; // Hauteur approximative de la boîte d'emojis
		var emojiContainerWidth = 300; // Largeur approximative de la boîte d'emojis

		// Si la boîte d'emoji déborde en bas de l'écran, ajuster la position vers le haut
		if ((topPosition + emojiContainerHeight) > (window.scrollY + window.innerHeight)) {
			topPosition = topPosition - emojiContainerHeight - pickerRect.height;
		}

		// Si la boîte d'emoji déborde à droite de l'écran, ajuster la position vers la gauche
		if ((leftPosition + emojiContainerWidth) > (window.scrollX + window.innerWidth)) {
			leftPosition = window.innerWidth - emojiContainerWidth + window.scrollX;
		}

		emojiContainer.style.left = leftPosition + 'px';
		emojiContainer.style.top = topPosition + 'px';
	}

	

    // Fonction pour ajouter des emojis au champ de texte actif
    function addEmojiToTextarea(emoji) {
        if (activeTextarea) {
            activeTextarea.value += emoji;
        }
    }

    // Fonction pour fermer le sélecteur d'emojis
    function closeEmojiPicker() {
        if (emojiContainer && emojiContainer.parentNode) {
            emojiContainer.parentNode.removeChild(emojiContainer);
            emojiContainer = null;
        }
    }

    // Gestionnaire d'événements pour fermer le sélecteur d'emojis lorsque l'utilisateur clique en dehors
    document.addEventListener('click', function(event) {
        if (emojiContainer && !emojiContainer.contains(event.target) && !event.target.classList.contains('emoji-picker')) {
            closeEmojiPicker();
        }
    });

    // Fonction pour mettre à jour les emojis affichés selon la catégorie
    function updateEmojiDisplay(category) {
        var emojiDisplayArea = emojiContainer.querySelector('.emoji-display-area');
        emojiDisplayArea.innerHTML = ''; // Effacer les emojis précédents
        emojiCategories[category].forEach(function(emoji) {
            var emojiSpan = document.createElement('span');
            emojiSpan.textContent = emoji;
            emojiSpan.style.cursor = 'pointer';
            emojiSpan.style.margin = '5px';
            emojiSpan.addEventListener('click', function() {
                addEmojiToTextarea(emoji); // Ajoute l'emoji au champ de texte actif
                closeEmojiPicker();
            });
            emojiDisplayArea.appendChild(emojiSpan);
        });
    }
	

    emojiPickers.forEach(function(picker) {
        picker.addEventListener('click', function(event) {
            event.stopPropagation();

            if (emojiContainer && document.body.contains(emojiContainer)) {
                closeEmojiPicker();
                return;
            }
            activeTextarea = this.closest('.comment-section, .comment-input-section').querySelector('textarea');
            currentPicker = this; // Mémoriser le bouton emoji actuel

            emojiContainer = document.createElement('div');
            emojiContainer.className = 'emoji-container';
            emojiContainer.style.position = 'fixed';
            emojiContainer.style.zIndex = '1000';
            emojiContainer.style.display = 'flex';
            emojiContainer.style.flexDirection = 'row';

            var pickerRect = picker.getBoundingClientRect();
            var leftPosition = pickerRect.left;
            var topPosition = pickerRect.top;
            var bottomSpace = window.innerHeight - pickerRect.bottom;

            if (bottomSpace < 350) { // 350 est la hauteur approximative de la boîte d'emojis
                emojiContainer.style.bottom = (window.innerHeight - topPosition) + 'px';
            } else {
                emojiContainer.style.top = (topPosition + pickerRect.height) + 'px';
            }

            if (window.innerWidth - leftPosition < 300) { // 300 est la largeur approximative de la boîte d'emojis
                leftPosition = window.innerWidth - 300;
            }
            emojiContainer.style.left = leftPosition + 'px';

            var emojiDisplayArea = document.createElement('div');
            emojiDisplayArea.className = 'emoji-display-area';
            emojiDisplayArea.style.flexGrow = '1';
            emojiDisplayArea.style.display = 'flex';
            emojiDisplayArea.style.flexWrap = 'wrap';
            emojiContainer.appendChild(emojiDisplayArea);

            var categoryMenu = document.createElement('div');
            categoryMenu.className = 'emoji-category-menu';
            categoryMenu.style.display = 'flex';
            categoryMenu.style.flexDirection = 'column';

            Object.keys(emojiCategories).forEach(function(category) {
                var button = document.createElement('button');
                button.className = 'emoji-category-button';
                button.innerHTML = emojiCategories[category][0];
                button.addEventListener('click', function() {
                    updateEmojiDisplay(category);
                });
                categoryMenu.appendChild(button);
            });

            emojiContainer.appendChild(categoryMenu);
            document.body.appendChild(emojiContainer);
            updateEmojiDisplay(Object.keys(emojiCategories)[0]);
			
            updateEmojiContainerPosition(); // Mettre à jour la position initiale
        });
    });
	
    // Gestionnaire d'événements pour mettre à jour la position lors du défilement
    window.addEventListener('scroll', function() {
        updateEmojiContainerPosition();
    }, true);


    document.addEventListener('click', function(event) {
        if (emojiContainer && !emojiContainer.contains(event.target) && !event.target.classList.contains('emoji-picker')) {
            closeEmojiPicker();
        }
    });
});

function convertEmojis(text) {
    return emojione.toImage(text);
}

</script>

</body>