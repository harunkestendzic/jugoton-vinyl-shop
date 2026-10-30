<?php
$conn = mysqli_connect("localhost", "root", "", 'vinyl_shop');

/*
DELIMITER $$

CREATE TRIGGER tr_manage_default_address
BEFORE UPDATE ON Addresses
FOR EACH ROW
BEGIN
    IF NEW.is_default = 1 AND OLD.is_default = 0 THEN
        UPDATE Addresses 
        SET is_default = 0 
        WHERE user_id = NEW.user_id 
          AND address_id != NEW.address_id;
    END IF;
END$$

DELIMITER ;


DELIMITER $$

CREATE TRIGGER tr_verify_vinyl_stock
BEFORE INSERT ON Order_Items
FOR EACH ROW
BEGIN
    DECLARE current_stock INT;

    SELECT stock INTO current_stock 
    FROM Vinyls 
    WHERE vinyl_id = NEW.vinyl_id;

    IF current_stock < NEW.quantity THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Transaction aborted: Insufficient vinyl inventory available.';
    END IF;
END$$

DELIMITER ;


CREATE OR REPLACE VIEW v_vinyl_sales_analytics AS
SELECT 
    v.vinyl_id,
    v.title AS vinyl_title,
    a.artist_name,
    ft.type_name AS format_type,
    v.price AS current_unit_price,
    v.stock AS remaining_inventory,
    COALESCE(SUM(oi.quantity), 0) AS total_units_sold,
    COALESCE(SUM(oi.quantity * oi.unit_price), 0.00) AS total_revenue_generated,
    COUNT(DISTINCT r.review_id) AS total_reviews_received,
    COALESCE(AVG(r.rating), 0.0) AS average_user_rating
FROM Vinyls v
JOIN Artists a ON v.artist_id = a.artist_id
JOIN Format_Type ft ON v.type_id = ft.type_id
LEFT JOIN Order_Items oi ON v.vinyl_id = oi.vinyl_id
LEFT JOIN Reviews r ON v.vinyl_id = r.vinyl_id
GROUP BY 
    v.vinyl_id, 
    v.title, 
    a.artist_name, 
    ft.type_name, 
    v.price, 
    v.stock;


    DELIMITER $$

CREATE PROCEDURE sp_place_order(
    IN p_user_id INT,
    IN p_address_id INT,
    IN p_vinyl_id INT,
    IN p_quantity INT,
    OUT p_success INT,
    OUT p_message VARCHAR(255)
)
sp_label: BEGIN
    DECLARE v_available_stock INT;
    DECLARE v_item_price DECIMAL(10,2);
    DECLARE v_new_order_id INT;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_success = 0;
        SET p_message = 'Transaction aborted: Internal database processing error occurred.';
    END;

    SET p_success = 1;
    SET p_message = 'Order processed and recorded successfully.';

    START TRANSACTION;

    SELECT stock, price INTO v_available_stock, v_item_price
    FROM Vinyls
    WHERE vinyl_id = p_vinyl_id;

    IF v_available_stock IS NULL THEN
        SET p_success = 0;
        SET p_message = 'Order failure: The requested vinyl target item ID does not exist.';
        ROLLBACK;
        LEAVE sp_label;
    END IF;

    IF v_available_stock < p_quantity THEN
        SET p_success = 0;
        SET p_message = CONCAT('Order failure: Insufficient inventory. Requested: ', p_quantity, ' Available: ', v_available_stock);
        ROLLBACK;
        LEAVE sp_label;
    END IF;

    INSERT INTO Orders (user_id, address_id, total_price, order_date)
    VALUES (p_user_id, p_address_id, (v_item_price * p_quantity), NOW());

    SET v_new_order_id = LAST_INSERT_ID();

    INSERT INTO Order_Items (order_id, vinyl_id, quantity, unit_price)
    VALUES (v_new_order_id, p_vinyl_id, p_quantity, v_item_price);

    UPDATE Vinyls
    SET stock = stock - p_quantity
    WHERE vinyl_id = p_vinyl_id;

    COMMIT;
END$$

DELIMITER ;


DELIMITER $$

CREATE FUNCTION fn_determine_user_tier(p_user_id INT)
RETURNS VARCHAR(50)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_lifetime_spending DECIMAL(10,2);
    DECLARE v_tier VARCHAR(50);

    SELECT COALESCE(SUM(total_price), 0.00) INTO v_lifetime_spending
    FROM Orders
    WHERE user_id = p_user_id;

    IF v_lifetime_spending >= 500.00 THEN
        SET v_tier = 'Diamond Collector';
    ELSEIF v_lifetime_spending >= 250.00 THEN
        SET v_tier = 'Platinum Enthusiast';
    ELSEIF v_lifetime_spending >= 100.00 THEN
        SET v_tier = 'Gold Listener';
    ELSE
        SET v_tier = 'Standard Member';
    END IF;

    RETURN v_tier;
END$$

DELIMITER ;


CREATE INDEX idx_artists_name ON Artists(artist_name);

CREATE INDEX idx_vinyls_title ON Vinyls(title);

CREATE INDEX idx_orders_date ON Orders(order_date);

CREATE INDEX idx_reviews_timestamp ON Reviews(created_at);


*/