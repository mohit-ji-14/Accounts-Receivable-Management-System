<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    exit("Access Denied");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $entity_id = (int)$_POST['entity_id'];

    // Validate entity
    if (in_array($entity_id, [1, 2, 3])) {
        $_SESSION['selected_entity'] = $entity_id;
    }
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit;