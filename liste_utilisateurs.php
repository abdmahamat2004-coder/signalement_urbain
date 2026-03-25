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

// RÉCUPÉRER TOUS LES UTILISATEURS
$sql = "SELECT * FROM utilisateurs ORDER BY date_creation DESC";
$stmt = $pdo->query($sql);
$utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// COMPTER LES STATISTIQUES
$total_utilisateurs = count($utilisateurs);
$admins = array_filter($utilisateurs, function($user) {
    return $user['role'] === 'admin';
});
$total_admins = count($admins);
$total_utilisateurs_normaux = $total_utilisateurs - $total_admins;

// TRAITEMENT DES ACTIONS
$notification = '';
$notification_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CHANGER LE RÔLE D'UN UTILISATEUR
    if (isset($_POST['changer_role'])) {
        $user_id = $_POST['user_id'];
        $nouveau_role = $_POST['nouveau_role'];
        
        // Vérifier que le rôle est valide
        $roles_valides = ['user', 'admin'];
        if (!in_array($nouveau_role, $roles_valides)) {
            $notification = "❌ Rôle invalide.";
            $notification_type = "error";
            // Sortir du traitement
            goto fin_traitement;
        }
        
        // Empêcher l'admin de se retirer ses propres droits
        if ($user_id == $_SESSION['user_id'] && $nouveau_role == 'user') {
            $notification = "❌ Vous ne pouvez pas retirer vos propres droits d'administrateur.";
            $notification_type = "error";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE utilisateurs SET role = ? WHERE id = ?");
                $stmt->execute([$nouveau_role, $user_id]);
                
                $notification = "✅ Rôle mis à jour avec succès !";
                $notification_type = "success";
                
                // Recharger les données
                $stmt = $pdo->query($sql);
                $utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } catch(PDOException $e) {
                $notification = "❌ Erreur : " . $e->getMessage();
                $notification_type = "error";
            }
        }
    }
    
    // SUPPRIMER UN UTILISATEUR
    elseif (isset($_POST['supprimer_id'])) {
        $user_id = $_POST['supprimer_id'];
        
        // Empêcher la suppression de soi-même
        if ($user_id == $_SESSION['user_id']) {
            $notification = "❌ Vous ne pouvez pas supprimer votre propre compte.";
            $notification_type = "error";
        } else {
            try {
                // D'abord supprimer les signalements de l'utilisateur
                $stmt = $pdo->prepare("DELETE FROM signalements WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                // Puis supprimer l'utilisateur
                $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id = ?");
                $stmt->execute([$user_id]);
                
                $notification = "✅ Utilisateur supprimé avec succès !";
                $notification_type = "success";
                
                // Recharger les données
                $stmt = $pdo->query($sql);
                $utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } catch(PDOException $e) {
                $notification = "❌ Erreur : " . $e->getMessage();
                $notification_type = "error";
            }
        }
    }
}

fin_traitement:
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Gestion des Utilisateurs - Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    /* Style similaire à vos autres pages admin */
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

    .stats-cards {
      display: flex;
      gap: 20px;
      margin-bottom: 30px;
      flex-wrap: wrap;
    }

    .stat-card {
      flex: 1;
      min-width: 200px;
      background: white;
      padding: 25px;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      text-align: center;
      transition: var(--transition);
    }

    .stat-card:hover {
      transform: translateY(-5px);
    }

    .stat-card h3 {
      margin: 0 0 10px 0;
      font-size: 1rem;
      color: var(--gray);
    }

    .stat-card .number {
      font-size: 2.5rem;
      font-weight: bold;
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

    td {
      padding: 15px;
      vertical-align: middle;
    }

    .role-badge {
      display: inline-block;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 600;
    }

    .role-admin {
      background-color: #e3f2fd;
      color: #1976d2;
    }

    .role-user {
      background-color: #f3e5f5;
      color: #7b1fa2;
    }

    .action-buttons {
      display: flex;
      gap: 8px;
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

    @media (max-width: 768px) {
      .stats-cards {
        flex-direction: column;
      }
      
      .stat-card {
        min-width: 100%;
      }
      
      .table-container {
        overflow-x: auto;
      }
      
      table {
        min-width: 700px;
      }
    }
  </style>
</head>
<body>
  <header>
    <div class="header-left">
      <a href="tableau_de_bord_admin.php"><i class="fas fa-home"></i></a>
      <span class="header-title">Gestion des Utilisateurs</span>
    </div>
    <a href="profil-admin.php"><i class="fas fa-user"></i></a>
  </header>

  <div class="container">
    <div class="title-section">
      <h1>Gestion des Utilisateurs</h1>
      <p>Gérez les comptes utilisateurs et leurs permissions</p>
    </div>

    <?php if ($notification): ?>
    <div class="notification <?php echo $notification_type; ?>">
      <?php echo $notification; ?>
    </div>
    <?php endif; ?>

    <!-- Cartes de statistiques -->
    <div class="stats-cards">
      <div class="stat-card">
        <h3>Total Utilisateurs</h3>
        <div class="number"><?php echo $total_utilisateurs; ?></div>
      </div>
      <div class="stat-card">
        <h3>Administrateurs</h3>
        <div class="number"><?php echo $total_admins; ?></div>
      </div>
      <div class="stat-card">
        <h3>Utilisateurs Normaux</h3>
        <div class="number"><?php echo $total_utilisateurs_normaux; ?></div>
      </div>
    </div>

    <!-- Tableau des utilisateurs -->
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Nom Complet</th>
            <th>Email</th>
            <th>Rôle</th>
            <th>Date d'inscription</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($utilisateurs as $user): ?>
          <tr>
            <td>#<?php echo str_pad($user['id'], 3, '0', STR_PAD_LEFT); ?></td>
            <td><?php echo htmlspecialchars($user['nom_complet']); ?></td>
            <td><?php echo htmlspecialchars($user['email']); ?></td>
            <td>
              <span class="role-badge role-<?php echo $user['role']; ?>">
                <?php echo $user['role'] === 'admin' ? 'Administrateur' : 'Utilisateur'; ?>
              </span>
            </td>
            <td><?php echo date('d/m/Y', strtotime($user['date_creation'])); ?></td>
            <td>
              <div class="action-buttons">
                <!-- Formulaire pour changer le rôle -->
                <form method="POST" action="" style="display: inline;" id="formRole<?php echo $user['id']; ?>">
                  <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                  <select name="nouveau_role" style="padding: 6px; border-radius: 4px; border: 1px solid #ddd;"
                          onchange="document.getElementById('formRole<?php echo $user['id']; ?>').submit()">
                    <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>Utilisateur</option>
                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Administrateur</option>
                  </select>
                  <input type="hidden" name="changer_role" value="1">
                </form>
                
                <!-- Bouton supprimer (sauf pour soi-même) -->
                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ? Tous ses signalements seront également supprimés.');">
                  <input type="hidden" name="supprimer_id" value="<?php echo $user['id']; ?>">
                  <button type="submit" class="btn btn-supprimer">
                    <i class="fas fa-trash"></i> Supprimer
                  </button>
                </form>
                <?php else: ?>
                <span style="color: var(--gray); font-size: 0.8rem;">(Vous)</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          
          <?php if (empty($utilisateurs)): ?>
          <tr>
            <td colspan="6">
              <div class="empty-state">
                <i class="fas fa-users"></i>
                <h3>Aucun utilisateur trouvé</h3>
                <p>Il n'y a aucun utilisateur dans le système pour le moment.</p>
              </div>
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <script>
    // Cacher la notification après 5 secondes
    setTimeout(function() {
      const notification = document.querySelector('.notification');
      if (notification) {
        notification.style.display = 'none';
      }
    }, 5000);
  </script>


</body>
</html>