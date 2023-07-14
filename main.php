<?php

if (!defined('IN_INDEX')) { exit("This file can only be included in index.php."); }

print TwigHelper::getInstance()->render('main.html', [
    'searches' => $_SESSION['searches'],
    'num_of_searches' => $_SESSION['num_of_searches']
]);
