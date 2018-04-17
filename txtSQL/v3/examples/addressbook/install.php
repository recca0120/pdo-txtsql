<?php
/* Define txtSQL Constants */
define('TXTSQL_CORE_PATH', '../../');
define('TXTSQL_PARSER_PATH', '../../');
include('../../txtSQL.class.php');

if (@$_GET['action'] == 'finish') {
    /* Load txtSQL, connect to it and select our database */
    $sql = new txtSQL('../../data');
    $sql->connect($_POST['user'], $_POST['pass']) or die();

    $sql->query('CREATE TABLE ' . $_POST['db'] . '.' . $_POST['table'] . ' (
		     	id primary key permanent int auto_increment,
		     	address,
		     	city,
		     	state,
		     	zip,
		     	phone,
		     	email)') or die();

    die("DONE INSTALLING! <a href=\"index.php\">View your guestbook</a>");
} else {
    ?>

<form method="post" action="install.php?action=finish">
txtSQL Username <input type="text" name="user" value="root"><br/>
txtSQL Password <input type="password" name="pass"><br/>
txtSQL Database <input type="text" name="db" value="guestbook"><br/>
What to name the table? <input type="text" name="table" value="book1"><br/>
<input type="submit" value="Install">

<?php
} ?>
