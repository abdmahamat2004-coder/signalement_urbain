<?php
session_start();

// Vérifier si l'utilisateur est connecté ET est administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: connexion.php');
    exit();
}

// Connexion à la base de données
$host = 'localhost';
$dbname = 'mon_app';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// DÉTERMINER CE QU'ON EXPORTE
$type = isset($_GET['type']) ? $_GET['type'] : 'signalements';

if ($type === 'signalements') {
    // Export des signalements
    $sql = "SELECT s.id, s.titre, s.categorie, s.statut, s.date_signalement, 
                   s.rue, s.quartier, s.niveau, u.nom_complet, u.email
            FROM signalements s
            LEFT JOIN utilisateurs u ON s.user_id = u.id
            ORDER BY s.date_signalement DESC";
    $filename = 'signalements_' . date('Y-m-d') . '.csv';
    
} elseif ($type === 'utilisateurs') {
    // Export des utilisateurs
    $sql = "SELECT id, nom_complet, email, role, date_creation 
            FROM utilisateurs 
            ORDER BY date_creation DESC";
    $filename = 'utilisateurs_' . date('Y-m-d') . '.csv';
}

// EXÉCUTER LA REQUÊTE
$stmt = $pdo->query($sql);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// CRÉER LE FICHIER CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Ouvrir le flux de sortie
$output = fopen('php://output', 'w');

// Écrire l'en-tête (noms des colonnes)
if (!empty($data)) {
    fputcsv($output, array_keys($data[0]), ';');
    
    // Écrire les données
    foreach ($data as $row) {
        fputcsv($output, $row, ';');
    }
}

fclose($output);
exit();