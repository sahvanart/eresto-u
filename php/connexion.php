<?php

require_once('./eRestoU.php');
require_once('./bibli.php');

// bufferisation des sorties
ob_start();

// démarrage de la session
session_start();

// si l'utilisateur est déjà authentifié on le renvoie vers l'acceuil
if (isset($_SESSION['user'])) {
    header('location: ../index.php');
    exit();
}

// si formulaire soumis, vérification des infos de connexion
if (isset($_POST['btnConnexion'])) {
    $erreur = sll_traitement_connexion();
} else {
    $erreur = false;
}

// génération de la page
sl_entete('Connexion', '../styles/eResto.css');

sl_menu('Connexion', '..', false); // false car cette page n'est pas accessible si connecté

sll_contenu($erreur);

sl_pied_de_page('..', false);

ob_end_flush();


/**
 * Contenu de la page : affichage du formulaire de connexion. 
 *
 * En absence de soumission, $erreur est égal à FALSE
 * Quand l'authentification échoue, $erreur est égal à TRUE  
 *
 *  @param bool    $erreur
 */
function sll_contenu($erreur)
{

    $login = (isset($_POST['login'])) ? proteger_sortie($_POST['login']) : '';
    if ($erreur) {
        // conservation de la même valeur dans l'input de type hidden
        $source = $_POST['redirection'];
    } else {
        // $_SERVER['HTTP_REFERER'] n'est pas défini quand l'utilisateur saisit directement 
        // l'url de la page dans la barre d'adresse
        $source = isset($_SERVER['HTTP_REFERER'])
            ? $_SERVER['HTTP_REFERER']
            : '../index.php';
    }

    echo
    '<section>',
    '<h3>Formulaire de connexion</h3>',
    '<p>Pour vous identifier, remplissez le formulaire ci-dessous.</p>',
    '<form action="connexion.php" method="post">', ($erreur ? '<p class="erreur">&Eacute;chec d\'authentification. Utilisateur inconnu ou mot de passe incorrect.</p>' : ''),
    '<table>',
    '<tr>',
    '<td><label for="txtPseudo">Login ENT :</label></td>',
    '<td><input type="text" name="login" id="txtPseudo" value="', $login, '"></td>',
    '</tr>',
    '<tr>',
    '<td><label for="txtPassword">Mot de passe :</label></td>',
    '<td><input type="password" name="password" id="txtPassword"></td>',
    '</tr>',
    '<tr>',
    '<td colspan="2">',
    '<input type="submit" name="btnConnexion" value="Se connecter">',
    '<input type="reset" value="Annuler">',
    '</td>',
    '</tr>',
    '</table>',
    '<input type="hidden" name="redirection" value="', $source, '">',
    '</form>',
    '<p>Pas encore inscrit ? N\'attendez pas, <a href="inscription.php">inscrivez-vous</a> !</p>',
    '<p>Mot de passe oublié ? Réinitialisez-le, <a href="reinititmdp.php">maintenant</a> !</p>',
    '</section>';
}


/**
 *  Traitement d'une demande d'authentification 
 *
 *  @return true    si une erreur d'authentification a eu lieu
 */
function sll_traitement_connexion()
{

    /* Toutes les erreurs détectées ci dessous qui nécessitent une modification du code 
    HTML sont considérées comme des tentatives de piratage et donc entraînent l'appel 
    de la fonction sl_exit_session() */

    if (!sl_controle_parametres(
        'post',
        array('login', 'password', 'btnConnexion', 'redirection')
    )) {
        sl_exit_session();
    }

    // vérification de $_POST['redirection']
    if (strip_tags($_POST['redirection']) != $_POST['redirection']) {
        sl_exit_session();
    }
    if (!filter_var($_POST['redirection'], FILTER_VALIDATE_URL)) {
        // filter_var() renvoie la variable si elle correspond au filtre 
        // sinon elle renvoie false
        sl_exit_session();
    }
    $t = parse_url($_POST['redirection']);
    // parse_url renvoie un tableau associatif avec pour clés les éléments d'un URL 
    // (voir documentation) et pour valeurs associées, les éléments trouvés dans l'url

    $name = basename($t['path']);
    // la clé path contient le chemin des répertoires sur le serveur jusqu'au fichier
    // demandé dans l'url. basename() renvoie la partie fichier du chemin d'accès

    if (!(file_exists($name) || $name == 'index.php')) {
        sl_exit_session();
    }

    // Vérifications terminées, ouverture de la connexion à la base 
    $co = connecter();

    // nettoyage des données saisies, le pseudo va être envoyé à la bdd
    $pseudo = proteger_entree($co, trim($_POST['login']));

    // création de la requête SQL
    // on cherche s'il existe un étudiant dans la bdd correspondant au pseudo entré
    $sql =
        "SELECT etLogin, etNumero, etMotDePasse 
    FROM etudiant 
    WHERE etLogin = '{$pseudo}'";

    // envoi de la requête, si échec, appel de la fonction bd_erreur qui va afficher le 
    // problème et la requête qui a provoqué l'erreur
    $res = $co->query($sql) or bd_erreur($co, $sql);
    // $co->query($sql) : syntaxe objet, autre possibilité : mysqli_query($co, $sql)

    if ($res->num_rows == 0) { // si le pseudo n'est pas trouvé dans la bdd
        // libération des ressources
        $res->free();
        // fermeture de la connexion à la base de données
        $co->close();
        return true;
    }

    // stockage du résultat de la requête 
    $tab = $res->fetch_assoc();

    // libération des ressources
    $res->free();

    // vérification du mot de passe 
    if (!password_verify($_POST['password'], $tab['etMotDePasse'])) {
        // fermeture de la connexion à la base de données
        $co->close();
        return true;
    }

    // enregistrement de la variable de session (un tableau associatif)
    $_SESSION['user'] = array('login' => $tab['etLogin'], 'numero' => $tab['etNumero']);

    // fermeture de la connexion à la base de données
    $co->close();

    // redirection sur la page d'origine
    header("location: {$_POST['redirection']}");
    exit(); // à ne pas oublier
}
