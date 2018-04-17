<style>
	TD,BODY,INPUT { FONT-FAMILY: TREBUCHET MS; FONT-SIZE: 11PX; }
	A { COLOR: #404040; TEXT-DECORATION:NONE;FONT-WEIGHT:BOLD; }
	A:HOVER { COLOR: #000000; }
</style>
<center><h4>Welcome to my guestbook</h1></center>
<?php
/**************/
$username = 'root';
$password = '';
$database = 'guestbook';
$table    = 'book1';

define('TXTSQL_CORE_PATH', '../../');
define('TXTSQL_PARSER_PATH', '../../');
include('../../txtSQL.class.php');
/***************/

/* Load txtSQL, connect to it and select our database */
$sql = new txtSQL('../../data')         or @ob_get_clean().die($sql->get_last_error());
$sql->connect($username, $password) or @ob_get_clean().die($sql->get_last_error());
$sql->selectdb($database)            or @ob_get_clean().die($sql->get_last_error());

/* View the guest book */
if (@$_GET['action'] == 'view') {
    include('./view.php');
}

/* Sign the guestbook */
elseif (@$_GET['action'] == 'post') {
    include('./sign.php');
}

/* Show the menu */
else {
    echo "<center><a href=\"index.php?action=view\">View Guestbook</a> | " .
         "<a href=\"index.php?action=post\">Sign Guestbook</a>";
}
?>