-- Add pending qty tracking ke po_items
ALTER TABLE po_items ADD COLUMN qty_available INT DEFAULT 0 AFTER qty;
ALTER TABLE po_items ADD COLUMN qty_pending INT DEFAULT 0 AFTER qty_available;
