<?php

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $sql->query('DELETE FROM ' . $database . '.' . $table . ' WHERE id = ' . $_GET['id']);

    header('Location: index.php');
}
