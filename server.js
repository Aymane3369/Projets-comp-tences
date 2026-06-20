require('dotenv').config();
const express = require('express');
const session = require('express-session');
const multer = require('multer');
const path = require('path');
const fs = require('fs');
const cors = require('cors');

const app = express();
const PORT = process.env.PORT || 3000;

// ========== Configuration ==========
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'MonMotDePasseSecret';
const UPLOAD_DIR = path.join(__dirname, 'uploads');
const FILES_PER_PAGE = 20;

// Créer le dossier uploads s'il n'existe pas
if (!fs.existsSync(UPLOAD_DIR)) {
  fs.mkdirSync(UPLOAD_DIR);
}

// ========== Middleware ==========
app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(session({
  secret: 'un-secret-tres-securise',
  resave: false,
  saveUninitialized: false,
  cookie: { secure: false } // mettre true si HTTPS
}));

// Servir les fichiers statiques (frontend)
app.use(express.static(path.join(__dirname, 'public')));

// Servir les fichiers uploads (pour les liens d'accès direct)
app.use('/uploads', express.static(UPLOAD_DIR));

// ========== Middleware d'authentification ==========
function isAdmin(req, res, next) {
  if (req.session && req.session.admin) {
    next();
  } else {
    res.status(401).json({ error: 'Non autorisé' });
  }
}

// ========== Routes API ==========

// Connexion
app.post('/api/login', (req, res) => {
  const { password } = req.body;
  if (password === ADMIN_PASSWORD) {
    req.session.admin = true;
    res.json({ success: true, message: 'Connecté' });
  } else {
    res.status(401).json({ success: false, message: 'Mot de passe incorrect' });
  }
});

// Déconnexion
app.get('/api/logout', (req, res) => {
  req.session.destroy();
  res.json({ success: true, message: 'Déconnecté' });
});

// Vérifier le statut admin
app.get('/api/status', (req, res) => {
  res.json({ admin: !!req.session.admin });
});

// Lister les fichiers d'une catégorie (avec pagination)
app.get('/api/files/:category', (req, res) => {
  const category = req.params.category;
  const page = parseInt(req.query.page) || 1;
  const categoryPath = path.join(UPLOAD_DIR, category);
  
  if (!fs.existsSync(categoryPath)) {
    return res.json({ files: [], total: 0, page, totalPages: 0 });
  }

  try {
    const allFiles = fs.readdirSync(categoryPath)
      .filter(f => path.extname(f).toLowerCase() === '.pdf')
      .sort((a, b) => a.localeCompare(b));

    const total = allFiles.length;
    const totalPages = Math.ceil(total / FILES_PER_PAGE);
    const start = (page - 1) * FILES_PER_PAGE;
    const end = start + FILES_PER_PAGE;
    const files = allFiles.slice(start, end);

    res.json({
      files,
      total,
      page,
      totalPages,
      perPage: FILES_PER_PAGE
    });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// Upload d'un fichier (admin seulement)
const storage = multer.diskStorage({
  destination: (req, file, cb) => {
    const category = req.body.category;
    if (!category) return cb(new Error('Catégorie manquante'));
    const dest = path.join(UPLOAD_DIR, category);
    if (!fs.existsSync(dest)) {
      fs.mkdirSync(dest, { recursive: true });
    }
    cb(null, dest);
  },
  filename: (req, file, cb) => {
    // Gérer les doublons
    const category = req.body.category;
    const baseName = path.basename(file.originalname, path.extname(file.originalname));
    const ext = path.extname(file.originalname);
    let filename = file.originalname;
    let counter = 1;
    const dest = path.join(UPLOAD_DIR, category);
    while (fs.existsSync(path.join(dest, filename))) {
      filename = `${baseName}_${counter}${ext}`;
      counter++;
    }
    cb(null, filename);
  }
});

const upload = multer({
  storage,
  limits: { fileSize: 10 * 1024 * 1024 }, // 10 Mo
  fileFilter: (req, file, cb) => {
    if (path.extname(file.originalname).toLowerCase() !== '.pdf') {
      return cb(new Error('Seul le format PDF est accepté'));
    }
    cb(null, true);
  }
});

app.post('/api/upload', isAdmin, upload.single('file'), (req, res) => {
  if (!req.file) {
    return res.status(400).json({ error: 'Aucun fichier reçu' });
  }
  res.json({ success: true, filename: req.file.filename });
});

// Gestionnaire d'erreur Multer
app.use((err, req, res, next) => {
  if (err instanceof multer.MulterError) {
    return res.status(400).json({ error: err.message });
  }
  if (err) {
    return res.status(400).json({ error: err.message });
  }
  next();
});

// Suppression d'un fichier (admin seulement)
app.delete('/api/file', isAdmin, (req, res) => {
  const { category, filename } = req.query;
  if (!category || !filename) {
    return res.status(400).json({ error: 'Catégorie et nom de fichier requis' });
  }
  // Sécuriser les chemins
  const safeCategory = path.normalize(category).replace(/^(\.\.[\/\\])+/, '');
  const safeFilename = path.normalize(filename).replace(/^(\.\.[\/\\])+/, '');
  const filePath = path.join(UPLOAD_DIR, safeCategory, safeFilename);

  if (!fs.existsSync(filePath)) {
    return res.status(404).json({ error: 'Fichier introuvable' });
  }
  if (path.extname(filePath).toLowerCase() !== '.pdf') {
    return res.status(400).json({ error: 'Fichier non valide' });
  }

  try {
    fs.unlinkSync(filePath);
    res.json({ success: true, message: 'Fichier supprimé' });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// ========== Démarrer le serveur ==========
app.listen(PORT, () => {
  console.log(`Serveur démarré sur http://localhost:${PORT}`);
});
