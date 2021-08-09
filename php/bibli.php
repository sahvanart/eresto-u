<?php

//___________________________________________________________________
/**
 * Fonction qui réalise la connexion à une base de données MySQL.
 * En cas d'erreur de connexion le script est arrêté.
 *
 * @return objet connecteur à la base de données
 */
function connecter()
{
    $bd = mysqli_connect(BD_SERVER, BD_USER, BD_PASS, BD_NAME);

    if ($bd !== FALSE) {
        // mysqli_set_charset() définit le jeu de caractères par défaut à utiliser
        // lors de l'envoi de données depuis et vers le serveur de base de données.
        mysqli_set_charset($bd, 'utf8') or
            bd_erreurExit('<h4>Erreur lors du chargement du charset utf8</h4>');
        return $bd;     // connexion OK --> sortie
    }

    // Dans le cas d'une erreur de connexion,
    // collecte des informations facilitant le debugage
    $msg = '<h4>Erreur de connexion base MySQL</h4>'
        . '<div style="margin: 20px auto; width: 350px;">'
        . 'BD_SERVER : ' . BD_SERVER
        . '<br>BD_USER : ' . BD_USER
        . '<br>BD_PASS : ' . BD_PASS
        . '<br>BD_NAME : ' . BD_NAME
        . '<p>Erreur MySQL num&eacute;ro : ' . mysqli_connect_errno($bd)
        . '<br>' . htmlentities(mysqli_connect_error(), ENT_QUOTES, 'ISO-8859-1')
        // appel de htmlentities() pour que les éventuels accents s'affiche correctement
        . '</div>';

    bd_erreurExit($msg);
}


//___________________________________________________________________
/**
 * Fonction provoquant un arrêt du script si erreur base de données.
 * Affichage d'un message d'erreur si on est en phase de
 * développement, sinon stockage dans un fichier log.
 *
 * @param string    $msg    Message affiché ou stocké.
 */
function bd_erreurExit($msg)
{
    ob_end_clean();         // Supression de tout ce qui
    // a pu être déjà généré

    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>',
    'Erreur base de données</title></head><body>',
    $msg,
    '</body></html>';
    exit();
}


//___________________________________________________________________
/**
 * Gestion d'une erreur de requête à la base de données.
 *
 * @param mysqli  $bd     connecteur sur la bdd ouverte
 * @param string $sql    requête SQL ayant provoqué l'erreur
 */
function bd_erreur($bd, $sql)
{
    $errNum = mysqli_errno($bd); // code (numéro) de l'erreur
    $errTxt = mysqli_error($bd); // texte associé à l'erreur

    // Collecte des informations facilitant le debugage
    $msg =  '<h4>Erreur de requête</h4>'
        . "<b>Erreur mysql :</b> $errNum"
        . "<br> $errTxt"
        . "<br><br><b>Requête :</b><br><pre>$sql</pre>"
        . '<br><br><b>Pile des appels de fonction :</b>';

    $tdStyle = 'style="border: 1px solid black;padding: 4px 10px"';

    // Récupération de la pile des appels de fonction
    $msg .= '<table style="border-collapse: collapse">'
        . "<tr><td $tdStyle>Fonction</td>"
        . "<td $tdStyle>Appelée ligne</td>"
        . "<td $tdStyle>Fichier</td></tr>";

    $appels = debug_backtrace();
    for ($i = 0, $iMax = count($appels); $i < $iMax; $i++) {
        $msg .= "<tr style='text-align: center'><td $tdStyle>"
            . $appels[$i]['function'] . "</td><td $tdStyle>"
            . $appels[$i]['line'] . "</td><td $tdStyle>"
            . $appels[$i]['file'] . '</td></tr>';
    }

    $msg .= '</table>';

    bd_erreurExit($msg);
}


/** 
 *  Protection des sorties (code HTML généré à destination du client)
 *
 *  Fonction à appeler pour toutes les chaines provenant de :
 *      - saisies de l'utilisateur (formulaires)
 *      - la bdd
 *  Permet de se protéger contre les attaques XSS (Cross Site Scripting)
 *  Convertit tous les caractères éligibles en entités HTML, notamment :
 *      - les caractères ayant une signification spéciales en HTML (<, >, ...)
 *      - les caractères accentués
 * 
 *  Si on lui transmet un tableau, la fonction renvoie un tableau où toutes les chaines
 *  qu'il contient sont protégées, les autres données du tableau ne sont pas modifiées. 
 *
 *  @param  mixed  $content   la chaine ou un tableau contenant des chaines, à protéger 
 *  @return mixed  $content   la chaîne protégée ou le tableau de chaines
 */
function proteger_sortie($content)
{
    if (is_array($content)) {
        foreach ($content as &$value) {
            $value = proteger_sortie($value);
        }
        unset($value); // à ne pas oublier (de façon générale)
        return $content;
    }
    if (is_string($content)) {
        return htmlentities($content, ENT_QUOTES, 'UTF-8');
    }
    return $content;
}


/**
 *  Protection des entrées (chaînes envoyées au serveur MySQL)
 * 
 * Avant insertion dans une requête SQL, certains caractères spéciaux doivent être échappés 
 * (", ', ...)
 * Toutes les chaines de caractères provenant de saisies de l'utilisateur doivent être 
 * protégées en utilisant la fonction mysqli_real_escape_string() (si elle est disponible)
 * Cette dernière fonction :
 * - protège les caractères spéciaux d'une chaîne (en particulier les guillemets)
 * - permet de se protéger contre les attaques de type injections SQL
 *
 *  Si on lui transmet un tableau, la fonction renvoie un tableau où toutes les chaines
 *  qu'il contient sont protégées, les autres données du tableau ne sont pas modifiées.  
 *   
 *   @param    mysqli  $co         l'objet représantant la connexion au serveur MySQL
 *   @param    mixed   $content    la chaine ou un tableau contenant des chaines à protéger 
 *  @return    mixed   $content    la chaîne ou le tableau protégés
 */
function proteger_entree($co, $content)
{
    if (is_array($content)) {
        foreach ($content as &$value) {
            $value = proteger_entree($co, $value); // récursivité 
        }
        unset($value); // à ne pas oublier (de façon générale)
        return $content;
    }
    if (is_string($content)) {
        if (function_exists('mysqli_real_escape_string')) {
            return mysqli_real_escape_string($co, $content);
        }
        if (function_exists('mysqli_escape_string')) {
            return mysqli_escape_string($co, $content);
        }
        return addslashes($content); // si la fonction mysqli_real_escape_string n'existe 
        // pas dans l'environnement  

    }
    return $content;
}

//___________________________________________________________________
/**
 * Teste si un nombre est compris entre 2 autres
 *
 * @param integer    $x  nombre à tester
 * @return boolean   TRUE si ok, FALSE sinon
 */
function estEntre($x, $min, $max)
{
    return ($x >= $min) && ($x <= $max);
}

//___________________________________________________________________
/**
 * Teste si une valeur est une valeur entière
 *
 * @param mixed      $x  valeur à tester
 * @return boolean   TRUE si entier, FALSE sinon
 */
function estEntier($x)
{
    return is_numeric($x) && ($x == (int) $x);
}


//___________________________________________________________________
/**
 * Créé une liste déroulante à partir des options passées en paramètres
 *
 * @param string     $nom       Le nom de la liste déroulante
 * @param array      $options   Tableau associatif donnant la liste des options sous la 
 *                              forme valeur => libelle 
 * @param string     $default   La valeur qui doit être sélectionnée par défaut 
 */
function sl_creer_select($nom, $options, $defaut)
{
    echo '<select name="', $nom, '">';
    foreach ($options as $valeur => $libelle) {
        echo '<option value="', $valeur, '"', (($defaut == $valeur) ? ' selected' : ''),
        '>', $libelle, '</option>';
    }
    echo '</select>';
}

//___________________________________________________________________
/**
 * Créé une liste déroulante d'une suite de nombre à partir des options passées en paramètres.
 *
 * @param string     $nom       Le nom de la liste déroulante
 * @param int        $min       La valeur minimale de la liste
 * @param int        $max       La valeur maximale de la liste 
 * @param int        $pas       Pas d'itération (>0 énumération croissante, <0 décroissante) 
 * @param int        $default   La valeur qui doit être sélectionnée par défaut 
 */
function sl_creer_liste_nombre($nom, $min, $max, $pas, $defaut)
{
    echo '<select name="', $nom, '">';
    if ($pas > 0) {
        for ($i = $min; $i <= $max; $i += $pas) {
            echo '<option value="', $i, '"', (($defaut == $i) ? ' selected' : ''),
            '>', $i, '</option>';
        }
    } else {
        for ($i = $max; $i >= $min; $i += $pas) {
            echo '<option value="', $i, '"', (($defaut == $i) ? ' selected' : ''),
            '>', $i, '</option>';
        }
    }
    echo '</select>';
}

//___________________________________________________________________
/**
 * Renvoie un tableau contenant le nom des mois (utile pour certains affichages)
 *
 * @return array     Tableau à indices numériques contenant les noms des mois
 */
function get_tableau_mois()
{
    return array(
        'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août',
        'Septembre', 'Octobre', 'Novembre', 'Décembre'
    );
}

//___________________________________________________________________
/**
 * Affiche une liste déroulante représentant les 12 mois de l'année
 *
 * @param string    $nom      Le nom de la liste déroulante (valeur de l'attribut name)
 * @param int       $defaut   Le mois qui doit être sélectionné par défaut (1 pour janvier)
 */
function sl_creer_liste_mois($nom, $defaut)
{
    $mois = get_tableau_mois();
    $m = array();
    foreach ($mois as $k => $v) {
        $m[$k + 1] = mb_strtolower($v, 'UTF-8'); // 1 pour janvier
        // comme on est en UTF-8 on utilise la fonction mb_strtolower
        // qui est une multi byte function car en UTF-8 certains caractères
        // sont codés sur plusieurs octets (ex : les lettres accentuées)
    }
    sl_creer_select($nom, $m, $defaut);
}

//___________________________________________________________________
/**
 * Contrôle des clés présentes dans les tableaux $_GET ou $_POST - piratage ?
 *
 * Cette fonction renvoie false en présence d'une suspicion de piratage 
 * et true quand il n'y a pas de problème détecté.
 *
 * Soit $x l'ensemble des clés contenues dans $_GET ou $_POST 
 * L'ensemble des clés obligatoires doit être inclus dans $x.
 * De même $x doit être inclus dans l'ensemble des clés autorisées, formé par l'union de 
 * l'ensemble des clés facultatives et de l'ensemble des clés obligatoires.
 * Si ces 2 conditions sont vraies, la fonction renvoie true, sinon, elle renvoie false.
 * Dit autrement, la fonction renvoie false si une clé obligatoire est absente ou 
 * si une clé non autorisée est présente; elle renvoie true si "tout va bien"
 * 
 * @param string    $tab_global 'post' ou 'get'
 * @param array     $cles_obligatoires tableau contenant les clés qui doivent 
 *                  obligatoirement être présentes
 * @param array     $cles_facultatives tableau contenant les clés facultatives
 * @global array    $_GET
 * @global array    $_POST
 * @return boolean  true si les paramètres sont corrects, false sinon
 */
function sl_controle_parametres(
    $tab_global,
    $cles_obligatoires,
    $cles_facultatives = array()
) {

    $x = strtolower($tab_global) == 'post' ? $_POST : $_GET;

    $x = array_keys($x);
    // $cles_obligatoires doit être inclus dans $x
    if (count(array_diff($cles_obligatoires, $x)) > 0) {
        // array_diff renvoie les éléments de $cles_obligatoires qui ne sont pas présents
        // dans $x, donc s'il y a en a au moins une
        return false;
    }
    // $x doit être inclus dans $cles_obligatoires Union $cles_facultatives
    if (count(array_diff($x, array_merge($cles_obligatoires, $cles_facultatives))) > 0) {
        return false;
    }

    return true;
}

/**
 * Envoie à la sortie standard le nombre d'éléments et le contenu d'un tableau
 *
 * @param array	$t	tableau dont les infos sont à afficher
 */
function infoTableau($t)
{
    echo 'Tableau de ', count($t), ' &eacute;l&eacute;ments',
    '<pre>', print_r($t, true), '</pre>';
}
