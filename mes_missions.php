<?php
session_start();

// Vérifier si l'utilisateur est connecté ET est agent
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'agent') {
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

// Récupérer les missions assignées à CET agent
$agent_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT s.*, u.nom_complet as citoyen_nom 
                        FROM signalements s 
                        LEFT JOIN utilisateurs u ON s.user_id = u.id 
                        WHERE s.agent_id = ?
                        ORDER BY s.date_signalement DESC");
$stmt->execute([$agent_id]);
$missions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes missions - Agent</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 20px;
        }
        
        .header {
            background: linear-gradient(135deg, #28A745 0%, #218838 100%);
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
        
        .missions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .mission-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .mission-card:hover {
            transform: translateY(-5px);
        }
        
        .mission-card h3 {
            color: #28A745;
            margin: 0 0 10px 0;
        }
        
        .mission-info {
            margin: 10px 0;
            color: #666;
        }
        
        .mission-info i {
            width: 20px;
            color: #28A745;
            margin-right: 5px;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .badge.en_attente {
            background: #fdebd0;
            color: #e67e22;
        }
        
        .badge.en_cours {
            background: #d6eaf8;
            color: #3498db;
        }
        
        .badge.resolu {
            background: #d5f4e6;
            color: #27ae60;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 15px;
            background: #28A745;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 15px;
        }
        
        .btn:hover {
            background: #218838;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 64px;
            color: #ccc;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-tasks"></i> Mes missions</h1>
        
    </div>

    <?php if (empty($missions)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h2>Aucune mission assignée</h2>
            <p>Vous n'avez pas encore de missions. L'administrateur vous assignera bientôt des signalements.</p>
        </div>
    <?php else: ?>
        <div class="missions-grid">
            <?php foreach ($missions as $m): ?>
                <div class="mission-card">
                    <h3><?php echo htmlspecialchars($m['titre']); ?></h3>
                    <div class="mission-info">
                        <i class="fas fa-tag"></i> <?php echo htmlspecialchars($m['categorie']); ?>
                    </div>
                    <div class="mission-info">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($m['citoyen_nom'] ?? 'Anonyme'); ?>
                    </div>
                    <div class="mission-info">
                        <i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($m['date_signalement'])); ?>
                    </div>
                    <div class="mission-info">
                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($m['rue'] ?? 'Non spécifiée'); ?>
                    </div>
                    
                    <span class="badge <?php echo $m['statut']; ?>">
                        <?php 
                            if ($m['statut'] == 'en_attente') echo 'En attente';
                            elseif ($m['statut'] == 'en_cours') echo 'En cours';
                            else echo 'Résolu';
                        ?>
                    </span>
                    
                    <div style="margin-top: 15px;">
                        <a href="detail_mission_agent.php=<?php echo $m['id']; ?>" class="btn">
    <i class="fas fa-eye"></i> Voir détails
</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</body>
</html>