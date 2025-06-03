-- Offer Management Schema Updates
-- Add missing columns to offers table

ALTER TABLE offers 
ADD COLUMN IF NOT EXISTS approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
ADD COLUMN IF NOT EXISTS template_id INT NULL,
ADD COLUMN IF NOT EXISTS rejection_reason TEXT NULL,
ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS candidate_response_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS response_notes TEXT NULL,
ADD COLUMN IF NOT EXISTS response_token VARCHAR(64) NULL,
ADD COLUMN IF NOT EXISTS custom_terms TEXT NULL;

-- Add foreign key constraint for template_id
ALTER TABLE offers 
ADD CONSTRAINT fk_offers_template_id 
FOREIGN KEY (template_id) REFERENCES offer_templates(id) ON DELETE SET NULL;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_offers_approval_status ON offers(approval_status);
CREATE INDEX IF NOT EXISTS idx_offers_status ON offers(status);
CREATE INDEX IF NOT EXISTS idx_offers_candidate_id ON offers(candidate_id);
CREATE INDEX IF NOT EXISTS idx_offers_created_at ON offers(created_at);
CREATE INDEX IF NOT EXISTS idx_offers_response_token ON offers(response_token);

-- Create offer_responses table for tracking candidate responses
CREATE TABLE IF NOT EXISTS offer_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    offer_id INT NOT NULL,
    response ENUM('accept', 'reject', 'negotiate') NOT NULL,
    response_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    comments TEXT,
    negotiation_details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE
);

-- Create offer_notifications table for tracking email communications
CREATE TABLE IF NOT EXISTS offer_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    offer_id INT NOT NULL,
    notification_type ENUM('offer_sent', 'reminder', 'accepted', 'rejected', 'expired') NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delivery_status ENUM('pending', 'sent', 'delivered', 'failed') DEFAULT 'pending',
    opened_at TIMESTAMP NULL,
    clicked_at TIMESTAMP NULL,
    FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE
);

-- Update existing offers to have pending approval status if not set
UPDATE offers SET approval_status = 'pending' WHERE approval_status IS NULL;

-- Generate secure tokens for existing offers that don't have them
UPDATE offers 
SET response_token = SHA2(CONCAT(id, candidate_id, created_at, RAND()), 256)
WHERE response_token IS NULL OR response_token = '';

-- Create trigger to automatically generate response tokens for new offers
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS generate_offer_token 
BEFORE INSERT ON offers
FOR EACH ROW
BEGIN
    IF NEW.response_token IS NULL OR NEW.response_token = '' THEN
        SET NEW.response_token = SHA2(CONCAT(NEW.candidate_id, NOW(), RAND()), 256);
    END IF;
END$$
DELIMITER ; 