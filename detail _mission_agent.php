<?php
session_start();

// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Vérifier si l'utilisateur est connecté ET est agent
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'agent') {
    header('Location: connexion.php');
    exit();
}

// Vérifier si un ID est passé en paramètre
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: mes_missions.php');
    exit();
}

$signalement_id = $_GET['id'];

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

// Récupérer les détails du signalement
$sql = "SELECT s.*, u.nom_complet, u.email as user_email 
        FROM signalements s 
        LEFT JOIN utilisateurs u ON s.user_id = u.id 
        WHERE s.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$signalement_id]);
$signalement = $stmt->fetch(PDO::FETCH_ASSOC);

// Si le signalement n'existe pas
if (!$signalement) {
    echo "Erreur : Signalement avec l'ID " . $signalement_id . " non trouvé.";
    exit();
}

// Vérifier que ce signalement est bien assigné à cet agent
if ($signalement['agent_id'] != $_SESSION['user_id']) {
    echo "Erreur : Ce signalement ne vous est pas assigné.";
    exit();
}

// Traitement du changement de statut
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['changer_statut'])) {
        $nouveau_statut = $_POST['nouveau_statut'];
        $stmt = $pdo->prepare("UPDATE signalements SET statut = ? WHERE id = ?");
        $stmt->execute([$nouveau_statut, $signalement_id]);
        
        // Recharger les données
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$signalement_id]);
        $signalement = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (isset($_POST['upload_photo']) && isset($_FILES['photo_resolution']) && $_FILES['photo_resolution']['error'] === 0) {
        
        if (!file_exists('uploads')) {
            mkdir('uploads', 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['photo_resolution']['name'], PATHINFO_EXTENSION);
        $new_filename = 'resolu_' . time() . '_' . $signalement_id . '.' . $file_extension;
        $photo_path = 'uploads/' . $new_filename;
        
        if (move_uploaded_file($_FILES['photo_resolution']['tmp_name'], $photo_path)) {
            $stmt = $pdo->prepare("UPDATE signalements SET photo_resolution = ?, statut = 'resolu' WHERE id = ?");
            $stmt->execute([$photo_path, $signalement_id]);
            
            // Recharger les données
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$signalement_id]);
            $signalement = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}

// Déterminer le texte du statut
$status_text = '';
switch($signalement['statut']) {
    case 'en_attente':
        $status_text = 'En attente';
        break;
    case 'en_cours':
        $status_text = 'En cours';
        break;
    case 'resolu':
        $status_text = 'Résolu';
        break;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Mission #<?php echo $signalement['id']; ?></title>
    <style>
        body { font-family: Arial; background: #f0f0f0; padding: 20px; }
        .container { max-width: 800px; margin: auto; background: white; padding: 20px; border-radius: 10px; }
        .header { background: #28a745; color: white; padding: 15px; border-radius: 5px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .header a { color: white; text-decoration: none; background: rgba(255,255,255,0.2); padding: 8px 15px; border-radius: 5px; }
        .info-box { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .info-box p { margin: 10px 0; }
        .status-badge { display: inline-block; padding: 5px 15px; border-radius: 15px; font-weight: bold; }
        .en-attente { background: #fdebd0; color: #e67e22; }
        .en-cours { background: #d6eaf8; color: #3498db; }
        .resolu { background: #d5f4e6; color: #27ae60; }
        .photo-container { text-align: center; margin: 20px 0; }
        .photo-container img { max-width: 100%; max-height: 300px; border: 1px solid #ddd; border-radius: 5px; }
        .form-group { margin: 15px 0; }
        select, input[type="file"], button { padding: 10px; font-size: 16px; border-radius: 5px; border: 1px solid #ddd; }
        button { background: #28a745; color: white; border: none; cursor: pointer; }
        button:hover { background: #218838; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Mission #<?php echo str_pad($signalement['id'], 3, '0', STR_PAD_LEFT); ?></h2>
            <a href="mes_missions.php">← Retour</a>
        </div>
        
        <div class="info-box">
            <p><strong>Titre :</strong> <?php echo htmlspecialchars($signalement['titre']); ?></p>
            <p><strong>Catégorie :</strong> <?php echo htmlspecialchars($signalement['categorie']); ?></p>
            <p><strong>Adresse :</strong> <?php echo htmlspecialchars($signalement['rue'] ?? 'Non spécifiée'); ?></p>
            <p><strong>Description :</strong> <?php echo nl2br(htmlspecialchars($signalement['description'] ?? '')); ?></p>
            <p><strong>Statut :</strong> 
                <span class="status-badge <?php echo $signalement['statut']; ?>">
                    <?php echo $status_text; ?>
                </span>
            </p>
        </div>
        
        <div class="photo-container">
            <h3>Photo du problème</h3>
            <?php if (!empty($signalement['photo_path']) && file_exists($signalement['photo_path'])): ?>
                <img src="<?php echo $signalement['photo_path']; ?>" alt="Photo problème">
            <?php else: ?>
                <p>Aucune photo</p>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($signalement['photo_resolution']) && file_exists($signalement['photo_resolution'])): ?>
        <div class="photo-container">
            <h3>Photo après résolution</h3>
            <img src="<?php echo $signalement['photo_resolution']; ?>" alt="Photo résolution">
        </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <h3>Changer le statut</h3>
            <div class="form-group">
                <select name="nouveau_statut">
                    <option value="en_attente" <?php echo $signalement['statut']=='en_attente'?'selected':''; ?>>En attente</option>
                    <option value="en_cours" <?php echo $signalement['statut']=='en_cours'?'selected':''; ?>>En cours</option>
                    <option value="resolu" <?php echo $signalement['statut']=='resolu'?'selected':''; ?>>Résolu</option>
                </select>
                <button type="submit" name="changer_statut">Mettre à jour</button>
            </div>
        </form>
        
        <?php if ($signalement['statut'] != 'resolu'): ?>
        <form method="POST" action="" enctype="multipart/form-data">
            <h3>Ajouter une photo après résolution</h3>
            <div class="form-group">
                <input type="file" name="photo_resolution" accept="image/*" required>
                <button type="submit" name="upload_photo">Télécharger</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>