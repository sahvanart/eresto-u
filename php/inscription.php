<?php

require_once('./eRestoU.php');
require_once('./bibli.php');

// bufferisation des sorties
ob_start();

// démarrage de la session
session_start();

// si l'utilisateur est déjà authentifié
if (isset($_SESSION['user'])) {
    header('location: ../index.php');
    exit();
}

// si formulaire soumis, traitement de la demande d'inscription
if (isset($_POST['btnInscription'])) {
    $erreurs = sll_traitement_inscription();
} else {
    $erreurs = FALSE;
}

// génération de la page
sl_entete('Inscription', '../styles/eResto.css');

sl_menu('Inscription', '..', false);

sll_contenu($erreurs);

sl_pied_de_page('..', false);

ob_end_flush();


/**
 * Contenu de la page : affichage du formulaire d'inscription
 *
 * En absence de soumission, $erreurs est égal à FALSE
 * Quand l'inscription échoue, $erreurs est un tableau de chaînes  
 *
 *  @param mixed    $erreurs
 */
function sll_contenu($erreurs)
{

    $anneeCourante = (int) date('Y'); // on aura besoin de l'année à plusieurs endroits

    // une variable pour chaque information requise, si elle exsistent, on les traite, si ce sont des données
    // numériques issues du formulaire, on se contente de les convertir en type int (entier)
    $login = (isset($_POST['login'])) ? proteger_sortie($_POST['login']) : '';
    $nom = (isset($_POST['nom'])) ? proteger_sortie($_POST['nom']) : '';
    $prenom = (isset($_POST['prenom'])) ? proteger_sortie($_POST['prenom']) : '';
    $numero = (isset($_POST['numero'])) ? proteger_sortie($_POST['numero']) : '';
    $jour = (isset($_POST['jour'])) ? (int) $_POST['jour'] : 1;
    $mois = (isset($_POST['mois'])) ? (int) $_POST['mois'] : 1;
    $annee = (isset($_POST['annee'])) ? (int) $_POST['annee'] : $anneeCourante;


    echo
    '<section>',
    '<h3>Formulaire d\'inscription</h3>',
    '<p>Pour vous inscrire, remplissez le formulaire ci-dessous.</p>',
    '<form action="inscription.php" method="post">';

    // n'affiche rien si pas de soumission ($erreurs vaut false)
    sl_afficher_resultat_soumission($erreurs, 'Les erreurs suivantes ont été relevées lors de votre inscription');

    echo '<table>',
    '<tr>',
    '<td><label for="txtLogin">Entrez votre login étudiant :</label></td>',
    '<td><input type="text" name="login" id="txtLogin" value="', $login, '"></td>',
    '</tr>',
    '<tr>',
    '<td><label for="txtNumero">Votre numéro étudiant :</label></td>',
    '<td><input type="text" name="numero" id="txtNumero" value="', $numero, '"></td>',
    '</tr>',
    '<tr>',
    '<td><label for="txtNom">Votre nom :</label></td>',
    '<td><input type="text" name="nom" id="txtNom" value="', $nom, '"></td>',
    '</tr>',
    '<tr>',
    '<td><label for="txtPrenom">Votre prénom :</label></td>',
    '<td><input type="text" name="prenom" id="txtPrenom" value="', $prenom, '"></td>',
    '</tr>',
    '<tr>',
    '<td>Votre date de naissance :</td>',
    '<td>';
    sl_creer_liste_nombre('jour', 1, 31, 1, $jour);
    sl_creer_liste_mois('mois', $mois);
    sl_creer_liste_nombre('annee', $anneeCourante - NB_ANNEE_DATE_NAISSANCE + 1, $anneeCourante, -1, $annee);
    echo        '</td>',
    '</tr>',
    '<tr>',
    '<td><label for="txtPassword1">Choissiez un mot de passe :</label></td>',
    '<td><input type="password" name="passe1" id="txtPassword1"></td>',
    '</tr>',
    '<tr>',
    '<td><label for="txtPassword2">Répétez le mot de passe :</label></td>',
    '<td><input type="password" name="passe2" id="txtPassword2"></td>',
    '</tr>',
    '<tr>',
    '<td>',
    '<input type="submit" name="btnInscription" value="S\'inscrire">',
    '</td><td>',
    '<input type="reset" value="Réinitialiser">',
    '</td>',
    '</tr>',
    '</table>',
    '</form>',
    '</section>';
}


/**
 *  Traitement d'une demande d'inscription 
 *  
 *  Si l'inscription réussit, un nouvel enregistrement est ajouté dans la table utilisateur, 
 *  la variable de session $_SESSION['user'] est créée et l'utilisateur est redirigé vers la
 *  page index.php
 *
 *  @return array    un tableau contenant les erreurs s'il y en a
 */
function sll_traitement_inscription()
{

    /* Toutes les erreurs détectées ci dessous qui nécessitent une modification du code 
    HTML sont considérées comme des tentatives de piratage et donc entraînent l'appel 
    de la fonction sl_exit_session() */

    if (!sl_controle_parametres(
        'post',
        array(
            'login', 'nom', 'prenom', 'jour', 'mois', 'annee', 'passe1', 'passe2',
            'numero', 'btnInscription'
        )
    )) {
        sl_exit_session();
    }

    $erreurs = array();

    // vérification du pseudo
    $login = trim($_POST['login']);

    // Définition de l'encodage des caractères pour les expressions rationnelles 
    // multi-octets (lettres accentuées)
    mb_regex_encoding('UTF-8');

    if (!mb_ereg_match('^[a-zA-Z][a-zA-Z0-9]{' . (LMIN_LOGIN - 1) . ',' . (LMAX_LOGIN - 1) . '}$', $login)) {
        $erreurs[] = 'Le login doit contenir entre ' . LMIN_LOGIN . ' et ' . LMAX_LOGIN .
            ' caractères alphanumériques (lettres sans accents ou chiffres) et' .
            ' commencer par une lettre.';
    }

    // vérification des noms et prénoms
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    sl_verifier_texte($nom, 'Le nom', $erreurs, LMAX_NOM);
    sl_verifier_texte($prenom, 'Le prénom', $erreurs, LMAX_PRENOM);

    // vérification de la date de naissance
    if (!(estEntier($_POST['jour']) && estEntre($_POST['jour'], 1, 31))) {
        sl_exit_session();
    }
    if (!(estEntier($_POST['mois']) && estEntre($_POST['mois'], 1, 12))) {
        sl_exit_session();
    }

    $anneeCourante = (int) date('Y');
    if (!(estEntier($_POST['annee'])
        && estEntre(
            $_POST['annee'],
            $anneeCourante - NB_ANNEE_DATE_NAISSANCE + 1,
            $anneeCourante
        ))) {
        sl_exit_session();
    }

    $jour = (int) $_POST['jour'];
    $mois = (int) $_POST['mois'];
    $annee = (int) $_POST['annee'];

    if (!checkdate($mois, $jour, $annee)) {
        $erreurs[] = 'La date de naissance n\'est pas valide.';
    } else if (mktime(0, 0, 0, $mois, $jour, $annee + AGE_MINIMUM) > time()) {
        // mktime() donne le timestamp qui correspond à la date entrée à 0h0min0s
        // time() donne le timestamp de la date actuelle 
        // si le premier dépasse le second, c'est que l'utilisateur est trop jeune
        $erreurs[] = 'Vous devez avoir au moins ' . AGE_MINIMUM . ' ans pour vous inscrire.';
    }

    // vérification du numéro d'étudiant
    $numero = trim($_POST['numero']);
    mb_regex_encoding('UTF-8');
    if (!mb_ereg_match('^[0-9]{8,9}$', $numero)) {
        $erreurs[] = 'Le numéro d\'étudiant doit faire 8 ou 9 chiffres.';
    }

    // vérification des mots de passe
    $passe1 = $_POST['passe1'];
    $passe2 = $_POST['passe2'];
    if (empty($passe1) || empty($passe2)) {
        $erreurs[] = 'Les mots de passe ne doivent pas être vides.';
    } else if ($passe1 !== $passe2) {
        $erreurs[] = 'Les mots de passe doivent être identiques.';
    }

    // si erreurs --> retour avec le tableau contenant les erreurs
    if (count($erreurs) > 0) {
        return $erreurs;   // FIN DE LA FONCTION
    }

    // on vérifie maintenant si le login et le numéro étudiant ne sont pas encore utilisés 
    // (que si tous les autres champs précédemment vérifiés sont valides) 
    // car ces 2 dernières vérifications coûtent un bras !

    // ouverture de la connexion à la base 
    $co = connecter();

    // vérification de l'existence du pseudo ou de l'email
    $login2 = proteger_entree($co, $login);
    // fait par principe, mais inutile ici car on a déjà vérifié que le login
    // ne contenait que des caractères alphanumériques

    $numero2 = proteger_entree($co, $numero); // idem

    $sql =
        "SELECT etLogin, etNumero FROM etudiant 
    WHERE etLogin = '{$login2}' OR etNumero = '{$numero2}'";

    $res = $co->query($sql) or bd_erreur($co, $sql);

    while ($tab = $res->fetch_assoc()) {
        if ($tab['etLogin'] == $login) {
            $erreurs[] = 'Le login existe déjà.';
        }
        if ($tab['etNumero'] == $numero) {
            $erreurs[] = 'Le numéro d\'étudiant existe déjà.';
        }
    }
    $res->free();

    // si erreurs dans le login et le numéro d'étudiant
    if (count($erreurs) > 0) {
        // fermeture de la connexion à la base de données
        $co->close();
        return $erreurs;   // FIN DE LA FONCTION
    }

    // calcul du hash du mot de passe pour enregistrement dans la base
    $passe = password_hash($passe1, PASSWORD_DEFAULT);

    $passe = proteger_entree($co, $passe);

    // dates dans la bdd sont au format jjmmaaaa
    if ($mois < 10) $mois = '0' . $mois;
    if ($jour < 10) $jour = '0' . $jour;

    $nom = proteger_entree($co, $nom);
    $prenom = proteger_entree($co, $prenom);

    $sql =
        "INSERT INTO etudiant(etLogin, etMotDePasse, etNumero, etNom, etPrenom, etDateNaissance) 
    VALUES ('{$login2}','{$passe}','{$numero2}', '{$nom}', '{$prenom}', {$annee}{$mois}{$jour})";

    $co->query($sql) or bd_erreur($co, $sql);

    // enregistrement dans la variable de session du pseudo (avant passage par la fonction 
    // proteger_entree(), donc $login) car, d'une façon générale, celle-ci risque de 
    // rajouter des antislashs Rappel : ici, elle ne rajoute jamais d'antislash car le 
    // pseudo ne peut contenir que des caractères alphanumériques
    $_SESSION['user'] = array('login' => $login, 'numero' => $numero2);

    // fermeture de la connexion à la base de données
    $co->close();

    // redirection vers la page index.php
    header('location: ../index.php');
    exit(); // Fin du script
}
