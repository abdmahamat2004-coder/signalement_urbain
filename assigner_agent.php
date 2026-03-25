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

// Vérifier si un ID de signalement est passé
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: liste_sign_admin.php');
    exit();
}

$signalement_id = $_GET['id'];

// Récupérer les informations du signalement
$stmt = $pdo->prepare("SELECT * FROM signalements WHERE id = ?");
$stmt->execute([$signalement_id]);
$signalement = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$signalement) {
    header('Location: liste_sign_admin.php');
    exit();
}

// Récupérer la liste des agents
$stmt_agents = $pdo->prepare("SELECT id, nom_complet, email FROM utilisateurs WHERE role = 'agent' ORDER BY nom_complet");
$stmt_agents->execute();
$agents = $stmt_agents->fetchAll(PDO::FETCH_ASSOC);

// Traitement du formulaire d'assignation
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assigner'])) {
    $agent_id = $_POST['agent_id'];
    
    try {
        $stmt = $pdo->prepare("UPDATE signalements SET agent_id = ? WHERE id = ?");
        $stmt->execute([$agent_id, $signalement_id]);
        
        $message = "✅ Mission assignée avec succès !";
        
        // Recharger les informations
        $stmt = $pdo->prepare("SELECT * FROM signalements WHERE id = ?");
        $stmt->execute([$signalement_id]);
        $signalement = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch(PDOException $e) {
        $message = "❌ Erreur : " . $e->getMessage();
    }
}

// Traitement pour enlever l'assignation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enlever'])) {
    try {
        $stmt = $pdo->prepare("UPDATE signalements SET agent_id = NULL WHERE id = ?");
        $stmt->execute([$signalement_id]);
        
        $message = "✅ Assignation retirée !";
        
        // Recharger les informations
        $stmt = $pdo->prepare("SELECT * FROM signalements WHERE id = ?");
        $stmt->execute([$signalement_id]);
        $signalement = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch(PDOException $e) {
        $message = "❌ Erreur : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assigner une mission - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .header {
            background: linear-gradient(135deg, #007BFF 0%, #0056b3 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header a {
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 5px;
            background: rgba(255,255,255,0.2);
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .card h2 {
            color: #007BFF;
            margin-top: 0;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
        }
        
        .info-item {
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .info-label {
            font-weight: bold;
            color: #666;
            display: block;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #333;
            font-size: 1.1rem;
        }
        
        .current-agent {
            background: #e3f2fd;
            color: #007BFF;
            padding: 10px;
            border-radius: 5px;
            margin: 20px 0;
        }
        
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            margin-bottom: 20px;
        }
        
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
        }
        
        .btn-primary {
            background: #007BFF;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-user-tie"></i> Assigner une mission</h1>
            
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Signalement #<?php echo str_pad($signalement['id'], 3, '0', STR_PAD_LEFT); ?></h2>
            
            <div class="info-item">
                <span class="info-label">Titre :</span>
                <span class="info-value"><?php echo htmlspecialchars($signalement['titre']); ?></span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Catégorie :</span>
                <span class="info-value"><?php echo htmlspecialchars($signalement['categorie']); ?></span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Rue :</span>
                <span class="info-value"><?php echo htmlspecialchars($signalement['rue'] ?? 'Non spécifiée'); ?></span>
            </div>
            
            <?php if (!empty($signalement['agent_id'])): ?>
                <?php
                // Récupérer le nom de l'agent assigné
                $stmt_agent = $pdo->prepare("SELECT nom_complet FROM utilisateurs WHERE id = ?");
                $stmt_agent->execute([$signalement['agent_id']]);
                $agent_assigné = $stmt_agent->fetchColumn();
                ?>
                <div class="current-agent">
                    <i class="fas fa-check-circle"></i> 
                    Actuellement assigné à : <strong><?php echo htmlspecialchars($agent_assigné ?: 'Agent inconnu'); ?></strong>
                </div>
            <?php endif; ?>
            
            <h3>Choisir un agent</h3>
            
            <form method="POST" action="">
                <select name="agent_id" required>
                    <option value="">-- Sélectionnez un agent --</option>
                    <?php foreach ($agents as $agent): ?>
                        <option value="<?php echo $agent['id']; ?>" <?php echo ($signalement['agent_id'] == $agent['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($agent['nom_complet'] . ' (' . $agent['email'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" name="assigner" class="btn btn-primary">
                    <i class="fas fa-check"></i> Assigner cette mission
                </button>
                
                <?php if (!empty($signalement['agent_id'])): ?>
                    <button type="submit" name="enlever" class="btn btn-danger" onclick="return confirm('Retirer l\'assignation ?')">
                        <i class="fas fa-times"></i> Retirer l'assignation
                    </button>
                <?php endif; ?>
                
                <a href="liste_sign_admin.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Annuler
                </a>
            </form>
        </div>
    </div>
</body>
</html>