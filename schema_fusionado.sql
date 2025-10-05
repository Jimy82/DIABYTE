-- =========================================
-- ESQUEMA COMPLETO DAW INSULINA v1.0 + FUSIÓN DE ALIMENTOS
-- =========================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS recipe_items;
DROP TABLE IF EXISTS recipes;
DROP TABLE IF EXISTS menu_items;
DROP TABLE IF EXISTS menus;
DROP TABLE IF EXISTS intakes;
DROP TABLE IF EXISTS insulin_params;
DROP TABLE IF EXISTS foods;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(120) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE insulin_params (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  carb_ratio DECIMAL(6,3) NOT NULL DEFAULT 10.000,
  insulin_sensitivity DECIMAL(6,2) NOT NULL DEFAULT 50.00,
  target_bg SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  active_insulin_time SMALLINT UNSIGNED NOT NULL DEFAULT 240,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_insulin_params_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE foods (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  brand VARCHAR(120) NULL,
  carbs_per_100g DECIMAL(6,2) NOT NULL,
  protein_per_100g DECIMAL(6,2) NULL,
  fat_per_100g DECIMAL(6,2) NULL,
  kcal_per_100g DECIMAL(6,1) NULL,
  glycemic_index TINYINT UNSIGNED NULL,
  unit VARCHAR(20) NOT NULL DEFAULT 'g',
  default_serving_g DECIMAL(6,1) NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_foods_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  KEY idx_foods_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recipes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_recipes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_user_recipe (user_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recipe_items (
  recipe_id INT UNSIGNED NOT NULL,
  food_id INT UNSIGNED NOT NULL,
  grams DECIMAL(7,2) NOT NULL,
  PRIMARY KEY (recipe_id, food_id),
  CONSTRAINT fk_recipe_items_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
  CONSTRAINT fk_recipe_items_food FOREIGN KEY (food_id) REFERENCES foods(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE menus (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  date DATE NOT NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_menus_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_menu_day (user_id, date),
  KEY idx_menu_user_date (user_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE menu_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  menu_id INT UNSIGNED NOT NULL,
  type ENUM('desayuno','comida','cena','snack') NOT NULL DEFAULT 'comida',
  source_type ENUM('food','recipe') NOT NULL DEFAULT 'food',
  source_id INT UNSIGNED NOT NULL,
  grams DECIMAL(7,2) NOT NULL,
  CONSTRAINT fk_menu_items_menu FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE intakes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  source_type ENUM('food','recipe') NOT NULL DEFAULT 'food',
  source_id INT UNSIGNED NULL,
  grams DECIMAL(7,2) NOT NULL,
  carbs_g DECIMAL(7,2) NOT NULL,
  dose_units DECIMAL(6,2) NOT NULL,
  pre_bg SMALLINT UNSIGNED NULL,
  post_bg SMALLINT UNSIGNED NULL,
  occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  notes TEXT NULL,
  CONSTRAINT fk_intakes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (email, password_hash, full_name, role)
VALUES ('admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'admin');

INSERT INTO insulin_params (user_id) SELECT id FROM users WHERE email='admin@example.com';

-- Fusión desde tabla alimentos
START TRANSACTION;
DROP TEMPORARY TABLE IF EXISTS _alimentos_clean;
CREATE TEMPORARY TABLE _alimentos_clean (
  name VARCHAR(190) NOT NULL,
  carbs_per_100g DECIMAL(6,2) NOT NULL,
  glycemic_index TINYINT UNSIGNED NULL,
  unit VARCHAR(20) NOT NULL DEFAULT 'g',
  brand VARCHAR(120) NULL
) ENGINE=Memory DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO _alimentos_clean (name, carbs_per_100g, glycemic_index, unit, brand)
SELECT TRIM(nombre),
       ROUND(GREATEST(0, LEAST(100, IFNULL(hidratos, 0))),2),
       CASE WHEN glucemico IS NULL THEN NULL
            WHEN glucemico < 0 THEN NULL
            WHEN glucemico > 120 THEN 120
            ELSE glucemico END,
       'g',
       NULLIF(TRIM(tipo), '')
FROM alimentos
WHERE TRIM(nombre) <> '';

INSERT INTO foods (name, carbs_per_100g, glycemic_index, unit, brand, created_by)
SELECT c.name, c.carbs_per_100g, c.glycemic_index, c.unit, c.brand, NULL
FROM _alimentos_clean c
LEFT JOIN foods f ON f.name = c.name
WHERE f.id IS NULL;

UPDATE foods f
JOIN _alimentos_clean c ON c.name = f.name
SET f.carbs_per_100g = c.carbs_per_100g,
    f.glycemic_index = c.glycemic_index,
    f.unit = c.unit,
    f.brand = COALESCE(c.brand, f.brand)
WHERE (f.carbs_per_100g <> c.carbs_per_100g)
   OR (f.glycemic_index <> c.glycemic_index)
   OR (f.unit <> c.unit)
   OR (f.brand <> c.brand);

COMMIT;
