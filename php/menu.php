<?php

/* Architecture de la page :
* - étape 1 : traitement des soumisions
* - étape 2 : génération du code HTML de la page
*/

// chargement des bibliothèques de fonctions
require_once('eRestoU.php');
require_once('bibli.php');

// démarrage de la session
session_start();

// bufferisation des sorties
ob_start();


/*------------------------- Etape 1 --------------------------------------------
-------------------- traitement des soumissions -----------------------
Si l'utilisateur a commandé quelque chose, on vérifie sa commande
Traitement d'une éventuelle soumission : à faire en premier pour pouvoir 
réafficher les choix de l'utilisateur en cas d'erreur
------------------------------------------------------------------------------*/

if (isset($_POST['btnCommander']) && !isset($_SESSION['user'])) {
    sl_exit_session(); // ne devrait pas se produire, piratage ?
}

// ouverture de la connexion à la base de données, la fonction connecter() s'occupe
// de la gestion des erreurs de connexion à la base de données
$conn = connecter();


if (isset($_POST['btnCommander'])) {
    $resultat = sll_verifier_commande($conn);
} else {
    $resultat = FALSE;
}

/*------------------------- Etape 2 -----------------------------------
---------------------- génération de la page --------------------------
---------------------------------------------------------------------*/

// affichage de l'entête
sl_entete('Menus', '../styles/eResto.css');
// affichage du menu
sl_menu('Menus  ', '..', isset($_SESSION['user']) ? $_SESSION['user']['login'] : false);

// contenu de la page 
sll_contenu($conn, $resultat);

// fermeture de la connexion
$conn->close();

// affichage du pied de page
sl_pied_de_page('..', isset($_SESSION['user']) ? $_SESSION['user']['login'] : false);

// fin du script --> envoi de la page 
ob_end_flush();



//_______________________________________________________________
/**
 *  Génère le contenu de la page
 *
 *  @param  objet   $conn       connexion à la base de données MySQL
 *  @param  mixed   $resultat   un tableau de chaînes, une chaîne ou FALSE
 */
function sll_contenu($conn, $resultat)
{

    // contrôle des paramètres reçus dans l'URL
    if (!sl_controle_parametres('GET', array(), array('jour', 'mois', 'annee'))) {
        echo '<h4 style="text-align: center;">Erreur dans l\'URL</h4>',
        '<p style="min-height: 300px;">Il faut utiliser une URL de la forme :<br>',
        'http://..../php/menu.php?jour=12&mois=10&annee=2020</p>';
        return;
    }

    // identification de la date du jour, getdate retourne la date avec ses composants
    // disponibles dans un tableau associatif
    $aujourdhui = getdate(mktime(
        12,
        0,
        0,
        substr(DATE_AUJOURDHUI, 4, 2),
        substr(DATE_AUJOURDHUI, 6, 2),
        substr(DATE_AUJOURDHUI, 0, 4)
    ));

    // récupération de la date demandée (si problème, utilisation de la date du jour)
    $mois = (isset($_GET['mois']) && estEntier($_GET['mois'])) ?
        (int) $_GET['mois'] : $aujourdhui['mon'];
    $jour = (isset($_GET['jour']) && estEntier($_GET['jour'])) ?
        (int) $_GET['jour'] : $aujourdhui['mday'];
    $annee = (isset($_GET['annee']) && estEntier($_GET['annee'])) ?
        (int) $_GET['annee'] : $aujourdhui['year'];

    // cas d'erreur sur la date, date incorrecte 
    if (!checkdate($mois, $jour, $annee)) {
        echo '<h4 style="text-align: center;">Menu du ', $jour, '/', $mois, '/', $annee,
        '</h4><p style="min-height: 300px;">La date demandée n\'existe pas.</p>';
        return;
    }

    // date OK
    // Génération de la navigation entre les dates 
    sll_navigation_date($jour, $mois, $annee);

    $date = $annee * 10000 + $mois * 100 + $jour; // on obtient un entier sous la forme aaaammjj


    //-------------- Devoir 3 ----------------------
    $select = isset($_SESSION['user']) ? 'repas.reQuantite, ' : '';
    $jointExt = isset($_SESSION['user']) ?
        "LEFT OUTER JOIN repas ON (reDate={$date} 
    AND rePlat=plID AND reEtudiant={$_SESSION['user']['numero']})" : '';
    //----------------------------------------------

    // Récupération des plats qui sont proposés pour le menu
    $sql =
        "SELECT {$select}plat.* FROM (menu INNER JOIN plat ON mePlat=plID) {$jointExt} 
    WHERE meDate = {$date} 
    UNION 
    SELECT {$select}plat.* FROM plat {$jointExt} 
    WHERE plCategorie = 'divers' OR plCategorie = 'boisson'";
    // on veut récupérer les supplément et les boissons

    // envoi de la requête SQL
    $res = $conn->query($sql) or bd_erreur($conn, $sql);

    // si aucun résultat --> pas de menu pour ce jour
    if ($res->num_rows < 7) {
        // ici on sait que l'on a toujours 6 éléments issus des deux catégories 
        // 'divers' et 'boisson'
        echo '<p>Aucun repas n\'est servi ce jour.</p>';
        // libération des ressources 
        $res->free();
        return;
    }


    // tableau associatif contenant les constituants du menu
    $menu = array(
        "entrees"       => array(),
        "plats"           => array(),
        "accompagnements" => array(),
        "desserts"        => array(),
        "boissons"        => array(),
        "divers"          => array()
    );

    $commande = false;

    // parcours des ressources : 
    while ($tab = $res->fetch_assoc()) {
        if (isset($tab['reQuantite'])) {
            $commande = true;
        }
        switch ($tab['plCategorie']) {
            case 'entree':
                $menu['entrees'][] = $tab;
                break;
            case 'viande':
            case 'poisson':
                $menu['plats'][] = $tab;
                break;
            case 'accompagnement':
                $menu['accompagnements'][] = $tab;
                break;
            case 'dessert':
            case 'fromage':
                $menu['desserts'][] = $tab;
                break;
            case 'boisson':
                $menu['boissons'][] = $tab;
                break;
            default:
                $menu['divers'][] = $tab;
        }
    }
    // libération des ressources 
    $res->free();

    // protection des données du tableau pour affichage
    $menu = proteger_sortie($menu);

    //----------------------------------------------

    // indique s'il faut créer un formulaire pour enregistrer la commande 
    // (utilisateur connecté + jour courant + commande inexistante)
    $inForm = (isset($_SESSION['user']) && !$commande && $date == DATE_AUJOURDHUI);

    // modification pour pouvoir faire des tests (à retirer pour avoir le comportement 
    // normal de l'application) 
    // $inForm = (isset($_SESSION['user']) && ! $commande);

    // message d'information en début de page
    if ($inForm) {
        echo '<p class="notice">Toutes les commandes sont préparées sur des plateaux ',
        'contenant des couverts (couteau, fourchette, petite cuillère) et un verre. </p>',
        '<form method="POST" action="menu.php?jour=', $jour, '&mois=', $mois, '&annee=',
        $annee, '">';
    }

    sl_afficher_resultat_soumission(
        $resultat,
        'Les erreurs suivantes ont été rencontrées durant le traitement de votre commande'
    );

    if ($inForm) { // on va utiliser array_splice pour réaliser une insertion dans $menu
        // le troisième paramètre est à mettre à 0 dans le cas d'une insertion
        // le deuxième paramètre concerne l'indice pour lequel on souhaite insérer
        // le quatrième paramètre est ce qu'on souhaite insérer (ici tableau de tableau)
        array_splice(
            $menu['entrees'],
            0,
            0,
            array(array('plID' => 0, 'plCategorie' => 'entree', 'plNom' => 'Pas d\'entrée'))
        );
        array_splice(
            $menu['plats'],
            0,
            0,
            array(array('plID' => 0, 'plCategorie' => 'viande', 'plNom' => 'Pas de plat'))
        );
        array_splice(
            $menu['desserts'],
            0,
            0,
            array(array('plID' => 0, 'plCategorie' => 'dessert', 'plNom' => 'Pas de fromage/dessert'))
        );
    }

    // tableau des clés du tableau $menu
    $h3 = array(
        'entrees'           => 'Entrées',
        'plats'             => 'Plat',
        'accompagnements'   => 'Accompagnement',
        'desserts'          => 'Fromage/dessert',
        'boissons'          => 'Boisson'
    );

    // affichage du menu
    foreach ($menu as $key => $value) {
        if (!array_key_exists($key, $h3)) {
            continue;
        }
        echo '<section><h3>', $h3[$key], '</h3><div class="flexdiv">';
        foreach ($value as $p) {
            sll_afficher_plat($p, $inForm);
        }
        echo '</div></section>';
    }

    // ajout des suppléments ("divers"), uniquement si on fait une saisie de la commande
    if ($inForm) {
        echo '<section><h3>Suppléments</h3><div class="flexdiv">';
        foreach ($menu['divers'] as $d) {
            sll_afficher_divers($d);
        }
        echo '</div></section><section><h3>Validation de la commande</h3>',
        '<p class="attention">Toute commande passée qui ne sera pas récupérée sera ',
        'facturée à l\'étudiant la somme forfaitaire de 20 euros.</p>',
        '<p style="text-align:center;">',
        '<input type="submit" name="btnCommander" value="Commander">',
        '<input type="reset" name="btnAnnuler" value="Annuler">',
        '</p>',
        '</section></form>';
    }

    // affichage des commentaires 
    if ($date <= DATE_AUJOURDHUI) sll_afficher_commentaires($conn, $date, $commande);
}

//_______________________________________________________________
/**
 *  Génération de la navigation entre les dates
 * 
 *  @param  int     $jour   jour de la date consultée
 *  @param  int     $mois   mois de la date consultée
 *  @param  int     $annee  année de la date consultée
 */
function sll_navigation_date($jour, $mois, $annee)
{

    // on détermine le jour précédent (ni samedi, ni dimanche)
    $jj = 0;
    do {
        $jj--;
        $dateVeille = getdate(mktime(12, 0, 0, $mois, $jour + $jj, $annee));
    } while ($dateVeille['wday'] == 0 || $dateVeille['wday'] == 6);
    // 0 : dimanche, 6 : samedi
    // on détermine le jour suivant (ni samedi, ni dimanche)
    $jj = 0;
    do {
        $jj++;
        $dateDemain = getdate(mktime(12, 0, 0, $mois, $jour + $jj, $annee));
    } while ($dateDemain['wday'] == 0 || $dateDemain['wday'] == 6);

    $dateJour = getdate(mktime(12, 0, 0, $mois, $jour, $annee));
    $jourSemaine = array(
        "Dimanche", "Lundi", "Mardi", "Mercredi", "Jeudi", "Vendredi",
        "Samedi"
    );

    // affichage de la navigation pour choisir le jour affiché
    echo '<h2>',
    $jourSemaine[$dateJour['wday']], ' ',
    $jour, ' ',
    get_tableau_mois()[$dateJour['mon'] - 1], ' ',
    $annee,
    '</h2>',

    // on utilise un formulaire qui renvoie sur la page courante avec une méthode GET  
    // pour faire apparaître les 3 paramètres sur l'URL
    '<form action="menu.php" method="GET" style="text-align: center;">',
    '<a href="menu.php?jour=', $dateVeille['mday'], '&mois=', $dateVeille['mon'],
    '&annee=',  $dateVeille['year'], '" class="bouton" style="float: left;">Jour précédent</a>',
    '<a href="menu.php?jour=', $dateDemain['mday'], '&mois=', $dateDemain['mon'],
    '&annee=', $dateDemain['year'], '" class="bouton" style="float: right;">Jour suivant</a>',
    'Date : ';
    sl_creer_liste_nombre('jour', 1, 31, 1, $jour);
    sl_creer_liste_mois('mois', $mois);
    sl_creer_liste_nombre('annee', 2021, 2023, 1, $annee);
    echo '<input type="submit" value="Consulter" style="padding: 2px;">',
    '</form>';

    // le bouton submit n'a pas d'attribut name, => il n'y a pas d'élément 
    // correspondant transmis dans l'URL lors de la soumission du formulaire. Ainsi, l'URL de 
    // la page a toujours la même forme (http://.../php/menu.php?jour=12&mois=10&annee=2020) 
    // quelque soit le moyen de navigation utilisé (formulaire avec bouton 'Consulter', 
    // ou lien 'précédent' ou 'suivant')
}


//_______________________________________________________________
/**
 *  Affichage d'un des constituants du menu
 * 
 *  @param  array       $i      tab asso contenant les infos du plat en cours d'affichage
 *  @param  boolean     $inForm affichage est réalisé dans un form de saisie de commande
 */
function sll_afficher_plat($i, $inForm = false)
{

    // on utilise ici le même schéma quelque soit le type d'affichage : 
    //  - un bouton radio/une case à cocher qui sera :
    //          -- sélectionné si l'utilisateur a déjà commandé ce produit,
    //          -- désactivée si on est pas dans un formulaire de commande 
    //  - un label qui affiche l'image du plat et son libellé

    // utilisé pour les boutons radio
    $categorie2name = array(
        'entree' => 'radEntree',
        'viande' => 'radPlat',
        'poisson' => 'radPlat',
        'dessert' => 'radDessert',
        'fromage' => 'radDessert',
        'boisson' => 'radBoisson'
    );

    if (array_key_exists($i['plCategorie'], $categorie2name)) { // bouton radio
        $name = $categorie2name[$i['plCategorie']];
        $id = "{$name}{$i['plID']}";
        $type = 'radio';
    } else { // checkbox
        $id = $name = "cb{$i['plID']}";
        $type = 'checkbox';
    }

    $disabled = !$inForm;
    // si on trouve une quantité => commande a été passée et existe dans la bdd,
    // ou bien si la commande vient d'être passée
    $checked = (!$inForm && isset($i['reQuantite'])) ||
        (isset($_POST[$name]) && $_POST[$name] == $i['plID']); // 

    echo '<input id="', $id, '" name="', $name, '" type="', $type, '" value="', $i['plID'], '"', ($disabled ? ' disabled' : ''), ($checked ? ' checked' : ''), '>',
    '<label class="plat" for="', $id, '">',
    '<img src="../images/repas/', $i['plID'], '.jpg" alt="', $i['plNom'], '" title="', $i['plNom'], '">',
    $i['plNom'], '</label>';
}

//_______________________________________________________________________________
/**
 *  Génère la zone de saisie qui correspond aux suppléments
 *  Pour ceux-ci on génère un label et une zone de saisie de nombre entre 0 et 2
 *
 *  @param  array   $i   tab asso contenant les infos du supplément en cours d'affichage  
 */
function sll_afficher_divers($i)
{
    // en cours de commande, si erreur, on réaffiche le choix du supplément, sinon 0
    $value = isset($_POST["num{$i['plID']}"]) ? (int) $_POST["num{$i['plID']}"] : 0;

    echo '<label class="plat">',
    '<img src="../images/repas/', $i['plID'], '.jpg" alt="', $i['plNom'], '" title="', $i['plNom'], '">',
    $i['plNom'],
    '<input type="number" min="0" max="2" name="num', $i['plID'], '" value="', $value, '">',
    '</label>';
}


//_______________________________________________________________________________
/**
 *  Génère la zone de saisie qui correspond aux suppléments
 *
 *  @param  objet   $conn connexion à la base de données MySQL
 *  @return mixed   un tableau contenant les erreurs rencontrées ou une chaîne informant 
 *                  du bon déroulement de l'action demandée
 */
function sll_verifier_commande($conn)
{

    /* Toutes les erreurs détectées qui nécessitent une modification du code HTML 
    sont considérées comme des tentatives de piratage 
    et donc entraînent l'appel de la fonction sl_exit_session() */

    $err = array();

    // vérification des entrées
    if (!isset($_POST['radEntree'])) {
        $err[] = 'Choix d\'entrée incorrect.';
    }
    // vérification du plat
    if (!isset($_POST['radPlat'])) {
        $err[] = 'Choix de plat incorrect.';
    }

    // vérification du dessert
    if (!isset($_POST['radDessert'])) {
        $err[] = 'Choix de déssert/fromage incorrect.';
    }

    // vérification de la boisson
    if (!isset($_POST['radBoisson'])) {
        $err[] = 'Le choix d\'une boisson est obligatoire.';
    }

    $data = array();
    // tableau qui contiendra les plats commandés à enregistrer en bdd 
    // (indice : identifiant du plat, valeur : quantité du plat)

    $nbPlats = 0;
    $countElts = 0; // compte les éléments autorisés que l'on trouve dans $_POST

    foreach ($_POST as $key => $value) {
        if (substr($key, 0, 2) == 'cb') { // vérif sur les accompagnements
            $id = substr($key, 2);
            if (!estEntier($value) || !estEntier($id) || $id != $value || $id < 0) {
                $conn->close();
                sl_exit_session();
            }

            $nbPlats++;
            $data[(int)$value] = 1;
            $countElts++;
        } else if (substr($key, 0, 3) == 'num') { // vérif sur les suppléments
            $id = substr($key, 3);
            if (!estEntier($value) || !estEntier($id) || $id < 0) {
                $conn->close();
                sl_exit_session();
            }
            if ($value < 0 || $value > 2) {
                $conn->close();
                sl_exit_session();
            }
            if ($value > 0) { // $value in [1,2], OK
                $data[(int)$id] = (int)$value;
            }
            $countElts++;
        } else if (($key == 'radDessert' || $key == 'radPlat' || $key == 'radEntree'
            || $key == 'radBoisson')) {
            if (!estEntier($value) || $value < 0) { // autres vérifications
                $conn->close();
                sl_exit_session();
            }
            if ($value > 0) { // Test nécessaire car on ne veut pas 0
                $data[(int)$value] = 1;
            }
            $countElts++;
        }
    }

    if ($countElts + 1 != count($_POST)) { // + 1 car clé bouton submit
        $conn->close();
        sl_exit_session();
    }

    if ($nbPlats == 0) {
        $err[] = 'La commande doit contenir au moins un accompagnement.';
    }

    // verification que le resto U est bien ouvert aujourdhui
    $sql = 'SELECT * FROM menu WHERE meDate=' . DATE_AUJOURDHUI;
    $res = $conn->query($sql) or bd_erreur($conn, $sql);
    if ($res->num_rows == 0) { // bdd ne trouve pas cette date qui est censée exister
        $res->free();
        $conn->close();
        sl_exit_session();
    }
    $res->free();

    // vérification que l'utilisateur n'a pas déjà choisi son repas aujourdhui
    $date = DATE_AUJOURDHUI;
    $sql = "SELECT * FROM repas 
            WHERE reDate={$date} 
            AND reEtudiant={$_SESSION['user']['numero']}";
    $res = $conn->query($sql) or bd_erreur($conn, $sql);
    if ($res->num_rows != 0) { // bdd trouve une commande qui ne devrait pas exister
        $res->free();
        $conn->close();
        sl_exit_session();
    }
    $res->free();

    // s'il y a des erreurs, on renvoie le tableau qui les contient
    if (count($err) > 0) {
        return $err;
    }

    // si on arrive à ce point de l'exécution, tout est OK
    // ajout de la commande dans la base de données 
    $sql = 'INSERT INTO repas (rePlat, reDate, reQuantite, reEtudiant) VALUES ';
    $i = 0;
    $etudiant = $_SESSION['user']['numero'];
    foreach ($data as $plat => $quantite) {
        $sql = $sql . (($i > 0) ? ',' : '') . " ($plat, $date, $quantite, $etudiant)";
        $i++;
    }
    $conn->query($sql) or bd_erreur($conn, $sql);
    return 'La commande a été enregistrée avec succès.';
}


/**
 *  Affichage des commentaires associés au menu du jour
 *
 *  @param  objet   $conn   la connexion a la base MySQL
 *  @param  string  $date   la date du jour au format AAAAMMJJ
 *  @param  bool    $commOK indique si l'utilisateur peut commenter (commande existe)                  
 */
function sll_afficher_commentaires($conn, $date, $commOK)
{

    // titre de la sous partie
    echo '<h4 id="commentaires" class="comment">Commentaires sur ce menu</h4>';

    // récupération des commentaires
    $sql =
        "SELECT * FROM commentaire INNER JOIN etudiant ON etNumero = coEtudiant 
    WHERE coDateRepas = $date ORDER BY coDatePublication DESC";

    $res = $conn->query($sql) or bd_erreur($conn, $sql);

    $comments = array();
    $nbCommUtilisateurCourant = 0;
    $total = 0; // total des commentaires pour le menu considéré
    while ($tab = $res->fetch_assoc()) {
        $comments[] = $tab;
        $total += $tab['coNote']; // pour la note moyenne
        if (isset($_SESSION['user']) && $tab['etNumero'] == $_SESSION['user']['numero']) {
            $nbCommUtilisateurCourant++; // incrémente le nb de com du l'util connecté
        }
    }
    $res->free();

    // si l'utilisateur connecté n'a pas déjà commenté : on lui propose de le faire
    if ($commOK && $nbCommUtilisateurCourant == 0 && isset($_SESSION['user'])) {
        echo '<form action="commentaire.php" method="post"><input type="hidden" value="',
        $date, '" name="date">', 'Un avis à donner ? <input type="submit" ',
        'style="width: auto;" value="Ecrire un commentaire"></form>';
    }

    // s'il n'y a pas de commentaires
    $nb = count($comments);
    if ($nb == 0) echo '<p class="comment">Pas de commentaires pour ce menu.</p>';
    return;

    // calcul et affichage de la moyenne et du nombre de commentaires
    $moyenne = round($total / $nb, 1);
    echo '<p>Note moyenne de ce menu : ', $moyenne, '/5 sur la base de ',
    $nb, ' commentaire', (($nb > 1) ? 's' : ''), '</p>';

    $tabMois = get_tableau_mois(); // commence à 0 pour Janvier

    // affichage des commentaires
    foreach ($comments as $comm) {
        $d = $comm['coDatePublication'];

        echo '<article>',
        '<h5>Commentaire de ', $comm['etPrenom'], ' ', $comm['etNom'],
        ', publié le ', intval(substr($d, 6, 2)), ' ',
        mb_strtolower($tabMois[intval(substr($d, 4, 2)) - 1], 'UTF-8'),
        ' ', substr($d, 0, 4),
        ' à ', intval(substr($d, 8, 2)), 'h', intval(substr($d, 10)),
        '</h5>', '<p>', proteger_sortie($comm['coTexte']), '</p>',
        '<footer>Note : ', $comm['coNote'], ' / 5</footer>';
        // comme on est en UTF-8 on utilise la fonction mb_strtolower()


        // ajoute de la photo d'illustration si elle existe dans le répertoire "upload"
        $file = "../upload/{$date}_{$comm['etNumero']}.jpg";
        if (file_exists($file)) {
            echo '<a href="', $file, '" target="_blank"><img src="', $file,
            '" alt="Photo illustrant le commentaire" title="Cliquez pour agrandir"></a>';
        }

        // si l'utilisateur est l'auteur du commentaire, on ajoute un bouton qui emmène 
        // vers la page "commentaire.php" en passant en paramètre la date du commentaire 
        // au format AAAAMMJJ

        if (isset($_SESSION['user']) && $_SESSION['user']['numero'] == $comm['etNumero']) {
            echo '<form action="commentaire.php" method="post">',
            '<input type="hidden" value="', $date, '" name="date">',
            '<input type="submit" style="width: auto;" value="Editer le commentaire">',
            '</form>';
        }
        echo '</article>';
    }
}
