<?php
// script php qui génère les valeurs à introduire dans le script sql concernant la table repas
// ne pas oublier de remplacer la dernière virgule par un point virgule

$valeurs_menus = "";                        // variable contenant la chaine résultat
$ts_init = time();                          // timestamp du jour

$ts_fin = newTimestamp(2, 'a', $ts_init);
// timestamp de fin, c'est ici qu'on choisi jusqu'où on veut générer des données 
// 'a' pour année, ici deux ans à partir de la date de la génération des données

for ($ts_init; $ts_init < $ts_fin; $ts_init = $ts_init + 86400) { // dans un jour il y a 86400 secondes

    // pour chaque jour où le restaurant est ouvert, on génère un menu aléatoire
    $entree = randomArray(3, 1, 9);
    $plat = randomArray(2, 10, 27);
    $accomp = randomArray(3, 28, 33);
    $boisson = randomArray(3, 34, 37);
    $dessert = randomArray(3, 40, 49);

    // la date du jour courant au format souhaité YYYYMMJJ
    $date_init = date('Ymd', $ts_init);

    // si le jour courant est un samedi ou un dimanche, on continue sans générer de données
    if (
        !checkdate(date('n', $ts_init), date('j', $ts_init), date('Y', $ts_init))
        || date('w', $ts_init) == 0 || date('w', $ts_init) == 6
    ) {
        continue;
    };

    // sinon on concatène la chaine résultat
    $valeurs_menus .= "($date_init, $entree[0]),\n($date_init, $entree[1]),\n($date_init, $entree[2]),\n";
    $valeurs_menus .= "($date_init, $plat[0]),\n($date_init, $plat[1]),\n";
    $valeurs_menus .= "($date_init, $accomp[0]),\n($date_init, $accomp[1]),\n($date_init, $accomp[2]),\n";
    $valeurs_menus .= "($date_init, $boisson[0]),\n($date_init, $boisson[1]),\n($date_init, $boisson[2]),\n";
    $valeurs_menus .= "($date_init, $dessert[0]),\n($date_init, $dessert[1]),\n($date_init, $dessert[2]),\n";
}

// écriture dans le fichier résultat
$fichier = fopen('resultat-menus.txt', 'w+');
fwrite($fichier, $valeurs_menus);
fclose($fichier);

echo 'Le fichier a bien été généré.';


/*************************************** fonctions utiles au script ***************************************/

/**
 * Créé un tableau d'une certaine longeur contenant des nombres aléatoires tous distincts
 * 
 * @param integer $long    la longueur du tableau souhaitée i.e. le nombre de chiffres aléatoires voulus
 * @param integer $min     la borne min
 * @param integer $max     la borne max 
 */

function randomArray($long, $min, $max)
{
    $random_array = [rand($min, $max)];
    $cpt = 1;

    while ($cpt < $long) {
        $l = count($random_array);
        do {
            $random = rand($min, $max);
        } while (in_array($random, $random_array));
        array_push($random_array, $random);
        $cpt++;
    }

    return $random_array;
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

/**
 * Ajoute ou retranche un laps de temps à un timestamp
 *
 * @param integer	$nbre	nombre d'unités
 * @param string	$unite	unité : h, i, s, a, m, j, t, w
 * @param integer	$debut	timestamp de départ du calcul
 * @return integer	nouveau timestamp
 */

function newTimestamp($nbre, $unite, $debut)
{
    $d = getdate($debut);
    $h = $d['hours'];
    $i = $d['minutes'];
    $s = $d['seconds'];
    $a = $d['year'];
    $m = $d['mon'];
    $j = $d['mday'];

    switch ($unite) {
        case ('a'):
            return mktime($h, $i, $s, $m, $j, $a + $nbre);
        case ('t'):
            return mktime($h, $i, $s, $m + ($nbre * 3), $j, $a);
        case ('m'):
            return mktime($h, $i, $s, $m + $nbre, $j, $a);
        case ('j'):
            return mktime($h, $i, $s, $m, $j + $nbre, $a);
        case ('w'):
            return mktime($h, $i, $s, $m, $j + ($nbre * 7), $a);
        case ('h'):
            return mktime($h + $nbre, $i, $s, $m, $j, $a);
        case ('i'):
            return mktime($h, $i + $nbre, $s, $m, $j, $a);
        case ('s'):
            return mktime($h, $i, $s + $nbre, $m, $j, $a);
    }

    // Si on est ici c'est que le 2éme paramètre 
    // n'était pas bon. On retourne -1.
    return -1;
}
