README : Projet RAPLY

Le projet RAPLY est une initiative personnelle que j'ai développée durant mon temps libre.
Actuellement, je ne suis plus en mesure de poursuivre son développement. 
Ainsi, j'ai décidé de le rendre open source afin que d'autres personnes puissent l'utiliser, l'améliorer et l'exploiter selon leurs besoins.

Vous trouverez ci-dessous les tables SQL nécessaires au fonctionnement du site. 
Pour les utiliser, il suffit de les copier et de les coller dans votre gestionnaire de base de données (comme PHPMYADMIN ou équivalent), et le site devrait fonctionner sans problème.



Le projet a été développé par YND Weezy. Si vous êtes à la recherche d'un professionnel dans les domaines suivants : développement web, illustration, art 3D, ingénierie sonore, montage vidéo, ou autres services créatifs, n'hésitez pas à me contacter.

Pour me joindre :
Instagram : https://www.instagram.com/yndweezy/
Chaîne YouTube : https://www.youtube.com/@YNDWeezy




Voici ce que j'ai développé jusqu'à présent.

Page d'Accueil : Elle inclut une section "Tendances" affichant les vidéos les plus visionnées, ainsi qu'une catégorie "Ajouts Récents" pour les nouvelles vidéos.

Fonctionnalités d'Utilisateur :

Connexion et Inscription : 
Le site intègre un modal (fenêtre pop-up) pour la connexion et l'inscription, qui est déjà opérationnel.
Page Utilisateur : Chaque utilisateur dispose de sa propre page. Cette page inclut un système de suivi avec les options "Followers" (abonnés) et "Following" (abonnements), ainsi que l'affichage des "Likes" reçus sur l'ensemble de ses vidéos.
Lecteur Vidéo : Inspiré de Netflix mais personnalisé selon mes idées, ce lecteur vidéo comprend plusieurs fonctionnalités supplémentaires :

Système de Commentaires : Les utilisateurs peuvent mentionner d'autres personnes, répondre pour créer un fil de discussion, et "liker" des commentaires ou réponses.
Emoji : Un système d'emoji est également intégré au système de commentaires.


Pour voir à quoi ressemble le projet : https://discord.gg/KR6ZpbWXk8


IMPORTANT!
Il faut créer les dossiers "banner", "upload", "users". Le dossier miniature est déjà présent.


CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL, -- pour stocker le mot de passe crypté
    subscribers_count INT DEFAULT 0,     -- nombre d'abonnés
    subscriptions_count INT DEFAULT 0    -- nombre d'abonnements
);
ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255);
ALTER TABLE users ADD COLUMN profile_banner VARCHAR(255);

CREATE TABLE videos (
    video_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,   -- l'utilisateur qui a téléchargé la vidéo
    title VARCHAR(255) NOT NULL,
    description TEXT,
    thumbnail VARCHAR(255),   -- URL ou chemin vers la miniature
    video_path VARCHAR(255) NOT NULL,  -- URL ou chemin du fichier vidéo
    upload_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);
ALTER TABLE videos ADD views_count INT DEFAULT 0;
ALTER TABLE videos ADD video_code VARCHAR(11) UNIQUE NOT NULL;

CREATE TABLE likes (
    like_id INT PRIMARY KEY AUTO_INCREMENT,
    video_id INT,
    user_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (video_id) REFERENCES videos(video_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE comments (
    comment_id INT PRIMARY KEY AUTO_INCREMENT,
    video_id INT,
    user_id INT,   -- l'utilisateur qui a commenté
    content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (video_id) REFERENCES videos(video_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE comment_replies (
    reply_id INT PRIMARY KEY AUTO_INCREMENT,
    comment_id INT,
    user_id INT,
    content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (comment_id) REFERENCES comments(comment_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE comment_likes (
    like_id INT PRIMARY KEY AUTO_INCREMENT,
    comment_id INT,
    user_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (comment_id) REFERENCES comments(comment_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE reply_likes (
    like_id INT PRIMARY KEY AUTO_INCREMENT,
    reply_id INT,
    user_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reply_id) REFERENCES comment_replies(reply_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);
CREATE TABLE subscribers (
    subscriber_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,     -- l'utilisateur qui est abonné
    channel_id INT,  -- l'utilisateur qui possède la chaîne
    subscribed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (channel_id) REFERENCES users(user_id) ON DELETE CASCADE
);
ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255);
ALTER TABLE users ADD COLUMN profile_banner VARCHAR(255);


CREATE TABLE subscriptions (
    subscription_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,     -- l'utilisateur qui s'est abonné
    channel_id INT,  -- la chaîne à laquelle ils sont abonnés
    subscribed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (channel_id) REFERENCES users(user_id) ON DELETE CASCADE
);
