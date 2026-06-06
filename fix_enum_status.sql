ALTER TABLE po 
MODIFY COLUMN status ENUM('draft','pending_review','approved','rejected','completed') 
DEFAULT 'draft';

DESC po;
