-- Add payment_id field to settlements table
ALTER TABLE settlements
ADD COLUMN payment_id VARCHAR(100) DEFAULT NULL AFTER payment_reference,
ADD COLUMN payment_status VARCHAR(50) DEFAULT NULL AFTER payment_id;
