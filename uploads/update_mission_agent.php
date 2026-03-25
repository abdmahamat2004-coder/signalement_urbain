<?php
session_start();
$pdo = new PDO("mysql:host=localhost;dbname=mon_app;charset=utf8", "root", "");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signalement_id'])) {
    $id = $_POST['signalement_id'];
    $photo_path = "";

    if (isset($_FILES['photo_res']) && $_FILES['photo_res']['error'] === 0) {
        $name = time() . '_' . $_FILES['photo_res']['name'];
        move_uploaded_file($_FILES['photo_res']['tmp_name'], 'uploads/' . $name);
        $photo_path = 'uploads/' . $name;
    }

    $stmt = $pdo->prepare("UPDATE signalements SET statut = 'resolu', photo_resolution = ? WHERE id = ?");
    $stmt->execute([$photo_path, $id]);

    header("Location: tableau_de_bord_agent.php?success=1");
}