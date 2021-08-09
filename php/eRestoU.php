<?php

// Force l'affichage des erreurs
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Constantes : les paramètres de connexion au serveur MySQL
define('BD_NAME', 'eRestoU_bd');
define('BD_USER', 'eRestoU_user');
define('BD_PASS', 'eRestoU_pass');
define('BD_SERVER', 'localhost');

// Définit le fuseau horaire par défaut à utiliser. 
date_default_timezone_set('Europe/Paris');

// Définit la date d'aujourd'hui au format AAAAMMJJ
define('DATE_AUJOURDHUI', date('Ymd'));
define('NB_ANNEE_DATE_NAISSANCE', 100);
define('AGE_MINIMUM', 16);

// limites liées aux tailles des champs de la table etudiant
define('LMAX_LOGIN', 8);    // taille max du champ etLogin de la table etudiant
define('LMIN_LOGIN', 4);    // taille min du champ etLogin de la table etudiant
define('LMAX_NOM', 50);     // taille max du champ etNom de la table etudiant
define('LMAX_PRENOM', 80);  // taille max du champ etPrenom de la table etudiant

// longueur maximale du texte d'un commentaire
define('LMAX_COTEXTE', 1000);
// Remarque : cette limite est compatible avec le type du champ coTexte de la table 
// commentaire (TEXT). Un champ de type TEXT peut contenir jusqu'à 65535 caractères


//_______________________________________________________________
/**
 * Termine une session et effectue une redirection vers la page transmise en paramètre
 *
 * Cette fonction utilise :
 *   -   la fonction session_destroy() qui détruit la session existante
 *   -   la fonction session_unset() qui efface toutes les variables de session
 * Elle supprime également le cookie de session
 *
 * Cette fonction est appelée quand l'utilisateur se déconnecte "normalement" et quand une 
 * tentative de piratage est détectée. On pourrait améliorer l'application en différenciant
 * ces 2 situations. Et en cas de tentative de piratage, on pourrait faire des traitements  
 * pour stocker par exemple l'adresse IP, etc.
 * 
 * @param string    URL de la page vers laquelle l'utilisateur est redirigé
 */
function sl_exit_session($page = '../index.php')
{
    session_destroy();
    session_unset();
    $cookieParams = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 86400,
        $cookieParams['path'],
        $cookieParams['domain'],
        $cookieParams['secure'],
        $cookieParams['httponly']
    );
    header("Location: $page");
    exit();
}


//_______________________________________________________________
/**
 *   Affichage de l'entete HTML + entete de la page web (bandeau de titre + menu)
 *
 *   @param  string  $title  Le titre de la page (<head>)
 *   @param  string  $css    Le chemin vers la feuille de style à inclure
 */
function sl_entete($titre, $css)
{

    echo '<!doctype html>',
    '<html lang="fr">',
    '<head>',
    '<meta charset="UTF-8">',
    '<meta name="viewport" content="width=device-width, initial-scale=1.0">',
    '<title>eRestoU | ', $titre, '</title>',
    '<link rel="stylesheet" type="text/css" href="', $css, '">',
    '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css">',
    '</head>',
    '<body>',
    '<main>';
}


//_______________________________________________________________
/**
 *  Génération du menu

 *  @param  string  $prefixe    préfixe à utiliser pour construire les chemins par rapport 
 *                              à la page appelante (., .., etc) 
 *  @param  mixed   $connecte   login de l'utilisateur s'il est authentifié, false sinon 
 */
function sl_menu($titre, $prefixe, $connecte)
{
    echo
    '<header>',
    '<div class="menu">',
    '<input type="checkbox" id="nav-toggle" class="nav-toggle">',
    '<nav>',
    '<ul>',
    '<li><a href="', $prefixe, '/index.php">Accueil</a></li>',
    '<li><a href="', $prefixe, '/php/menu.php">Menus</a></li>', (($connecte)
        ? "<li><a href='{$prefixe}/php/profil.php'>Mon profil</a></li><li><a href='{$prefixe}/php/deconnexion.php'>Déconnexion [{$connecte}]</a></li>"
        : "<li><a href='{$prefixe}/php/connexion.php'>Connexion</a></li>"),
    '</ul>',
    '</nav>',
    '<label for="nav-toggle" class="nav-toggle-label"><span></span></label>',
    '</div>',
    '</header>',
    '<div class="welcome-background">',
    '<h1>', $titre, '</h1>',
    '<a href="http://www.crous-bfc.fr" target="_blank"></a>',
    '<a href="http://www.univ-fcomte.fr" target="_blank"></a>',
    '</div>';
}


//_______________________________________________________________
/**
 *  Génération du pied de page. 
 */
function sl_pied_de_page($prefixe, $connecte)
{
    echo '<footer>',
    '<p>&copy; 2020 sahvanart</p>',
    '<p>',
    '<a href="mailto:sahvanart@gmail.com?subject=Web%20Dev%20Contact"><i class="fa fa-envelope"></i></a>',
    '<a href="https://github.com/sahvanart" target="new_blank"><i class="fa fa-github"></i></a>',
    '<a href="https://twitter.com/sahvanart" target="new_blank"><i class="fa fa-twitter"></i></a>',
    '</p>',
    '</footer>',
    '</main>',
    '</body>',
    '</html>';
}


//___________________________________________________________________
/**
 * Affiche le résultat (succès ou erreur(s)) d'une demande de soumission
 * 
 * En l'absence de soumission, $resultat est égal à FALSE et la fonction n'affiche rien
 * Si soumission d'un formulaire :
 * - en cas de soumission réussie, $resultat est une chaîne non vide
 * - quand la demande de soumission échoue, $resultat est un tableau de chaînes 
 *
 * @param mixed      $resultat   string ou array
 * @param string     $titre      titre du bloc div affiché en cas d'erreur
 */
function sl_afficher_resultat_soumission(
    $resultat,
    $titre = 'Les erreurs suivantes ont été relevées'
) {

    if ($resultat) {
        if (is_array($resultat)) {
            echo '<div class="erreur">', $titre, ' :<ul>';
            foreach ($resultat as $err) {
                echo '<li>', $err, '</li>';
            }
            echo '</ul></div>';
        } else {
            echo '<p class="succes">', $resultat, '</p>';
        }
    }
}

//___________________________________________________________________
/**
 * Vérification des champs de type texte des formulaires
 *
 * @param  string        $texte      texte à vérifier
 * @param  string        $nom        chaîne à ajouter dans celle qui décrit l'erreur
 * @param  array         $erreurs    tableau dans lequel les erreurs sont ajoutées
 * @param  int           $long       longueur maximale du champ correspondant dans la bdd
 */
function sl_verifier_texte($texte, $nom, &$erreurs, $long = -1)
{
    if (empty($texte)) {
        $erreurs[] = "$nom ne doit pas être vide.";
    } elseif (strip_tags($texte) != $texte) {
        // strip_tags renvoie la chaines sans les tags HTML
        $erreurs[] = "$nom ne doit pas contenir de tags HTML";
    } elseif ($long > 0 && mb_strlen($texte, 'UTF-8') > $long) {
        $erreurs[] = "$nom ne peut pas dépasser $long caractères";
    }
}
