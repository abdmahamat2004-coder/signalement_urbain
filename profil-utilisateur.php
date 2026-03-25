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

// Récupérer les informations de l'utilisateur
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Gérer la photo de profil
$photo_profil = $user['photo_profil'] ?? 'default.jpg';
$photo_path = !empty($user['photo_profil']) ? 'uploads/profils/' . $user['photo_profil'] : 'https://ui-avatars.com/api/?name=' . urlencode($user['nom_complet']) . '&background=007bff&color=fff&size=150';

// Traitement de la modification du profil
$message = '';
$message_class = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Changement de photo de profil
    if (isset($_FILES['photo_profil']) && $_FILES['photo_profil']['error'] === 0) {
        $file = $_FILES['photo_profil'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        
        if (in_array($file['type'], $allowed_types) && $file['size'] <= 2 * 1024 * 1024) {
            // Créer dossier uploads si inexistant
            if (!file_exists('uploads/profils')) {
                mkdir('uploads/profils', 0777, true);
            }
            
            // Supprimer ancienne photo
            if (!empty($user['photo_profil']) && file_exists('uploads/profils/' . $user['photo_profil'])) {
                unlink('uploads/profils/' . $user['photo_profil']);
            }
            
            // Générer nouveau nom
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
            
            if (move_uploaded_file($file['tmp_name'], 'uploads/profils/' . $new_filename)) {
                $stmt = $pdo->prepare("UPDATE utilisateurs SET photo_profil = ? WHERE id = ?");
                $stmt->execute([$new_filename, $user_id]);
                $user['photo_profil'] = $new_filename;
                $photo_path = 'uploads/profils/' . $new_filename;
                $message = "✅ Photo mise à jour !";
                $message_class = "success";
            }
        }
    }
    
    // 2. Modification des informations (votre code existant)
    elseif (isset($_POST['modifier_profil'])) {
        // ... votre code existant pour modifier nom/email/password ...
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Mon Profil</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <style>
    /* Styles améliorés */
    .profile-container {
      max-width: 800px;
      margin: 0 auto;
      padding: 20px;
    }
    
    .profile-header {
      display: flex;
      align-items: center;
      gap: 30px;
      margin-bottom: 30px;
      background: white;
      padding: 25px;
      border-radius: 15px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .profile-avatar {
      position: relative;
    }
    
    .profile-img {
      width: 150px;
      height: 150px;
      border-radius: 50%;
      object-fit: cover;
      border: 5px solid #007bff;
      cursor: pointer;
      transition: all 0.3s;
    }
    
    .profile-img:hover {
      transform: scale(1.05);
      border-color: #0056b3;
    }
    
    .change-photo-btn {
      position: absolute;
      bottom: 10px;
      right: 10px;
      background: #007bff;
      color: white;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      border: 3px solid white;
    }
    
    .user-details h2 {
      margin: 0 0 10px 0;
      color: #333;
    }
    
    .user-details p {
      margin: 5px 0;
      color: #666;
    }
    
    .badge {
      display: inline-block;
      padding: 5px 15px;
      background: #28a745;
      color: white;
      border-radius: 20px;
      font-size: 14px;
      margin-top: 10px;
    }
    
    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }
    
    .form-card {
      background: white;
      padding: 25px;
      border-radius: 15px;
      box-shadow: 0 3px 10px rgba(0,0,0,0.08);
    }
    
    .form-card h3 {
      margin-top: 0;
      color: #007bff;
      border-bottom: 2px solid #f0f0f0;
      padding-bottom: 10px;
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: #555;
    }
    
    .form-control {
      width: 100%;
      padding: 12px;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-size: 16px;
      transition: border-color 0.3s;
    }
    
    .form-control:focus {
      border-color: #007bff;
      outline: none;
    }
    
    .form-control:disabled {
      background: #f8f9fa;
      cursor: not-allowed;
    }
    
    .btn {
      padding: 12px 30px;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
    }
    
    .btn-primary {
      background: linear-gradient(to right, #007bff, #0056b3);
      color: white;
    }
    
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,123,255,0.3);
    }
    
    .btn-success {
      background: linear-gradient(to right, #28a745, #1e7e34);
      color: white;
    }
    
    .btn-secondary {
      background: #6c757d;
      color: white;
    }
    
    .action-buttons {
      display: flex;
      gap: 15px;
      justify-content: center;
      margin-top: 30px;
    }
    
    .alert {
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      text-align: center;
    }
    
    .alert-success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    
    .alert-error {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
    
    .info-item {
      display: flex;
      justify-content: space-between;
      padding: 10px 0;
      border-bottom: 1px solid #f0f0f0;
    }
    
    .info-label {
      font-weight: 600;
      color: #666;
    }
    
    .info-value {
      color: #333;
    }
  </style>
</head>
<body>

  <div class="profile-container">
    <!-- En-tête -->
    <div class="profile-header">
      <div class="profile-avatar">
        <img src="<?php echo $photo_path; ?>" 
             alt="Photo de profil" 
             class="profile-img"
             id="profileImage"
             onclick="document.getElementById('photoInput').click()">
        
        <form method="POST" action="" enctype="multipart/form-data" id="photoForm" style="display: none;">
          <input type="file" id="photoInput" name="photo_profil" accept="image/*" onchange="this.form.submit()">
        </form>
        
        <div class="change-photo-btn" onclick="document.getElementById('photoInput').click()">
          <i class="fas fa-camera"></i>
        </div>
      </div>
      
      <div class="user-details">
        <h2><?php echo htmlspecialchars($user['nom_complet']); ?></h2>
        <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
        <p><i class="fas fa-calendar"></i> Membre depuis : <?php echo date('d/m/Y', strtotime($user['date_inscription'] ?? 'now')); ?></p>
        <span class="badge">
          <i class="fas fa-user"></i> Utilisateur
        </span>
      </div>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_class === 'success' ? 'success' : 'error'; ?>">
      <?php echo $message; ?>
    </div>
    <?php endif; ?>

    <!-- Formulaire -->
    <form method="POST" action="" id="profileForm">
      <div class="form-grid">
        <!-- Informations personnelles -->
        <div class="form-card">
          <h3><i class="fas fa-user-circle"></i> Informations personnelles</h3>
          
          <div class="form-group">
            <label for="nom_complet">Nom complet</label>
            <input type="text" id="nom_complet" name="nom_complet" 
                   class="form-control"
                   value="<?php echo htmlspecialchars($user['nom_complet']); ?>" 
                   disabled required>
          </div>
          
          <div class="form-group">
            <label for="email">Adresse email</label>
            <input type="email" id="email" name="email" 
                   class="form-control"
                   value="<?php echo htmlspecialchars($user['email']); ?>" 
                   disabled required>
          </div>
          
          <div class="info-item">
            <span class="info-label">Date d'inscription</span>
            <span class="info-value"><?php echo date('d/m/Y', strtotime($user['date_inscription'] ?? 'now')); ?></span>
          </div>
        </div>

        <!-- Sécurité -->
        <div class="form-card">
          <h3><i class="fas fa-shield-alt"></i> Sécurité du compte</h3>
          
          <div class="form-group">
            <label for="password">Nouveau mot de passe</label>
            <input type="password" id="password" name="password" 
                   class="form-control"
                   value="" 
                   placeholder="Laisser vide pour ne pas changer"
                   disabled>
          </div>
          
          <div class="form-group">
            <label for="confirm_password">Confirmer le mot de passe</label>
            <input type="password" id="confirm_password" name="confirm_password" 
                   class="form-control"
                   value="" 
                   placeholder="Confirmez le nouveau mot de passe"
                   disabled>
          </div>
          
          <div class="info-item">
            <span class="info-label">Dernière connexion</span>
            <span class="info-value">Aujourd'hui</span>
          </div>
        </div>
      </div>

      <!-- Boutons d'action -->
      <div class="action-buttons">
        <button type="button" class="btn btn-primary" id="editBtn">
          <i class="fas fa-edit"></i> Modifier le profil
        </button>
        
        <button type="submit" class="btn btn-success" id="saveBtn" name="modifier_profil" style="display: none;">
          <i class="fas fa-save"></i> Enregistrer les modifications
        </button>
        
        <a href="tableau_de_bord.php" class="btn btn-secondary">
          <i class="fas fa-arrow-left"></i> Retour au tableau de bord
        </a>
      </div>
    </form>
  </div>

  <script>
    const editBtn = document.getElementById("editBtn");
    const saveBtn = document.getElementById("saveBtn");
    const inputs = document.querySelectorAll("#profileForm .form-control");
    
    editBtn.addEventListener("click", () => {
      const isDisabled = inputs[0].disabled;
      
      // Activer/désactiver les champs
      inputs.forEach(input => input.disabled = !isDisabled);
      
      // Changer les boutons
      if (isDisabled) {
        editBtn.innerHTML = '<i class="fas fa-times"></i> Annuler';
        editBtn.className = 'btn btn-secondary';
        saveBtn.style.display = 'inline-block';
        // Focus sur le premier champ
        inputs[0].focus();
      } else {
        editBtn.innerHTML = '<i class="fas fa-edit"></i> Modifier le profil';
        editBtn.className = 'btn btn-primary';
        saveBtn.style.display = 'none';
        // Réinitialiser les mots de passe
        document.getElementById("password").value = "";
        document.getElementById("confirm_password").value = "";
      }
    });

    // Validation côté client
    document.getElementById("profileForm").addEventListener("submit", function(e) {
      const password = document.getElementById("password").value.trim();
      const confirmPassword = document.getElementById("confirm_password").value.trim();
      
      if (password !== "") {
        if (password.length < 6) {
          e.preventDefault();
          alert("⚠️ Le mot de passe doit contenir au moins 6 caractères.");
          return false;
        }
        
        if (password !== confirmPassword) {
          e.preventDefault();
          alert("⚠️ Les mots de passe ne correspondent pas.");
          return false;
        }
      }
    });

    // Prévisualisation de la photo
    document.getElementById("photoInput")?.addEventListener("change", function(e) {
      if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
          document.getElementById("profileImage").src = e.target.result;
        };
        reader.readAsDataURL(this.files[0]);
      }
    });
  </script>

</body>
</html>