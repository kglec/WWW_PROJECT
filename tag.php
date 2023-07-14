<?php

if (!defined('IN_INDEX')) { exit("This file can only be included in index.php."); }

if(isset($_SESSION["#".strval($_GET['tag'])]) && $_SESSION["#".strval($_GET['tag'])]){
    print TwigHelper::getInstance()->render('tag.html', [
        'articles' => $_SESSION["#".strval($_GET['tag'])],
        'tag' => $_GET['tag'],
    ]);
} else {
    print TwigHelper::getInstance()->render('tag.html');
}
