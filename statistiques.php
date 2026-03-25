<?php
session_start();

// Empêcher la mise en cache pour les pages sensibles
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

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

// RÉCUPÉRER LES STATISTIQUES
// 1. Statistiques générales
$total_utilisateurs = $pdo->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn();
$total_signalements = $pdo->query("SELECT COUNT(*) FROM signalements")->fetchColumn();
$signalements_resolus = $pdo->query("SELECT COUNT(*) FROM signalements WHERE statut = 'resolu'")->fetchColumn();
$taux_resolution = $total_signalements > 0 ? round(($signalements_resolus / $total_signalements) * 100, 1) : 0;

// 2. Signalements par catégorie
$categories = $pdo->query("SELECT categorie, COUNT(*) as count FROM signalements GROUP BY categorie ORDER BY count DESC")->fetchAll(PDO::FETCH_ASSOC);

// 3. Signalements par statut
$statuts = $pdo->query("SELECT statut, COUNT(*) as count FROM signalements GROUP BY statut")->fetchAll(PDO::FETCH_ASSOC);

// 4. Évolution mensuelle des signalements
$evolution = $pdo->query("SELECT DATE_FORMAT(date_signalement, '%Y-%m') as mois, COUNT(*) as count 
                          FROM signalements 
                          GROUP BY DATE_FORMAT(date_signalement, '%Y-%m') 
                          ORDER BY mois DESC 
                          LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);

// 5. Top utilisateurs (ceux qui ont fait le plus de signalements)
$top_utilisateurs = $pdo->query("SELECT u.nom_complet, COUNT(s.id) as nb_signalements 
                                 FROM utilisateurs u 
                                 LEFT JOIN signalements s ON u.id = s.user_id 
                                 GROUP BY u.id 
                                 ORDER BY nb_signalements DESC 
                                 LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Statistiques - Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Chart.js pour les graphiques -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root {
      --primary: #007BFF;
      --primary-dark: #0056b3;
      --success: #28a745;
      --warning: #ffc107;
      --danger: #dc3545;
      --light: #f8f9fa;
      --dark: #343a40;
      --gray: #6c757d;
      --border: #dee2e6;
      --shadow: 0 4px 6px rgba(0,0,0,0.1);
      --radius: 8px;
      --transition: all 0.3s ease;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
      margin: 0;
      padding: 0;
      min-height: 100vh;
      color: #333;
      line-height: 1.6;
    }

    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: linear-gradient(90deg, var(--primary) 0%, var(--primary-dark) 100%);
      padding: 15px 20px;
      color: white;
      box-shadow: var(--shadow);
      position: sticky;
      top: 0;
      z-index: 100;
    }

    .header-left {
      display: flex;
      align-items: center;
      gap: 20px;
    }

    header a {
      color: white;
      text-decoration: none;
      font-size: 18px;
      transition: var(--transition);
      padding: 8px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 40px;
      height: 40px;
    }

    header a:hover {
      background-color: rgba(255, 255, 255, 0.2);
      transform: scale(1.1);
    }

    .header-title {
      font-size: 1.2rem;
      font-weight: 600;
    }

    .container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 20px;
    }

    .title-section {
      background: white;
      padding: 25px;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      margin-bottom: 25px;
      text-align: center;
    }

    .title-section h1 {
      margin: 0;
      font-size: 2rem;
      color: var(--primary);
      margin-bottom: 10px;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 25px;
      margin-bottom: 30px;
    }

    .stat-card {
      background: white;
      padding: 25px;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
    }

    .stat-card h2 {
      color: var(--primary);
      margin-bottom: 20px;
      padding-bottom: 10px;
      border-bottom: 2px solid #f0f0f0;
      font-size: 1.3rem;
    }

    .chart-container {
      height: 300px;
      margin-top: 20px;
    }

    .stat-number {
      font-size: 2.5rem;
      font-weight: bold;
      color: var(--primary);
      text-align: center;
      margin: 20px 0;
    }

    .stat-label {
      text-align: center;
      color: var(--gray);
      font-size: 1rem;
    }

    .list-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px 0;
      border-bottom: 1px solid #f0f0f0;
    }

    .list-item:last-child {
      border-bottom: none;
    }

    .badge {
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 600;
    }

    .badge-primary {
      background-color: #e3f2fd;
      color: #1976d2;
    }

    .badge-success {
      background-color: #d4edda;
      color: #155724;
    }

    .badge-warning {
      background-color: #fff3cd;
      color: #856404;
    }

    .badge-danger {
      background-color: #f8d7da;
      color: #721c24;
    }

    @media (max-width: 768px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }
      
      .container {
        padding: 15px;
      }
    }
  </style>
</head>
<body>
  <header>
    <div class="header-left">
      
      <a href="tableau_de_bord_admin.php"><i class="fas fa-home"></i></a>
      <span class="header-title">Statistiques de la plateforme</span>
    </div>
    <a href="profil-admin.php"><i class="fas fa-user"></i></a>
  </header>

  <div class="container">
    <div class="title-section">
      <h1>Statistiques de la Plateforme</h1>
      <p>Analyse des données et performances du système</p>
    </div>

    <div class="stats-grid">
      <!-- Carte 1 : Statistiques générales -->
      <div class="stat-card">
        <h2><i class="fas fa-chart-bar"></i> Vue d'ensemble</h2>
        <div class="stat-number"><?php echo $total_utilisateurs; ?></div>
        <div class="stat-label">Utilisateurs inscrits</div>
        
        <div class="stat-number"><?php echo $total_signalements; ?></div>
        <div class="stat-label">Signalements totaux</div>
        
        <div class="stat-number"><?php echo $taux_resolution; ?>%</div>
        <div class="stat-label">Taux de résolution</div>
      </div>

      <!-- Carte 2 : Signalements par catégorie -->
      <div class="stat-card">
        <h2><i class="fas fa-list"></i> Par catégorie</h2>
        <div class="chart-container">
          <canvas id="categorieChart"></canvas>
        </div>
      </div>

      <!-- Carte 3 : Signalements par statut -->
      <div class="stat-card">
        <h2><i class="fas fa-tasks"></i> Par statut</h2>
        <div class="chart-container">
          <canvas id="statutChart"></canvas>
        </div>
      </div>

      <!-- Carte 4 : Top utilisateurs -->
      <div class="stat-card">
        <h2><i class="fas fa-trophy"></i> Top contributeurs</h2>
        <?php foreach ($top_utilisateurs as $index => $user): ?>
        <div class="list-item">
          <div>
            <strong><?php echo $index + 1; ?>. <?php echo htmlspecialchars($user['nom_complet']); ?></strong>
            <div style="font-size: 0.8rem; color: var(--gray);">
              <?php echo $user['nb_signalements']; ?> signalement(s)
            </div>
          </div>
          <span class="badge badge-primary">N°<?php echo $index + 1; ?></span>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($top_utilisateurs)): ?>
        <p style="text-align: center; color: var(--gray); padding: 20px;">
          Aucune donnée disponible
        </p>
        <?php endif; ?>
      </div>

      <!-- Carte 5 : Évolution mensuelle -->
      <div class="stat-card">
        <h2><i class="fas fa-chart-line"></i> Évolution (6 derniers mois)</h2>
        <div class="chart-container">
          <canvas id="evolutionChart"></canvas>
        </div>
      </div>

      <!-- Carte 6 : Détails des statuts -->
      <div class="stat-card">
        <h2><i class="fas fa-info-circle"></i> Détails des statuts</h2>
        <?php foreach ($statuts as $statut): 
          $statut_nom = $statut['statut'] == 'en_attente' ? 'En attente' : 
                       ($statut['statut'] == 'en_cours' ? 'En cours' : 'Résolu');
          $badge_class = $statut['statut'] == 'en_attente' ? 'badge-danger' : 
                        ($statut['statut'] == 'en_cours' ? 'badge-warning' : 'badge-success');
        ?>
        <div class="list-item">
          <span><?php echo $statut_nom; ?></span>
          <span class="badge <?php echo $badge_class; ?>"><?php echo $statut['count']; ?></span>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($statuts)): ?>
        <p style="text-align: center; color: var(--gray); padding: 20px;">
          Aucun signalement pour le moment
        </p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script>
    // Données pour les graphiques
    const categoriesData = {
      labels: <?php echo json_encode(array_column($categories, 'categorie')); ?>,
      datasets: [{
        data: <?php echo json_encode(array_column($categories, 'count')); ?>,
        backgroundColor: [
          '#007BFF', '#28a745', '#ffc107', '#dc3545', '#6c757d', '#17a2b8'
        ]
      }]
    };

    const statutsData = {
      labels: <?php echo json_encode(array_map(function($s) { 
        return $s['statut'] == 'en_attente' ? 'En attente' : 
              ($s['statut'] == 'en_cours' ? 'En cours' : 'Résolu'); 
      }, $statuts)); ?>,
      datasets: [{
        data: <?php echo json_encode(array_column($statuts, 'count')); ?>,
        backgroundColor: [
          '#dc3545', '#ffc107', '#28a745'
        ]
      }]
    };

    const evolutionData = {
      labels: <?php echo json_encode(array_column($evolution, 'mois')); ?>,
      datasets: [{
        label: 'Nombre de signalements',
        data: <?php echo json_encode(array_column($evolution, 'count')); ?>,
        borderColor: '#007BFF',
        backgroundColor: 'rgba(0, 123, 255, 0.1)',
        fill: true
      }]
    };

    // Initialiser les graphiques
    document.addEventListener('DOMContentLoaded', function() {
      // Graphique des catégories
      new Chart(document.getElementById('categorieChart'), {
        type: 'doughnut',
        data: categoriesData,
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom'
            }
          }
        }
      });

      // Graphique des statuts
      new Chart(document.getElementById('statutChart'), {
        type: 'pie',
        data: statutsData,
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom'
            }
          }
        }
      });

      // Graphique d'évolution
      new Chart(document.getElementById('evolutionChart'), {
        type: 'line',
        data: evolutionData,
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: {
              beginAtZero: true
            }
          }
        }
      });
    });
  </script>
</body>
</html>