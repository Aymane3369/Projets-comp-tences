<?php
session_start();

// ==================== CONFIGURATION ====================
define('ADMIN_PASS', 'MonMotDePasseSecret'); // À modifier

// ==================== GESTION DES ACTIONS ====================

// Déconnexion
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Connexion
if (isset($_POST['login_password'])) {
    if ($_POST['login_password'] === ADMIN_PASS) {
        $_SESSION['admin'] = true;
        $_SESSION['message'] = 'Connecté avec succès.';
    } else {
        $_SESSION['message'] = 'Mot de passe incorrect.';
    }
    header('Location: index.php');
    exit;
}

// Upload (admin uniquement) - VÉRIFICATION DE LA MÉTHODE AJOUTÉE
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fichier']) && isset($_POST['dossier'])) {
    if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
        $_SESSION['message'] = 'Action non autorisée.';
        header('Location: index.php');
        exit;
    }
    $dossier = $_POST['dossier'];
    $fichier = $_FILES['fichier'];
    $extension = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
    if ($extension !== 'pdf') {
        $_SESSION['message'] = 'Seul le format PDF est accepté.';
    } elseif ($fichier['size'] > 10 * 1024 * 1024) {
        $_SESSION['message'] = 'Fichier trop volumineux (max 10 Mo).';
    } else {
        $chemin = __DIR__ . '/uploads/' . $dossier;
        if (!is_dir($chemin)) mkdir($chemin, 0777, true);
        $nomFichier = basename($fichier['name']);
        $destination = $chemin . '/' . $nomFichier;
        $i = 1;
        while (file_exists($destination)) {
            $info = pathinfo($nomFichier);
            $nomFichier = $info['filename'] . '_' . $i . '.' . $info['extension'];
            $destination = $chemin . '/' . $nomFichier;
            $i++;
        }
        if (move_uploaded_file($fichier['tmp_name'], $destination)) {
            $_SESSION['message'] = 'Fichier téléversé avec succès.';
        } else {
            $_SESSION['message'] = 'Erreur lors du téléversement.';
        }
    }
    header('Location: index.php');
    exit;
}

// Suppression (admin uniquement)
if (isset($_GET['delete']) && isset($_GET['dossier']) && isset($_GET['fichier'])) {
    if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
        $_SESSION['message'] = 'Action non autorisée.';
        header('Location: index.php');
        exit;
    }
    $dossier = str_replace(['..', '/', '\\'], '', $_GET['dossier']);
    $fichier = str_replace(['..', '/', '\\'], '', $_GET['fichier']);
    $cheminFichier = __DIR__ . '/uploads/' . $dossier . '/' . $fichier;
    if (file_exists($cheminFichier) && pathinfo($cheminFichier, PATHINFO_EXTENSION) === 'pdf') {
        if (unlink($cheminFichier)) {
            $_SESSION['message'] = 'Fichier supprimé.';
        } else {
            $_SESSION['message'] = 'Erreur lors de la suppression.';
        }
    } else {
        $_SESSION['message'] = 'Fichier introuvable.';
    }
    header('Location: index.php');
    exit;
}

// ==================== AFFICHAGE ====================

$isAdmin = isset($_SESSION['admin']) && $_SESSION['admin'] === true;
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

$categories = [
    'Béton armé et Contreventement' => 'beton_arme_contreventement',
    'Bois' => 'bois',
    'Construction Métallique et mixte' => 'construction_metallique_mixte',
    'Géotechnique' => 'geotechnique',
    'Projets' => [
        'BA' => 'projets/ba',
        'Construction mixte' => 'projets/construction_mixte'
    ],
    'logiciels' => [
        'Robot' => 'logiciels/robot',
        'Kréa' => 'logiciels/krea',
        'Plaxis' => 'logiciels/plaxis'
    ]
];

function listerPDF($dossier) {
    $chemin = __DIR__ . '/uploads/' . $dossier;
    if (!is_dir($chemin)) return [];
    $fichiers = scandir($chemin);
    $pdfs = [];
    foreach ($fichiers as $f) {
        if (pathinfo($f, PATHINFO_EXTENSION) === 'pdf') {
            $pdfs[] = $f;
        }
    }
    return $pdfs;
}

function afficherCategorie($nom, $dossier, $isAdmin, $niveau = 0) {
    if (is_array($dossier)) {
        echo '<div class="categorie" style="margin-left: ' . ($niveau * 20) . 'px;">';
        echo '<h3>' . htmlspecialchars($nom) . '</h3>';
        foreach ($dossier as $sousNom => $sousDossier) {
            afficherCategorie($sousNom, $sousDossier, $isAdmin, $niveau + 1);
        }
        echo '</div>';
    } else {
        $pdfs = listerPDF($dossier);
        echo '<div class="categorie" style="margin-left: ' . ($niveau * 20) . 'px;">';
        echo '<h4>' . htmlspecialchars($nom) . '</h4>';
        if (count($pdfs) > 0) {
            echo '<ul>';
            foreach ($pdfs as $pdf) {
                echo '<li>';
                echo '<a href="uploads/' . $dossier . '/' . $pdf . '" target="_blank">' . htmlspecialchars($pdf) . '</a>';
                if ($isAdmin) {
                    echo ' <a href="?delete=1&dossier=' . urlencode($dossier) . '&fichier=' . urlencode($pdf) . '" 
                              class="btn-delete" 
                              onclick="return confirm(\'Supprimer définitivement ce fichier ?\')">🗑️</a>';
                }
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>Aucun PDF pour le moment.</p>';
        }
        if ($isAdmin) {
            echo '<form action="index.php" method="post" enctype="multipart/form-data" class="upload-form">';
            echo '<input type="hidden" name="dossier" value="' . $dossier . '">';
            echo '<input type="file" name="fichier" accept=".pdf" required>';
            echo '<button type="submit">Téléverser un PDF</button>';
            echo '</form>';
        }
        echo '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quelques projets</title>
    <style>
        /* === Styles === (inchangés) */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; margin-bottom: 20px; }
        .message { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px; border: 1px solid #c3e6cb; }
        .admin-bar { background: #ecf0f1; padding: 10px 15px; border-radius: 6px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .btn-login, .btn-logout { background: #3498db; color: white; padding: 5px 15px; border-radius: 4px; text-decoration: none; font-size: 0.9em; }
        .btn-logout { background: #e74c3c; }
        .btn-login:hover { background: #2980b9; }
        .btn-logout:hover { background: #c0392b; }
        #loginForm { width: 100%; margin-top: 8px; }
        #loginForm input[type="password"] { padding: 5px; border: 1px solid #bdc3c7; border-radius: 4px; }
        #loginForm button { background: #2ecc71; color: white; border: none; padding: 5px 15px; border-radius: 4px; cursor: pointer; }
        .accordion-item { border-bottom: 1px solid #e0e0e0; }
        .accordion-header { background: #ecf0f1; padding: 15px; font-weight: bold; cursor: pointer; transition: background 0.2s; }
        .accordion-header:hover { background: #d5dbe0; }
        .accordion-content { padding: 15px 20px; display: none; }
        .categorie { margin-bottom: 15px; }
        .categorie h3 { color: #2980b9; margin: 10px 0 5px 0; font-size: 1.1em; }
        .categorie h4 { color: #34495e; margin: 8px 0 4px 0; font-size: 1em; }
        .categorie ul { list-style: none; padding-left: 10px; }
        .categorie ul li { padding: 4px 0; display: flex; align-items: center; flex-wrap: wrap; }
        .categorie ul li a { color: #2980b9; text-decoration: none; margin-right: 10px; }
        .categorie ul li a:hover { text-decoration: underline; }
        .btn-delete { color: #e74c3c; text-decoration: none; font-size: 1.1em; font-weight: bold; cursor: pointer; }
        .btn-delete:hover { color: #c0392b; }
        .upload-form { margin-top: 10px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .upload-form input[type="file"] { padding: 5px; border: 1px solid #bdc3c7; border-radius: 4px; }
        .upload-form button { background: #3498db; color: white; border: none; padding: 6px 16px; border-radius: 4px; cursor: pointer; font-size: 0.9em; }
        .upload-form button:hover { background: #2980b9; }
        p { color: #7f8c8d; font-style: italic; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📁 Quelques projets</h1>
        
        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="admin-bar">
            <?php if ($isAdmin): ?>
                <span>👋 Connecté en tant qu'admin</span>
                <a href="?logout=1" class="btn-logout">Déconnexion</a>
            <?php else: ?>
                <a href="#" id="showLogin" class="btn-login">🔒 Connexion admin</a>
                <div id="loginForm" style="display:none; margin-top:10px;">
                    <form method="post" action="index.php">
                        <input type="password" name="login_password" placeholder="Mot de passe" required>
                        <button type="submit">Se connecter</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <div id="accordion">
            <?php
            foreach ($categories as $nom => $dossier) {
                echo '<div class="accordion-item">';
                echo '<div class="accordion-header">' . htmlspecialchars($nom) . '</div>';
                echo '<div class="accordion-content">';
                afficherCategorie($nom, $dossier, $isAdmin, 0);
                echo '</div></div>';
            }
            ?>
        </div>
    </div>
    <script>
        // Accordéon
        document.querySelectorAll('.accordion-header').forEach(header => {
            header.addEventListener('click', function() {
                const content = this.nextElementSibling;
                content.style.display = content.style.display === 'block' ? 'none' : 'block';
            });
        });
        document.querySelector('.accordion-content').style.display = 'block';

        // Formulaire de connexion
        document.getElementById('showLogin')?.addEventListener('click', function(e) {
            e.preventDefault();
            const form = document.getElementById('loginForm');
            form.style.display = form.style.display === 'block' ? 'none' : 'block';
        });
    </script>
</body>
</html>
