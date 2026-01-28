<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: connexion.php');
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin - En construction</title>
</head>
<body>
    <h1>👑 Espace ADMIN (en construction)</h1>
    <p>Connecté en tant que : <?php echo $_SESSION['user_nom']; ?></p>
    <p><a href="tableau_de_bord.php">← Retourner à l'espace utilisateur</a></p>
    <p><a href="deconnexion.php">Déconnexion</a></p>
</body>
</html>