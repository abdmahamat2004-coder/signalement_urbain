<?php
session_start();

// Vérifier si l'utilisateur est connecté ET est administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: connexion.php');
    exit();
}

// Vérifier si un ID est passé en paramètre
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: liste_sign_admin.php');
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

// Récupérer les détails du signalement avec les infos utilisateur
$sql = "SELECT s.*, u.nom_complet, u.email as user_email 
        FROM signalements s 
        LEFT JOIN utilisateurs u ON s.user_id = u.id 
        WHERE s.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$signalement_id]);
$signalement = $stmt->fetch(PDO::FETCH_ASSOC);

// Si le signalement n'existe pas, rediriger
if (!$signalement) {
    header('Location: liste_sign_admin.php');
    exit();
}

// Déterminer le statut
$status_class = '';
$status_text = '';
switch($signalement['statut']) {
    case 'en_attente':
        $status_class = 'en-attente';
        $status_text = 'En attente';
        break;
    case 'en_cours':
        $status_class = 'en-cours';
        $status_text = 'En cours';
        break;
    case 'resolu':
        $status_class = 'resolu';
        $status_text = 'Résolu';
        break;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Détails du Signalement - Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Leaflet CSS pour la carte -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
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
      max-width: 1200px;
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

    .content-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 25px;
      margin-bottom: 30px;
    }

    @media (max-width: 992px) {
      .content-grid {
        grid-template-columns: 1fr;
      }
    }

    .info-card {
      background: white;
      padding: 25px;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
    }

    .info-card h2 {
      color: var(--primary);
      margin-bottom: 20px;
      padding-bottom: 10px;
      border-bottom: 2px solid #f0f0f0;
      font-size: 1.5rem;
    }

    .info-item {
      margin-bottom: 15px;
      padding-bottom: 15px;
      border-bottom: 1px solid #f5f5f5;
    }

    .info-item:last-child {
      border-bottom: none;
      margin-bottom: 0;
      padding-bottom: 0;
    }

    .info-label {
      font-weight: 600;
      color: var(--dark);
      margin-bottom: 5px;
      display: block;
    }

    .info-value {
      color: var(--gray);
      font-size: 1.1rem;
    }

    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 16px;
      border-radius: 20px;
      font-weight: 600;
      font-size: 0.9rem;
    }

    .en-attente { 
      background-color: #ffeaea;
      color: var(--danger);
    }

    .en-cours { 
      background-color: #fff3cd;
      color: #856404;
    }

    .resolu { 
      background-color: #e7f4e4;
      color: var(--success);
    }

    .status-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
    }

    .en-attente .status-dot { 
      background-color: var(--danger);
    }

    .en-cours .status-dot { 
      background-color: #ffc107;
    }

    .resolu .status-dot { 
      background-color: var(--success);
    }

    .photo-container {
      text-align: center;
      margin-top: 20px;
    }

    .photo-container img {
      max-width: 100%;
      max-height: 400px;
      border-radius: var(--radius);
      border: 1px solid var(--border);
      box-shadow: var(--shadow);
    }

    .no-photo {
      text-align: center;
      padding: 40px 20px;
      color: var(--gray);
      background: #f8f9fa;
      border-radius: var(--radius);
      border: 2px dashed #dee2e6;
    }

    .no-photo i {
      font-size: 3rem;
      margin-bottom: 15px;
      color: #ccc;
    }

    .map-container {
      height: 400px;
      border-radius: var(--radius);
      overflow: hidden;
      border: 1px solid var(--border);
      box-shadow: var(--shadow);
      margin-top: 20px;
    }

    .no-map {
      text-align: center;
      padding: 40px 20px;
      color: var(--gray);
      background: #f8f9fa;
      border-radius: var(--radius);
      border: 2px dashed #dee2e6;
      height: 100%;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
    }

    .no-map i {
      font-size: 3rem;
      margin-bottom: 15px;
      color: #ccc;
    }

    .action-buttons {
      display: flex;
      gap: 15px;
      justify-content: center;
      margin-top: 30px;
    }

    .btn {
      padding: 12px 24px;
      border: none;
      border-radius: var(--radius);
      cursor: pointer;
      transition: var(--transition);
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 1rem;
      text-decoration: none;
    }

    .btn-retour {
      background-color: var(--gray);
      color: white;
    }

    .btn-retour:hover {
      background-color: #5a6268;
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(108, 117, 125, 0.3);
    }

    .btn-modifier {
      background-color: var(--warning);
      color: white;
    }

    .btn-modifier:hover {
      background-color: #e0a800;
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(255, 193, 7, 0.3);
    }
  </style>
</head>
<body>
  <header>
    <div class="header-left">
      
      <a href="tableau_de_bord_admin.php"><i class="fas fa-home"></i></a>
      <span class="header-title">Détails du Signalement</span>
    </div>
    <a href="profil-admin.php"><i class="fas fa-user"></i></a>
  </header>

  <div class="container">
    <div class="title-section">
      <h1>Signalement #<?php echo str_pad($signalement['id'], 3, '0', STR_PAD_LEFT); ?></h1>
      <p>Informations complètes du signalement</p>
    </div>

    <div class="content-grid">
      <!-- Colonne gauche : Informations générales -->
      <div class="info-card">
        <h2>Informations générales</h2>
        
        <div class="info-item">
          <span class="info-label">ID du signalement</span>
          <div class="info-value">#<?php echo str_pad($signalement['id'], 3, '0', STR_PAD_LEFT); ?></div>
        </div>

        <div class="info-item">
          <span class="info-label">Statut</span>
          <div class="info-value">
            <div class="status-badge <?php echo $status_class; ?>">
              <span class="status-dot"></span><?php echo $status_text; ?>
            </div>
          </div>
        </div>

        <div class="info-item">
          <span class="info-label">Titre</span>
          <div class="info-value"><?php echo htmlspecialchars($signalement['titre']); ?></div>
        </div>

        <div class="info-item">
          <span class="info-label">Catégorie</span>
          <div class="info-value"><?php echo htmlspecialchars($signalement['categorie']); ?></div>
        </div>

        <div class="info-item">
          <span class="info-label">Date de signalement</span>
          <div class="info-value"><?php echo date('d/m/Y à H:i', strtotime($signalement['date_signalement'])); ?></div>
        </div>

        <div class="info-item">
          <span class="info-label">Niveau de gravité</span>
          <div class="info-value"><?php echo htmlspecialchars($signalement['niveau'] ?? 'Non spécifié'); ?></div>
        </div>
      </div>

      <!-- Colonne droite : Informations utilisateur et localisation -->
      <div class="info-card">
        <h2>Informations utilisateur</h2>
        
        <div class="info-item">
          <span class="info-label">Nom complet</span>
          <div class="info-value"><?php echo htmlspecialchars($signalement['nom_complet'] ?? 'Anonyme'); ?></div>
        </div>

        <div class="info-item">
          <span class="info-label">Email</span>
          <div class="info-value"><?php echo htmlspecialchars($signalement['user_email'] ?? $signalement['email']); ?></div>
        </div>

        <div class="info-item">
          <span class="info-label">Localisation</span>
          <div class="info-value">
            <?php if (!empty($signalement['rue'])): ?>
              <?php echo htmlspecialchars($signalement['rue']); ?>
              <?php if (!empty($signalement['quartier'])): ?>
                <br><?php echo htmlspecialchars($signalement['quartier']); ?>
              <?php endif; ?>
            <?php else: ?>
              Non spécifiée
            <?php endif; ?>
          </div>
        </div>

        <div class="info-item">
          <span class="info-label">Description</span>
          <div class="info-value" style="white-space: pre-wrap;"><?php echo htmlspecialchars($signalement['description'] ?? 'Aucune description'); ?></div>
        </div>
      </div>
    </div>

    <!-- Photo du signalement -->
    <div class="info-card">
      <h2>Photo du problème</h2>
      <div class="photo-container">
        <?php if (!empty($signalement['photo_path']) && file_exists($signalement['photo_path'])): ?>
          <img src="<?php echo htmlspecialchars($signalement['photo_path']); ?>" 
               alt="Photo du signalement #<?php echo $signalement['id']; ?>">
        <?php else: ?>
          <div class="no-photo">
            <i class="fas fa-camera"></i>
            <p>Aucune photo disponible pour ce signalement</p>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Carte de localisation -->
    <div class="info-card">
      <h2>Localisation sur la carte</h2>
      <div id="map" class="map-container">
        <?php if (!empty($signalement['latitude']) && !empty($signalement['longitude'])): ?>
          <!-- La carte sera chargée par JavaScript -->
        <?php else: ?>
          <div class="no-map">
            <i class="fas fa-map-marker-alt"></i>
            <p>Localisation non disponible</p>
            <p>Aucune coordonnée GPS n'a été enregistrée pour ce signalement.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Boutons d'action -->
    <div class="action-buttons">
      <a href="liste_sign_admin.php" class="btn btn-retour">
        <i class="fas fa-arrow-left"></i> Retour à la liste
      </a>
      <a href="liste_sign_admin.php" class="btn btn-modifier">
        <i class="fas fa-edit"></i> Modifier ce signalement
      </a>
    </div>
  </div>

  <!-- Leaflet JS pour la carte -->
  <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
  
  <script>
    // Initialiser la carte si les coordonnées existent
    <?php if (!empty($signalement['latitude']) && !empty($signalement['longitude'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
      const lat = <?php echo $signalement['latitude']; ?>;
      const lng = <?php echo $signalement['longitude']; ?>;
      
      // Créer la carte
      const map = L.map('map').setView([lat, lng], 16);
      
      // Ajouter les tuiles OpenStreetMap
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
      }).addTo(map);
      
      // Ajouter un marqueur
      L.marker([lat, lng]).addTo(map)
        .bindPopup('Localisation du signalement')
        .openPopup();
    });
    <?php endif; ?>
  </script>
</body>
</html>