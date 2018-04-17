<?php
@ob_start();
/*****************************************/
$username = 'root';
$password = '';
$database = 'addressbook';
$table    = 'addressbook';

define('TXTSQL_CORE_PATH', '../../');
define('TXTSQL_PARSER_PATH', '../../');
include('../../txtSQL.class.php');

$sql = new txtSQL('../../data') or @ob_get_clean().die($sql->get_last_error());
$sql->connect($username, $password) or @ob_get_clean().die($sql->get_last_error());
/*****************************************/

// Check if there is a specific thing that we have to do
if (isset($_GET['page']) && !empty($_GET['page'])) {
    switch ($_GET['page']) {
        case 'delete':
        {
            require_once('./delete.php');

            break;
        }

        case 'add':
        {
            require_once('./add.php');

            break;
        }
    }
}
?>

<style>
	td, th, body { font-family: trebuchet ms; font-size: 11px; color: black; }
	a { color: 800000; font-weight: bold; text-decoration: none }
	a:hover { color: DF0000; }
	th { color: white; background-color: #0C2C7E; }
</style>

<?php

// Else, display all the records
$records = $sql->query('SELECT * FROM ' .  $database . '.' . $table);

if (empty($records)) {
    echo "<b>No records yet</b><br />\n";
} else {
    $max = count($records);

    echo "<table border=0 cellspacing=1 cellpadding=5 bgcolor=#0C2C7E width=100%><tr>";
    echo "<th>ID</th><th>Name</th><th>Address</th><th>City</th><th>State</th><th>Zip Code</th><th>Phone</th><th>E-mail</th></tr>";

    foreach ($records as $key => $value) {
        echo "<tr bgcolor=\"beige\">";

        foreach ($value as $key1 => $value1) {
            echo "<td>".($key1 == 'name' ? "<b>$value1</b>" : $value1)."</td>";
        }

        echo "<td><a href='index.php?page=delete&id={$value['id']}'>Delete</a></td>";

        echo "</tr>\n";
    }

    echo "</table>";
}

?>

<form method="post" action="index.php?page=add">
<b>Add an entry</b>
<table cellpadding="5" cellspacing="1" bgcolor="FAFAFA" style="border-top:1px solid #C0C0C0;" width="100%">
<tr>
	<td>Name</td><td><input type="text" name="info[name]"></td>
	<td>Address</td><td><input type="text" name="info[address]"></td>
</tr>
<tr>
	<td>City</td><td><input type="text" name="info[city]"></td>
	<td>State</td><td><input type="text" name="info[state]"></td>
</tr>
<tr>
	<td>Zip</td><td><input type="text" name="info[zip]"></td>
	<td>Phone</td><td><input type="text" name="info[phone]"></td>
</tr>
<tr>
	<td>E-mail</td><td><input type="text" name="info[email]"></td>
	<td colspan="2" align="center"><input type="submit" value="Add Entry"></td>
</tr>
</table>
</form>