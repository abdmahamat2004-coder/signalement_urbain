<?php
session_start();

// Vérifier si l'utilisateur est connecté ET est administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: connexion.php');
    exit();
}

// Sécurité : empêcher la mise en cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

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

// COMPTER LES STATISTIQUES POUR L'ADMIN
// 1. Nombre total d'utilisateurs
$stmt_users = $pdo->query("SELECT COUNT(*) FROM utilisateurs");
$total_users = $stmt_users->fetchColumn();

// 2. Nombre total de signalements
$stmt_signalements = $pdo->query("SELECT COUNT(*) FROM signalements");
$total_signalements = $stmt_signalements->fetchColumn();

// 3. Signalements en attente
$stmt_attente = $pdo->query("SELECT COUNT(*) FROM signalements WHERE statut = 'en_attente'");
$signalements_attente = $stmt_attente->fetchColumn();

// 4. Signalements résolus
$stmt_resolus = $pdo->query("SELECT COUNT(*) FROM signalements WHERE statut = 'resolu'");
$signalements_resolus = $stmt_resolus->fetchColumn();

// 5. Signalements en cours
$stmt_cours = $pdo->query("SELECT COUNT(*) FROM signalements WHERE statut = 'en_cours'");
$signalements_cours = $stmt_cours->fetchColumn();

// 5. Récupérer les derniers signalements (pour le tableau)
$stmt_derniers = $pdo->query("SELECT s.*, u.nom_complet FROM signalements s LEFT JOIN utilisateurs u ON s.user_id = u.id ORDER BY s.date_signalement DESC LIMIT 5");
$derniers_signalements = $stmt_derniers->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">         
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Tableau de bord Administrateur</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  
  <!-- Même style que le tableau de bord utilisateur -->
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: Arial, sans-serif;
      background-color: #f5f7fa;
      display: flex;
      min-height: 100vh;
    }

    /* Menu latéral ADMIN */
    .sidebar {
      width: 250px;
      background: linear-gradient(180deg, #2c3e50 0%, #1a252f 100%);
      color: white;
      position: fixed;
      height: 100vh;
      transition: transform 0.3s ease;
      z-index: 1000;
    }

    .sidebar.hidden {
      transform: translateX(-100%);
    }

    .sidebar ul {
      list-style: none;
      padding: 20px 0;
    }

    .sidebar li {
      padding: 0;
    }

    .sidebar a {
      display: flex;
      align-items: center;
      color: #ecf0f1;
      text-decoration: none;
      padding: 15px 25px;
      transition: all 0.3s;
      border-left: 4px solid transparent;
    }

    .sidebar a:hover {
      background-color: #34495e;
      border-left: 4px solid #3498db;
      color: white;
    }

    .sidebar a i {
      margin-right: 15px;
      width: 20px;
      text-align: center;
    }

    .sidebar .logout a {
      color: #e74c3c;
      margin-top: 30px;
      border-left: 4px solid transparent;
    }

    .sidebar .logout a:hover {
      background-color: #c0392b;
      color: white;
      border-left: 4px solid #e74c3c;
    }

    /* Icône du menu */
    #menu-icon {
      position: fixed;
      top: 20px;
      left: 20px;
      background: #2c3e50;
      color: white;
      width: 50px;
      height: 50px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      z-index: 999;
      box-shadow: 0 2px 10px rgba(0,0,0,0.2);
      transition: all 0.3s;
    }

    #menu-icon:hover {
      background: #34495e;
      transform: scale(1.05);
    }

    /* Contenu principal */
    .main-content {
      flex: 1;
      padding: 20px;
      margin-left: 250px;
      transition: margin-left 0.3s ease;
    }

    .sidebar.hidden + .main-content {
      margin-left: 0;
    }

    /* En-tête */
    .top-bar {
      background: white;
      padding: 20px 30px;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
      margin-bottom: 30px;
    }

    .top-bar h1 {
      color: #2c3e50;
      font-size: 1.8rem;
      font-weight: 600;
    }

    /* Cartes statistiques - PLUS DE COULEURS POUR ADMIN */
    .cards {
      display: flex;
      gap: 20px;
      margin-bottom: 30px;
      flex-wrap: wrap;
    }

    .card {
      flex: 1;
      min-width: 200px;
      padding: 25px;
      border-radius: 10px;
      color: white;
      font-size: 1.5rem;
      font-weight: bold;
      text-align: center;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      transition: transform 0.3s;
    }

    .card:hover {
      transform: translateY(-5px);
    }

    .card.blue {
      background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    }

    .card.purple {
      background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
    }

    .card.orange {
      background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
    }

    .card.green {
      background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
    }
    
    .card.cyan {
  background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
  color: white;
}
    /* Barre de recherche */
    .search-bar {
      position: relative;
      margin-bottom: 30px;
      max-width: 500px;
    }

    .search-bar input {
      width: 100%;
      padding: 15px 20px 15px 50px;
      border: 2px solid #e0e0e0;
      border-radius: 10px;
      font-size: 16px;
      transition: all 0.3s;
    }

    .search-bar input:focus {
      outline: none;
      border-color: #3498db;
      box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
    }

    .search-bar i {
      position: absolute;
      left: 20px;
      top: 50%;
      transform: translateY(-50%);
      color: #7f8c8d;
    }

    /* Tableau */
    .signalement-table {
      width: 100%;
      background: white;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }

    .signalement-table thead {
      background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
      color: white;
    }

    .signalement-table th {
      padding: 20px;
      text-align: left;
      font-weight: 600;
    }

    .signalement-table tbody tr {
      border-bottom: 1px solid #f0f0f0;
      transition: background-color 0.3s;
    }

    .signalement-table tbody tr:hover {
      background-color: #f8f9fa;
    }

    .signalement-table td {
      padding: 20px;
      color: #2c3e50;
    }

    /* Badges */
    .badge {
      padding: 8px 16px;
      border-radius: 20px;
      font-size: 14px;
      font-weight: 600;
      text-transform: uppercase;
    }

    .badge.green {
      background-color: #d5f4e6;
      color: #27ae60;
    }

    .badge.orange {
      background-color: #fdebd0;
      color: #e67e22;
    }

    .badge.blue {
      background-color: #d6eaf8;
      color: #3498db;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .sidebar {
        width: 200px;
      }
      
      .main-content {
        margin-left: 200px;
        padding: 15px;
      }
      
      .sidebar.hidden + .main-content {
        margin-left: 0;
      }
      
      .cards {
        flex-direction: column;
      }
      
      .card {
        min-width: 100%;
      }
      
      .search-bar {
        max-width: 100%;
      }
    }

    @media (max-width: 480px) {
      #menu-icon {
        top: 10px;
        left: 10px;
        width: 40px;
        height: 40px;
      }
      
      .top-bar {
        padding: 15px 20px;
      }
      
      .top-bar h1 {
        font-size: 1.5rem;
      }
      
      .signalement-table th,
      .signalement-table td {
        padding: 12px;
        font-size: 14px;
      }
      .card.cyan {
  background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
}
    }
  </style>
</head>
<body>

  <!-- Icône du menu -->
  <div id="menu-icon">
    <i class="fas fa-bars"></i>
  </div>

  <!-- Menu latéral ADMIN -->
  <aside class="sidebar hidden" id="sidebar">
    <ul>
      <li><a href="tableau_de_bord_admin.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
      <li><a href="liste_sign_admin.php"><i class="fas fa-list-alt"></i> Liste des signalements</a></li>
      <li><a href="liste_utilisateurs.php"><i class="fas fa-users"></i> Gestion utilisateurs</a></li>
      <li><a href="statistiques.php"><i class="fas fa-chart-bar"></i> Statistiques</a></li>
      <li><a href="export.php?type=signalements"><i class="fas fa-file-export"></i> Exporter signalements</a></li>
      <li><a href="export.php?type=utilisateurs"><i class="fas fa-user-export"></i> Exporter utilisateurs</a></li>
      <li><a href="profil-admin.php"><i class="fas fa-user-cog"></i> Profil Admin</a></li>
      <li class="logout"><a href="deconnexion.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
    </ul>
  </aside>

  <!-- Contenu principal -->
  <main class="main-content" id="main-content">
    <!-- En-tête -->
    <div class="top-bar">
      <h1>Tableau de bord Administrateur</h1>
      <p style="color: #666; margin-top: 10px; font-size: 0.9rem;">
        Bienvenue, <strong><?php echo htmlspecialchars($_SESSION['user_nom']); ?></strong> (Administrateur)
      </p>
    </div>

    <!-- Cartes statistiques POUR ADMIN -->
    <div class="cards">
  <div class="card blue">Utilisateurs: <?php echo $total_users; ?></div>
  <div class="card purple">Signalements: <?php echo $total_signalements; ?></div>
  <div class="card orange">En attente: <?php echo $signalements_attente; ?></div>
  <div class="card cyan">En cours: <?php echo $signalements_cours; ?></div>
  <div class="card green">Résolus: <?php echo $signalements_resolus; ?></div>
</div>

    <!-- Barre de recherche -->
    <div class="search-bar">
      <input type="text" id="search" placeholder="Rechercher un signalement...">
      <i class="fas fa-search"></i>
    </div>

    <!-- Tableau des derniers signalements -->
    <table class="signalement-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Titre</th>
          <th>Utilisateur</th>
          <th>Date</th>
          <th>Statut</th>
        </tr>
      </thead>
      <tbody id="table-content">
        <?php if (empty($derniers_signalements)): ?>
        <tr>
          <td colspan="5" style="text-align: center; padding: 40px; color: #7f8c8d;">
            <i class="fas fa-inbox" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
            Aucun signalement pour le moment
          </td>
        </tr>
        <?php else: ?>
        
        <?php foreach ($derniers_signalements as $signalement): 
          $status_class = $signalement['statut'] == 'resolu' ? 'badge green' : 'badge orange';
          $status_text = $signalement['statut'] == 'resolu' ? 'Résolu' : 'En attente';
        ?>
        <tr>
          <td>#<?php echo str_pad($signalement['id'], 3, '0', STR_PAD_LEFT); ?></td>
          <td><?php echo htmlspecialchars($signalement['titre']); ?></td>
          <td><?php echo htmlspecialchars($signalement['nom_complet'] ?? 'Anonyme'); ?></td>
          <td><?php echo date('d/m/Y', strtotime($signalement['date_signalement'])); ?></td>
          <td><span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
        </tr>
        <?php endforeach; ?>
        
        <?php endif; ?>
      </tbody>
    </table>
  </main>

  <!-- JavaScript (identique à votre code) -->
  <script>
    const menuIcon = document.getElementById('menu-icon');
    const sidebar = document.getElementById('sidebar');

    menuIcon.addEventListener('click', (e) => {
      e.stopPropagation();
      sidebar.classList.toggle('hidden');
    });

    document.addEventListener('click', (e) => {
      if (!sidebar.contains(e.target) && !menuIcon.contains(e.target)) {
        sidebar.classList.add('hidden');
      }
    });

    const searchInput = document.getElementById("search");
    const tableRows = document.querySelectorAll("#table-content tr");

    if (searchInput && tableRows.length > 0) {
      searchInput.addEventListener("keyup", function () {
        const value = searchInput.value.toLowerCase();
        tableRows.forEach(row => {
          const text = row.textContent.toLowerCase();
          row.style.display = text.includes(value) ? "" : "none";
        });
      });
    }
  </script>

</body>
</html>