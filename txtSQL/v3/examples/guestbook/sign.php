<?php
/* Post the entry */
if (@$_GET['do'] == 'finish') {
    /* Add slashes to posts to prevent SQL Injections (not that good)
       and convert html carets (< and >) to their ASCII equivalents */
    foreach ($_POST as $key => $value) {
        $_POST[$key] = htmlentities(addslashes(addslashes($value)));
    }

    /* Add the new entry */
    $sql->query('INSERT INTO ' . $table . ' SET(
			author="'  . $_POST['name']  . '", email="' . $_POST['email'] . '",
			aim="'     . $_POST['aim']   . '", msn="'   . $_POST['msn']   . '",
			yahoo="'   . $_POST['yahoo'] . '", icq="'   . $_POST['icq']   . '",
			www="'     . $_POST['www']   . '", entry="' . $_POST['entry'] . '")')
     or @ob_get_clean().die($sql->get_last_error());

    echo "<center>Your entry has been added: <a href=\"index.php?action=view\">View Guestbook</a>";
    exit;
}

/* Display the form */
?>
<center><input type="button" onclick="document.location.href='index.php?action=view'" value="View Guestbook"></center><br/>
<form method=post action="index.php?action=post&do=finish">
<table>
	<tr>
		<td>Name</td><td><input type=text name=name></td>
	</tr>
	<tr>
		<td>E-mail</td><td><input type=text name=email></td>
	</tr>
	<tr>
		<td>AIM</td><td><input type=text name=aim></td>
	</tr>
	<tr>
		<td>MSN</td><td><input type=text name=msn></td>
	</tr>
	<tr>
		<td>Yahoo</td><td><input type=text name=yahoo></td>
	</tr>
	<tr>
		<td>ICQ</td><td><input type=text name=icq></td>
	</tr>
	<tr>
		<td>WWW</td><td><input type=text name=www></td>
	</tr>
	<tr>
		<td>MESSAGE:</td><td><textarea name=entry cols=30 rows=10></textarea></td>
	</tr>
	<tr>
		<td colspan="2" align="center"><input type=submit value="Sign Guestbook"></td>
	</tr>
</table>
</form>