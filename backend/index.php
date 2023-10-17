<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once "controller/Interactions.php";
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset:"utf8">
        <title>Ludiquation core - PHP</title>
        <style>
            body{ background-color:#000;color:#aaa; }
        </style>
    </head>
    <body>
        <?php
            Interactions::unOrselect_block('eq2b0');
            Interactions::unOrselect_block('eq2b2');
            $mess = Interactions::combine2blocks();
            $clr = (preg_match('/^err/',$mess)) ? '#900' : '#090';
            Interactions::show($clr,CheckFormat::trad($mess));
        ?>
        
        <?php Server::show_data(); ?>
        
    </body>
</html>