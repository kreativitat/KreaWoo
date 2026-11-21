-- ===================================================================
-- Copyright (C) 2024-2026 Kreativitat <mail@kreativitat.com>
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 2 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program; if not, write to the Free Software
-- Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
--
-- ===================================================================

ALTER TABLE `llx_kreawoo_product_site_data` ADD UNIQUE KEY `uk_kreawoo_product_site_data` (`fk_product`, `fk_site`, `entity`);
ALTER TABLE `llx_kreawoo_product_site_data` ADD INDEX `idx_kreawoo_product_site_data_fk_product` (`fk_product`);
ALTER TABLE `llx_kreawoo_product_site_data` ADD INDEX `idx_kreawoo_product_site_data_fk_site` (`fk_site`);
ALTER TABLE `llx_kreawoo_product_site_data` ADD INDEX `idx_kreawoo_product_site_data_entity` (`entity`);
