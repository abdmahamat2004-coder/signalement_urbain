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

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $email = $_SESSION['user_email'];
    $categorie = trim($_POST['categorie']);
    $titre = trim($_POST['titre']);
    $rue = trim($_POST['rue']);
    $quartier = trim($_POST['quartier']);
    $niveau = trim($_POST['niveau']);
    $description = trim($_POST['description']);
    $latitude = !empty($_POST['latitude']) ? $_POST['latitude'] : null;
    $longitude = !empty($_POST['longitude']) ? $_POST['longitude'] : null;
    
    // 1. TRAITEMENT DE L'IMAGE
    $photo_path = null;
    
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        // Vérifier le type de fichier
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
        $file_type = $_FILES['photo']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            // Vérifier la taille (max 5MB)
            if ($_FILES['photo']['size'] <= 5 * 1024 * 1024) {
                // Créer le dossier "uploads" s'il n'existe pas
                if (!file_exists('uploads')) {
                    mkdir('uploads', 0777, true);
                }
                
                // Générer un nom unique pour le fichier
                $file_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                $new_filename = 'signalement_' . time() . '_' . $user_id . '.' . $file_extension;
                $photo_path = 'uploads/' . $new_filename;
                
                // Déplacer le fichier uploadé vers le dossier "uploads"
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path)) {
                    // Succès - le fichier est sauvegardé
                } else {
                    $error_message = "Erreur lors du téléchargement de l'image.";
                }
            } else {
                $error_message = "L'image est trop volumineuse (max 5MB).";
            }
        } else {
            $error_message = "Type de fichier non autorisé. Formats acceptés : JPG, PNG, GIF.";
        }
    }
    
    // 2. INSERTION DANS LA BASE DE DONNÉES
    if (!isset($error_message)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO signalements (user_id, email, categorie, titre, rue, quartier, niveau, description, latitude, longitude, photo_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $user_id,
                $email,
                $categorie,
                $titre,
                $rue,
                $quartier,
                $niveau,
                $description,
                $latitude,
                $longitude,
                $photo_path
            ]);
            
            // Rediriger vers la liste des signalements
            header('Location: liste-sign-utilisateur.php?success=1');
            exit();
            
        } catch(PDOException $e) {
            $error_message = "Erreur lors de l'envoi : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Formulaire Signalement Urbain</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css"/>

    <style>
        /* VOTRE CSS ORIGINAL - JE NE CHANGE RIEN */
        body {
            font-family: Arial, sans-serif;
            background-color: #DFFBEA;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 800px;
            margin: auto;
            padding: 20px;
        }

        .header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .header button {
            font-size: 18px;
            background: none;
            border: none;
            cursor: pointer;
        }

        h2 {
            flex-grow: 1;
            text-align: center;
        }

        .field {
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
        }

        label {
            font-weight: bold;
            margin-bottom: 5px;
        }

        input[type="text"],
        input[type="email"],
        textarea,
        select {
            width: 100%;
            padding: 10px;
            font-size: 16px;
            border-radius: 5px;
            border: 1px solid #ccc;
            box-sizing: border-box;
            height: 45px;
        }

        textarea {
            height: auto;
        }

        .row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .row .field {
            flex: 1;
            min-width: 150px;
        }

        #map {
            height: 300px;
            width: 100%;
            border: 2px solid #ccc;
            border-radius: 5px;
            margin-top: 10px;
            margin-bottom: 15px;
            text-align: center;
            line-height: 300px;
            background-color: #f0f0f0;
        }

        .photo-upload {
            text-align: center;
            padding: 15px;
            border: 2px dashed green;
            background-color: #E6FFE6;
            cursor: pointer;
            border-radius: 5px;
        }

        .photo-upload input {
            display: none;
        }

        .submit-btn {
            background-color: #28a745;
            color: white;
            font-size: 18px;
            padding: 12px;
            border: none;
            border-radius: 5px;
            width: 100%;
            cursor: pointer;
        }

        small {
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <button onclick="history.back()">←</button>
            <h2>Nouveau Signalement</h2>
        </div>

        <?php if (isset($error_message)): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
            <?php echo $error_message; ?>
        </div>
        <?php endif; ?>

        <!-- FORMULAIRE AVEC enctype="multipart/form-data" POUR LES IMAGES -->
        <form method="POST" action="" enctype="multipart/form-data">
            <!-- Champ email -->
            <div class="field">
                <label for="email">Votre adresse e-mail</label>
                <input type="email" id="email" name="email" 
                       value="<?php echo htmlspecialchars($_SESSION['user_email']); ?>" 
                       readonly required>
            </div>

            <!-- Catégorie -->
            <div class="field">
                <label for="categorie">Catégorie du problème</label>
                <select id="categorie" name="categorie" required>
                    <option value="Problème de route">Problème de route</option>
                    <option value="Éclairage">Éclairage</option>
                    <option value="Déchets">Déchets</option>
                    <option value="Problème d'eau">Problème d'eau</option>
                    <option value="Sécurité publique">Sécurité publique</option>
                    <option value="Transport public">Transport public</option>
                </select>
            </div>

            <!-- Titre -->
            <div class="field">
                <label for="titre">Titre du signalement</label>
                <input type="text" id="titre" name="titre" placeholder="Ex: Nids de poule sur la route principale" required>
            </div>

            <!-- Localisation -->
            <div class="field">
                <label>Localisation <button type="button" onclick="getLocation()" style="font-size: 20px; background: none; border: none; cursor: pointer;">📍</button></label>
                <div id="map">Cliquez sur 📍 pour afficher votre position</div>
                <input type="hidden" id="latitude" name="latitude">
                <input type="hidden" id="longitude" name="longitude">
            </div>

            <!-- Rue et Quartier (champs requis mais cachés pour l'interface) -->
            <div style="display: none;">
                <input type="text" id="rue" name="rue" value="Rue détectée" required>
                <input type="text" id="quartier" name="quartier" value="Quartier détecté">
            </div>

            <!-- Gravité -->
            <div class="row">
                <div class="field">
                    <label for="niveau">Gravité</label>
                    <select id="niveau" name="niveau">
                        <option>Faible</option>
                        <option>Moyenne</option>
                        <option>Élevé</option>
                    </select>
                </div>
            </div>

            <!-- Description -->
            <div class="field">
                <label for="description">Décrivez le problème</label>
                <textarea id="description" name="description" rows="5" maxlength="500" placeholder="Ex: Les lampadaires ne fonctionnent plus depuis 3 jours..." required></textarea>
                <small><span id="char-count">0</span>/500 caractères</small>
            </div>

            <!-- Photos - MAINTENANT FONCTIONNEL -->
            <div class="field photo-upload" onclick="document.getElementById('photo').click()">
                <p>📸 Ajouter des photos</p>
                <input type="file" id="photo" name="photo" accept="image/*" multiple onchange="showFileName()">
                <small id="file-name" style="display: none; margin-top: 10px;"></small>
            </div>

            <!-- Envoyer -->
            <div class="field">
                <button type="submit" class="submit-btn">📤 Envoyer le signalement</button>
            </div>
        </form>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>

    <script>
        // VOTRE JAVASCRIPT ORIGINAL
        let map;
        let marker;

        function getLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition((position) => {
                    const lat = position.coords.latitude;
                    const lon = position.coords.longitude;
                    showMap(lat, lon);
                    
                    // Remplir les champs cachés
                    document.getElementById('latitude').value = lat;
                    document.getElementById('longitude').value = lon;
                    
                }, () => {
                    alert("Impossible d'obtenir votre position.");
                });
            } else {
                alert("La géolocalisation n'est pas supportée sur cet appareil.");
            }
        }

        function showMap(lat, lon) {
            if (!map) {
                map = L.map('map').setView([lat, lon], 16);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap'
                }).addTo(map);
                marker = L.marker([lat, lon]).addTo(map);
            } else {
                map.setView([lat, lon], 16);
                marker.setLatLng([lat, lon]);
            }
            
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lon;
        }

        // Afficher le nom du fichier sélectionné
        function showFileName() {
            const input = document.getElementById('photo');
            const fileNameDisplay = document.getElementById('file-name');
            
            if (input.files.length > 0) {
                let fileNames = [];
                for (let i = 0; i < input.files.length; i++) {
                    fileNames.push(input.files[i].name);
                }
                fileNameDisplay.textContent = 'Fichier(s) sélectionné(s) : ' + fileNames.join(', ');
                fileNameDisplay.style.display = 'block';
            } else {
                fileNameDisplay.style.display = 'none';
            }
        }

        document.getElementById("description").addEventListener("input", function () {
            document.getElementById("char-count").innerText = this.value.length;
        });

        // Validation
        document.querySelector("form").addEventListener("submit", function(e) {
            const titre = document.getElementById("titre").value.trim();
            const description = document.getElementById("description").value.trim();
            
            if (!titre || !description) {
                e.preventDefault();
                alert("Veuillez remplir le titre et la description.");
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>