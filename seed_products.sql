-- Valluvam local dev seed data
-- Creates product_details table (if missing) and seeds sample products
-- using images that already exist in assets/uploads, so the site renders
-- correctly on the local XAMPP environment.

CREATE TABLE IF NOT EXISTS product_details (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_name VARCHAR(255) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  dis_price DECIMAL(10,2) DEFAULT NULL,
  category VARCHAR(100) NOT NULL,
  quantity VARCHAR(50) DEFAULT NULL,
  rating DECIMAL(2,1) DEFAULT NULL,
  description TEXT,
  benefits TEXT,
  image VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_category (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(100) NOT NULL
);

INSERT INTO product_category (category_name) VALUES
('Dryfruits'), ('nuts'), ('spices'), ('oils'), ('millets'), ('combo');

-- Only seed if the table is empty, so re-running this script is safe
INSERT INTO product_details (product_name, price, dis_price, category, quantity, rating, description, benefits, image)
SELECT * FROM (SELECT
  'Premium Almonds' AS product_name, 650.00 AS price, 549.00 AS dis_price, 'nuts' AS category, '500g' AS quantity, 4.5 AS rating,
  'Handpicked premium quality almonds, naturally sourced and hygienically packed.' AS description,
  'Rich in protein, fiber, vitamin E and healthy fats.' AS benefits,
  '1755253446_Copy of Valluvam nuts & spices  (1).jpg' AS image
UNION ALL SELECT 'Roasted Cashew Nuts', 720.00, 599.00, 'nuts', '500g', 4.6,
  'Farm fresh cashew nuts, roasted and lightly salted.',
  'Good source of protein and heart-healthy fats.',
  '1754288388_products-22.jpg'
UNION ALL SELECT 'Pistachios', 950.00, 849.00, 'nuts', '250g', 4.4,
  'Premium quality pistachios, hand sorted for size and freshness.',
  'High in antioxidants and fiber.',
  '1754288544_products-20.jpg'
UNION ALL SELECT 'Walnuts', 880.00, NULL, 'nuts', '500g', 4.3,
  'Naturally grown walnuts, rich and crunchy.',
  'Excellent source of omega-3 fatty acids.',
  '1754288681_products-28.jpg'

UNION ALL SELECT 'Dried Black Raisins', 220.00, 189.00, 'Dryfruits', '500g', 4.2,
  'Sweet and naturally sun-dried black raisins.',
  'Good source of iron and natural energy.',
  '1754932133_products-22.jpg'
UNION ALL SELECT 'Dried Apricots', 380.00, 329.00, 'Dryfruits', '250g', 4.3,
  'Soft and tangy dried apricots, naturally sun dried.',
  'Rich in vitamin A and dietary fiber.',
  '1754932514_products-20.jpg'
UNION ALL SELECT 'Dates (Medjool)', 450.00, NULL, 'Dryfruits', '500g', 4.6,
  'Premium soft medjool dates, naturally sweet.',
  'Natural energy booster, rich in potassium.',
  '1754932680_products-28.jpg'
UNION ALL SELECT 'Dried Figs (Anjeer)', 520.00, 449.00, 'Dryfruits', '250g', 4.4,
  'Naturally dried figs, soft and nutritious.',
  'Good source of calcium and fiber.',
  '1754932736_products-47.jpg'

UNION ALL SELECT 'Cold Pressed Groundnut Oil', 380.00, 349.00, 'oils', '1L', 4.7,
  'Wood-pressed groundnut oil, 100% natural and chemical free.',
  'Retains natural nutrients, good for heart health.',
  '1754382229_ground-1L.jpg'
UNION ALL SELECT 'Cold Pressed Sesame Oil', 420.00, 379.00, 'oils', '1L', 4.6,
  'Traditionally wood-pressed sesame (gingelly) oil.',
  'Rich in antioxidants, supports skin and hair health.',
  '1754036129_sesame-1L.jpg'
UNION ALL SELECT 'Cold Pressed Coconut Oil', 350.00, NULL, 'oils', '1L', 4.8,
  'Pure wood-pressed coconut oil, naturally extracted.',
  'Great for cooking, skin and hair care.',
  '1754054022_coconut-1L.jpg'
UNION ALL SELECT 'Pure Cow Ghee', 620.00, 549.00, 'oils', '500ml', 4.9,
  'Traditionally made pure cow ghee.',
  'Supports digestion and immunity.',
  'ghee(500).jpg'

UNION ALL SELECT 'Turmeric Powder', 120.00, NULL, 'spices', '200g', 4.5,
  'Naturally dried and stone-ground turmeric powder.',
  'Anti-inflammatory and rich in curcumin.',
  'spices-1.jpeg'
UNION ALL SELECT 'Red Chilli Powder', 140.00, 119.00, 'spices', '200g', 4.4,
  'Farm fresh sun-dried red chillies, stone ground.',
  'Adds natural heat and flavour, rich in vitamin C.',
  'spices-2.jpeg'
UNION ALL SELECT 'Coriander Powder', 100.00, NULL, 'spices', '200g', 4.3,
  'Freshly ground coriander seeds.',
  'Aids digestion, mild and aromatic.',
  'spices-3.jpeg'
UNION ALL SELECT 'Black Pepper', 260.00, 229.00, 'spices', '100g', 4.6,
  'Premium quality whole black pepper.',
  'Rich in antioxidants, boosts metabolism.',
  'spices-5.jpeg'

UNION ALL SELECT 'Foxtail Millet', 110.00, 95.00, 'millets', '1kg', 4.4,
  'Naturally grown foxtail millet, unpolished.',
  'High in fiber, good for diabetics.',
  'foxtail.jpg'
UNION ALL SELECT 'Little Millet', 105.00, NULL, 'millets', '1kg', 4.3,
  'Traditionally grown little millet.',
  'Low glycemic index, aids weight management.',
  'little.jpg'
UNION ALL SELECT 'Barnyard Millet', 115.00, 99.00, 'millets', '1kg', 4.2,
  'Naturally grown barnyard millet.',
  'Rich in fiber and iron.',
  'barnyard.jpg'
UNION ALL SELECT 'Kodo Millet', 108.00, NULL, 'millets', '1kg', 4.3,
  'Traditionally grown kodo millet.',
  'Good source of B vitamins and minerals.',
  'kodo.jpg'

UNION ALL SELECT 'Sesame & Groundnut Oil Combo', 750.00, 699.00, 'combo', '1L + 1L', 4.7,
  'Combo pack of cold pressed sesame oil and groundnut oil.',
  'Two everyday cooking oils, naturally extracted.',
  '1755325941_sesame&groundnut.jpg'
UNION ALL SELECT 'Coconut & Groundnut Oil Combo', 700.00, 649.00, 'combo', '1L + 1L', 4.6,
  'Combo pack of cold pressed coconut oil and groundnut oil.',
  'Two everyday cooking oils, naturally extracted.',
  '1755324420_coconut1&ground1.jpg'
) AS seed_data
WHERE (SELECT COUNT(*) FROM product_details) = 0;
