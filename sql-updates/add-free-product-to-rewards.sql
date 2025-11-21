-- Hozzáadja a free_product és free_product_value mezőket a ppv_rewards táblához
-- Használat: Futtasd le ezt az SQL-t az adatbázisban

ALTER TABLE wp_ppv_rewards
ADD COLUMN IF NOT EXISTS free_product VARCHAR(255) DEFAULT '' AFTER action_value,
ADD COLUMN IF NOT EXISTS free_product_value DECIMAL(10,2) DEFAULT 0.00 AFTER free_product;

-- Megjegyzés:
-- free_product = termék neve (pl. "Kaffee + Kuchen")
-- free_product_value = termék értéke (pl. 5.50)
