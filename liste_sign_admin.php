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

// RÉCUPÉRER TOUS LES SIGNALEMENTS AVEC LE NOM DE L'AGENT
$sql = "SELECT s.*, 
               u.nom_complet, 
               u.email as user_email,
               a.nom_complet as agent_nom 
        FROM signalements s 
        LEFT JOIN utilisateurs u ON s.user_id = u.id 
        LEFT JOIN utilisateurs a ON s.agent_id = a.id 
        ORDER BY s.date_signalement DESC";
$stmt = $pdo->query($sql);
$signalements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// TRAITEMENT DES ACTIONS
$notification = '';
$notification_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // MODIFIER UN SIGNALEMENT
    if (isset($_POST['modifier_signalement'])) {
        $id = $_POST['id'];
        $titre = trim($_POST['titre']);
        $categorie = $_POST['categorie'];
        $statut = $_POST['statut'];
        $description = trim($_POST['description']);
        
        try {
            $stmt = $pdo->prepare("UPDATE signalements SET titre = ?, categorie = ?, statut = ?, description = ? WHERE id = ?");
            $stmt->execute([$titre, $categorie, $statut, $description, $id]);
            
            $notification = "✅ Signalement modifié avec succès !";
            $notification_type = "success";
            
            // Recharger les données
            $stmt = $pdo->query($sql);
            $signalements = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            $notification = "❌ Erreur : " . $e->getMessage();
            $notification_type = "error";
        }
    }
    
    // SUPPRIMER UN SIGNALEMENT
    elseif (isset($_POST['supprimer_id'])) {
        $id = $_POST['supprimer_id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM signalements WHERE id = ?");
            $stmt->execute([$id]);
            
            $notification = "✅ Signalement supprimé avec succès !";
            $notification_type = "success";
            
            // Recharger les données
            $stmt = $pdo->query($sql);
            $signalements = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            $notification = "❌ Erreur : " . $e->getMessage();
            $notification_type = "error";
        }
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

    .title-section p {
      margin: 0;
      font-size: 1rem;
      color: var(--gray);
      max-width: 600px;
      margin: 0 auto;
    }

    .filters {
      display: flex;
      gap: 15px;
      margin-bottom: 25px;
      flex-wrap: wrap;
      background: white;
      padding: 20px;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
    }

    .search-container {
      flex: 1;
      min-width: 250px;
    }

    .search-container input {
      width: 100%;
      padding: 12px 15px;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      font-size: 1rem;
      transition: var(--transition);
    }

    .search-container input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
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
      border: 1px solid var(--border);
      border-radius: var(--radius);
      cursor: pointer;
      transition: var(--transition);
      font-size: 1rem;
      width: 100%;
    }

    .dropdown-btn:hover {
      border-color: var(--primary);
    }

    .dropdown-menu {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background-color: white;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      display: none;
      z-index: 10;
      box-shadow: var(--shadow);
      margin-top: 5px;
      overflow: hidden;
    }

    .dropdown-menu div {
      padding: 12px 15px;
      cursor: pointer;
      transition: var(--transition);
      border-bottom: 1px solid #f0f0f0;
    }

    .dropdown-menu div:last-child {
      border-bottom: none;
    }

    .dropdown-menu div:hover {
      background-color: #f8f9fa;
      color: var(--primary);
    }

    .table-container {
      background: white;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      overflow: hidden;
      margin-bottom: 30px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    thead {
      background: linear-gradient(90deg, var(--primary) 0%, var(--primary-dark) 100%);
      color: white;
    }

    th {
      padding: 15px;
      text-align: left;
      font-weight: 600;
      font-size: 0.9rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    tbody tr {
      transition: var(--transition);
      border-bottom: 1px solid #f0f0f0;
    }

    tbody tr:hover {
      background-color: #f8f9fa;
      transform: translateY(-2px);
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }

    tbody tr.selected {
      background-color: #e3f2fd;
    }

    td {
      padding: 15px;
      vertical-align: middle;
    }

    .status {
      display: flex;
      align-items: center;
      gap: 8px;
      font-weight: 600;
      padding: 6px 12px;
      border-radius: 20px;
      width: fit-content;
    }

    .status-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
    }

    .en-attente { 
      background-color: #ffeaea;
      color: var(--danger);
    }
    .en-attente .status-dot { 
      background-color: var(--danger);
    }

    .en-cours { 
      background-color: #fff3cd;
      color: #856404;
    }
    .en-cours .status-dot { 
      background-color: #ffc107;
    }

    .resolu { 
      background-color: #e7f4e4;
      color: var(--success);
    }
    .resolu .status-dot { 
      background-color: var(--success);
    }

    .action-buttons {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .btn {
      padding: 8px 12px;
      border: none;
      border-radius: var(--radius);
      cursor: pointer;
      transition: var(--transition);
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 5px;
      font-size: 0.85rem;
      text-decoration: none;
    }

    .btn-voir {
      background-color: var(--primary);
      color: white;
    }

    .btn-voir:hover {
      background-color: var(--primary-dark);
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0, 123, 255, 0.3);
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

    .btn-supprimer {
      background-color: var(--danger);
      color: white;
    }

    .btn-supprimer:hover {
      background-color: #c82333;
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
    }

    .btn-assigner {
      background-color: #17a2b8;
      color: white;
    }

    .btn-assigner:hover {
      background-color: #138496;
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(23, 162, 184, 0.3);
    }

    .assigned-badge {
      color: #28a745;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .assigned-badge i {
      font-size: 14px;
    }

    .not-assigned {
      color: #999;
      font-style: italic;
    }

    .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: var(--gray);
    }

    .empty-state i {
      font-size: 3rem;
      margin-bottom: 15px;
      color: #ddd;
    }

    /* MODAL POUR LES DÉTAILS */
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    }

    .modal-content {
      background: white;
      border-radius: var(--radius);
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

    .detail-item {
      margin-bottom: 15px;
      padding: 10px;
      background: #f8f9fa;
      border-radius: 5px;
    }

    .detail-item strong {
      color: var(--primary);
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
      color: var(--primary);
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

    /* Modal pour modifier */
    .modal-small {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    }

    .modal-small .modal-content {
      max-width: 500px;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 500;
      color: var(--dark);
    }

    .form-group input, .form-group select, .form-group textarea {
      width: 100%;
      padding: 12px 15px;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      font-size: 16px;
    }

    .notification {
      padding: 15px 20px;
      border-radius: 5px;
      margin-bottom: 20px;
      display: none;
    }

    .notification.success {
      background: rgba(40, 167, 69, 0.1);
      color: var(--success);
      border-left: 4px solid var(--success);
      display: block;
    }

    .notification.error {
      background: rgba(220, 53, 69, 0.1);
      color: var(--danger);
      border-left: 4px solid var(--danger);
      display: block;
    }

    @media (max-width: 768px) {
      .photo-container {
        flex-direction: column;
      }
      .table-container {
    overflow-x: auto;
    max-width: 100%;
}

.signalement-table {
    min-width: 1000px; /* Force le scroll horizontal si nécessaire */
}
    }
  </style>
</head>
<body>
  <header>
    <div class="header-left">
      <a href="tableau_de_bord_admin.php"><i class="fas fa-home"></i></a>
      <span class="header-title">Liste des signalements - Admin</span>
    </div>
    <a href="profil-admin.php"><i class="fas fa-user"></i></a>
  </header>

  <div class="container">
    <div class="title-section">
      <h1>Gestion des signalements</h1>
      <p>Interface d'administration pour gérer tous les signalements. Vous pouvez voir, modifier, assigner et supprimer les signalements.</p>
    </div>

    <?php if ($notification): ?>
    <div id="notification" class="notification <?php echo $notification_type; ?>">
      <?php echo $notification; ?>
    </div>
    <?php endif; ?>

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
            <div onclick="filterBy('cat', '')">Toutes les catégories</div>
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
            <div onclick="filterBy('statut', '')">Tous les statuts</div>
          </div>
        </div>
      </div>
    </div>

    <div class="table-container">
      <table id="signalementTable">
        <thead>
          <tr>
            <th>ID</th>
            <th>Email</th>
            <th>Titre</th>
            <th>Catégorie</th>
            <th>Date</th>
            <th>Statut</th>
            <th>Assigné à</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="table-body">
          <?php foreach ($signalements as $signalement): 
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
            <td><?php echo htmlspecialchars($signalement['user_email'] ?? $signalement['email']); ?></td>
            <td><?php echo htmlspecialchars($signalement['titre']); ?></td>
            <td><?php echo htmlspecialchars($signalement['categorie']); ?></td>
            <td><?php echo date('d/m/Y', strtotime($signalement['date_signalement'])); ?></td>
            <td><div class="status <?php echo $status_class; ?>"><span class="status-dot"></span><?php echo $status_text; ?></div></td>
            
            <td>
              <?php if (!empty($signalement['agent_nom'])): ?>
                <span class="assigned-badge">
                  <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($signalement['agent_nom']); ?>
                </span>
              <?php else: ?>
                <span class="not-assigned">Non assigné</span>
              <?php endif; ?>
            </td>
            
            <td>
              <div class="action-buttons">
                <button class="btn btn-voir" onclick="afficherDetails(<?php echo $signalement['id']; ?>)">
                  <i class="fas fa-eye"></i> Voir
                </button>
                
                <a href="assigner_agent.php?id=<?php echo $signalement['id']; ?>" class="btn btn-assigner">
                  <i class="fas fa-user-tie"></i> Assigner
                </a>
                
                <button class="btn btn-modifier" onclick="modifierSignalement(
                  <?php echo $signalement['id']; ?>,
                  '<?php echo htmlspecialchars($signalement['titre'], ENT_QUOTES); ?>',
                  '<?php echo htmlspecialchars($signalement['categorie'], ENT_QUOTES); ?>',
                  '<?php echo $signalement['statut']; ?>',
                  '<?php echo htmlspecialchars($signalement['description'] ?? '', ENT_QUOTES); ?>'
                )">
                  <i class="fas fa-edit"></i> Modifier
                </button>
                
                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce signalement ?');">
                  <input type="hidden" name="supprimer_id" value="<?php echo $signalement['id']; ?>">
                  <button type="submit" class="btn btn-supprimer">
                    <i class="fas fa-trash"></i> Supprimer
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          
          <?php if (empty($signalements)): ?>
          <tr>
            <td colspan="8" style="text-align: center; padding: 40px;">
              <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>Aucun signalement trouvé</h3>
                <p>Il n'y a aucun signalement dans le système pour le moment.</p>
              </div>
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- MODAL POUR LES DÉTAILS (AVEC 2 PHOTOS) -->
  <div class="modal" id="modalDetails">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Détails du Signalement</h2>
        <button class="close-modal" onclick="fermerModal()">&times;</button>
      </div>
      <div id="contenuDetails"></div>
    </div>
  </div>

  <!-- MODAL POUR MODIFIER -->
  <div class="modal-small" id="editModal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Modifier le signalement</h3>
        <button class="close-modal" onclick="closeModal('editModal')">&times;</button>
      </div>
      <form method="POST" action="" id="editForm">
        <input type="hidden" id="edit-id" name="id">
        <div class="form-group">
          <label for="edit-titre">Titre</label>
          <input type="text" id="edit-titre" name="titre" required>
        </div>
        <div class="form-group">
          <label for="edit-categorie">Catégorie</label>
          <select id="edit-categorie" name="categorie" required>
            <option value="Problème de route">Problème de route</option>
            <option value="Éclairage">Éclairage</option>
            <option value="Déchets">Déchets</option>
            <option value="Problème d'eau">Problème d'eau</option>
            <option value="Sécurité publique">Sécurité publique</option>
            <option value="Transport public">Transport public</option>
          </select>
        </div>
        <div class="form-group">
          <label for="edit-statut">Statut</label>
          <select id="edit-statut" name="statut" required>
            <option value="en_attente">En attente</option>
            <option value="en_cours">En cours</option>
            <option value="resolu">Résolu</option>
          </select>
        </div>
        <div class="form-group">
          <label for="edit-description">Description</label>
          <textarea id="edit-description" name="description"></textarea>
        </div>
        <div class="modal-actions" style="display: flex; gap: 10px; justify-content: flex-end;">
          <button type="button" class="btn btn-modifier" onclick="closeModal('editModal')">Annuler</button>
          <button type="submit" name="modifier_signalement" class="btn btn-voir">Enregistrer</button>
        </div>
      </form>
    </div>
  </div>

  <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>

  <script>
    // Données des signalements
    const signalements = <?php echo json_encode($signalements); ?>;
    
    // Fonctions de filtrage
    function toggleDropdown(id) {
      document.querySelectorAll('.dropdown-menu').forEach(menu => {
        if (menu.id !== id) menu.style.display = 'none';
      });
      
      const el = document.getElementById(id);
      el.style.display = el.style.display === 'block' ? 'none' : 'block';
      
      if (el.style.display === 'block') {
        setTimeout(() => {
          document.addEventListener('click', closeDropdowns);
        }, 10);
      } else {
        document.removeEventListener('click', closeDropdowns);
      }
    }

    function closeDropdowns(event) {
      if (!event.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
          menu.style.display = 'none';
        });
        document.removeEventListener('click', closeDropdowns);
      }
    }

    function filterRows() {
      const input = document.getElementById('search').value.toLowerCase();
      const rows = document.querySelectorAll('#signalementTable tbody tr');
      
      rows.forEach(row => {
        if (row.querySelector('.empty-state')) {
          row.style.display = 'none';
          return;
        }
        const isVisible = row.innerText.toLowerCase().includes(input);
        row.style.display = isVisible ? '' : 'none';
      });
    }

    function filterBy(type, value) {
      const rows = document.querySelectorAll('#signalementTable tbody tr');
      
      rows.forEach(row => {
        if (row.querySelector('.empty-state')) {
          row.style.display = 'none';
          return;
        }
        
        let show = true;
        if (value !== '') {
          if (type === 'cat') {
            const cat = row.cells[3].innerText;
            show = cat.includes(value);
          } else if (type === 'statut') {
            const statut = row.cells[5].innerText.toLowerCase();
            show = statut.includes(value.toLowerCase());
          }
        }
        
        row.style.display = show ? '' : 'none';
      });
      
      document.querySelectorAll('.dropdown-menu').forEach(menu => {
        menu.style.display = 'none';
      });
    }

    // Fonction pour afficher les détails avec 2 photos
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
          <strong>Signalé par:</strong> ${signalement.nom_complet || 'Anonyme'} (${signalement.user_email || signalement.email})
        </div>
        
        <div class="detail-item">
          <strong>Assigné à:</strong> ${signalement.agent_nom || 'Non assigné'}
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

    // Modifier un signalement
    function modifierSignalement(id, titre, categorie, statut, description) {
      document.getElementById('edit-id').value = id;
      document.getElementById('edit-titre').value = titre;
      document.getElementById('edit-categorie').value = categorie;
      document.getElementById('edit-statut').value = statut;
      document.getElementById('edit-description').value = description;
      
      document.getElementById('editModal').style.display = 'flex';
    }

    // Fermer les modales
    function fermerModal() {
      document.getElementById('modalDetails').style.display = 'none';
    }

    function closeModal(modalId) {
      document.getElementById(modalId).style.display = 'none';
    }

    // Fermer en cliquant à l'extérieur
    window.addEventListener('click', function(e) {
      if (e.target.classList.contains('modal')) {
        e.target.style.display = 'none';
      }
      if (e.target.classList.contains('modal-small')) {
        e.target.style.display = 'none';
      }
    });

    // Cacher la notification après 5 secondes
    setTimeout(function() {
      const notification = document.getElementById('notification');
      if (notification) {
        notification.style.display = 'none';
      }
    }, 5000);
  </script>
</body>
</html>