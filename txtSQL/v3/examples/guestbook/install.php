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
		     	date date,
		     	author,
		     	email,
		     	aim,
		     	msn,
		     	yahoo,
		     	icq,
		     	www,
		     	entry text(1000))') or die();

    $file     = file('./index.php');
    $file[6]  = '<?php' . "\n";
    $file[7]  = '/**************/' . "\n";
    $file[8]  = '$username = \'' . $_POST['user'] . '\';' . "\n";
    $file[9]  = '$password = \'' . $_POST['pass'] . '\';' . "\n";
    $file[10] = '$database = \'' . $_POST['db'] . '\';' . "\n";
    $file[11] = '$table    = \'' . $_POST['table'] . '\';' . "\n";
    $file     = implode('', $file);

    $fp = fopen('./index.php', 'w');
    fwrite($fp, $file);
    fclose($fp);

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
