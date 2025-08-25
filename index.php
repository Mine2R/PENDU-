<?php
session_start();

define('WORDS_FILE', __DIR__ . '/mots.txt');
$MAX_ERRORS = 8; // stages 0..7

function load_words() {
    if (!file_exists(WORDS_FILE)) {
        $default = "dune\noasis\nchameau\ncaravane\noued\nerg\nnomade\nmirage\ndatte\npuits\nsahel\nmehari\nsable\nsirocco\nksar\ndesert\npalmeraie\nburnous\nchergui\nmarabout\n";
        file_put_contents(WORDS_FILE, $default, LOCK_EX);
    }
    $lines = file(WORDS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $words = [];
    foreach ($lines as $w) {
        $w = trim($w);
        $w = mb_strtolower($w, 'UTF-8');
        if (preg_match('/^[a-z]+$/', $w)) $words[] = $w;
    }
    if (empty($words)) {
        $words = ['desert'];
        file_put_contents(WORDS_FILE, implode(PHP_EOL, $words) . PHP_EOL, LOCK_EX);
    }
    return $words;
}

function pick_word_by_difficulty($difficulty) {
    $words = load_words();
    $filtered = [];
    foreach ($words as $w) {
        $len = strlen($w);
        if ($difficulty === 'facile' && $len >= 4 && $len <= 6) $filtered[] = $w;
        elseif ($difficulty === 'moyen' && $len >= 7 && $len <= 8) $filtered[] = $w;
        elseif ($difficulty === 'difficile' && $len >= 9) $filtered[] = $w;
    }
    if (empty($filtered)) $filtered = $words;
    return strtoupper($filtered[array_rand($filtered)]);
}

function start_new_game($difficulty) {
    global $MAX_ERRORS;
    $_SESSION['mot'] = pick_word_by_difficulty($difficulty);
    $_SESSION['devinees'] = [];
    $_SESSION['fautes'] = 0;
    $_SESSION['etat'] = 'en_cours';
    $_SESSION['difficulte'] = $difficulty;
    $_SESSION['indice_utilise'] = false; // <— AJOUT
}

function masked_word() {
    $mot = $_SESSION['mot'] ?? '';
    $devinees = $_SESSION['devinees'] ?? [];
    $masked = '';
    for ($i=0; $i<strlen($mot); $i++) {
        $c = $mot[$i];
        $masked .= in_array($c, $devinees) ? $c . ' ' : '_ ';
    }
    return trim($masked);
}

function check_victory() {
    $mot = $_SESSION['mot'];
    foreach (str_split($mot) as $c) {
        if (!in_array($c, $_SESSION['devinees'])) return false;
    }
    return true;
}



function hangman_svg($errors) {
    $errors = max(0, min((int)$errors, 7));
    // Dessin d’un décor désert + potence + bonhomme progressif
    $parts = [
        // 0: décor + potence vide
        '<g stroke="#7a4a1d" stroke-width="4" fill="none">
            <line x1="40" y1="240" x2="260" y2="240" /> <!-- sol -->
            <line x1="80" y1="240" x2="80" y2="50" />  <!-- poteau -->
            <line x1="80" y1="50"  x2="180" y2="50" /> <!-- poutre -->
            <line x1="180" y1="50" x2="180" y2="80" /> <!-- corde -->
        </g>
        <g fill="#d6b36d" opacity="0.4">
            <ellipse cx="200" cy="245" rx="70" ry="10" />
            <ellipse cx="120" cy="245" rx="60" ry="8" />
        </g>
        <g fill="#96b87d" opacity="0.4">
            <circle cx="300" cy="100" r="22"/>
            <rect x="295" y="120" width="10" height="45" rx="3"/>
        </g>',
        // 1: tête
        '<circle cx="180" cy="100" r="18" stroke="#333" stroke-width="3" fill="#ffe0c1"/>',
        // 2: tronc
        '<line x1="180" y1="118" x2="180" y2="165" stroke="#333" stroke-width="3"/>',
        // 3: bras gauche
        '<line x1="180" y1="132" x2="160" y2="150" stroke="#333" stroke-width="3"/>',
        // 4: bras droit
        '<line x1="180" y1="132" x2="200" y2="150" stroke="#333" stroke-width="3"/>',
        // 5: jambe gauche
        '<line x1="180" y1="165" x2="165" y2="195" stroke="#333" stroke-width="3"/>',
        // 6: jambe droite
        '<line x1="180" y1="165" x2="195" y2="195" stroke="#333" stroke-width="3"/>',
        // 7: croix yeux (défaite)
        '<g stroke="#a40000" stroke-width="2">
            <line x1="172" y1="94" x2="178" y2="100"/>
            <line x1="178" y1="94" x2="172" y2="100"/>
            <line x1="182" y1="94" x2="188" y2="100"/>
            <line x1="188" y1="94" x2="182" y2="100"/>
        </g>',
    ];

    // Compose les étapes selon $errors
    $svg = $parts[0];
    for ($i = 1; $i <= min($errors, 6); $i++) {
        $svg .= $parts[$i];
    }
    if ($errors >= 7) $svg .= $parts[7]; // croix yeux à la toute fin

    return '<svg class="svg-gallows" viewBox="0 0 360 260" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Pendu"><rect x="0" y="0" width="360" height="260" fill="url(#bg)"/>
    <defs>
      <linearGradient id="bg" x1="0" x2="0" y1="0" y2="1">
        <stop offset="0%" stop-color="#fce9b1"/>
        <stop offset="100%" stop-color="#d79e63"/>
      </linearGradient>
    </defs>'
    . $svg .
    '</svg>';
}

function get_definition($word) {
    static $defs = null;
    if ($defs === null) {
        $file = __DIR__ . '/definitions.json';
        if (file_exists($file)) {
            $json = file_get_contents($file);
            $defs = json_decode($json, true) ?: [];
        } else {
            $defs = [];
        }
    }
    $k = strtolower($word ?? '');
    return $defs[$k] ?? null;
}



// Handle actions
if (isset($_POST['nouvelle_partie']) || !isset($_SESSION['mot'])) {
    $d = $_POST['difficulte'] ?? ($_SESSION['difficulte'] ?? 'facile');
    start_new_game($d);
}

if (isset($_POST['abandonner']) && ($_SESSION['etat'] ?? null) === 'en_cours') {
    $_SESSION['etat'] = 'perdu';
}

if (isset($_POST['lettre']) && ($_SESSION['etat'] ?? null) === 'en_cours') {
    $lettre = strtoupper($_POST['lettre']);
    if (preg_match('/^[A-Z]$/', $lettre) && !in_array($lettre, $_SESSION['devinees'])) {
        $_SESSION['devinees'][] = $lettre;
        if (strpos($_SESSION['mot'], $lettre) === false) {
            $_SESSION['fautes']++;
            if ($_SESSION['fautes'] >= $MAX_ERRORS - 1) {
                $_SESSION['etat'] = 'perdu';
            }
        } else {
            if (check_victory()) {
                $_SESSION['etat'] = 'gagne';
            }
        }
    }
}
if (isset($_POST['rejouer'])) {
    $d = $_SESSION['difficulte'] ?? 'facile';
    start_new_game($d);
}
if (isset($_POST['indice']) && (($_SESSION['etat'] ?? null) === 'en_cours') && empty($_SESSION['indice_utilise'])) {
    $_SESSION['indice_utilise'] = true;
}

// Post/Redirect/Get pour éviter la resoumission des formulaires au rafraîchissement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}


$alphabet = range('A', 'Z');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pendu — Oasis du Désert</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
  <header>
    <h1>🏜️ PENDU — <span>Oasis du Désert</span></h1>
    <p class="subtitle">Trouve le mot avant que le voyageur ne soit perdu dans les dunes…</p>
  </header>

  <section class="board">
    <div class="gallows"><?= hangman_svg($_SESSION['fautes'] ?? 0) ?></div>
    <div class="word">
      <?= htmlspecialchars(masked_word()) ?>
    </div>

    <div class="status">
      <?php if (($_SESSION['etat'] ?? null) === 'gagne'): ?>
        <p class="win">🌟 Victoire ! Le mot était <strong><?= htmlspecialchars($_SESSION['mot']) ?></strong></p>
      <?php elseif (($_SESSION['etat'] ?? null) === 'perdu'): ?>
        <p class="lose">💀 Défaite… Le mot était <strong><?= htmlspecialchars($_SESSION['mot']) ?></strong>.</p>
      <?php else: ?>
        <p>Erreurs : <strong><?= $_SESSION['fautes'] ?? 0 ?></strong> / <?= $MAX_ERRORS-1 ?></p>
      <?php endif; ?>

      <?php if (in_array($_SESSION['etat'] ?? '', ['gagne','perdu']) || (!empty($_SESSION['indice_utilise']) && (($_SESSION['etat'] ?? null) === 'en_cours'))): ?>
        <?php $def = get_definition($_SESSION['mot'] ?? ''); ?>
        <p class="definition">
          <strong><?= (!empty($_SESSION['indice_utilise']) && (($_SESSION['etat'] ?? null) === 'en_cours')) ? 'Indice' : 'Définition' ?> :</strong>
          <?= htmlspecialchars($def ?? 'Définition indisponible.') ?>
        </p>
      <?php endif; ?>

      <?php if (($_SESSION['etat'] ?? null) !== 'en_cours'): ?>
        <form method="post"><button class="replay" type="submit" name="rejouer">Rejouer</button></form>
      <?php endif; ?>
    </div>



    <form method="post" class="actions">
      <button type="submit" name="abandonner" <?= (($_SESSION['etat'] ?? null) !== 'en_cours') ? 'disabled' : '' ?>>Abandonner</button>
      <button type="submit" name="indice" <?= ((($_SESSION['etat'] ?? null) !== 'en_cours') || !empty($_SESSION['indice_utilise'])) ? 'disabled' : '' ?>>Indice</button>
    </form>


    <div class="keyboard">
      <?php foreach ($alphabet as $L): 
        $already = in_array($L, $_SESSION['devinees'] ?? []);
        $disabled = $already || (($_SESSION['etat'] ?? null) !== 'en_cours');
      ?>
        <form method="post" class="key">
          <input type="hidden" name="lettre" value="<?= $L ?>">
          <button type="submit" <?= $disabled ? 'disabled' : '' ?>><?= $L ?></button>
        </form>
      <?php endforeach; ?>
    </div>

    <div class="history">
      <strong>Lettres proposées :</strong>
      <span>
        <?= implode(' ', $_SESSION['devinees'] ?? []) ?: '—' ?>
      </span>
    </div>

    <footer>
      <a class="link" href="admin.php">⚙️ Gérer les mots</a>
    </footer>
  </section>
</div>
</body>
</html>
