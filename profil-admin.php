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

// Récupérer les informations de l'administrateur
$admin_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Chemin par défaut de la photo de profil
$photo_profil = $admin['photo_profil'] ?? 'default_admin.jpg';
$photo_path = !empty($admin['photo_profil']) ? 'uploads/profils/' . $admin['photo_profil'] : 'https://ui-avatars.com/api/?name=' . urlencode($admin['nom_complet']) . '&background=007bff&color=fff&size=128';

// Traitement de la modification du profil
$message = '';
$message_class = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. CHANGEMENT DE PHOTO DE PROFIL
    if (isset($_FILES['photo_profil']) && $_FILES['photo_profil']['error'] === 0) {
        $file = $_FILES['photo_profil'];
        
        // Vérifier le type de fichier
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
        $file_type = $file['type'];
        
        if (in_array($file_type, $allowed_types)) {
            // Vérifier la taille (max 2MB)
            if ($file['size'] <= 2 * 1024 * 1024) {
                // Créer le dossier "uploads/profils" s'il n'existe pas
                if (!file_exists('uploads/profils')) {
                    mkdir('uploads/profils', 0777, true);
                }
                
                // Supprimer l'ancienne photo si ce n'est pas la photo par défaut
                if (!empty($admin['photo_profil']) && file_exists('uploads/profils/' . $admin['photo_profil'])) {
                    unlink('uploads/profils/' . $admin['photo_profil']);
                }
                
                // Générer un nom unique pour le fichier
                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $new_filename = 'admin_' . $admin_id . '_' . time() . '.' . $file_extension;
                
                // Déplacer le fichier uploadé
                if (move_uploaded_file($file['tmp_name'], 'uploads/profils/' . $new_filename)) {
                    // Mettre à jour la base de données
                    $stmt = $pdo->prepare("UPDATE utilisateurs SET photo_profil = ? WHERE id = ?");
                    $stmt->execute([$new_filename, $admin_id]);
                    
                    // Mettre à jour la variable locale
                    $admin['photo_profil'] = $new_filename;
                    $photo_path = 'uploads/profils/' . $new_filename;
                    
                    $message = "✅ Photo de profil mise à jour avec succès !";
                    $message_class = "success";
                } else {
                    $message = "❌ Erreur lors du téléchargement de la photo.";
                    $message_class = "error";
                }
            } else {
                $message = "❌ L'image est trop volumineuse (max 2MB).";
                $message_class = "error";
            }
        } else {
            $message = "❌ Type de fichier non autorisé. Formats acceptés : JPG, PNG, GIF.";
            $message_class = "error";
        }
    }
    
    // 2. MODIFICATION DES INFORMATIONS (si formulaire envoyé)
    elseif (isset($_POST['modifier_profil'])) {
        $nom_complet = trim($_POST['nom_complet']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        $errors = [];
        
        // Validation
        if (empty($nom_complet)) {
            $errors[] = "Le nom complet est requis.";
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Adresse email invalide.";
        }
        
        // Vérifier si l'email existe déjà (sauf pour l'admin actuel)
        if (!empty($email)) {
            $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ? AND id != ?");
            $stmt->execute([$email, $admin_id]);
            if ($stmt->rowCount() > 0) {
                $errors[] = "Cet email est déjà utilisé par un autre compte.";
            }
        }
        
        // Si mot de passe fourni, le valider
        if (!empty($password)) {
            if (strlen($password) < 6) {
                $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
            }
            if ($password !== $confirm_password) {
                $errors[] = "Les mots de passe ne correspondent pas.";
            }
        }
        
        if (empty($errors)) {
            try {
                // Préparer la requête de mise à jour
                if (!empty($password)) {
                    // Mettre à jour avec nouveau mot de passe
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE utilisateurs SET nom_complet = ?, email = ?, mot_de_passe = ? WHERE id = ?");
                    $stmt->execute([$nom_complet, $email, $password_hash, $admin_id]);
                } else {
                    // Mettre à jour sans changer le mot de passe
                    $stmt = $pdo->prepare("UPDATE utilisateurs SET nom_complet = ?, email = ? WHERE id = ?");
                    $stmt->execute([$nom_complet, $email, $admin_id]);
                }
                
                // Mettre à jour la session
                $_SESSION['user_nom'] = $nom_complet;
                $_SESSION['user_email'] = $email;
                
                $message = "✅ Profil administrateur mis à jour avec succès !";
                $message_class = "success";
                
                // Recharger les données admin
                $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
                $stmt->execute([$admin_id]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                
            } catch(PDOException $e) {
                $message = "❌ Erreur lors de la mise à jour : " . $e->getMessage();
                $message_class = "error";
            }
        } else {
            $message = implode("<br>", $errors);
            $message_class = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Profil Administrateur</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f0f4f8;
      margin: 0;
      padding: 20px;
    }

    .header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 20px;
    }

    .header .back-btn {
      font-size: 18px;
      color: #333;
      cursor: pointer;
      text-decoration: none;
    }

    .header h2 {
      text-align: center;
      flex-grow: 1;
      margin: 0;
      color: #333;
    }

    .profile-top {
      display: flex;
      flex-direction: column;
      align-items: center;
      margin-top: 20px;
      gap: 20px;
    }

    .photo-container {
      position: relative;
      width: 150px;
      height: 150px;
    }

    .profile-photo {
      width: 150px;
      height: 150px;
      border-radius: 50%;
      object-fit: cover;
      border: 5px solid #007bff;
      box-shadow: 0 4px 10px rgba(0,0,0,0.2);
      cursor: pointer;
      transition: all 0.3s;
    }

    .profile-photo:hover {
      transform: scale(1.05);
      border-color: #0056b3;
    }

    .photo-overlay {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      background: rgba(0, 0, 0, 0.7);
      color: white;
      padding: 8px;
      text-align: center;
      border-bottom-left-radius: 75px;
      border-bottom-right-radius: 75px;
      font-size: 14px;
      display: none;
    }

    .photo-container:hover .photo-overlay {
      display: block;
    }

    .user-info {
      text-align: center;
      flex: 1;
    }

    .user-info p {
      margin: 5px 0;
      font-size: 16px;
    }

    .form-container {
      display: flex;
      justify-content: center;
      margin-top: 30px;
      gap: 20px;
      flex-wrap: wrap;
    }

    .form-section {
      background-color: white;
      padding: 20px;
      border-radius: 10px;
      width: 300px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }

    .form-section h3 {
      margin-top: 0;
      color: #007bff;
      border-bottom: 2px solid #f0f0f0;
      padding-bottom: 10px;
    }

    .form-section label {
      display: block;
      margin-bottom: 5px;
      margin-top: 10px;
      font-weight: bold;
      color: #555;
    }

    .form-section input {
      width: 100%;
      padding: 10px;
      margin-bottom: 5px;
      border: 1px solid #ccc;
      border-radius: 5px;
      font-size: 14px;
    }

    .save-btn {
      display: block;
      margin: 30px auto 0;
      background-color: #28a745;
      color: white;
      padding: 12px 25px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-size: 16px;
      font-weight: bold;
      transition: background-color 0.3s;
    }

    .save-btn:hover {
      background-color: #218838;
    }

    .message {
      text-align: center;
      margin-top: 15px;
      font-weight: bold;
      padding: 10px;
      border-radius: 5px;
      max-width: 600px;
      margin-left: auto;
      margin-right: auto;
    }

    .error {
      color: #721c24;
      background-color: #f8d7da;
      border: 1px solid #f5c6cb;
    }

    .success {
      color: #155724;
      background-color: #d4edda;
      border: 1px solid #c3e6cb;
    }

    input[disabled] {
      background-color: #f8f9fa;
      color: #6c757d;
    }

    /* Modal pour changer la photo */
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
      border-radius: 10px;
      padding: 30px;
      width: 90%;
      max-width: 400px;
      text-align: center;
    }

    .modal-header {
      margin-bottom: 20px;
    }

    .modal-header h3 {
      color: #333;
      margin: 0;
    }

    .close-modal {
      position: absolute;
      top: 15px;
      right: 15px;
      background: none;
      border: none;
      font-size: 24px;
      cursor: pointer;
      color: #666;
    }

    .close-modal:hover {
      color: #333;
    }

    .photo-preview {
      width: 150px;
      height: 150px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid #ddd;
      margin: 20px auto;
      display: none;
    }

    .file-input {
      margin: 20px 0;
    }

    .file-input label {
      display: inline-block;
      background: #007bff;
      color: white;
      padding: 10px 20px;
      border-radius: 5px;
      cursor: pointer;
      transition: background-color 0.3s;
    }

    .file-input label:hover {
      background: #0056b3;
    }

    .file-input input {
      display: none;
    }

    .modal-actions {
      display: flex;
      gap: 10px;
      justify-content: center;
      margin-top: 20px;
    }

    .btn {
      padding: 10px 20px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-size: 14px;
      font-weight: bold;
    }

    .btn-cancel {
      background: #6c757d;
      color: white;
    }

    .btn-cancel:hover {
      background: #5a6268;
    }

    .btn-upload {
      background: #28a745;
      color: white;
    }

    .btn-upload:hover {
      background: #218838;
    }
  </style>
</head>
<body>

  <div class="header">
    
    <h2>Profil Administrateur</h2>
    <div></div>
  </div>

  <div class="profile-top">
    <div class="photo-container">
      <img src="<?php echo $photo_path; ?>" 
           alt="Photo de profil" 
           class="profile-photo"
           onclick="openPhotoModal()">
      <div class="photo-overlay">
        <i class="fas fa-camera"></i> Cliquer pour changer
      </div>
    </div>
    
    <div class="user-info">
      <p><strong><?php echo htmlspecialchars($admin['nom_complet']); ?></strong></p>
      <p><?php echo htmlspecialchars($admin['email']); ?></p>
      <p style="color: #007bff; font-size: 14px; margin-top: 5px;">
        <i class="fas fa-crown"></i> Rôle: Administrateur
      </p>
    </div>
  </div>

  <?php if ($message): ?>
  <div class="message <?php echo $message_class; ?>">
    <?php echo $message; ?>
  </div>
  <?php endif; ?>

  <form method="POST" action="" id="profileForm">
    <div class="form-container">
      <div class="form-section">
        <h3>Informations personnelles</h3>
        <label for="nom_complet">Nom complet</label>
        <input type="text" id="nom_complet" name="nom_complet" 
               value="<?php echo htmlspecialchars($admin['nom_complet']); ?>" disabled required>
        
        <label for="email">Adresse Email</label>
        <input type="email" id="email" name="email" 
               value="<?php echo htmlspecialchars($admin['email']); ?>" disabled required>
      </div>

      <div class="form-section">
        <h3>Sécurité du compte</h3>
        <label for="password">Nouveau mot de passe (laisser vide pour ne pas changer)</label>
        <input type="password" id="password" name="password" value="" disabled>
        
        <label for="confirm_password">Confirmer le nouveau mot de passe</label>
        <input type="password" id="confirm_password" name="confirm_password" value="" disabled>
      </div>
    </div>

    <button type="submit" class="save-btn" id="saveBtn" name="modifier_profil" style="display: none;">Enregistrer les modifications</button>
    <button type="button" class="save-btn" id="editBtn" style="background-color: #007bff;">Modifier le profil</button>
  </form>

  <!-- Modal pour changer la photo de profil -->
  <div class="modal" id="photoModal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Changer la photo de profil</h3>
        <button class="close-modal" onclick="closePhotoModal()">&times;</button>
      </div>
      
      <form method="POST" action="" enctype="multipart/form-data" id="photoForm">
        <img id="photoPreview" class="photo-preview" src="<?php echo $photo_path; ?>" alt="Aperçu">
        
        <div class="file-input">
          <label for="uploadPhoto">
            <i class="fas fa-folder-open"></i> Choisir une photo
          </label>
          <input type="file" id="uploadPhoto" name="photo_profil" accept="image/*" onchange="previewPhoto(event)" required>
        </div>
        
        <p style="font-size: 12px; color: #666; margin: 10px 0;">
          Formats acceptés: JPG, PNG, GIF (max 2MB)
        </p>
        
        <div class="modal-actions">
          <button type="button" class="btn btn-cancel" onclick="closePhotoModal()">Annuler</button>
          <button type="submit" class="btn btn-upload" name="upload_photo">Changer la photo</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    const editBtn = document.getElementById("editBtn");
    const saveBtn = document.getElementById("saveBtn");
    const inputs = document.querySelectorAll("#profileForm input");
    const message = document.querySelector(".message");

    editBtn.addEventListener("click", () => {
      const isDisabled = inputs[0].disabled;
      
      // Activer/désactiver tous les champs
      inputs.forEach(input => input.disabled = !isDisabled);
      
      // Changer le texte du bouton
      if (isDisabled) {
        editBtn.textContent = "Annuler";
        editBtn.style.backgroundColor = "#6c757d";
        saveBtn.style.display = "block";
        if (message) message.style.display = "none";
      } else {
        editBtn.textContent = "Modifier le profil";
        editBtn.style.backgroundColor = "#007bff";
        saveBtn.style.display = "none";
        // Réinitialiser les champs de mot de passe
        document.getElementById("password").value = "";
        document.getElementById("confirm_password").value = "";
      }
    });

    // Fonctions pour le modal de photo
    function openPhotoModal() {
      document.getElementById('photoModal').style.display = 'flex';
    }

    function closePhotoModal() {
      document.getElementById('photoModal').style.display = 'none';
      // Réinitialiser l'aperçu
      document.getElementById('photoPreview').src = "<?php echo $photo_path; ?>";
      document.getElementById('uploadPhoto').value = "";
    }

    function previewPhoto(event) {
      const input = event.target;
      const preview = document.getElementById('photoPreview');
      
      if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
          preview.src = e.target.result;
          preview.style.display = 'block';
        }
        
        reader.readAsDataURL(input.files[0]);
      }
    }

    // Fermer le modal en cliquant à l'extérieur
    window.addEventListener('click', function(event) {
      const modal = document.getElementById('photoModal');
      if (event.target === modal) {
        closePhotoModal();
      }
    });

    // Validation côté client pour le formulaire principal
    document.getElementById("profileForm").addEventListener("submit", function(e) {
      const password = document.getElementById("password").value.trim();
      const confirmPassword = document.getElementById("confirm_password").value.trim();
      
      // Si un mot de passe est fourni, le valider
      if (password !== "") {
        if (password.length < 6) {
          e.preventDefault();
          alert("Le mot de passe doit contenir au moins 6 caractères.");
          return false;
        }
        
        if (password !== confirmPassword) {
          e.preventDefault();
          alert("Les mots de passe ne correspondent pas.");
          return false;
        }
      }
    });

    // Cacher le message après 5 secondes
    setTimeout(function() {
      const messageEl = document.querySelector('.message');
      if (messageEl) {
        messageEl.style.display = 'none';
      }
    }, 5000);
  </script>

</body>
</html>