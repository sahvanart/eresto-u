<?php

/* ------------------------------------------------------------------------------
    Architecture de la page
    - étape 1 : vérification diverses
    - étape 2 : traitement des soumissions
    - étape 3 : génération du code HTML de la page
    
    Cette page est toujours appelée avec la méthode POST
    Le tableau $_POST reçu contient toujours la date du commentaire à ajouter 
    ou modifier
------------------------------------------------------------------------------*/

// chargement des bibliothèques de fonctions
require_once('./eRestoU.php');
require_once('./bibli.php');

// bufferisation des sorties
ob_start();

// démarrage de la session
session_start();

/*------------------------- Etape 1 --------------------------------------------
- vérification :
    - de l'authentification de l'utilisateur
    - des clés reçues dans $_POST
    - si l'utilisateur a bien passé une commande ce jour-là
------------------------------------------------------------------------------*/

// si l'utilisateur n'est pas déjà authentifié
if (!isset($_SESSION['user'])) {
    header('location: ../index.php');
    exit();
}

// Si un utilisateur authentifié tente d'accéder "directement" à cette page en utilisant 
// la méthode GET
if (count($_POST) == 0) {
    sl_entete('Commentaires', '../styles/eResto.css');
    sl_menu('Commentaires', '..', $_SESSION['user']['login']);

    echo    '<h4 style="text-align: center;">Erreur</h4>',
    '<p style="min-height: 300px;">',
    'Cette page ne peut pas être demandée "directement" ',
    '(c\'est à dire par la méthode GET pour les spécialistes).<br>',
    'Vous pouvez y accéder à partir des liens de la page menu.php.',
    '</p>';

    sl_pied_de_page('..', $_SESSION['user']['login']);
    ob_end_flush();
    exit();
}

// contrôle des clés reçues dans $_POST
if (isset($_POST['btnAjouter'])) {
    sl_controle_parametres('post', array('date', 'texte', 'note', 'btnAjouter'))
        or sl_exit_session();
} else if (isset($_POST['btnEnregistrer'])) {
    sl_controle_parametres('post', array('date', 'texte', 'note', 'btnEnregistrer'))
        or sl_exit_session();
} else if (isset($_POST['btnUpload'])) {
    sl_controle_parametres('post', array('MAX_FILE_SIZE', 'btnUpload', 'date'))
        or sl_exit_session();
} else { // on vient de la page menu.php
    sl_controle_parametres('post', array('date')) or sl_exit_session();
}

// vérification de la date, si un des test des faux, la condition sera vraie, déconnexion 
if (!(isset($_POST['date']) && estEntier($_POST['date']) && estEntre(
    $_POST['date'],
    20200101,
    DATE_AUJOURDHUI
))) {
    sl_exit_session();
}

$_POST['date'] = (int) $_POST['date'];


// ouverture de la connexion
$conn = connecter();

// une seule requête pour vérifier si l'étudiant a commandé un repas à la date reçue dans 
// $_POST et pour récupérer le texte et la note d'un éventuel commentaire déjà enregistré
// ATTENTION : les données sont lues dans la base avant le traitement des soumissions 
$sql = "SELECT DISTINCT coNote, coTexte FROM repas 
        LEFT OUTER JOIN commentaire ON (reDate=coDateRepas AND reEtudiant = coEtudiant) 
        WHERE reEtudiant={$_SESSION['user']['numero']} AND reDate={$_POST['date']}";

$res = $conn->query($sql) or bd_erreur($conn, $sql);

if ($res->num_rows == 0) { // si l'étudiant n'a pas commandé de repas ce jour-là 
    $res->free();
    $conn->close();
    sl_exit_session(); // tentative de piratage ?
}

$data = $res->fetch_assoc();
$res->free();


/*------------------------- Etape 2 -----------------------------------
-------------------- traitement des soumissions -----------------------
---------------------------------------------------------------------*/

// si formulaire de saisie du texte et de la note soumis, vérification des données 
if (isset($_POST['btnAjouter']) || isset($_POST['btnEnregistrer'])) {
    $resultat_form1 = sll_traitement_enregistrement($conn);
} else {
    $resultat_form1 = FALSE;
}

// vérification des données si soumission du formulaire d'upload de photo
if (isset($_POST['btnUpload'])) {
    $resultat_form_photo = sll_verification_image($conn);
} else {
    $resultat_form_photo = FALSE;
}

// fermeture de la connexion au serveur de base de données
$conn->close();


/*------------------------- Etape 3 -----------------------------------
---------------------- génération de la page --------------------------
---------------------------------------------------------------------*/

// affichage de l'entête
sl_entete('Commentaires', '../styles/eResto.css');

sl_menu('Commentaires', '..', $_SESSION['user']['login']);

sll_contenu($data, $resultat_form1, $resultat_form_photo);

sl_pied_de_page('..', $_SESSION['user']['login']);

// fin du script --> envoi de la page 
ob_end_flush();


/**
 *  Contenu de la page : affichage du formulaire d'ajout/édition d'un commentaire
 *
 *  En cas d'édition d'un commentaire, l'utilisateur a la possibilté d'uploader une photo
 *
 *  En absence de soumission, $resultat_xxx est égal à FALSE
 *  Si soumission d'un formulaire :
 *      - en cas de soumission réussie, $resultat_xxx est une chaîne non vide
 *      - sinon, $resultat_xxx est un tableau de chaînes
 *
 *  @param array    $data                   texte et note du commentaire récupérés en bdd 
 *                                          (utilisées lors du 1er affichage)
 *  @param mixed    $resultat_form1         un tableau de chaînes, une chaîne ou FALSE 
 *                                          (résultat du formulaire de saisie du texte + note)
 *  @param mixed    $resultat_form_photo    un tableau de chaînes, une chaîne ou FALSE 
 *                                          (résultat du formulaire d'upload de la photo)
 */
function sll_contenu($data, $resultat_form1, $resultat_form_photo)
{

    echo    '<p>Pour revenir au menu sujet du commentaire, cliquez <a href="menu.php?jour=',
    substr($_POST['date'], 6, 2), '&mois=', substr($_POST['date'], 4, 2), '&annee=',
    substr($_POST['date'], 0, 4), '#commentaires">ici</a>.</p>';

    if ($data['coTexte'] === NULL && !is_string($resultat_form1)) {
        // l'utilisateur n'a pas enregistré de commentaire, utilisation du triple = avec null
        // l'ajout de la condition ! is_string($resultat_form1) est nécessaire car les données 
        // sont lues en bdd AVANT le traitement de l'éventuelle soumission du formulaire 
        // de saisie du texte et de la note
        $data['coTexte'] = '';
        $data['coNote'] = 5;
        $nomBtnSubmit = 'btnAjouter';
    } else {
        $nomBtnSubmit = 'btnEnregistrer';
    }

    // si soumission, on réaffiche les données soumises (contenant éventuellement des erreurs)
    if (isset($_POST['btnAjouter']) || isset($_POST['btnEnregistrer'])) {
        $data = array('coNote' => $_POST['note'], 'coTexte' => $_POST['texte']);
    }

    // protection des données du tableau pour affichage
    $data = proteger_sortie($data);

    echo '<section>',
    '<h3>', (($nomBtnSubmit == 'btnAjouter') ? 'Ajout' : 'Edition'),
    ' d\'un commentaire</h3>',
    '<form action="commentaire.php" method="POST">';

    sl_afficher_resultat_soumission($resultat_form1); // n'affiche rien si pas de soumission

    echo     '<table>',
    '<tr>',
    '<td><label for="txtCommentaire">Texte du commentaire :</label></td>',
    '<td><textarea name="texte" id="txtCommentaire">', $data['coTexte'],
    '</textarea></td>',
    '</tr>',
    '<tr>',
    '<td>Note du repas :</td>',
    '<td>';

    sl_creer_select(
        'note',
        array(
            '1' => 'Nul (1/5)', '2' => 'Bof (2/5)', '3' => 'Moyen (3/5)',
            '4' => 'Pas mal (4/5)', '5' => 'Super (5/5)'
        ),
        $data['coNote']
    );

    echo        '</td>',
    '</tr>',
    '<tr>',
    '<td>',
    '<input type="submit" name="', $nomBtnSubmit, '" value="Enregistrer">',
    '</td>',
    '<td>',
    '<input type="reset" value="Annuler">',
    '</td>',
    '</tr>',
    '</table>',
    '<input type="hidden" name="date" value="', $_POST['date'], '">',
    '</form>',
    '</section>';


    // si on est en train d'éditer un commentaire, 
    // on ajoute en bas de page la possibilité de changer l'image 
    if ($nomBtnSubmit == 'btnEnregistrer') {

        echo '<h3 id="photo">Photo d\'illustration</h3>';


        sl_afficher_resultat_soumission($resultat_form_photo);
        //n'affiche rien si pas de soumission


        $file = "../upload/{$_POST['date']}_{$_SESSION['user']['numero']}.jpg";
        if (file_exists($file)) {
            echo '<p>La photo ci-dessous est actuellement associée au commentaire: <br>',
            '<img src="', $file,
            '" alt="Photo actuellement utilisée" style="width: 200px; height: auto;',
            'border: solid 1px #000;">',
            '</p>';
        } else {
            echo '<p>Aucune photo n\'est associée au commentaire.</p>';
        }
        // noter le #photo
        echo '<form action="commentaire.php#photo" method="POST" enctype="multipart/form-data">',
        '<input type="hidden" name="MAX_FILE_SIZE" value="100000">',
        '<p>Les images acceptées sont des fichiers JPG de taille 100 ko maximum.</p>',
        '<input type="hidden" name="date" value="', $_POST['date'], '">',
        '<p>Choisissez un fichier à télécharger : <input type="file" name="uplFichier">',
        '<input type="submit" name="btnUpload" value="Envoyer l\'image"></p>',
        '</form>';
    }
}


/**
 *  Traitement d'une demande d'ajout/modification du texte et de la note d'un commentaire
 *
 *  @param  objet   $conn objet représentant la connexion au serveur MySQL
 *  @return mixed   un tableau de chaînes en cas d'erreur, une chaîne non vide sinon
 */
function sll_traitement_enregistrement($conn)
{

    /* Toutes les erreurs détectées qui nécessitent une modification du code HTML sont 
    considérées comme des tentatives de piratage 
    et donc entraînent l'appel de la fonction sl_exit_session() */

    $err = array();
    $_POST['texte'] = trim($_POST['texte']);
    sl_verifier_texte($_POST['texte'], 'Le texte du commentaire', $err, LMAX_COTEXTE);

    if (!(estEntier($_POST['note']) && estEntre(intval($_POST['note']), 1, 5))) {
        $conn->close();
        sl_exit_session();
    }

    if (count($err) > 0) {
        return $err;
    }

    // com + note valides
    $texte = proteger_entree($conn, $_POST['texte']);
    $_POST['note'] = (int) $_POST['note'];
    $datePublication = DATE_AUJOURDHUI . date('Hi');
    if (isset($_POST['btnEnregistrer'])) {
        $sql = "UPDATE commentaire 
                SET coTexte = '$texte', coNote = {$_POST['note']}, coDatePublication = $datePublication 
                WHERE coEtudiant = {$_SESSION['user']['numero']} AND coDateRepas = {$_POST['date']}";
        $err = 'Commentaire mis à jour avec succès.';
    } else {
        $sql = "INSERT INTO commentaire(coTexte, coEtudiant, coDateRepas, coNote, coDatePublication) 
                VALUES ('$texte', {$_SESSION['user']['numero']}, {$_POST['date']}, {$_POST['note']}, $datePublication)";
        $err = 'Commentaire ajouté avec succès.';
    }

    $conn->query($sql) or bd_erreur($conn, $sql);

    return $err;
}



/**
 *  Traitement d'une demande d' ajout/modification de l'image du commentaire
 *
 *  @param  objet   $conn objet représentant la connexion au serveur MySQL
 *  @return mixed   un tableau de chaînes en cas d'erreur, une chaîne non vide sinon
 */
function sll_verification_image($conn)
{

    // Vérification si erreurs
    $f = $_FILES['uplFichier'];
    switch ($f['error']) {
        case 1: // Value: 1; The uploaded file exceeds the upload_max_filesize directive in php.ini
        case 2: // Value: 2; The uploaded file exceeds the MAX_FILE_SIZE directive that was specified
            // in the HTML form
            return array("Le fichier \"{$f['name']}\" est trop gros.");
            break;
        case 3: // Value: 3; The uploaded file was only partially uploaded
            return  array("Erreur de transfert du fichier \"{$f['name']}\"");
        case 4: // Value: 4; No file was uploaded
            return array('Le fichier est introuvable.');
    }

    /* 
    $f['type'] est déterminé par le navigateur. On ne peut donc pas s'y fier. En effet, il est 
    possible de le tromper simplement en modifiant manuellement l’extension du fichier.
    La fonction mime_content_type() permet de faire un contrôle du type MIME coté serveur.
    Elle retourne 'image/jpeg' pour les fichiers 'jpeg'/'jpg'. 
    */

    $type_mime = mime_content_type($f['tmp_name']);
    if ($f['type'] != 'image/jpeg' || $type_mime != 'image/jpeg') {
        return array("Le fichier \"{$f['name']}\" n'est pas au format JPG.");
    }

    // Pas d'erreur => placement du fichier
    if (!@is_uploaded_file($f['tmp_name'])) {
        return array('Erreur de transfert interne');
    }

    $place = realpath('../upload') . "/{$_POST['date']}_{$_SESSION['user']['numero']}.jpg";
    if (!@move_uploaded_file($f['tmp_name'], $place)) {
        return array('Erreur interne de transfert');
    }

    // mise à jour de la date de publication du commentaire dans la base
    $datePublication = DATE_AUJOURDHUI . date('Hi');
    $sql = "UPDATE commentaire SET coDatePublication = $datePublication 
            WHERE coEtudiant = {$_SESSION['user']['numero']} AND coDateRepas = {$_POST['date']}";

    $conn->query($sql) or bd_erreur($conn, $sql);

    return 'Fichier transféré avec succès.';
}
