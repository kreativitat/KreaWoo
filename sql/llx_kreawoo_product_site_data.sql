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

CREATE TABLE llx_kreawoo_product_site_data (
  rowid integer NOT NULL auto_increment PRIMARY KEY,
  tms timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_product integer NOT NULL,
  fk_site integer NOT NULL,
  entity integer NOT NULL DEFAULT 1,
  description text DEFAULT NULL,
  short_description text DEFAULT NULL,
  wc_status varchar(255) DEFAULT NULL,
  tax_class varchar(255) DEFAULT NULL,
  wc_regular_price double(24,8) DEFAULT NULL,
  wc_sale_price double(24,8) DEFAULT NULL,
  wc_date_on_sale_from date DEFAULT NULL,
  wc_date_on_sale_to date DEFAULT NULL,
  wc_manage_stock integer(1) DEFAULT 0,
  wc_stock_status varchar(32) DEFAULT 'instock',
  wc_dont_update_stock integer(1) DEFAULT 0,
  stockable_product integer(1) DEFAULT 1,
  import_key varchar(14) DEFAULT NULL
) ENGINE=InnoDB COMMENT='KreaWoo dedicated product site metadata';
