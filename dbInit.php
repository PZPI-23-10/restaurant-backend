<?php
require_once 'db.php';
$db = getDb();

$db->exec("
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  first_name TEXT NOT NULL,
  last_name TEXT,
  middle_name TEXT,
  email TEXT NOT NULL UNIQUE,
  password TEXT,
  city TEXT,
  street TEXT
);

CREATE TABLE IF NOT EXISTS restaurants (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  description TEXT,
  city TEXT,
  region TEXT,
  street TEXT,
  email TEXT,
  layout TEXT,
  organization TEXT,
  photo_url TEXT,
  latitude REAL,
  longitude REAL,
  has_parking INTEGER,
  accessible INTEGER,
  user_id INTEGER,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS restaurant_moderators (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  restaurant_id INTEGER,
  user_id INTEGER,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS dishes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT,
  photo_url TEXT,
  price REAL,
  weight REAL,
  ingredients TEXT,
  restaurant_id INTEGER,
  FOREIGN KEY(restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS favourite_dishes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER,
  dish_id INTEGER,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(dish_id) REFERENCES dishes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS favourite_restaurants (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER,
  restaurant_id INTEGER,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tables (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  table_number INTEGER,
  is_taken INTEGER,
  restaurant_id INTEGER,
  FOREIGN KEY(restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS reservations (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  people_count INTEGER,
  start_date TEXT,
  end_date TEXT,
  table_id INTEGER,
  user_id INTEGER,
  FOREIGN KEY(table_id) REFERENCES tables(id) ON DELETE CASCADE,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS reviews (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  rating INTEGER,
  comment TEXT,
  restaurant_id INTEGER,
  user_id INTEGER,
  FOREIGN KEY(restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tags (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT
);

CREATE TABLE IF NOT EXISTS restaurant_tags (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  restaurant_id INTEGER,
  tag_id INTEGER,
  FOREIGN KEY(restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
  FOREIGN KEY(tag_id) REFERENCES tags(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS dress_codes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT
);

CREATE TABLE IF NOT EXISTS restaurant_dress_codes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  restaurant_id INTEGER,
  dress_code_id INTEGER,
  FOREIGN KEY(restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
  FOREIGN KEY(dress_code_id) REFERENCES dress_codes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS cuisines (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT
);

CREATE TABLE IF NOT EXISTS restaurant_cuisines (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  restaurant_id INTEGER,
  cuisine_id INTEGER,
  FOREIGN KEY(restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
  FOREIGN KEY(cuisine_id) REFERENCES cuisines(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS schedules (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  day TEXT,
  is_day_off INTEGER,
  open TEXT,
  close TEXT,
  restaurant_id INTEGER,
  FOREIGN KEY(restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS restaurant_photos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  url TEXT,
  restaurant_id INTEGER,
  FOREIGN KEY(restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
);
");

$cuisines = [
  'Українська кухня',
  'Італійська кухня',
  'Японська кухня',
  'Французька кухня',
  'Китайська кухня',
  'Мексиканська кухня',
  'Середземноморська кухня',
  'Азійська кухня',
  'Американська кухня',
  'Вегетаріанська кухня',
  'Веганська кухня'
];

foreach ($cuisines as $name) {
  $stmt = $db->prepare("INSERT INTO cuisines (name) VALUES (?)");
  $stmt->execute([$name]);
}

// Инициализация tags
$tags = [
  'Стейк-хаус',
  'Морепродукти',
  'Суші',
  'Піца',
  'Десерти',
  'Кафе',
  'Ресторан',
  'Бар',
  'Паб',
  'Фаст-фуд',
  'Їдальня',
  'Бістро',
  'Кондитерська',
  'Винний бар',
  'Кальян-бар'
];

foreach ($tags as $name) {
  $stmt = $db->prepare("INSERT INTO tags (name) VALUES (?)");
  $stmt->execute([$name]);
}

// Инициализация dress_codes
$dressCodes = [
  'Casual',
  'Smart Casual',
  'Business Casual',
  'Formal',
  'Business',
  'No Dress Code',
  'Cocktail Attire',
  'Black Tie',
  'White Party',
  'Boho Chic',
  'Creative Attire',
  'Resort Wear',
  'Glam Rock',
  'Vintage Style',
  'Eco Friendly'
];

foreach ($dressCodes as $name) {
  $stmt = $db->prepare("INSERT INTO dress_codes (name) VALUES (?)");
  $stmt->execute([$name]);
}

echo "База данных успешно инициализирована.";
