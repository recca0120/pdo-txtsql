<?php

$sql->insert(array('db' => $database,
                   'table' => $table,
                   'values' => array('name'    => $_POST['info']['name'],
                                'address' => $_POST['info']['address'],
                                'city'    => $_POST['info']['city'],
                                'state'   => strtoupper($_POST['info']['state']),
                                'zip'     => $_POST['info']['zip'],
                                'phone'   => $_POST['info']['phone'],
                                'email'   => $_POST['info']['email']))) or die();

header('Location: index.php');
