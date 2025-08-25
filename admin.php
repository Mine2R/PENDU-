<?php
session_start();
define('WORDS_FILE', __DIR__ . '/mots.txt');

function read_words() {
    if (!file_exists(WORDS_FILE)) {
        return [];
    }
    $lines = file(WORDS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $out = [];
    foreach ($lines as $w) {
        $w = trim($w);
        if ($w !== '') $out[] = $w;
    }
    return $out;
}

function write_words($arr) {
    $content = implode(PHP_EOL, $arr) . PHP_EOL;
    file_put_contents(WORDS_FILE, $content, LOCK_EX);
}

$errors = [];
$success = [];

if (!file_exists(WORDS_FILE)) {
    write_words(['desert']);
}

// Add word
if (isset($_POST['ajouter'])) {
    $word = strtolower(trim($_POST['word'] ?? ''));
    if ($word === '') {
        $errors[] = "Le mot est vide.";
    } elseif (!preg_match('/^[a-z]+$/', $word)) {
        $errors[] = "Uniquement des lettres a–z (sans accents, ni espaces).";
    } else {
        $words = read_words();
        if (in_array($word, $words)) {
            $errors[] = "Le mot existe déjà.";
        } else {
            $words[] = $word;
            sort($words);
            write_words($words);
            $success[] = "Mot « $word » ajouté.";
        }
    }
}

// Delete word
if (isset($_POST['supprimer'])) {
    $target = $_POST['supprimer'];
    $words = read_words();
    if (!in_array($target, $words)) {
        $errors[] = "Mot introuvable.";
    } elseif (count($words) <= 1) {
        $errors[] = "Impossible de supprimer : il doit rester au moins un mot.";
    } else {
        $words = array_values(array_filter($words, fn($w) => $w !== $target));
        write_words($words);
        $success[] = "Mot « $target » supprimé.";
    }
}

$words = read_words();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — Pendu Oasis</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
  <header>
    <h1>⚙️ Administration — <span>Oasis du Désert</span></h1>
    <p class="subtitle">Ajouter / Supprimer des mots (a–z, sans accents)</p>
  </header>

  <?php if ($errors): ?>
    <div class="alert error">
      <ul>
        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert success">
      <ul>
        <?php foreach ($success as $s): ?><li><?= htmlspecialchars($s) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <section class="admin">
    <form method="post" class="add-form">
      <input type="text" name="word" placeholder="Nouveau mot (ex: caravane)" required pattern="[a-z]+" title="Lettres a–z uniquement, sans accents">
      <button type="submit" name="ajouter">Ajouter</button>
    </form>

    <h2>Liste des mots (<?= count($words) ?>)</h2>
    <ul class="word-list">
      <?php foreach ($words as $w): ?>
        <li>
          <span><?= htmlspecialchars($w) ?></span>
          <form method="post" class="inline">
            <input type="hidden" name="supprimer" value="<?= htmlspecialchars($w) ?>">
            <button type="submit">Supprimer</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>

    <footer>
      <a class="link" href="index.php">← Revenir au jeu</a>
    </footer>
  </section>
</div>
</body>
</html>
