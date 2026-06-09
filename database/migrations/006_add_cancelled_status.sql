-- Description: Add 'cancelled' status to leave_requests enum for leave cancellation feature

-- UP

ALTER TABLE leave_requests MODIFY COLUMN status ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending';

-- DOWN

ALTER TABLE leave_requests MODIFY COLUMN status ENUM('pending','approved','rejected') DEFAULT 'pending';
