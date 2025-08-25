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

function hangman_ascii($errors) {
    $stages = [
" 
     ┌───────┐
     │       │
             │
             │
             │
             │
   ──────────┴─
",
"
     ┌───────┐
     │       │
     O       │
             │
             │
             │
   ──────────┴─
",
"
     ┌───────┐
     │       │
     O       │
     |       │
             │
             │
   ──────────┴─
",
"
     ┌───────┐
     │       │
     O       │
    /|       │
             │
             │
   ──────────┴─
",
"
     ┌───────┐
     │       │
     O       │
    /|\\      │
             │
             │
   ──────────┴─
",
"
     ┌───────┐
     │       │
     O       │
    /|\\      │
    /        │
             │
   ──────────┴─
",
"
     ┌───────┐
     │       │
     O       │
    /|\\      │
    / \\      │
             │
   ──────────┴─
",
"
     ┌───────┐
     │       │
    [O]      │
    /|\\      │
    / \\      │
             │
   ──────────┴─
"
    ];
    $errors = max(0, min($errors, count($stages)-1));
    return $stages[$errors];
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
    <pre class="gallows"><?= htmlspecialchars(hangman_ascii($_SESSION['fautes'] ?? 0)) ?></pre>
    <div class="word">
      <?= htmlspecialchars(masked_word()) ?>
    </div>

    <div class="status">
      <?php if (($_SESSION['etat'] ?? null) === 'gagne'): ?>
        <p class="win">🌟 Victoire ! Le mot était <strong><?= $_SESSION['mot'] ?></strong>. Une datte pour la route ?</p>
      <?php elseif (($_SESSION['etat'] ?? null) === 'perdu'): ?>
        <p class="lose">💀 Défaite… Le vent l'a emporté. Le mot était <strong><?= $_SESSION['mot'] ?></strong>.</p>
      <?php else: ?>
        <p>Erreurs : <strong><?= $_SESSION['fautes'] ?? 0 ?></strong> / <?= $MAX_ERRORS-1 ?></p>
      <?php endif; ?>
    </div>

    <form method="post" class="actions">
      <label for="difficulte">Difficulté :</label>
      <select name="difficulte" id="difficulte" onchange="this.form.submit()">
        <?php
          $d = $_SESSION['difficulte'] ?? 'facile';
          $opts = ['facile' => 'Facile (4–6)', 'moyen' => 'Moyen (7–8)', 'difficile' => 'Difficile (9+)'];
          foreach ($opts as $val => $label) {
              $sel = $d === $val ? 'selected' : '';
              echo "<option value=\"$val\" $sel>$label</option>";
          }
        ?>
      </select>
      <button type="submit" name="nouvelle_partie">Nouvelle partie</button>
      <button type="submit" name="abandonner" <?= (($_SESSION['etat'] ?? null) !== 'en_cours') ? 'disabled' : '' ?>>Abandonner</button>
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
