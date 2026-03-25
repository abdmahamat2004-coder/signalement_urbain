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

// RÉCUPÉRER TOUS LES SIGNALEMENTS (pas seulement ceux d'un utilisateur)
$sql = "SELECT s.*, u.nom_complet FROM signalements s 
        LEFT JOIN utilisateurs u ON s.user_id = u.id 
        ORDER BY s.date_signalement DESC";
$stmt = $pdo->query($sql);
$signalements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// TRAITEMENT DU CHANGEMENT DE STATUT (si formulaire envoyé)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['changer_statut'])) {
    $signalement_id = $_POST['signalement_id'];
    $nouveau_statut = $_POST['nouveau_statut'];
    
    try {
        $stmt = $pdo->prepare("UPDATE signalements SET statut = ? WHERE id = ?");
        $stmt->execute([$nouveau_statut, $signalement_id]);
        
        // Recharger les signalements
        $stmt = $pdo->query($sql);
        $signalements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $message_success = "✅ Statut mis à jour avec succès !";
    } catch(PDOException $e) {
        $message_erreur = "❌ Erreur : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Liste des Signalements - Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    /* MÊME STYLE QUE liste-sign-utilisateur.php */
    body { font-family: Arial; margin: 0; background: #f5f7fa; }
    
    .header {
      background: #007BFF;
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
      max-width: 1400px;
      margin: 20px auto;
      padding: 0 20px;
    }
    
    .title {
      background: white;
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 20px;
      text-align: center;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
      border-color: #007BFF;
      box-shadow: 0 0 0 3px rgba(0,123,255,0.25);
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
      border-color: #007BFF;
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
      color: #007BFF;
    }
    
    /* MESSAGES */
    .success-message {
      background: #d4edda;
      color: #155724;
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 20px;
      text-align: center;
      border: 1px solid #c3e6cb;
    }
    
    .error-message {
      background: #f8d7da;
      color: #721c24;
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 20px;
      text-align: center;
      border: 1px solid #f5c6cb;
    }
    
    /* TABLEAU */
    table {
      width: 100%;
      background: white;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      border-collapse: collapse;
    }
    
    th {
      background: #007BFF;
      color: white;
      padding: 15px;
      text-align: left;
    }
    
    td {
      padding: 15px;
      border-bottom: 1px solid #eee;
      vertical-align: middle;
    }
    
    tr:hover {
      background: #f8f9fa;
    }
    
    /* BOUTONS */
    .btn-voir {
      background: #28a745;
      color: white;
      border: none;
      padding: 8px 12px;
      border-radius: 5px;
      cursor: pointer;
      font-size: 14px;
    }
    
    .btn-voir:hover {
      background: #218838;
    }
    
    .btn-modifier {
      background: #ffc107;
      color: black;
      border: none;
      padding: 8px 12px;
      border-radius: 5px;
      cursor: pointer;
      font-size: 14px;
    }
    
    .btn-modifier:hover {
      background: #e0a800;
    }
    
    .btn-supprimer {
      background: #dc3545;
      color: white;
      border: none;
      padding: 8px 12px;
      border-radius: 5px;
      cursor: pointer;
      font-size: 14px;
    }
    
    .btn-supprimer:hover {
      background: #c82333;
    }
    
    /* FORMULAIRE DE STATUT */
    .statut-form {
      display: flex;
      gap: 10px;
      align-items: center;
    }
    
    .statut-select {
      padding: 6px 10px;
      border-radius: 5px;
      border: 1px solid #ddd;
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
      color: #007BFF;
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
      
      table {
        font-size: 14px;
      }
      
      th, td {
        padding: 10px;
      }
    }
  </style>
</head>
<body>

  <!-- EN-TÊTE -->
  <div class="header">
    <div>
      <a href="tableau_de_bord_admin.php"><i class="fas fa-arrow-left"></i> Retour</a>
      <a href="tableau_de_bord_admin.php"><i class="fas fa-home"></i></a>
    </div>
    <span style="font-weight: bold;">Signalements - Administration</span>
    <a href="profil-admin.php"><i class="fas fa-user-cog"></i></a>
  </div>

  <!-- CONTENU -->
  <div class="container">
    <div class="title">
      <h1>Gestion des Signalements</h1>
      <p>Vous pouvez voir, modifier et gérer tous les signalements</p>
    </div>

    <?php if (isset($message_success)): ?>
    <div class="success-message">
      <?php echo $message_success; ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($message_erreur)): ?>
    <div class="error-message">
      <?php echo $message_erreur; ?>
    </div>
    <?php endif; ?>

    <!-- FILTRES ET RECHERCHE -->
    <div class="filters">
      <div class="search-container">
        <input type="text" id="search" placeholder="Rechercher un signalement..." onkeyup="filterRows()">
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

    <!-- TABLEAU DES SIGNALEMENTS -->
    <table id="signalementTable">
      <thead>
        <tr>
          <th>ID</th>
          <th>Titre</th>
          <th>Utilisateur</th>
          <th>Date</th>
          <th>Statut</th>
          <th>Actions</th>
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
        ?>
        <tr>
          <td>#<?php echo str_pad($signalement['id'], 3, '0', STR_PAD_LEFT); ?></td>
          <td><?php echo htmlspecialchars($signalement['titre']); ?></td>
          <td><?php echo htmlspecialchars($signalement['nom_complet'] ?? 'Anonyme'); ?></td>
          <td><?php echo date('d/m/Y', strtotime($signalement['date_signalement'])); ?></td>
          <td>
            <div class="status <?php echo $status_class; ?>">
              <span class="status-dot"></span><?php echo $status_text; ?>
            </div>
          </td>
          <td>
            <div style="display: flex; gap: 5px; flex-wrap: wrap;">
              <button class="btn-voir" onclick="afficherDetails(<?php echo $signalement['id']; ?>)">
                <i class="fas fa-eye"></i> Voir
              </button>
              
              <!-- FORMULAIRE POUR CHANGER LE STATUT -->
              <form method="POST" action="" class="statut-form" style="display: inline;">
                <input type="hidden" name="signalement_id" value="<?php echo $signalement['id']; ?>">
                <select name="nouveau_statut" class="statut-select" onchange="this.form.submit()">
                  <option value="en_attente" <?php echo $signalement['statut'] == 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                  <option value="en_cours" <?php echo $signalement['statut'] == 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                  <option value="resolu" <?php echo $signalement['statut'] == 'resolu' ? 'selected' : ''; ?>>Résolu</option>
                </select>
                <button type="submit" name="changer_statut" class="btn-modifier" style="display: none;">
                  <i class="fas fa-sync-alt"></i>
                </button>
              </form>
              
              <button class="btn-supprimer" onclick="supprimerSignalement(<?php echo $signalement['id']; ?>, '<?php echo htmlspecialchars($signalement['titre']); ?>')">
                <i class="fas fa-trash"></i> Suppr.
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        
        <?php if (empty($signalements)): ?>
        <tr>
          <td colspan="6" style="text-align: center; padding: 40px;">
            📭 Aucun signalement dans le système
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

  <!-- SCRIPT -->
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
        if (row.querySelector('a[href="nouveau-signalement.php"]')) return;
        
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(search) ? '' : 'none';
      });
    }
    
    function filterBy(type, value) {
      const rows = document.querySelectorAll('#table-body tr');
      
      rows.forEach(row => {
        if (row.querySelector('a[href="nouveau-signalement.php"]')) return;
        
        let show = true;
        if (value !== '') {
          if (type === 'cat') {
            const cat = row.cells[1].innerText; // Titre contient la catégorie
            show = cat.includes(value);
          } else if (type === 'statut') {
            const statut = row.cells[4].innerText.toLowerCase();
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
    
    // Fonction pour afficher les détails
    function afficherDetails(id) {
      const signalement = signalements.find(s => s.id == id);
      
      if (!signalement) {
        alert("Signalement non trouvé");
        return;
      }
      
      let html = `
        <div class="detail-item">
          <strong>ID:</strong> #${signalement.id.toString().padStart(3, '0')}
        </div>
        
        <div class="detail-item">
          <strong>Utilisateur:</strong> ${signalement.nom_complet || 'Anonyme'} (${signalement.email})
        </div>
        
        <div class="detail-item">
          <strong>Titre:</strong> ${signalement.titre}
        </div>
        
        <div class="detail-item">
          <strong>Catégorie:</strong> ${signalement.categorie}
        </div>
        
        <div class="detail-item">
          <strong>Date:</strong> ${new Date(signalement.date_signalement).toLocaleDateString('fr-FR')}
        </div>
        
        <div class="detail-item">
          <strong>Statut:</strong> ${signalement.statut === 'en_attente' ? 'En attente' : 
                                   signalement.statut === 'en_cours' ? 'En cours' : 'Résolu'}
        </div>
        
        <div class="detail-item">
          <strong>Rue:</strong> ${signalement.rue || 'Non spécifiée'}
        </div>
        
        <div class="detail-item">
          <strong>Quartier:</strong> ${signalement.quartier || 'Non spécifié'}
        </div>
        
        <div class="detail-item">
          <strong>Gravité:</strong> ${signalement.niveau || 'Non spécifiée'}
        </div>
        
        <div class="detail-item">
          <strong>Description:</strong><br>
          ${signalement.description || 'Aucune description'}
        </div>
      `;
      
      if (signalement.photo_path) {
        html += `
          <div class="detail-item">
            <strong>Photo:</strong><br>
            <img src="${signalement.photo_path}" alt="Photo" class="detail-photo">
          </div>
        `;
      }
      
      if (signalement.latitude && signalement.longitude) {
        html += `
          <div class="detail-item">
            <strong>Localisation sur la carte:</strong>
            <div class="map-container" id="carteDetails"></div>
          </div>
        `;
      }
      
      document.getElementById('contenuDetails').innerHTML = html;
      document.getElementById('modalDetails').style.display = 'block';
      
      setTimeout(() => {
        if (signalement.latitude && signalement.longitude) {
          if (typeof L !== 'undefined') {
            const map = L.map('carteDetails').setView([signalement.latitude, signalement.longitude], 16);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
            L.marker([signalement.latitude, signalement.longitude]).addTo(map);
          }
        }
      }, 100);
    }
    
    // Fonction pour supprimer un signalement
    function supprimerSignalement(id, titre) {
      if (confirm(`Voulez-vous vraiment supprimer le signalement : "${titre}" ?`)) {
        // Envoyer une requête pour supprimer
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        
        const inputId = document.createElement('input');
        inputId.type = 'hidden';
        inputId.name = 'supprimer_id';
        inputId.value = id;
        
        form.appendChild(inputId);
        document.body.appendChild(form);
        form.submit();
      }
    }
    
    // Fermer le modal
    function fermerModal() {
      document.getElementById('modalDetails').style.display = 'none';
    }
    
    window.onclick = function(event) {
      const modal = document.getElementById('modalDetails');
      if (event.target == modal) {
        modal.style.display = 'none';
      }
    }
  </script>

  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />

</body>
</html>