<?php
header("Access-Control-Allow-Origin: *");

if (!defined('IN_INDEX')) { exit("This file can only be included in index.php."); }

use Google\Cloud\Language\LanguageClient;

$req = ""; // przekazany tag (tutaj będzie)
$limit = explode("!_!", $_GET['tag'])[1]; // ograniczenie ilości artykułów
if ($limit == "" || strval($limit) > 20){
    $limit = 5; // jeżeli nic się nie podało w okienku lub więcej niż max, to dajemy maksymalną liczbę artykułów
} elseif (strval($limit) < 0){
    $limit = 1;
} else {
    $limit = strval($limit);
}
if (isset(explode("!_!", $_GET['tag'])[0]) && explode("!_!", $_GET['tag'])[0]) {
    $req = str_replace(" ", "0SPACJA0", explode("!_!", $_GET['tag'])[0]); // jak jakieś spacje są w tagu to zamieniam na coś innego idk xddd
    $req = preg_replace("/[^a-zA-Z0-9]/", "", $req);
}

// funkcja pod wysyłanie requestów
function send_request($u){
    $ch_session = curl_init();

    curl_setopt($ch_session, CURLOPT_RETURNTRANSFER, 1);

    curl_setopt($ch_session, CURLOPT_URL, $u);

    $userAgent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)';
    curl_setopt($ch_session, CURLOPT_USERAGENT, $userAgent);

    $result = curl_exec($ch_session);

    return $result;
}

// wysłanie zapytania o ogólnie taga
$tagTVP = send_request("https://www.tvp.info/tag?tag=" . str_replace("0SPACJA0", "%20", $req)); // bo tvp zamiast spacji w url przyjmuje %20
$tagPOLSAT = send_request("https://www.polsatnews.pl/wyszukiwarka/?text=%22" . str_replace("0SPACJA0", "%20", $req) . "%22&src=tag");

// wyciągnięcie tylko URLi poszczególnych artykułów na tej stronie
preg_match_all('/(?<="url" : ")((.|\n)*?)(?=",)/', $tagTVP, $urlsTVP);
preg_match_all('/(?<=<a class="news__link" href=")((.|\n)*?)(?=">)/', $tagPOLSAT, $urlsPOLSAT);

// jeżeli zwrócona została strona główna tvp to wtedy nie ma taga (nie ma urli)
if (empty($urlsTVP[0]) && empty($urlsPOLSAT[0])) {
    echo '404';
    return;
}

// modyfikacja ostatnich wyszukiwań uzytkownika
if (!in_array(explode("!_!", $_GET['tag'])[0], $_SESSION['searches'])) {
    $last = array_pop($_SESSION['searches']);
    unset($_SESSION["#".strval($last)]);
    array_unshift($_SESSION['searches'], explode("!_!", $_GET['tag'])[0]);
    if ($_SESSION['num_of_searches'] < 4) $_SESSION['num_of_searches']++;
}


// zamiania znaku "\" na ""
$urlsTVP = preg_replace("/\\\\{1}/", "", $urlsTVP[0]);
$urlsPOLSAT = $urlsPOLSAT[0];
$urlsTVP = array_slice($urlsTVP, 0, $limit);
$urlsPOLSAT = array_slice($urlsPOLSAT, 0, $limit);

$articles = [];
foreach($urlsTVP as $link){
    // wyslanie requesta pod konkretny artykul
    $article = send_request("https://www.tvp.info" . $link);

    // wyciągnięcie tytułu tekstu
    preg_match_all("/(?<=<h1>)((.|\n)*?)(?=<\/h1>)/", $article, $title);

    // wyciągnięcie zawartosci artykulu (tekstu)
    preg_match_all('/(?<=<p class="am-article__heading article__width">)(?s).*(?=<!--galeria-->)/', $article, $text); // zauwazylem, ze content jest aż do ukrytego tego galeria xd
    $text = $text[0];
    $text = preg_replace("/&nbsp;/", " ", $text); // usunięcie twardych spacji
    $text = preg_replace("/<br>/", " ", $text); // usunięcie nowych linii
    $text = preg_replace("/<b>/", " ", $text);
    $text = preg_replace("/<\/b>/", "", $text);
    $text = preg_replace('/<p class="am-article__description article__description">(.|\n)*?<\/p>/', " ", $text);
    $text = preg_replace("/<\/p>/", " ", $text);
    $text = preg_replace('/<div((.|\n)*?)>/', "", $text);
    $text = preg_replace('/<\/div>/', "", $text); 
    $text = preg_replace('/<img((.|\n)*?)>/', "", $text);
    $text = preg_replace('/<script>(.|\n)*?<\/script>/', "", $text);    
    $text = preg_replace('/<!--galeria-->/', "", $text);
    $text = preg_replace('/zobacz więcej/', "", $text);
    $text = preg_replace('/<!--galeria wideo-->/', "", $text);
    $text = preg_replace('/<!--covid-19 chart-->/', "", $text);
    $text = preg_replace('/<h((.|\n)*?)h\d>/', "", $text);
    $text = preg_replace('/<style((.|\n)*?)style>/', "", $text);
    $text = preg_replace('/<((.|\n)*?)>/', "", $text);
    $text = preg_replace('/#wieszwiecejPolub nas/', "", $text);
    $text = preg_replace('/#wieszwiecej Polub nas/', "", $text); 
    $text = preg_replace('/#wieszwiecej | Polub nas/', "", $text);

    $text = preg_replace("/</", "&lt;", $text);
    $text = preg_replace("/>/", "&gt;", $text); // usunięcie nowych linii


    // googlowski sentyment
    // $sent = "";
    // $magn = implode(" ", $text);
    $sent = 0.3;
    $magn = 1;
    try {
    	$language = new LanguageClient([
    	    'keyFilePath' => __DIR__ . '/www-project-391918-1097e3321356.json',
    	]);

    	$annotation = $language->analyzeSentiment(implode(" ", $text));
        $sent = $annotation->sentiment()['score'];
        $magn = $annotation->sentiment()['magnitude'];
    } catch(Exception $e) {
        // echo $e->getMessage();
    }

    $articles[] = array('title' => $title[0][0], 'link' => "https://www.tvp.info".$link, 'sentiment' => $sent, 'sent_magn' => $magn, 'site' => "TVP");
}

preg_match_all('/(?<=<h2 class="news__title">)((.|\n)*?)(?=<\/h2>)/', $tagPOLSAT, $titlesPOLSAT);
$titlesPOLSAT = $titlesPOLSAT[0];

$it = 0;
foreach($urlsPOLSAT as $link){
    // wyslanie requesta pod konkretny artykul
    $article = send_request($link);
    // print($urls[0] . "<br>");

    // wyciągnięcie tytułu tekstu
    $title = $titlesPOLSAT[$it];
    $it = $it + 1;

    // wyciągnięcie zawartosci artykulu (tekstu)
    preg_match_all('/(?<=<div class="news__preview">)(?s).*(?=<div class="news__rndvod">)/', $article, $text);
    $text = $text[0];
    $text = preg_replace("/&nbsp;/", " ", $text); // usunięcie twardych spacji
    $text = preg_replace("/<br>/", " ", $text); // usunięcie nowych linii
    $text = preg_replace("/<b>/", " ", $text);
    $text = preg_replace("/<\/b>/", "", $text);
    $text = preg_replace('/<p class="am-article__description article__description">(.|\n)*?<\/p>/', " ", $text);
    $text = preg_replace("/<\/p>/", " ", $text);
    $text = preg_replace('/<div((.|\n)*?)>/', "", $text);
    $text = preg_replace('/<\/div>/', "", $text); 
    $text = preg_replace('/<img((.|\n)*?)>/', "", $text);
    $text = preg_replace('/<script>(.|\n)*?<\/script>/', "", $text);    
    $text = preg_replace('/<h((.|\n)*?)h\d>/', "", $text);
    $text = preg_replace('/<style((.|\n)*?)style>/', "", $text);
    $text = preg_replace('/<((.|\n)*?)>/', "", $text);
    $text = preg_replace('/Twoja przeglądarka nie wspiera odtwarzacza wideo((.|\n)*?)}}}/', "", $text);

    $text = preg_replace("/</", "&lt;", $text);
    $text = preg_replace("/>/", "&gt;", $text); // usunięcie nowych linii

    // googlowski sentyment
    // $sent = "";
    // $magn = implode(" ", $text);
    $sent = 0.3;
    $magn = 1;
    try {
    	$language = new LanguageClient([
    	    'keyFilePath' => __DIR__ . '/www-project-391918-1097e3321356.json',
    	]);

    	$annotation = $language->analyzeSentiment(html_entity_decode(implode(" ", $text)));
        $sent = $annotation->sentiment()['score'];
        $magn = $annotation->sentiment()['magnitude'];
    } catch(Exception $e) {
        // echo $e->getMessage();
    }

    $articles[] = array('title' => $title, 'link' => $link, 'sentiment' => $sent, 'sent_magn' => $magn, 'site' => "POLSAT");
}

shuffle($articles);
$_SESSION["#".strval(explode("!_!", $_GET['tag'])[0])] = $articles;