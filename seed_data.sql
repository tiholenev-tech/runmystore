-- ========================================
-- SEED DATA: 100 артикула + продажби
-- tenant_id=7, store_id=1
-- ========================================

-- КАТЕГОРИИ
INSERT INTO categories (tenant_id, name, variant_type) VALUES
(7, 'Бельо', 'size_color'),
(7, 'Чорапи', 'size_color'),
(7, 'Пижами', 'size_color'),
(7, 'Бански', 'size_color'),
(7, 'Обувки', 'size_color'),
(7, 'Тениски', 'size_color'),
(7, 'Дънки', 'size_color'),
(7, 'Якета', 'size_color'),
(7, 'Аксесоари', 'none'),
(7, 'Спортни', 'size_color');

SET @cat_belyo = (SELECT id FROM categories WHERE tenant_id=7 AND name='Бельо' LIMIT 1);
SET @cat_chorapi = (SELECT id FROM categories WHERE tenant_id=7 AND name='Чорапи' LIMIT 1);
SET @cat_pizhami = (SELECT id FROM categories WHERE tenant_id=7 AND name='Пижами' LIMIT 1);
SET @cat_banski = (SELECT id FROM categories WHERE tenant_id=7 AND name='Бански' LIMIT 1);
SET @cat_obuvki = (SELECT id FROM categories WHERE tenant_id=7 AND name='Обувки' LIMIT 1);
SET @cat_teniski = (SELECT id FROM categories WHERE tenant_id=7 AND name='Тениски' LIMIT 1);
SET @cat_dynki = (SELECT id FROM categories WHERE tenant_id=7 AND name='Дънки' LIMIT 1);
SET @cat_yaketa = (SELECT id FROM categories WHERE tenant_id=7 AND name='Якета' LIMIT 1);
SET @cat_aksesoari = (SELECT id FROM categories WHERE tenant_id=7 AND name='Аксесоари' LIMIT 1);
SET @cat_sportni = (SELECT id FROM categories WHERE tenant_id=7 AND name='Спортни' LIMIT 1);

-- ПРОДУКТИ (100 бр)
INSERT INTO products (tenant_id, category_id, code, name, barcode, size, color, cost_price, wholesale_price, retail_price, min_quantity) VALUES
-- Бельо (20)
(7, @cat_belyo, 'BL001', 'Сутиен Triumph 70B', '5901234560001', '70B', 'Черен', 12.00, 18.00, 29.99, 3),
(7, @cat_belyo, 'BL002', 'Сутиен Triumph 75B', '5901234560002', '75B', 'Черен', 12.00, 18.00, 29.99, 3),
(7, @cat_belyo, 'BL003', 'Сутиен Triumph 80C', '5901234560003', '80C', 'Бял', 12.00, 18.00, 29.99, 3),
(7, @cat_belyo, 'BL004', 'Бикини Lisca S', '5901234560004', 'S', 'Розов', 5.00, 8.00, 14.99, 5),
(7, @cat_belyo, 'BL005', 'Бикини Lisca M', '5901234560005', 'M', 'Розов', 5.00, 8.00, 14.99, 5),
(7, @cat_belyo, 'BL006', 'Бикини Lisca L', '5901234560006', 'L', 'Черен', 5.00, 8.00, 14.99, 5),
(7, @cat_belyo, 'BL007', 'Комплект бельо Passionata S', '5901234560007', 'S', 'Червен', 22.00, 32.00, 54.99, 2),
(7, @cat_belyo, 'BL008', 'Комплект бельо Passionata M', '5901234560008', 'M', 'Червен', 22.00, 32.00, 54.99, 2),
(7, @cat_belyo, 'BL009', 'Боксерки мъжки M', '5901234560009', 'M', 'Сив', 4.00, 7.00, 12.99, 5),
(7, @cat_belyo, 'BL010', 'Боксерки мъжки L', '5901234560010', 'L', 'Сив', 4.00, 7.00, 12.99, 5),
(7, @cat_belyo, 'BL011', 'Боксерки мъжки XL', '5901234560011', 'XL', 'Черен', 4.00, 7.00, 12.99, 5),
(7, @cat_belyo, 'BL012', 'Корсаж Triumph 75C', '5901234560012', '75C', 'Бежов', 18.00, 26.00, 44.99, 2),
(7, @cat_belyo, 'BL013', 'Корсаж Triumph 80B', '5901234560013', '80B', 'Бежов', 18.00, 26.00, 44.99, 2),
(7, @cat_belyo, 'BL014', 'Прашки дамски S', '5901234560014', 'S', 'Черен', 3.50, 6.00, 9.99, 5),
(7, @cat_belyo, 'BL015', 'Прашки дамски M', '5901234560015', 'M', 'Бял', 3.50, 6.00, 9.99, 5),
(7, @cat_belyo, 'BL016', 'Топ без банели S', '5901234560016', 'S', 'Черен', 8.00, 13.00, 22.99, 3),
(7, @cat_belyo, 'BL017', 'Топ без банели M', '5901234560017', 'M', 'Розов', 8.00, 13.00, 22.99, 3),
(7, @cat_belyo, 'BL018', 'Топ без банели L', '5901234560018', 'L', 'Бял', 8.00, 13.00, 22.99, 3),
(7, @cat_belyo, 'BL019', 'Слип дамски M', '5901234560019', 'M', 'Бежов', 4.00, 7.00, 11.99, 5),
(7, @cat_belyo, 'BL020', 'Слип дамски L', '5901234560020', 'L', 'Бежов', 4.00, 7.00, 11.99, 5),

-- Чорапи (10)
(7, @cat_chorapi, 'CH001', 'Чорапи мъжки 39-42 черни', '5901234560021', '39-42', 'Черен', 1.50, 3.00, 5.99, 10),
(7, @cat_chorapi, 'CH002', 'Чорапи мъжки 43-46 черни', '5901234560022', '43-46', 'Черен', 1.50, 3.00, 5.99, 10),
(7, @cat_chorapi, 'CH003', 'Чорапи дамски 35-38', '5901234560023', '35-38', 'Бежов', 1.50, 3.00, 5.99, 10),
(7, @cat_chorapi, 'CH004', 'Чорапи дамски 39-42', '5901234560024', '39-42', 'Черен', 1.50, 3.00, 5.99, 10),
(7, @cat_chorapi, 'CH005', 'Чорапогащник Omsa 20DEN S', '5901234560025', 'S', 'Бежов', 2.00, 4.00, 7.99, 8),
(7, @cat_chorapi, 'CH006', 'Чорапогащник Omsa 20DEN M', '5901234560026', 'M', 'Бежов', 2.00, 4.00, 7.99, 8),
(7, @cat_chorapi, 'CH007', 'Чорапогащник Omsa 40DEN L', '5901234560027', 'L', 'Черен', 2.50, 4.50, 8.99, 8),
(7, @cat_chorapi, 'CH008', 'Термо чорапи мъжки 39-42', '5901234560028', '39-42', 'Сив', 3.00, 5.00, 9.99, 5),
(7, @cat_chorapi, 'CH009', 'Детски чорапи 25-30', '5901234560029', '25-30', 'Бял', 1.00, 2.50, 4.99, 10),
(7, @cat_chorapi, 'CH010', 'Спортни чорапи 39-42', '5901234560030', '39-42', 'Бял', 2.00, 4.00, 7.99, 8),

-- Пижами (10)
(7, @cat_pizhami, 'PJ001', 'Пижама дамска S флорал', '5901234560031', 'S', 'Розов', 14.00, 22.00, 39.99, 2),
(7, @cat_pizhami, 'PJ002', 'Пижама дамска M флорал', '5901234560032', 'M', 'Розов', 14.00, 22.00, 39.99, 2),
(7, @cat_pizhami, 'PJ003', 'Пижама дамска L сатен', '5901234560033', 'L', 'Черен', 18.00, 28.00, 49.99, 2),
(7, @cat_pizhami, 'PJ004', 'Пижама мъжка M', '5901234560034', 'M', 'Син', 12.00, 20.00, 34.99, 2),
(7, @cat_pizhami, 'PJ005', 'Пижама мъжка L', '5901234560035', 'L', 'Сив', 12.00, 20.00, 34.99, 2),
(7, @cat_pizhami, 'PJ006', 'Пижама мъжка XL', '5901234560036', 'XL', 'Син', 12.00, 20.00, 34.99, 2),
(7, @cat_pizhami, 'PJ007', 'Нощница дамска S', '5901234560037', 'S', 'Розов', 10.00, 16.00, 27.99, 3),
(7, @cat_pizhami, 'PJ008', 'Нощница дамска M', '5901234560038', 'M', 'Лилав', 10.00, 16.00, 27.99, 3),
(7, @cat_pizhami, 'PJ009', 'Халат дамски M', '5901234560039', 'M', 'Бял', 16.00, 25.00, 44.99, 2),
(7, @cat_pizhami, 'PJ010', 'Халат дамски L', '5901234560040', 'L', 'Розов', 16.00, 25.00, 44.99, 2),

-- Бански (10)
(7, @cat_banski, 'BA001', 'Бански цял дамски S', '5901234560041', 'S', 'Черен', 15.00, 24.00, 44.99, 2),
(7, @cat_banski, 'BA002', 'Бански цял дамски M', '5901234560042', 'M', 'Син', 15.00, 24.00, 44.99, 2),
(7, @cat_banski, 'BA003', 'Бански бикини S', '5901234560043', 'S', 'Червен', 12.00, 20.00, 36.99, 2),
(7, @cat_banski, 'BA004', 'Бански бикини M', '5901234560044', 'M', 'Бял', 12.00, 20.00, 36.99, 2),
(7, @cat_banski, 'BA005', 'Бански шорти мъжки M', '5901234560045', 'M', 'Син', 8.00, 14.00, 24.99, 3),
(7, @cat_banski, 'BA006', 'Бански шорти мъжки L', '5901234560046', 'L', 'Черен', 8.00, 14.00, 24.99, 3),
(7, @cat_banski, 'BA007', 'Бански шорти мъжки XL', '5901234560047', 'XL', 'Зелен', 8.00, 14.00, 24.99, 3),
(7, @cat_banski, 'BA008', 'Парео дамско', '5901234560048', 'ONE', 'Цветен', 6.00, 10.00, 18.99, 3),
(7, @cat_banski, 'BA009', 'Бански горнище S', '5901234560049', 'S', 'Жълт', 10.00, 16.00, 29.99, 2),
(7, @cat_banski, 'BA010', 'Бански горнище M', '5901234560050', 'M', 'Оранжев', 10.00, 16.00, 29.99, 2),

-- Обувки (10)
(7, @cat_obuvki, 'OB001', 'Маратонки Nike 40', '5901234560051', '40', 'Черен', 35.00, 52.00, 89.99, 2),
(7, @cat_obuvki, 'OB002', 'Маратонки Nike 42', '5901234560052', '42', 'Бял', 35.00, 52.00, 89.99, 2),
(7, @cat_obuvki, 'OB003', 'Маратонки Nike 44', '5901234560053', '44', 'Черен', 35.00, 52.00, 89.99, 2),
(7, @cat_obuvki, 'OB004', 'Сандали дамски 37', '5901234560054', '37', 'Бежов', 12.00, 20.00, 34.99, 2),
(7, @cat_obuvki, 'OB005', 'Сандали дамски 38', '5901234560055', '38', 'Черен', 12.00, 20.00, 34.99, 2),
(7, @cat_obuvki, 'OB006', 'Сандали дамски 39', '5901234560056', '39', 'Бежов', 12.00, 20.00, 34.99, 2),
(7, @cat_obuvki, 'OB007', 'Чехли мъжки 42', '5901234560057', '42', 'Черен', 6.00, 10.00, 17.99, 3),
(7, @cat_obuvki, 'OB008', 'Чехли мъжки 44', '5901234560058', '44', 'Син', 6.00, 10.00, 17.99, 3),
(7, @cat_obuvki, 'OB009', 'Ботуши дамски 38', '5901234560059', '38', 'Черен', 28.00, 42.00, 74.99, 1),
(7, @cat_obuvki, 'OB010', 'Ботуши дамски 39', '5901234560060', '39', 'Кафяв', 28.00, 42.00, 74.99, 1),

-- Тениски (10)
(7, @cat_teniski, 'TN001', 'Тениска мъжка S бяла', '5901234560061', 'S', 'Бял', 5.00, 9.00, 17.99, 5),
(7, @cat_teniski, 'TN002', 'Тениска мъжка M бяла', '5901234560062', 'M', 'Бял', 5.00, 9.00, 17.99, 5),
(7, @cat_teniski, 'TN003', 'Тениска мъжка L черна', '5901234560063', 'L', 'Черен', 5.00, 9.00, 17.99, 5),
(7, @cat_teniski, 'TN004', 'Тениска мъжка XL черна', '5901234560064', 'XL', 'Черен', 5.00, 9.00, 17.99, 5),
(7, @cat_teniski, 'TN005', 'Тениска дамска S розова', '5901234560065', 'S', 'Розов', 5.00, 9.00, 17.99, 5),
(7, @cat_teniski, 'TN006', 'Тениска дамска M розова', '5901234560066', 'M', 'Розов', 5.00, 9.00, 17.99, 5),
(7, @cat_teniski, 'TN007', 'Поло мъжко M', '5901234560067', 'M', 'Син', 8.00, 14.00, 24.99, 3),
(7, @cat_teniski, 'TN008', 'Поло мъжко L', '5901234560068', 'L', 'Зелен', 8.00, 14.00, 24.99, 3),
(7, @cat_teniski, 'TN009', 'Потник дамски S', '5901234560069', 'S', 'Бял', 4.00, 7.00, 12.99, 5),
(7, @cat_teniski, 'TN010', 'Потник дамски M', '5901234560070', 'M', 'Черен', 4.00, 7.00, 12.99, 5),

-- Дънки (10)
(7, @cat_dynki, 'DN001', 'Дънки мъжки 30 slim', '5901234560071', '30', 'Син', 18.00, 28.00, 49.99, 2),
(7, @cat_dynki, 'DN002', 'Дънки мъжки 32 slim', '5901234560072', '32', 'Син', 18.00, 28.00, 49.99, 2),
(7, @cat_dynki, 'DN003', 'Дънки мъжки 34 regular', '5901234560073', '34', 'Тъмносин', 18.00, 28.00, 49.99, 2),
(7, @cat_dynki, 'DN004', 'Дънки дамски 26 skinny', '5901234560074', '26', 'Черен', 16.00, 26.00, 44.99, 2),
(7, @cat_dynki, 'DN005', 'Дънки дамски 28 skinny', '5901234560075', '28', 'Син', 16.00, 26.00, 44.99, 2),
(7, @cat_dynki, 'DN006', 'Дънки дамски 30 mom', '5901234560076', '30', 'Светлосин', 16.00, 26.00, 44.99, 2),
(7, @cat_dynki, 'DN007', 'Къси дънки мъжки M', '5901234560077', 'M', 'Син', 10.00, 16.00, 29.99, 3),
(7, @cat_dynki, 'DN008', 'Къси дънки мъжки L', '5901234560078', 'L', 'Син', 10.00, 16.00, 29.99, 3),
(7, @cat_dynki, 'DN009', 'Къси дънки дамски S', '5901234560079', 'S', 'Бял', 10.00, 16.00, 29.99, 3),
(7, @cat_dynki, 'DN010', 'Къси дънки дамски M', '5901234560080', 'M', 'Син', 10.00, 16.00, 29.99, 3),

-- Якета (10)
(7, @cat_yaketa, 'YK001', 'Яке пролетно мъжко M', '5901234560081', 'M', 'Черен', 25.00, 38.00, 69.99, 2),
(7, @cat_yaketa, 'YK002', 'Яке пролетно мъжко L', '5901234560082', 'L', 'Тъмносин', 25.00, 38.00, 69.99, 2),
(7, @cat_yaketa, 'YK003', 'Яке пролетно дамско S', '5901234560083', 'S', 'Розов', 22.00, 34.00, 59.99, 2),
(7, @cat_yaketa, 'YK004', 'Яке пролетно дамско M', '5901234560084', 'M', 'Бежов', 22.00, 34.00, 59.99, 2),
(7, @cat_yaketa, 'YK005', 'Зимно яке мъжко L', '5901234560085', 'L', 'Черен', 40.00, 60.00, 109.99, 1),
(7, @cat_yaketa, 'YK006', 'Зимно яке мъжко XL', '5901234560086', 'XL', 'Сив', 40.00, 60.00, 109.99, 1),
(7, @cat_yaketa, 'YK007', 'Зимно яке дамско M', '5901234560087', 'M', 'Черен', 38.00, 56.00, 99.99, 1),
(7, @cat_yaketa, 'YK008', 'Суичър мъжки M', '5901234560088', 'M', 'Сив', 14.00, 22.00, 39.99, 3),
(7, @cat_yaketa, 'YK009', 'Суичър мъжки L', '5901234560089', 'L', 'Черен', 14.00, 22.00, 39.99, 3),
(7, @cat_yaketa, 'YK010', 'Суичър дамски S', '5901234560090', 'S', 'Розов', 14.00, 22.00, 39.99, 3),

-- Аксесоари (5)
(7, @cat_aksesoari, 'AK001', 'Колан мъжки кожен', '5901234560091', 'ONE', 'Черен', 6.00, 10.00, 19.99, 3),
(7, @cat_aksesoari, 'AK002', 'Колан мъжки текстилен', '5901234560092', 'ONE', 'Син', 4.00, 7.00, 14.99, 3),
(7, @cat_aksesoari, 'AK003', 'Шал дамски', '5901234560093', 'ONE', 'Червен', 5.00, 9.00, 16.99, 3),
(7, @cat_aksesoari, 'AK004', 'Шапка зимна', '5901234560094', 'ONE', 'Черен', 4.00, 7.00, 13.99, 5),
(7, @cat_aksesoari, 'AK005', 'Ръкавици кожени', '5901234560095', 'ONE', 'Черен', 8.00, 13.00, 24.99, 2),

-- Спортни (5)
(7, @cat_sportni, 'SP001', 'Клин дамски S', '5901234560096', 'S', 'Черен', 8.00, 14.00, 24.99, 3),
(7, @cat_sportni, 'SP002', 'Клин дамски M', '5901234560097', 'M', 'Сив', 8.00, 14.00, 24.99, 3),
(7, @cat_sportni, 'SP003', 'Шорти спортни мъжки M', '5901234560098', 'M', 'Черен', 6.00, 10.00, 19.99, 3),
(7, @cat_sportni, 'SP004', 'Шорти спортни мъжки L', '5901234560099', 'L', 'Сив', 6.00, 10.00, 19.99, 3),
(7, @cat_sportni, 'SP005', 'Спортен сутиен S', '5901234560100', 'S', 'Черен', 7.00, 12.00, 22.99, 3);

-- ИНВЕНТАР за всички продукти (store_id=1)
INSERT INTO inventory (tenant_id, store_id, product_id, quantity, min_quantity)
SELECT 7, 1, p.id,
  CASE
    WHEN p.code LIKE 'CH%' THEN FLOOR(15 + RAND()*30)
    WHEN p.code LIKE 'BL%' THEN FLOOR(5 + RAND()*15)
    WHEN p.code LIKE 'OB%' THEN FLOOR(2 + RAND()*6)
    WHEN p.code LIKE 'YK%' THEN FLOOR(2 + RAND()*5)
    ELSE FLOOR(3 + RAND()*12)
  END,
  p.min_quantity
FROM products p WHERE p.tenant_id=7 AND p.id > 2;

-- Инвентар за съществуващия продукт 2
INSERT INTO inventory (tenant_id, store_id, product_id, quantity, min_quantity)
VALUES (7, 1, 2, 8, 2)
ON DUPLICATE KEY UPDATE quantity=8;

-- ZOMBIE артикули (нулев инвентар, няма да имат продажби)
UPDATE inventory SET quantity=0 WHERE product_id IN (
  SELECT id FROM products WHERE tenant_id=7 AND code IN ('YK005','YK006','YK007','AK004','AK005','BA009','BA010')
);

-- LOW STOCK артикули
UPDATE inventory SET quantity=1 WHERE product_id IN (
  SELECT id FROM products WHERE tenant_id=7 AND code IN ('BL001','BL007','DN001','OB001','PJ003')
);

-- ========================================
-- ПРОДАЖБИ: ~300 за последните 60 дни
-- ========================================

-- Ще генерираме чрез процедура
DELIMITER //
CREATE PROCEDURE seed_sales()
BEGIN
  DECLARE i INT DEFAULT 0;
  DECLARE sale_date TIMESTAMP;
  DECLARE sale_id_var INT;
  DECLARE prod_id INT;
  DECLARE prod_price DECIMAL(12,4);
  DECLARE prod_cost DECIMAL(12,4);
  DECLARE qty INT;
  DECLARE items_count INT;
  DECLARE j INT;
  DECLARE sale_total DECIMAL(12,2);
  DECLARE item_total DECIMAL(12,2);
  DECLARE payment ENUM('cash','card');
  DECLARE user_id INT;

  SET user_id = (SELECT id FROM users WHERE email='tiholenev@gmail.com' LIMIT 1);

  WHILE i < 300 DO
    -- Случайна дата в последните 60 дни, между 9:00 и 20:00
    SET sale_date = DATE_SUB(NOW(), INTERVAL FLOOR(RAND()*60) DAY)
                    + INTERVAL FLOOR(9 + RAND()*11) HOUR
                    + INTERVAL FLOOR(RAND()*60) MINUTE;

    SET payment = IF(RAND() > 0.4, 'cash', 'card');
    SET items_count = 1 + FLOOR(RAND()*3); -- 1-3 артикула на продажба
    SET sale_total = 0;

    INSERT INTO sales (tenant_id, store_id, user_id, type, payment_method, subtotal, total, paid_amount, status, created_at)
    VALUES (7, 1, user_id, IF(RAND()>0.85,'wholesale','retail'), payment, 0, 0, 0, 'completed', sale_date);
    SET sale_id_var = LAST_INSERT_ID();

    SET j = 0;
    WHILE j < items_count DO
      -- Случаен продукт (с наличност > 0)
      SELECT p.id, p.retail_price, p.cost_price INTO prod_id, prod_price, prod_cost
      FROM products p
      JOIN inventory inv ON inv.product_id = p.id AND inv.store_id=1
      WHERE p.tenant_id=7 AND inv.quantity > 0 AND p.is_active=1
      ORDER BY RAND() LIMIT 1;

      IF prod_id IS NOT NULL THEN
        SET qty = 1 + FLOOR(RAND()*2);
        SET item_total = prod_price * qty;
        SET sale_total = sale_total + item_total;

        INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, cost_price, discount_pct, total)
        VALUES (sale_id_var, prod_id, qty, prod_price, prod_cost, 0, item_total);

        -- Намали инвентара
        UPDATE inventory SET quantity = GREATEST(quantity - qty, 0)
        WHERE product_id = prod_id AND store_id=1 AND tenant_id=7;
      END IF;

      SET j = j + 1;
    END WHILE;

    -- Ъпдейтни sale totals
    UPDATE sales SET subtotal = sale_total, total = sale_total, paid_amount = sale_total
    WHERE id = sale_id_var;

    SET i = i + 1;
  END WHILE;
END //
DELIMITER ;

CALL seed_sales();
DROP PROCEDURE seed_sales;
