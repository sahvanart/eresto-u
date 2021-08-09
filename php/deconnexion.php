<?php

require_once './eRestoU.php'; // syntaxe à privilégier

// démarrage de la session
session_start();

sl_exit_session(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '../index.php');
// $_SERVER['HTTP_REFERER'] peut ne pas exister si l'utilisateur entre directement l'URL
// dans la barre de recherche
