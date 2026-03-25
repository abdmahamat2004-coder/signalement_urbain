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

// Récupérer TOUS les signalements
$stmt = $pdo->prepare("SELECT s.*, u.nom_complet, u.email as user_email 
                       FROM signalements s 
                       LEFT JOIN utilisateurs u ON s.user_id = u.id 
                       ORDER BY s.date_signalement DESC");
$stmt->execute();
$signalements = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Tous les signalements - Agent</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
  <style>
    /* MÊME STYLE QUE TON FICHIER UTILISATEUR */
    body { font-family: Arial; margin: 0; background: #f5f7fa; }
    
    .header {
      background: #28A745;
      color: white;
      padding: 15px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    
    .header a {
      color: white;
      text-decoration: none;
      font-size: 18px;
      margin: 0 5px;
    }
    
    .container {
      max-width: 1200px;
      margin: 20px auto;
      padding: 0 20px;
    }
    
    .title {
      background: white;
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 20px;
      text-align: center;
    }
    
    /* FILTRES ET RECHERCHE */
    .filters {
      background: white;
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 20px;
      display: flex;
      gap: 15px;
      flex-wrap: wrap;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .search-container {
      flex: 1;
      min-width: 250px;
    }
    
    .search-container input {
      width: 100%;
      padding: 12px 15px;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 1rem;
    }
    
    .search-container input:focus {
      outline: none;
      border-color: #28A745;
      box-shadow: 0 0 0 3px rgba(40,167,69,0.25);
    }
    
    .filter-group {
      display: flex;
      gap: 15px;
      flex-wrap: wrap;
    }
    
    .dropdown {
      position: relative;
      min-width: 150px;
    }
    
    .dropdown-btn {
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: white;
      padding: 12px 15px;
      border: 1px solid #ddd;
      border-radius: 8px;
      cursor: pointer;
      font-size: 1rem;
      width: 100%;
    }
    
    .dropdown-btn:hover {
      border-color: #28A745;
    }
    
    .dropdown-menu {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: white;
      border: 1px solid #ddd;
      border-radius: 8px;
      display: none;
      z-index: 10;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      margin-top: 5px;
    }
    
    .dropdown-menu div {
      padding: 12px 15px;
      cursor: pointer;
      border-bottom: 1px solid #f0f0f0;
    }
    
    .dropdown-menu div:last-child {
      border-bottom: none;
    }
    
    .dropdown-menu div:hover {
      background: #f8f9fa;
      color: #28A745;
    }
    
    table {
      width: 100%;
      background: white;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      border-collapse: collapse;
    }
    
    th {
      background: #28A745;
      color: white;
      padding: 15px;
      text-align: left;
    }
    
    td {
      padding: 15px;
      border-bottom: 1px solid #eee;
    }
    
    tr:hover {
      background: #f8f9fa;
    }
    
    .btn-voir {
      background: #28a745;
      color: white;
      border: none;
      padding: 8px 12px;
      border-radius: 5px;
      cursor: pointer;
    }
    
    .btn-voir:hover {
      background: #218838;
    }
    
    .btn-modifier {
      background: #ffc107;
      color: #333;
      border: none;
      padding: 8px 12px;
      border-radius: 5px;
      cursor: pointer;
      margin-left: 5px;
    }
    
    .btn-modifier:hover {
      background: #e0a800;
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
    }
    
    .modal-content {
      background: white;
      width: 90%;
      max-width: 800px;
      margin: 50px auto;
      padding: 20px;
      border-radius: 10px;
      max-height: 80vh;
      overflow-y: auto;
    }
    
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      border-bottom: 1px solid #eee;
      padding-bottom: 15px;
    }
    
    .close-modal {
      background: none;
      border: none;
      font-size: 24px;
      cursor: pointer;
      color: #666;
    }
    
    /* DÉTAILS */
    .detail-item {
      margin-bottom: 15px;
      padding: 10px;
      background: #f8f9fa;
      border-radius: 5px;
    }
    
    .detail-item strong {
      color: #28A745;
    }
    
    .detail-photo {
      max-width: 100%;
      max-height: 300px;
      border-radius: 5px;
      border: 1px solid #ddd;
      margin-top: 10px;
    }
    
    .map-container {
      height: 300px;
      width: 100%;
      border: 1px solid #ddd;
      border-radius: 5px;
      margin-top: 10px;
    }
    
    /* STATUT */
    .status {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 5px 10px;
      border-radius: 15px;
      font-size: 12px;
      font-weight: bold;
    }
    
    .status-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
    }
    
    .en-attente { 
      background: #ffeaea;
      color: #dc3545;
    }
    .en-attente .status-dot { 
      background: #dc3545;
    }
    
    .en-cours { 
      background: #fff3cd;
      color: #856404;
    }
    .en-cours .status-dot { 
      background: #ffc107;
    }
    
    .resolu { 
      background: #d4edda;
      color: #28a745;
    }
    .resolu .status-dot { 
      background: #28a745;
    }
    
    .assigned-badge {
      background: #28a745;
      color: white;
      padding: 3px 8px;
      border-radius: 3px;
      font-size: 11px;
    }
    
    /* RESPONSIVE */
    @media (max-width: 768px) {
      .filters {
        flex-direction: column;
      }
      
      .search-container {
        min-width: 100%;
      }
      
      .filter-group {
        width: 100%;
      }
      
      .dropdown {
        width: 100%;
      }
    }
  </style>
</head>
<body>

  <!-- EN-TÊTE -->
  <div class="header">
    <div>
      
      <a href="tableau_de_bord_agent.php"><i class="fas fa-home"></i></a>
    </div>
    <span style="font-weight: bold;">Tous les signalements - Agent</span>
    <a href="tableau_de_bord_agent.php"><i class="fas fa-user"></i></a>
  </div>

  <!-- CONTENU -->
  <div class="container">
    <div class="title">
      <h1>Tous les signalements</h1>
      <p>Visualisez tous les signalements de la plateforme</p>
    </div>

    <!-- FILTRES ET RECHERCHE -->
    <div class="filters">
      <div class="search-container">
        <input type="text" id="search" placeholder="Rechercher dans les signalements..." onkeyup="filterRows()">
      </div>
      
      <div class="filter-group">
        <div class="dropdown">
          <div class="dropdown-btn" onclick="toggleDropdown('catMenu')">
            <span>Catégorie</span>
            <i class="fas fa-chevron-down"></i>
          </div>
          <div class="dropdown-menu" id="catMenu">
            <div onclick="filterBy('cat', 'Problème de route')">Problème de route</div>
            <div onclick="filterBy('cat', 'Éclairage')">Éclairage</div>
            <div onclick="filterBy('cat', 'Déchets')">Déchets</div>
            <div onclick="filterBy('cat', 'Problème d\'eau')">Problème d'eau</div>
            <div onclick="filterBy('cat', 'Sécurité publique')">Sécurité publique</div>
            <div onclick="filterBy('cat', 'Transport public')">Transport public</div>
            <div onclick="filterBy('cat', '')">Toutes</div>
          </div>
        </div>

        <div class="dropdown">
          <div class="dropdown-btn" onclick="toggleDropdown('statutMenu')">
            <span>Statut</span>
            <i class="fas fa-chevron-down"></i>
          </div>
          <div class="dropdown-menu" id="statutMenu">
            <div onclick="filterBy('statut', 'en_attente')">En attente</div>
            <div onclick="filterBy('statut', 'en_cours')">En cours</div>
            <div onclick="filterBy('statut', 'resolu')">Résolu</div>
            <div onclick="filterBy('statut', '')">Tous</div>
          </div>
        </div>
      </div>
    </div>

    <!-- TABLEAU -->
    <table id="signalementTable">
      <thead>
        <tr>
          <th>ID</th>
          <th>Titre</th>
          <th>Rue</th>
          <th>Signalé par</th>
          <th>Catégorie</th>
          <th>Date</th>
          <th>Statut</th>
          <th>Agent</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody id="table-body">
        <?php foreach ($signalements as $signalement): 
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
          
          // Vérifier si c'est ma mission
          $is_mine = ($signalement['agent_id'] == $_SESSION['user_id']);
        ?>
        <tr>
          <td>#<?php echo str_pad($signalement['id'], 3, '0', STR_PAD_LEFT); ?></td>
          <td><?php echo htmlspecialchars($signalement['titre']); ?></td>
          <td><?php echo htmlspecialchars($signalement['rue'] ?? 'Non spécifiée'); ?></td>
          <td><?php echo htmlspecialchars($signalement['nom_complet'] ?? 'Anonyme'); ?></td>
          <td><?php echo htmlspecialchars($signalement['categorie']); ?></td>
          <td><?php echo date('d/m/Y', strtotime($signalement['date_signalement'])); ?></td>
          <td>
            <div class="status <?php echo $status_class; ?>">
              <span class="status-dot"></span><?php echo $status_text; ?>
            </div>
          </td>
          <td>
            <?php if ($is_mine): ?>
              <span class="assigned-badge">Ma mission</span>
            <?php elseif ($signalement['agent_id']): ?>
              Assigné
            <?php else: ?>
              Non assigné
            <?php endif; ?>
          </td>
          <td>
            <button class="btn-voir" onclick="afficherDetails(<?php echo $signalement['id']; ?>)">
              <i class="fas fa-eye"></i> Voir
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
        
        <?php if (empty($signalements)): ?>
        <tr>
          <td colspan="9" style="text-align: center; padding: 40px;">
            📭 Aucun signalement
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- MODAL POUR LES DÉTAILS -->
  <div class="modal" id="modalDetails">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Détails du Signalement</h2>
        <button class="close-modal" onclick="fermerModal()">&times;</button>
      </div>
      <div id="contenuDetails">
        <!-- Les détails seront chargés ici -->
      </div>
      <div style="text-align: center; margin-top: 20px;">
        <button class="btn-voir" onclick="fermerModal()">Fermer</button>
      </div>
    </div>
  </div>

  <!-- SCRIPT AVEC FONCTIONS -->
  <script>
    // Données depuis PHP
    const signalements = <?php echo json_encode($signalements); ?>;
    
    // FONCTIONS DE FILTRAGE
    function toggleDropdown(id) {
      const menu = document.getElementById(id);
      const allMenus = document.querySelectorAll('.dropdown-menu');
      
      allMenus.forEach(m => {
        if (m.id !== id) m.style.display = 'none';
      });
      
      menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
    }
    
    function filterRows() {
      const search = document.getElementById('search').value.toLowerCase();
      const rows = document.querySelectorAll('#table-body tr');
      
      rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(search) ? '' : 'none';
      });
    }
    
    function filterBy(type, value) {
      const rows = document.querySelectorAll('#table-body tr');
      
      rows.forEach(row => {
        let show = true;
        if (value !== '') {
          if (type === 'cat') {
            const cat = row.cells[4].innerText;
            show = cat.includes(value);
          } else if (type === 'statut') {
            const statut = row.cells[6].innerText.toLowerCase();
            show = statut.includes(value.toLowerCase());
          }
        }
        
        row.style.display = show ? '' : 'none';
      });
      
      document.querySelectorAll('.dropdown-menu').forEach(menu => {
        menu.style.display = 'none';
      });
    }
    
    // Fermer les menus en cliquant à l'extérieur
    document.addEventListener('click', function(e) {
      if (!e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
          menu.style.display = 'none';
        });
      }
    });
    
    // Fonction pour afficher les détails (COMME CHEZ L'UTILISATEUR)
    function afficherDetails(id) {
      // Trouver le signalement
      const signalement = signalements.find(s => s.id == id);
      
      if (!signalement) {
        alert("Signalement non trouvé");
        return;
      }
      
      // Créer le contenu HTML
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
          <strong>Statut:</strong> ${signalement.statut === 'en_attente' ? 'En attente' : 
                                   signalement.statut === 'en_cours' ? 'En cours' : 'Résolu'}
        </div>
        
        <div class="detail-item">
          <strong>Gravité:</strong> ${signalement.niveau || 'Non spécifiée'}
        </div>
        
        <div class="detail-item">
          <strong>Signalé par:</strong> ${signalement.nom_complet || 'Anonyme'} (${signalement.user_email || signalement.email || 'Email non disponible'})
        </div>
        
        <div class="detail-item">
          <strong>Description:</strong><br>
          ${signalement.description || 'Aucune description'}
        </div>
      `;
      
      // Ajouter la photo si elle existe
      if (signalement.photo_path) {
        html += `
          <div class="detail-item">
            <strong>Photo:</strong><br>
            <img src="${signalement.photo_path}" alt="Photo" class="detail-photo" onerror="this.style.display='none'">
          </div>
        `;
      }
      
      // Ajouter la carte si les coordonnées existent
      if (signalement.latitude && signalement.longitude) {
        html += `
          <div class="detail-item">
            <strong>Localisation sur la carte:</strong>
            <div class="map-container" id="carteDetails"></div>
          </div>
        `;
      }
      
      // Afficher dans le modal
      document.getElementById('contenuDetails').innerHTML = html;
      document.getElementById('modalDetails').style.display = 'block';
      
      // Charger la carte Leaflet APRÈS que le modal est visible
      setTimeout(() => {
        if (signalement.latitude && signalement.longitude) {
          if (typeof L !== 'undefined') {
            const map = L.map('carteDetails').setView([signalement.latitude, signalement.longitude], 16);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
              attribution: '© OpenStreetMap'
            }).addTo(map);
            L.marker([signalement.latitude, signalement.longitude]).addTo(map);
          } else {
            // Si Leaflet n'est pas chargé, charger maintenant
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/leaflet@1.9.3/dist/leaflet.js';
            script.onload = function() {
              const map = L.map('carteDetails').setView([signalement.latitude, signalement.longitude], 16);
              L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
              L.marker([signalement.latitude, signalement.longitude]).addTo(map);
            };
            document.head.appendChild(script);
          }
        }
      }, 100);
    }
    
    // Fermer le modal
    function fermerModal() {
      document.getElementById('modalDetails').style.display = 'none';
    }
    
    // Fermer en cliquant à l'extérieur
    window.onclick = function(event) {
      const modal = document.getElementById('modalDetails');
      if (event.target == modal) {
        modal.style.display = 'none';
      }
    }
  </script>

</body>
</html>