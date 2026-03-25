<?php
// Fichier temporaire pour créer un administrateur - À SUPPRIMER APRÈS !

echo "<h2>Création d'un administrateur</h2>";

// Hash du mot de passe "admin123"
$password = "admin123";
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<p><strong>Mot de passe :</strong> $password</p>";
echo "<p><strong>Hash généré :</strong> $hash</p>";

// Connexion à la base de données
$host = 'localhost';
$dbname = 'mon_app';
$username = 'root';
$password_db = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Insérer l'administrateur
    $stmt = $pdo->prepare("INSERT INTO utilisateurs (nom_complet, email, mot_de_passe, role, date_creation) 
                           VALUES (?, ?, ?, 'admin', NOW())");
    
    $nom = "Administrateur Ville";
    $email = "ville@admin.fr";
    
    $stmt->execute([$nom, $email, $hash]);
    
    echo "<h3 style='color:green;'>✅ Administrateur créé avec succès !</h3>";
    echo "<p><strong>Email :</strong> $email</p>";
    echo "<p><strong>Mot de passe :</strong> admin123</p>";
    echo "<p><a href='connexion.php'>Aller à la page de connexion</a></p>";
    
} catch(PDOException $e) {
    echo "<p style='color:red;'>Erreur : " . $e->getMessage() . "</p>";
}
?>