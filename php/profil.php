<?php
// ---------------------------Siméon Lorieux -- page réalisée lors de l'examen---------------------------

// chargement des bibliothèques de fonctions
require_once('./eRestoU.php');
require_once('./bibli.php');

// bufferisation des sorties
ob_start();

// démarrage de la session
session_start();

/*------------------------- Etape 1 -----------------------------------
- vérifications :
    - de l'authentification de l'utilisateur
    - des clés reçues dans $_POST
----------------------------------------------------------------------*/

// si l'utilisateur n'est pas déjà authentifié, pas normal
if (!isset($_SESSION['user'])) {
    header('location: ../index.php');
    exit();
}

// contrôle des clés reçues dans $_POST
if (isset($_POST['btnSave'])) {
    sl_controle_parametres('post', array('nom', 'prenom', 'btnSave'))
        or sl_exit_session();
} else if (isset($_POST['btnEnr'])) {
    sl_controle_parametres('post', array('pass1', 'pass2', 'btnEnr'))
        or sl_exit_session();
}

/*------------------------- Etape 2 -----------------------------------
-------------------- traitement des soumissions -----------------------
---------------------------------------------------------------------*/
// ouverture de la connexion
$conn = connecter();

// si formulaire de saisie du nom et du prénom soumis, vérification des données 
if (isset($_POST['btnSave'])) {
    $resultat_form_id = sll_changer_id($conn);
} else {
    $resultat_form_id = false;
}

// vérification des données si soumission du formulaire de changement de mdp
if (isset($_POST['btnEnr'])) {
    $resultat_form_pass = sll_changer_mdp($conn);
} else {
    $resultat_form_pass = false;
}

// Récupération des statistiques de l'utilisateur dans la bdd
$statistiques = sll_recup_stat($conn);

// fermeture de la connexion au serveur de base de données
$conn->close();

/*------------------------- Etape 3 -----------------------------------
---------------------- génération de la page --------------------------
---------------------------------------------------------------------*/

// affichage de la page profil.php
sl_entete('Mon profil', '../styles/eResto.css');

sl_menu('Mon profil', '..', $_SESSION['user']['login']);

// se reporter aux définitions des fonctions 
sll_affich_id($resultat_form_id, $statistiques);
sll_affich_mdp($resultat_form_pass);
sll_affich_stat($statistiques);

sl_pied_de_page('..', $_SESSION['user']['login']);

// fin du script --> envoi de la page 
ob_end_flush();


// ----------------- fonctions locales à la page profil.php --------------

function sll_affich_id($resultat_form_id, $statistiques)
{


    echo
    '<section>',
    '<h3 id="id">Modification de vos nom et prénom</h3>',
    '<p>Pour les changer, remplissez le formulaire ci-dessous.</p>',
    '<form action="profil.php#id" method="post">';

    sl_afficher_resultat_soumission($resultat_form_id);

    echo           '<table>',
    '<tr>',
    '<td><label for="txtNom">Votre nom :</label></td>',
    '<td><input type="text" name="nom" id="txtNom" value="', $statistiques['nom'], '"></td>',
    '</tr>',
    '<tr>',
    '<td><label for="txtPrenom">Votre prénom :</label></td>',
    '<td><input type="text" name="prenom" id="txtPrenom" value="', $statistiques['prenom'], '"></td>',
    '</tr>',
    '<tr>',
    '<td colspan="2">',
    '<input type="submit" name="btnSave" value="Enregistrer">',
    '<input type="reset" value="Annuler">',
    '</td>',
    '</tr>',
    '</table>',
    '</form>',
    '</section>';
}

function sll_affich_mdp($resultat_form_pass)
{

    $pass1 = '';
    $pass2 = '';
    echo
    '<section>',
    '<h3 id="mdp">Modification de votre mot de passe</h3>',
    '<p>Pour changer votre mot de passe, remplissez le formulaire ci-dessous.</p>',
    '<form action="profil.php#mdp" method="post">';

    sl_afficher_resultat_soumission($resultat_form_pass);

    echo        '<table>',
    '<tr>',
    '<td><label for="txtPass1">Choisissez un nouveau mot de passe :</label></td>',
    '<td><input type="password" name="pass1" id="txtPass1" value="', $pass1, '"></td>',
    '</tr>',
    '<tr>',
    '<td><label for="txtPass2">Répétez le mot de passe :</label></td>',
    '<td><input type="password" name="pass2" id="txtPass2" value="', $pass2, '"></td>',
    '</tr>',
    '<tr>',
    '<td colspan="2"  style="text-align:center">',
    '<input type="submit" name="btnEnr" value="Enregistrer">',
    '</td>',
    '</tr>',
    '</table>',
    '</form>',
    '</section>';
}

function sll_affich_stat($statistiques)
{


    echo
    '<section>',
    '<h3>Vos statistiques</h3>',
    '<table>',
    '<tr>',
    '<td>Nombre de repas pris :</td>',
    '<td>', $statistiques['nbRepas'], '</td>',
    '</tr>',
    '<tr>',
    '<td>Nombre de repas commentés :</td>',
    '<td>', $statistiques['nbCom'], '</td>',
    '</tr>',
    '<tr>',
    '<td>Moyenne des notes :</td>',
    '<td>', round($statistiques['moyNotes'], 1), '</td>',
    '</tr>',
    '<tr>',
    '<td>Pourcentage de repas commentés :</td>',
    '<td>', round(($statistiques['nbCom'] / $statistiques['nbRepas']) * 100, 1),
    ' %</td>',
    '</tr>',
    '</table>',
    '</section>';
}

/**
 * Fonction qui traite la demande de modification du nom et du prénom
 *
 * @param   objet   $conn   objet représentant la connexion au serveur MySQL
 * @return  mixed   $resultat_form_id   un tableau de chaînes en cas d'erreur, une chaîne non vide sinon
 */
function sll_changer_id($conn)
{

    $resultat_form_id = array();

    // vérification du nom et prénom
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);

    // et pas trop long, selon la taille autorisée dans la bdd
    sl_verifier_texte($nom, 'Le nom', $resultat_form_id, LMAX_NOM);
    sl_verifier_texte($prenom, 'Le prénom', $resultat_form_id, LMAX_PRENOM);

    // si erreurs --> retour avec le tableau contenant les erreurs
    if (count($resultat_form_id) > 0) {
        return $resultat_form_id;   // fin de la fonction
    }

    // sinon, on peut procéder à la mise à jour du nom et prénom de l'utilisateur
    // on commence par protéger les données à destination de la bdd
    $nom = proteger_entree($conn, $_POST['nom']);
    $prenom = proteger_entree($conn, $_POST['prenom']);


    $sql = "UPDATE etudiant 
            SET etNom = '{$nom}', etPrenom = '{$prenom}'
            WHERE etNumero = {$_SESSION['user']['numero']}";

    $resultat_form_id = 'Votre nom et votre prénom ont été mis à jour avec succès.';

    $conn->query($sql) or bd_erreur($conn, $sql);

    return $resultat_form_id;
}

/**
 * Fonction qui traite la demande de modification du mot de passe
 *
 * @param   objet   $conn   objet représentant la connexion au serveur MySQL
 * @return  mixed   $resultat_form_pass   un tableau de chaînes en cas d'erreur, une chaîne non vide sinon
 */
function sll_changer_mdp($conn)
{
    $resultat_form_pass = array();

    // vérification du nom et prénom
    $pass1 = $_POST['pass1'];
    $pass2 = $_POST['pass2'];

    if (empty($pass1) || empty($pass2)) {
        $resultat_form_pass[] = 'Les mots de passe ne doivent pas être vides.';
    } else if ($pass1 !== $pass2) {
        $resultat_form_pass[] = 'Les mots de passe doivent être identiques.';
    }

    // si erreurs --> retour avec le tableau contenant les erreurs
    if (count($resultat_form_pass) > 0) {
        return $resultat_form_pass;   // fin de la fonction
    }

    // sinon on peut procéder à la modification du mdp
    // calcul du hash du mot de passe pour enregistrement dans la bdd
    $passe = password_hash($pass1, PASSWORD_DEFAULT);

    // protection de la chaine générée
    $passe = proteger_entree($conn, $passe);

    $sql = "UPDATE etudiant 
    SET etMotDePasse = '{$passe}'
    WHERE etNumero = {$_SESSION['user']['numero']}";

    $resultat_form_pass = 'Votre mot de passe a été mis à jour avec succès.';

    $conn->query($sql) or bd_erreur($conn, $sql);

    return $resultat_form_pass;
}


function sll_recup_stat($conn)
{

    $stats = array(
        "nbRepas"         => '',
        "nbCom"           => '',
        "moyNotes"        => '',
        "nom"             => '',
        "prenom"          => ''
    );

    $sql = "SELECT etNom, etPrenom, COUNT(DISTINCT reDate) as nbRepas, 
            COUNT(DISTINCT coDateRepas) as nbCom, AVG(coNote) as moy
            FROM etudiant, repas, commentaire
            WHERE etNumero = reEtudiant AND etNumero = coEtudiant
            AND reEtudiant = {$_SESSION['user']['numero']}";

    $res = $conn->query($sql) or bd_erreur($conn, $sql);
    $tab = $res->fetch_assoc();
    $res->free();

    $stats['nom'] = proteger_sortie($tab['etNom']);
    $stats['prenom'] = proteger_sortie($tab['etPrenom']);
    $stats['nbRepas'] = (int) proteger_sortie($tab['nbRepas']);
    $stats['nbCom'] = (int) proteger_sortie($tab['nbCom']);
    $stats['moyNotes'] = (float) proteger_sortie($tab['moy']);

    return $stats;
}
