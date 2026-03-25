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

// Statistiques globales
$en_attente = $pdo->query("SELECT COUNT(*) FROM signalements WHERE statut = 'en_attente'")->fetchColumn();
$en_cours = $pdo->query("SELECT COUNT(*) FROM signalements WHERE statut = 'en_cours'")->fetchColumn();
$resolus = $pdo->query("SELECT COUNT(*) FROM signalements WHERE statut = 'resolu'")->fetchColumn();

// Récupérer TOUS les signalements
$signalements = $pdo->query("SELECT s.*, u.nom_complet 
                              FROM signalements s 
                              LEFT JOIN utilisateurs u ON s.user_id = u.id 
                              ORDER BY s.date_signalement DESC")->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les missions assignées à CET agent
$agent_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT s.*, u.nom_complet as citoyen_nom 
                        FROM signalements s 
                        LEFT JOIN utilisateurs u ON s.user_id = u.id 
                        WHERE s.agent_id = ?
                        ORDER BY s.date_signalement DESC");
$stmt->execute([$agent_id]);
$missions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    $id_signalement = $_POST['signalement_id'];
    
    // CHANGEMENT DE STATUT
    if ($_POST['action'] === 'changer_statut') {
        $nouveau_statut = $_POST['nouveau_statut'];
        $stmt = $pdo->prepare("UPDATE signalements SET statut = ? WHERE id = ? AND agent_id = ?");
        $stmt->execute([$nouveau_statut, $id_signalement, $agent_id]);
        header('Location: tableau_de_bord_agent.php?success=statut&id=' . $id_signalement);
        exit();
    }
    
    // UPLOAD DE PHOTO
    if ($_POST['action'] === 'upload_photo' && isset($_FILES['photo_resolution']) && $_FILES['photo_resolution']['error'] === 0) {
        
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
        $file_type = $_FILES['photo_resolution']['type'];
        
        if (in_array($file_type, $allowed_types) && $_FILES['photo_resolution']['size'] <= 5 * 1024 * 1024) {
            
            if (!file_exists('uploads')) mkdir('uploads', 0777, true);
            
            $ext = pathinfo($_FILES['photo_resolution']['name'], PATHINFO_EXTENSION);
            $filename = 'resolu_' . time() . '_' . $id_signalement . '.' . $ext;
            $path = 'uploads/' . $filename;
            
            if (move_uploaded_file($_FILES['photo_resolution']['tmp_name'], $path)) {
                $stmt = $pdo->prepare("UPDATE signalements SET photo_resolution = ?, statut = 'resolu' WHERE id = ? AND agent_id = ?");
                $stmt->execute([$path, $id_signalement, $agent_id]);
                header('Location: tableau_de_bord_agent.php?success=photo&id=' . $id_signalement);
                exit();
            }
        }
        header('Location: tableau_de_bord_agent.php?error=upload&id=' . $id_signalement);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord Agent</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
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

        .sidebar.hidden { transform: translateX(-100%); }
        .sidebar ul { list-style: none; padding: 20px 0; }
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
        .sidebar a i { margin-right: 15px; width: 20px; text-align: center; }
        .sidebar .logout a {
            color: #e74c3c;
            margin-top: 30px;
        }
        .sidebar .logout a:hover {
            background-color: #c0392b;
            color: white;
            border-left: 4px solid #e74c3c;
        }

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
        #menu-icon:hover { background: #34495e; transform: scale(1.05); }

        .main-content {
            flex: 1;
            padding: 20px;
            margin-left: 250px;
            transition: margin-left 0.3s ease;
        }
        .sidebar.hidden + .main-content { margin-left: 0; }

        .top-bar {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        .top-bar h1 { color: #2c3e50; font-size: 1.8rem; }

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
        .card:hover { transform: translateY(-5px); }
        .card.orange { background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); }
        .card.blue { background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); }
        .card.green { background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); }

        .section-title {
            margin: 30px 0 20px 0;
            color: #2c3e50;
            font-size: 1.4rem;
            border-bottom: 2px solid #28A745;
            padding-bottom: 10px;
        }

        .signalement-table {
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        .signalement-table thead {
            background: linear-gradient(135deg, #28A745 0%, #218838 100%);
            color: white;
        }
        .signalement-table th { padding: 15px; text-align: left; }
        .signalement-table td { padding: 15px; border-bottom: 1px solid #f0f0f0; }

        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .badge.en_attente { background-color: #fdebd0; color: #e67e22; }
        .badge.en_cours { background-color: #d6eaf8; color: #3498db; }
        .badge.resolu { background-color: #d5f4e6; color: #27ae60; }

        .btn {
            display: inline-block;
            padding: 8px 12px;
            background: #28A745;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
        }
        .btn:hover { background: #218838; }

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
            color: #28A745;
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
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .photo-box h4 { color: #28A745; margin-bottom: 10px; }
        .photo-box img {
            max-width: 100%;
            max-height: 250px;
            border-radius: 5px;
        }

        .action-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .action-section h4 { color: #28A745; margin-bottom: 15px; }
        select, input[type="file"] {
            width: 100%;
            padding: 10px;
            margin: 5px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .btn-action {
            background: #28A745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
        }
        .btn-action:hover { background: #218838; }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .sidebar { width: 200px; }
            .main-content { margin-left: 200px; }
            .cards { flex-direction: column; }
            .photo-container { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div id="menu-icon"><i class="fas fa-bars"></i></div>

    <aside class="sidebar hidden" id="sidebar">
        <ul>
            <li><a href="tableau_de_bord_agent.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
            <li><a href="liste_sign_agent.php"><i class="fas fa-list-alt"></i> Tous les signalements</a></li>
            <li><a href="mes_missions.php"><i class="fas fa-tasks"></i> Mes missions</a></li>
            <li class="logout"><a href="deconnexion.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
    </aside>

    <main class="main-content" id="main-content">
        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">
                <?php if ($_GET['success'] == 'statut'): ?>✅ Statut mis à jour !
                <?php elseif ($_GET['success'] == 'photo'): ?>✅ Photo ajoutée et mission terminée !
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="top-bar">
            <h1>Tableau de bord Agent</h1>
            <p>Bienvenue, <strong><?php echo htmlspecialchars($_SESSION['user_nom']); ?></strong></p>
        </div>

        <div class="cards">
            <div class="card orange">En attente: <?php echo $en_attente; ?></div>
            <div class="card blue">En cours: <?php echo $en_cours; ?></div>
            <div class="card green">Résolus: <?php echo $resolus; ?></div>
        </div>

        <h2 class="section-title"><i class="fas fa-tasks"></i> Mes missions assignées</h2>
        <?php if (empty($missions)): ?>
            <div style="text-align:center; padding:40px;">Aucune mission assignée</div>
        <?php else: ?>
            <table class="signalement-table">
                <thead><tr><th>Titre</th><th>Catégorie</th><th>Signalé par</th><th>Date</th><th>Statut</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($missions as $m): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($m['titre']); ?></td>
                        <td><?php echo htmlspecialchars($m['categorie']); ?></td>
                        <td><?php echo htmlspecialchars($m['citoyen_nom'] ?? 'Anonyme'); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($m['date_signalement'])); ?></td>
                        <td><span class="badge <?php echo $m['statut']; ?>">
                            <?php if($m['statut']=='en_attente') echo 'En attente';
                            elseif($m['statut']=='en_cours') echo 'En cours';
                            else echo 'Résolu'; ?>
                        </span></td>
                        <td><button class="btn" onclick="afficherDetails(<?php echo $m['id']; ?>)">Voir</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h2 class="section-title"><i class="fas fa-list-alt"></i> Tous les signalements</h2>
        <table class="signalement-table">
            <thead><tr><th>Titre</th><th>Catégorie</th><th>Rue</th><th>Signalé par</th><th>Date</th><th>Statut</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($signalements as $s): ?>
                <tr>
                    <td><?php echo htmlspecialchars($s['titre']); ?></td>
                    <td><?php echo htmlspecialchars($s['categorie']); ?></td>
                    <td><?php echo htmlspecialchars($s['rue'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($s['nom_complet'] ?? 'Anonyme'); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($s['date_signalement'])); ?></td>
                    <td><span class="badge <?php echo $s['statut']; ?>">
                        <?php if($s['statut']=='en_attente') echo 'En attente';
                        elseif($s['statut']=='en_cours') echo 'En cours';
                        else echo 'Résolu'; ?>
                    </span></td>
                    <td><button class="btn" onclick="afficherDetails(<?php echo $s['id']; ?>)">Voir</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>

    <div class="modal" id="modalDetails">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Détails de la mission</h2>
                <button class="close-modal" onclick="fermerModal()">&times;</button>
            </div>
            <div id="contenuDetails"></div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
    <script>
        const menuIcon = document.getElementById('menu-icon');
        const sidebar = document.getElementById('sidebar');
        menuIcon.addEventListener('click', (e) => { e.stopPropagation(); sidebar.classList.toggle('hidden'); });
        document.addEventListener('click', (e) => { if (!sidebar.contains(e.target) && !menuIcon.contains(e.target)) sidebar.classList.add('hidden'); });

        const signalements = <?php echo json_encode($signalements); ?>;
        const agentId = <?php echo $_SESSION['user_id']; ?>;

        function afficherDetails(id) {
            const signalement = signalements.find(s => s.id == id);
            if (!signalement) return;

            const isMine = (signalement.agent_id == agentId);
            const statutTexte = signalement.statut === 'en_attente' ? 'En attente' : (signalement.statut === 'en_cours' ? 'En cours' : 'Résolu');

            let html = `
                <div class="detail-item"><strong>ID:</strong> #${signalement.id}</div>
                <div class="detail-item"><strong>Titre:</strong> ${signalement.titre || ''}</div>
                <div class="detail-item"><strong>Rue:</strong> ${signalement.rue || ''}</div>
                <div class="detail-item"><strong>Quartier:</strong> ${signalement.quartier || ''}</div>
                <div class="detail-item"><strong>Catégorie:</strong> ${signalement.categorie || ''}</div>
                <div class="detail-item"><strong>Date:</strong> ${new Date(signalement.date_signalement).toLocaleDateString('fr-FR')}</div>
                <div class="detail-item"><strong>Statut:</strong> <span class="badge ${signalement.statut}">${statutTexte}</span></div>
                <div class="detail-item"><strong>Gravité:</strong> ${signalement.niveau || ''}</div>
                <div class="detail-item"><strong>Signalé par:</strong> ${signalement.nom_complet || 'Anonyme'}</div>
                <div class="detail-item"><strong>Description:</strong><br>${signalement.description || ''}</div>
            `;

            // Photos
            html += `<div class="photo-container">`;
            html += `<div class="photo-box"><h4>📸 Avant</h4>`;
            if (signalement.photo_path) html += `<img src="${signalement.photo_path}">`;
            else html += `<div><i class="fas fa-camera"></i><br>Aucune photo</div>`;
            html += `</div><div class="photo-box"><h4>✅ Après</h4>`;
            if (signalement.photo_resolution) html += `<img src="${signalement.photo_resolution}">`;
            else html += `<div><i class="fas fa-cloud-upload-alt"></i><br>Pas de photo après</div>`;
            html += `</div></div>`;

            // Carte
            if (signalement.latitude && signalement.longitude) {
                html += `<div class="detail-item"><strong>🗺️ Localisation:</strong><div id="map_${signalement.id}" style="height:300px;"></div></div>`;
            }

            // Actions (uniquement si c'est sa mission et pas déjà résolu)
            if (isMine && signalement.statut !== 'resolu') {
                html += `
                    <div class="action-section">
                        <h4>Actions</h4>
                        <form method="POST" enctype="multipart/form-data" action="tableau_de_bord_agent.php">
                            <input type="hidden" name="action" value="changer_statut">
                            <input type="hidden" name="signalement_id" value="${signalement.id}">
                            <select name="nouveau_statut">
                                <option value="en_attente" ${signalement.statut==='en_attente'?'selected':''}>En attente</option>
                                <option value="en_cours" ${signalement.statut==='en_cours'?'selected':''}>En cours</option>
                                <option value="resolu">Résolu</option>
                            </select>
                            <button type="submit" class="btn-action" style="margin-top:10px;">Mettre à jour</button>
                        </form>
                        <hr>
                        <form method="POST" enctype="multipart/form-data" action="tableau_de_bord_agent.php">
                            <input type="hidden" name="action" value="upload_photo">
                            <input type="hidden" name="signalement_id" value="${signalement.id}">
                            <input type="file" name="photo_resolution" accept="image/*" required>
                            <button type="submit" class="btn-action" style="margin-top:10px;">Uploader photo et terminer</button>
                        </form>
                    </div>
                `;
            } else if (signalement.statut === 'resolu') {
                html += `<div class="action-section" style="text-align:center; color:#28a745;"><i class="fas fa-check-circle" style="font-size:48px;"></i><h4>Mission terminée</h4></div>`;
            }

            document.getElementById('contenuDetails').innerHTML = html;
            document.getElementById('modalDetails').style.display = 'block';

            setTimeout(() => {
                if (signalement.latitude && signalement.longitude) {
                    const map = L.map('map_' + signalement.id).setView([signalement.latitude, signalement.longitude], 16);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
                    L.marker([signalement.latitude, signalement.longitude]).addTo(map);
                }
            }, 200);
        }

        function fermerModal() { document.getElementById('modalDetails').style.display = 'none'; }
        window.onclick = (e) => { if (e.target.classList.contains('modal')) fermerModal(); };
    </script>
</body>
</html>