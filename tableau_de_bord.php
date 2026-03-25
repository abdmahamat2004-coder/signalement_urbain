<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
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

// COMPTER LES SIGNALEMENTS de l'utilisateur connecté
$user_id = $_SESSION['user_id'];

$stmt_attente = $pdo->prepare("SELECT COUNT(*) FROM signalements WHERE user_id = ? AND statut = 'en_attente'");
$stmt_attente->execute([$user_id]);
$en_attente = $stmt_attente->fetchColumn();

$stmt_cours = $pdo->prepare("SELECT COUNT(*) FROM signalements WHERE user_id = ? AND statut = 'en_cours'");
$stmt_cours->execute([$user_id]);
$en_cours = $stmt_cours->fetchColumn();

$stmt_resolus = $pdo->prepare("SELECT COUNT(*) FROM signalements WHERE user_id = ? AND statut = 'resolu'");
$stmt_resolus->execute([$user_id]);
$resolus = $stmt_resolus->fetchColumn();

// Récupérer UNIQUEMENT les signalements de l'utilisateur connecté
$stmt_mes_signalements = $pdo->prepare("SELECT s.*, u.nom_complet 
                                         FROM signalements s 
                                         LEFT JOIN utilisateurs u ON s.user_id = u.id 
                                         WHERE s.user_id = ?
                                         ORDER BY s.date_signalement DESC");
$stmt_mes_signalements->execute([$user_id]);
$mes_signalements = $stmt_mes_signalements->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">         
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Tableau de bord Utilisateur</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
  
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

    /* Menu latéral */
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

    /* Cartes statistiques */
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

    .card.orange {
      background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
    }

    .card.blue {
      background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    }

    .card.green {
      background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
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
    .table-container {
      overflow-x: auto;
      background: white;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }

    .signalement-table {
      width: 100%;
      min-width: 800px;
      border-collapse: collapse;
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
      display: inline-block;
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

    /* Bouton Voir */
    .btn-voir {
      background: #28a745;
      color: white;
      border: none;
      padding: 8px 12px;
      border-radius: 5px;
      cursor: pointer;
      font-size: 13px;
    }

    .btn-voir:hover {
      background: #218838;
    }

    /* Message quand aucun signalement */
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: #7f8c8d;
    }

    .empty-state i {
      font-size: 48px;
      margin-bottom: 15px;
      color: #ccc;
    }

    .empty-state a {
      display: inline-block;
      margin-top: 20px;
      padding: 12px 25px;
      background: #3498db;
      color: white;
      text-decoration: none;
      border-radius: 5px;
      transition: background 0.3s;
    }

    .empty-state a:hover {
      background: #2980b9;
    }

    /* MODAL */
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    }

    .modal-content {
      background: white;
      width: 90%;
      max-width: 900px;
      margin: 30px auto;
      padding: 20px;
      border-radius: 10px;
      max-height: 90vh;
      overflow-y: auto;
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid #eee;
      padding-bottom: 15px;
      margin-bottom: 20px;
    }

    .close-modal {
      background: none;
      border: none;
      font-size: 24px;
      cursor: pointer;
      color: #666;
    }

    .detail-item {
      margin-bottom: 15px;
      padding: 10px;
      background: #f8f9fa;
      border-radius: 5px;
    }

    .detail-item strong {
      color: #28a745;
      display: block;
      margin-bottom: 5px;
    }

    .photo-container {
      display: flex;
      gap: 20px;
      margin: 20px 0;
    }

    .photo-box {
      flex: 1;
      text-align: center;
      padding: 15px;
      background: #f8f9fa;
      border-radius: 5px;
    }

    .photo-box h4 {
      color: #28a745;
      margin-bottom: 10px;
    }

    .photo-box img {
      max-width: 100%;
      max-height: 250px;
      border-radius: 5px;
      border: 1px solid #ddd;
    }

    .no-photo {
      padding: 30px;
      color: #999;
    }

    .map-container {
      height: 300px;
      width: 100%;
      border: 1px solid #ddd;
      border-radius: 5px;
      margin-top: 10px;
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

      .photo-container {
        flex-direction: column;
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
    }
  </style>
</head>
<body>

  <div id="menu-icon">
    <i class="fas fa-bars"></i>
  </div>

  <aside class="sidebar hidden" id="sidebar">
    <ul>
      <li><a href="tableau_de_bord.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
      <li><a href="profil-utilisateur.php"><i class="fas fa-user"></i> Profil utilisateur</a></li>
      <li><a href="liste-sign-utilisateur.php"><i class="fas fa-list-alt"></i> Tous les signalements</a></li>
      <li><a href="nouveau-signalement.php"><i class="fas fa-exclamation-circle"></i> Signaler un problème</a></li>
      <li class="logout"><a href="deconnexion.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
    </ul>
  </aside>

  <main class="main-content" id="main-content">
    <div class="top-bar">
      <h1>Mon tableau de bord</h1>
      <p style="color: #666; margin-top: 10px; font-size: 0.9rem;">
        Bienvenue, <strong><?php echo htmlspecialchars($_SESSION['user_nom']); ?></strong>
      </p>
    </div>

    <div class="cards">
      <div class="card orange">En attente: <?php echo $en_attente; ?></div>
      <div class="card blue">En cours: <?php echo $en_cours; ?></div>
      <div class="card green">Résolu: <?php echo $resolus; ?></div>
    </div>

    <div class="search-bar">
      <input type="text" id="search" placeholder="Rechercher dans mes signalements...">
      <i class="fas fa-search"></i>
    </div>

    <div class="table-container">
      <table class="signalement-table" id="signalementTable">
        <thead>
          <tr>
            <th>Titre</th>
            <th>Rue</th>
            <th>Date</th>
            <th>Statut</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody id="table-body">
          <?php if (empty($mes_signalements)): ?>
          <tr>
            <td colspan="5">
              <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>Aucun signalement</h3>
                <p>Vous n'avez pas encore créé de signalement.</p>
                <a href="nouveau-signalement.php">Créer mon premier signalement</a>
              </div>
            </td>
          </tr>
          <?php else: ?>
          
          <?php foreach ($mes_signalements as $signalement): 
            $status_class = $signalement['statut'] == 'resolu' ? 'badge green' : ($signalement['statut'] == 'en_cours' ? 'badge blue' : 'badge orange');
            $status_text = $signalement['statut'] == 'resolu' ? 'Résolu' : ($signalement['statut'] == 'en_cours' ? 'En cours' : 'En attente');
          ?>
          <tr>
            <td><?php echo htmlspecialchars($signalement['titre']); ?></td>
            <td><?php echo htmlspecialchars($signalement['rue'] ?? 'Non spécifiée'); ?></td>
            <td><?php echo date('d/m/Y', strtotime($signalement['date_signalement'])); ?></td>
            <td><span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
            <td>
              <button class="btn-voir" onclick="afficherDetails(<?php echo $signalement['id']; ?>)">
                <i class="fas fa-eye"></i> Voir
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
          
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>

  <!-- MODAL POUR LES DÉTAILS -->
  <div class="modal" id="modalDetails">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Détails du Signalement</h2>
        <button class="close-modal" onclick="fermerModal()">&times;</button>
      </div>
      <div id="contenuDetails"></div>
    </div>
  </div>

  <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>

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
    const tableRows = document.querySelectorAll("#table-body tr");

    if (searchInput) {
      searchInput.addEventListener("keyup", function () {
        const value = searchInput.value.toLowerCase();
        tableRows.forEach(row => {
          // Ignorer la ligne du message vide
          if (row.querySelector('.empty-state')) return;
          const text = row.textContent.toLowerCase();
          row.style.display = text.includes(value) ? "" : "none";
        });
      });
    }

    // Données des signalements
    const signalements = <?php echo json_encode($mes_signalements); ?>;

    function afficherDetails(id) {
      const signalement = signalements.find(s => s.id == id);
      if (!signalement) return;

      let statutTexte = '';
      let statutBg = '';
      switch(signalement.statut) {
        case 'en_attente':
          statutTexte = 'En attente';
          statutBg = '#fdebd0';
          break;
        case 'en_cours':
          statutTexte = 'En cours';
          statutBg = '#d6eaf8';
          break;
        case 'resolu':
          statutTexte = 'Résolu';
          statutBg = '#d5f4e6';
          break;
      }

      let html = `
        <div class="detail-item">
          <strong>ID:</strong> #${signalement.id.toString().padStart(3, '0')}
        </div>
        
        <div class="detail-item">
          <strong>Titre:</strong> ${signalement.titre || ''}
        </div>
        
        <div class="detail-item">
          <strong>Rue:</strong> ${signalement.rue || 'Non spécifiée'}
        </div>
        
        <div class="detail-item">
          <strong>Quartier:</strong> ${signalement.quartier || 'Non spécifié'}
        </div>
        
        <div class="detail-item">
          <strong>Catégorie:</strong> ${signalement.categorie || ''}
        </div>
        
        <div class="detail-item">
          <strong>Date:</strong> ${new Date(signalement.date_signalement).toLocaleDateString('fr-FR')}
        </div>
        
        <div class="detail-item">
          <strong>Statut:</strong> 
          <span style="display:inline-block; padding:5px 10px; border-radius:15px; background:${statutBg};">${statutTexte}</span>
        </div>
        
        <div class="detail-item">
          <strong>Gravité:</strong> ${signalement.niveau || 'Non spécifiée'}
        </div>
        
        <div class="detail-item">
          <strong>Signalé par:</strong> ${signalement.nom_complet || 'Anonyme'}
        </div>
        
        <div class="detail-item">
          <strong>Description:</strong><br>
          ${signalement.description || 'Aucune description'}
        </div>
      `;

      // Photos avant/après
      html += `<div class="photo-container">`;
      
      // Photo avant
      html += `<div class="photo-box">`;
      html += `<h4>📸 Avant</h4>`;
      if (signalement.photo_path) {
        html += `<img src="${signalement.photo_path}" alt="Photo avant">`;
      } else {
        html += `<div class="no-photo"><i class="fas fa-camera"></i><br>Aucune photo avant</div>`;
      }
      html += `</div>`;
      
      // Photo après
      html += `<div class="photo-box">`;
      html += `<h4>✅ Après</h4>`;
      if (signalement.photo_resolution) {
        html += `<img src="${signalement.photo_resolution}" alt="Photo après">`;
        html += `<p style="color:#28a745; margin-top:10px;"><i class="fas fa-check-circle"></i> Résolu</p>`;
      } else {
        html += `<div class="no-photo"><i class="fas fa-cloud-upload-alt"></i><br>Pas encore de photo après</div>`;
      }
      html += `</div>`;
      
      html += `</div>`;

      // Carte
      if (signalement.latitude && signalement.longitude) {
        const mapId = 'map_' + signalement.id + '_' + Date.now();
        html += `
          <div class="detail-item">
            <strong>🗺️ Localisation:</strong>
            <div id="${mapId}" class="map-container"></div>
          </div>
        `;
      }

      document.getElementById('contenuDetails').innerHTML = html;
      document.getElementById('modalDetails').style.display = 'flex';

      setTimeout(() => {
        if (signalement.latitude && signalement.longitude) {
          const mapElements = document.querySelectorAll('[id^="map_"]');
          if (mapElements.length > 0 && typeof L !== 'undefined') {
            const mapElement = mapElements[mapElements.length - 1];
            const map = L.map(mapElement.id).setView([signalement.latitude, signalement.longitude], 16);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
              attribution: '©️ OpenStreetMap'
            }).addTo(map);
            L.marker([signalement.latitude, signalement.longitude]).addTo(map);
          }
        }
      }, 200);
    }

    function fermerModal() {
      document.getElementById('modalDetails').style.display = 'none';
    }

    window.onclick = function(e) {
      const modal = document.getElementById('modalDetails');
      if (e.target == modal) {
        modal.style.display = 'none';
      }
    }
  </script>

</body>
</html>