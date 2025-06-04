<?php
function getDb(): PDO {
  static $pdo;
  if ($pdo) return $pdo;

  $dbPath = __DIR__ . '/database.sqlite';
  $pdo = new PDO("sqlite:$dbPath");
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->exec("PRAGMA foreign_keys = ON");
  return $pdo;
}
