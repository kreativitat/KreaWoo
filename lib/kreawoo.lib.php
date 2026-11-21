<?php
/* Copyright (C) 2026 Kreativität Works                     <support@kreativitat.com>
 * Copyright (C) 2026  Kreativitat  <mail@kreativitat.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 * or see http://www.gnu.org/
 */


require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/images.lib.php';
dol_include_once('/kreawoo/class/data/kreawooProduct.class.php');
dol_include_once('/kreawoo/class/business/kreawooSynchro.class.php');
dol_include_once('/kreawoo/class/data/woocommerce/kreawooRemoteAccessWoocommerce.class.php');

/**
 * Return current module version from VERSION file.
 *
 * @return string
 */
function kreawooGetModuleVersion()
{
	static $version = null;
	if ($version !== null) {
		return $version;
	}

	$version = '';
	$paths = array(
		DOL_DOCUMENT_ROOT . '/custom/kreawoo/VERSION',
		dirname(__DIR__) . '/VERSION',
	);

	foreach ($paths as $path) {
		if (is_readable($path)) {
			$content = @file_get_contents($path);
			if ($content !== false) {
				$version = trim($content);
				if ($version !== '') {
					break;
				}
			}
		}
	}

	if ($version === '') {
		$version = '0.0.0';
	}

	return $version;
}

/**
 * Return option keys used for product site metadata.
 *
 * @param int $siteId
 * @param int $entity
 * @return array
 */
function kreawooGetProductSiteDataOptionKeys($siteId, $entity)
{
	return array(
		'description' => 'kreawoo_description_' . ((int) $entity),
		'short_description' => 'kreawoo_short_description_' . ((int) $entity),
		'wc_status' => 'kreawoo_wc_status_' . ((int) $siteId) . '_' . ((int) $entity),
		'tax_class' => 'kreawoo_tax_class_' . ((int) $siteId) . '_' . ((int) $entity),
		'wc_regular_price' => 'kreawoo_wc_regular_price_' . ((int) $siteId) . '_' . ((int) $entity),
		'wc_sale_price' => 'kreawoo_wc_sale_price_' . ((int) $siteId) . '_' . ((int) $entity),
		'wc_date_on_sale_from' => 'kreawoo_wc_date_on_sale_from_' . ((int) $siteId) . '_' . ((int) $entity),
		'wc_date_on_sale_to' => 'kreawoo_wc_date_on_sale_to_' . ((int) $siteId) . '_' . ((int) $entity),
		'wc_manage_stock' => 'kreawoo_wc_manage_stock_' . ((int) $siteId) . '_' . ((int) $entity),
		'wc_stock_status' => 'kreawoo_wc_stock_status_' . ((int) $siteId) . '_' . ((int) $entity),
		'wc_dont_update_stock' => 'kreawoo_wc_dont_update_stock_' . ((int) $siteId) . '_' . ((int) $entity),
		'stockable_product' => 'kreawoo_stockable_product',
	);
}

/**
 * Return default values for product site metadata.
 *
 * @return array
 */
function kreawooGetProductSiteDataDefaults()
{
	return array(
		'description' => '',
		'short_description' => '',
		'wc_status' => '',
		'tax_class' => '',
		'wc_regular_price' => '',
		'wc_sale_price' => '',
		'wc_date_on_sale_from' => '',
		'wc_date_on_sale_to' => '',
		'wc_manage_stock' => 0,
		'wc_stock_status' => 'instock',
		'wc_dont_update_stock' => 0,
		'stockable_product' => 1,
	);
}

/**
 * Normalize one product site metadata value.
 *
 * @param string $field
 * @param mixed  $value
 * @return mixed
 */
function kreawooNormalizeProductSiteDataValue($field, $value)
{
	if ($value === '' || $value === null) {
		return null;
	}

	if ($field === 'wc_regular_price' || $field === 'wc_sale_price') {
		return price2num($value);
	}

	if ($field === 'wc_manage_stock' || $field === 'wc_dont_update_stock' || $field === 'stockable_product') {
		return ((int) !empty($value));
	}

	if ($field === 'wc_date_on_sale_from' || $field === 'wc_date_on_sale_to') {
		if (is_numeric($value)) {
			return dol_print_date($value, '%Y-%m-%d');
		}
		$value = (string) $value;
		return preg_match('/^\d{4}-\d{2}-\d{2}/', $value) ? substr($value, 0, 10) : null;
	}

	if ($field === 'wc_stock_status') {
		$value = (string) $value;
		return in_array($value, array('instock', 'outofstock', 'onbackorder'), true) ? $value : null;
	}

	return (string) $value;
}

/**
 * Build normalized product site metadata from product array_options.
 *
 * @param array      $arrayOptions
 * @param int        $siteId
 * @param int        $entity
 * @param int|string $stockableProduct
 * @return array
 */
function kreawooExtractProductSiteDataFromArrayOptions($arrayOptions, $siteId, $entity, $stockableProduct = null)
{
	$optionKeys = kreawooGetProductSiteDataOptionKeys($siteId, $entity);
	$defaults = kreawooGetProductSiteDataDefaults();
	$data = array();

	foreach ($optionKeys as $field => $fieldKey) {
		$optionKey = 'options_' . $fieldKey;
		$value = array_key_exists($optionKey, $arrayOptions) ? $arrayOptions[$optionKey] : null;
		if ($value === null && $field === 'stockable_product' && $stockableProduct !== null) {
			$value = $stockableProduct;
		}

		$data[$field] = kreawooNormalizeProductSiteDataValue($field, $value);
		if ($data[$field] === null && isset($defaults[$field])) {
			$data[$field] = $defaults[$field];
		}
	}

	return $data;
}

/**
 * Escape SQL value.
 *
 * @param DoliDB $db
 * @param mixed  $value
 * @param string $type
 * @return string
 */
function kreawooProductSiteDataSqlValue($db, $value, $type = 'string')
{
	if ($value === null || $value === '') {
		return 'NULL';
	}

	if ($type === 'int') {
		return (string) ((int) $value);
	}
	if ($type === 'float') {
		return (string) price2num($value);
	}

	return "'" . $db->escape($value) . "'";
}

/**
 * Save one product site metadata row.
 *
 * @param DoliDB $db
 * @param int    $fkProduct
 * @param int    $fkSite
 * @param int    $entity
 * @param array  $data
 * @return int
 */
function kreawooSaveProductSiteData($db, $fkProduct, $fkSite, $entity, $data)
{
	$fkProduct = (int) $fkProduct;
	$fkSite = (int) $fkSite;
	$entity = (int) $entity;
	if ($fkProduct <= 0 || $fkSite <= 0 || $entity <= 0) {
		return 0;
	}
	$defaults = kreawooGetProductSiteDataDefaults();
	$data = array_merge($defaults, is_array($data) ? $data : array());
	foreach ($defaults as $field => $defaultValue) {
		$data[$field] = kreawooNormalizeProductSiteDataValue($field, $data[$field]);
		if ($data[$field] === null) {
			$data[$field] = $defaultValue;
		}
	}

	$sql = "INSERT INTO " . MAIN_DB_PREFIX . "kreawoo_product_site_data(";
	$sql .= "fk_product, fk_site, entity, description, short_description, wc_status, tax_class,";
	$sql .= " wc_regular_price, wc_sale_price, wc_date_on_sale_from, wc_date_on_sale_to,";
	$sql .= " wc_manage_stock, wc_stock_status, wc_dont_update_stock, stockable_product";
	$sql .= ") VALUES (";
	$sql .= $fkProduct . ", " . $fkSite . ", " . $entity;
	$sql .= ", " . kreawooProductSiteDataSqlValue($db, $data['description']);
	$sql .= ", " . kreawooProductSiteDataSqlValue($db, $data['short_description']);
	$sql .= ", " . kreawooProductSiteDataSqlValue($db, $data['wc_status']);
	$sql .= ", " . kreawooProductSiteDataSqlValue($db, $data['tax_class']);
	$sql .= ", " . kreawooProductSiteDataSqlValue($db, $data['wc_regular_price'], 'float');
	$sql .= ", " . kreawooProductSiteDataSqlValue($db, $data['wc_sale_price'], 'float');
	$sql .= ", " . kreawooProductSiteDataSqlValue($db, $data['wc_date_on_sale_from']);
	$sql .= ", " . kreawooProductSiteDataSqlValue($db, $data['wc_date_on_sale_to']);
	$sql .= ", " . kreawooProductSiteDataSqlValue($db, $data['wc_manage_stock'], 'int');
	$sql .= ", " . kreawooProductSiteDataSqlValue($db, $data['wc_stock_status']);
	$sql .= ", " . kreawooProductSiteDataSqlValue($db, $data['wc_dont_update_stock'], 'int');
	$sql .= ", " . kreawooProductSiteDataSqlValue($db, $data['stockable_product'], 'int');
	$sql .= ")";
	$sql .= " ON DUPLICATE KEY UPDATE";
	$sql .= " tms = CURRENT_TIMESTAMP,";
	$sql .= " description = VALUES(description),";
	$sql .= " short_description = VALUES(short_description),";
	$sql .= " wc_status = VALUES(wc_status),";
	$sql .= " tax_class = VALUES(tax_class),";
	$sql .= " wc_regular_price = VALUES(wc_regular_price),";
	$sql .= " wc_sale_price = VALUES(wc_sale_price),";
	$sql .= " wc_date_on_sale_from = VALUES(wc_date_on_sale_from),";
	$sql .= " wc_date_on_sale_to = VALUES(wc_date_on_sale_to),";
	$sql .= " wc_manage_stock = VALUES(wc_manage_stock),";
	$sql .= " wc_stock_status = VALUES(wc_stock_status),";
	$sql .= " wc_dont_update_stock = VALUES(wc_dont_update_stock),";
	$sql .= " stockable_product = VALUES(stockable_product)";

	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__METHOD__ . " Error sql=" . $sql . " - " . $db->lasterror(), LOG_ERR);
		return -1;
	}

	return 1;
}

/**
 * Save product site metadata from product array_options.
 *
 * @param DoliDB     $db
 * @param int        $fkProduct
 * @param int        $fkSite
 * @param int        $entity
 * @param array      $arrayOptions
 * @param int|string $stockableProduct
 * @return int
 */
function kreawooSaveProductSiteDataFromArrayOptions($db, $fkProduct, $fkSite, $entity, $arrayOptions, $stockableProduct = null)
{
	$data = kreawooExtractProductSiteDataFromArrayOptions($arrayOptions, $fkSite, $entity, $stockableProduct);
	return kreawooSaveProductSiteData($db, $fkProduct, $fkSite, $entity, $data);
}

/**
 * Save product site metadata from an extrafields array.
 *
 * @param DoliDB $db
 * @param int    $fkProduct
 * @param int    $fkSite
 * @param int    $entity
 * @param array  $extraFields
 * @return int
 */
function kreawooSaveProductSiteDataFromExtraFields($db, $fkProduct, $fkSite, $entity, $extraFields)
{
	$arrayOptions = array();
	if (is_array($extraFields)) {
		foreach ($extraFields as $key => $value) {
			$arrayOptions['options_' . $key] = $value;
		}
	}
	return kreawooSaveProductSiteDataFromArrayOptions($db, $fkProduct, $fkSite, $entity, $arrayOptions);
}

/**
 * Load one product site metadata row.
 *
 * @param DoliDB $db
 * @param int    $fkProduct
 * @param int    $fkSite
 * @param int    $entity
 * @return array
 */
function kreawooGetProductSiteData($db, $fkProduct, $fkSite, $entity)
{
	$fkProduct = (int) $fkProduct;
	$fkSite = (int) $fkSite;
	$entity = (int) $entity;
	if ($fkProduct <= 0 || $fkSite <= 0 || $entity <= 0) {
		return array();
	}

	$sql = "SELECT description, short_description, wc_status, tax_class,";
	$sql .= " wc_regular_price, wc_sale_price, wc_date_on_sale_from, wc_date_on_sale_to,";
	$sql .= " wc_manage_stock, wc_stock_status, wc_dont_update_stock, stockable_product";
	$sql .= " FROM " . MAIN_DB_PREFIX . "kreawoo_product_site_data";
	$sql .= " WHERE fk_product = " . $fkProduct;
	$sql .= " AND fk_site = " . $fkSite;
	$sql .= " AND entity = " . $entity;
	$sql .= " LIMIT 1";

	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__METHOD__ . " Error sql=" . $sql . " - " . $db->lasterror(), LOG_ERR);
		return array();
	}

	$obj = $db->fetch_object($resql);
	$db->free($resql);
	if (empty($obj)) {
		return array();
	}

	return array(
		'description' => $obj->description,
		'short_description' => $obj->short_description,
		'wc_status' => $obj->wc_status,
		'tax_class' => $obj->tax_class,
		'wc_regular_price' => $obj->wc_regular_price,
		'wc_sale_price' => $obj->wc_sale_price,
		'wc_date_on_sale_from' => $obj->wc_date_on_sale_from,
		'wc_date_on_sale_to' => $obj->wc_date_on_sale_to,
		'wc_manage_stock' => $obj->wc_manage_stock,
		'wc_stock_status' => $obj->wc_stock_status,
		'wc_dont_update_stock' => $obj->wc_dont_update_stock,
		'stockable_product' => $obj->stockable_product,
	);
}

/**
 * Inject product site metadata into product array_options keys.
 *
 * @param Product $product
 * @param DoliDB  $db
 * @param int     $siteId
 * @param int     $entity
 * @return array
 */
function kreawooInjectProductSiteDataIntoProduct(&$product, $db, $siteId, $entity)
{
	$optionKeys = kreawooGetProductSiteDataOptionKeys($siteId, $entity);
	$defaults = kreawooGetProductSiteDataDefaults();
	$data = kreawooGetProductSiteData($db, $product->id, $siteId, $entity);

	if (!is_array($product->array_options)) {
		$product->array_options = array();
	}

	foreach ($optionKeys as $field => $fieldKey) {
		$optionKey = 'options_' . $fieldKey;
		if (array_key_exists($field, $data) && $data[$field] !== null) {
			$product->array_options[$optionKey] = $data[$field];
		} elseif (!array_key_exists($optionKey, $product->array_options) && array_key_exists($field, $defaults)) {
			$product->array_options[$optionKey] = $defaults[$field];
		}
	}

	if (property_exists($product, 'stockable_product')) {
		if (array_key_exists('stockable_product', $data) && $data['stockable_product'] !== null) {
			$product->stockable_product = (int) $data['stockable_product'];
		} elseif (!isset($product->stockable_product)) {
			$product->stockable_product = (int) $defaults['stockable_product'];
		}
	}

	return $data;
}

/**
 * Prepare array with list of tabs for configuration of sites
 *
 * @param	kreawooSite	$object		Site handler
 * @return  array						Array of tabs to show
 */
function kreawooConfigSitePrepareHead($object)
{
	global $langs, $conf, $user;
	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/kreawoo/admin/setup.php", 1) . ($object->id > 0 ? '?id=' . $object->id : '');
	$head[$h][1] = $langs->trans("Parameters");
	$head[$h][2] = 'settings';
	$h++;

	if ($object->id > 0) {
		if (!empty($conf->societe->enabled)) {
			$langs->load('companies');
			$head[$h][0] = dol_buildpath("/kreawoo/admin/thirdparty.php", 1) . '?id=' . $object->id;
			$head[$h][1] = $langs->trans("ThirdParty");
			$head[$h][2] = 'thirdparty';
			$h++;
		}

		if (!empty($conf->product->enabled)) {
			$langs->load('products');
			$head[$h][0] = dol_buildpath("/kreawoo/admin/product.php", 1) . '?id=' . $object->id;
			$head[$h][1] = $langs->trans("Product");
			$head[$h][2] = 'product';
			$h++;

			$head[$h][0] = dol_buildpath("/kreawoo/admin/category.php", 1) . '?id=' . $object->id;
			$head[$h][1] = $langs->trans("kreawooCategoryCorrespondenceTab");
			$head[$h][2] = 'category';
			$h++;
		}

		if (!empty($conf->stock->enabled)) {
			$langs->load('products');
			$head[$h][0] = dol_buildpath("/kreawoo/admin/stock.php", 1) . '?id=' . $object->id;
			$head[$h][1] = $langs->trans("Stock");
			$head[$h][2] = 'stock';
			$h++;
		}

		if (!empty($conf->commande->enabled) || !empty($conf->facture->enabled)) {
			$langs->loadLangs(array('orders', 'bills'));
			$labels = array();
			if (!empty($conf->commande->enabled)) $labels[] = $langs->trans("Order");
			if (!empty($conf->facture->enabled)) $labels[] = $langs->trans("Invoice");
			$head[$h][0] = dol_buildpath("/kreawoo/admin/order.php", 1) . '?id=' . $object->id;
			$head[$h][1] = implode(' / ', $labels);
			$head[$h][2] = 'order_invoice';
			$h++;
		}
	}

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	// $this->tabs = array('entity:+tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__');   to add new tab
	// $this->tabs = array('entity:-tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__');   to remove a tab
	complete_head_from_modules($conf, $langs, null, $head, $h, 'kreawoo_config_site');

	complete_head_from_modules($conf, $langs, null, $head, $h, 'kreawoo_config_site', 'remove');

	$head[$h][0] = dol_buildpath("/kreawoo/admin/about.php", 1) . ($object->id > 0 ? '?id=' . $object->id : '');
	$head[$h][1] = $langs->trans("About") . " / " . $langs->trans("Support");
	$head[$h][2] = 'about';
	$h++;

	$head[$h][0] = dol_buildpath("/kreawoo/admin/changelog.php", 1) . ($object->id > 0 ? '?id=' . $object->id : '');
	$head[$h][1] = $langs->trans("KreativitatChangeLog");
	$head[$h][2] = 'changelog';
	$h++;

	return $head;
}

/**
 * Update the price for all product in the ecommerce product category for this site price level
 * @param kreawooSite  $siteDb    Object kreawooSite
 *
 * @return int                      <0 if KO, >0 if OK
 */
function updatePriceLevel($siteDb)
{
	global $db, $conf, $langs, $user;

	if (!empty($conf->global->PRODUIT_MULTIPRICES) && $siteDb->price_level > 0 && $siteDb->price_level <= intval($conf->global->PRODUIT_MULTIPRICES_LIMIT)) {
		$sql = 'SELECT p.rowid';
		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'product as p';
		$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . "categorie_product as cp ON p.rowid = cp.fk_product";
		$sql .= ' WHERE p.entity IN (' . getEntity('product', 1) . ')';
		$sql .= ' AND cp.fk_categorie = ' . $siteDb->fk_cat_product;
		$sql .= ' GROUP BY p.rowid';

		$db->begin();

		dol_syslog("updatePriceLevel sql=" . $sql);
		$resql = $db->query($sql);
		if ($resql) {
			$product = new Product($db);
			$kreawooProduct = new kreawooProduct($db);

			while ($obj = $db->fetch_object($resql)) {
				$product->fetch($obj->rowid);
				$kreawooProduct->fetchByProductId($obj->rowid, $siteDb->id);

				if ($kreawooProduct->remote_id > 0) {
					$kreawooSynchro = new kreawooSynchro($db, $siteDb);
					$kreawooSynchro->connect();
					if (count($kreawooSynchro->errors)) {
						dol_syslog("updatePriceLevel kreawooSynchro->connect() " . $kreawooSynchro->error, LOG_ERR);
						setEventMessages($kreawooSynchro->error, $kreawooSynchro->errors, 'errors');

						$db->rollback();
						return -1;
					}

					$product->price = $product->multiprices[$siteDb->price_level];

					$result = $kreawooSynchro->kreawooRemoteAccess->updateRemoteProduct($kreawooProduct->remote_id, $product);
					if (!$result) {
						dol_syslog("updatePriceLevel kreawooSynchro->kreawooRemoteAccess->updateRemoteProduct() " . $kreawooSynchro->kreawooRemoteAccess->error, LOG_ERR);
						setEventMessages($kreawooSynchro->kreawooRemoteAccess->error, $kreawooSynchro->kreawooRemoteAccess->errors, 'errors');

						$db->rollback();
						return -2;
					} else {
						$last_update_date = $result['last_update_date'];
						//kreawoo update link
						$now = dol_now();
						$kreawooProduct->fetchByRemoteId($obj->remote_id, $siteDb->id);
						$kreawooProduct->last_update = dol_print_date(max($last_update_date, dol_now()), '%Y-%m-%d %H:%M:%S');
						if ($kreawooProduct->stock_sync_direction == 'dolibarr2ecommerce') $kreawooProduct->last_update_stock = $kreawooProduct->last_update;
						if ($kreawooProduct->update($user) < 0) {
							$langs->load('kreawoo@kreawoo');
							$error_msg = $langs->trans('kreawooUpdateRemoteProductLink', $product->id, $siteDb->name, $kreawooProduct->error);
							dol_syslog("updatePriceLevel kreawooProduct->update() " . $error_msg, LOG_ERR);
							setEventMessages($error_msg, null, 'errors');

							$db->rollback();
							return -2;
						}
					}
				} else {
					dol_syslog("updatePriceLevel Product with id " . $product->id . " is not linked to an ecommerce record but has category flag to push on kreawoo. So we push it");
					// TODO
					//$result = $kreawooSynchro->kreawooRemoteAccess->updateRemoteProduct($kreawooProduct->remote_id);
				}
			}
		}

		$db->commit();
	}

	return 1;
}

function kreawoo_wordpress_sanitize_file_name( $filename )
{
	//$filename_raw = $filename;
	$special_chars = array("?", "[", "]", "/", "\\", "=", "<", ">", ":", ";", ",", "'", "\"", "&", "$", "#", "*", "(", ")", "|", "~", "`", "!", "{", "}", "%", "+", chr(0));
	/**
	 * Filters the list of characters to remove from a filename.
	 *
	 * @param array $special_chars Characters to remove.
	 * @param string $filename_raw Filename as it was passed into sanitize_file_name().
	 * @since 2.8.0
	 *
	 */
	$filename = preg_replace("#\x{00a0}#siu", ' ', $filename);
	$filename = str_replace($special_chars, '', $filename);
	$filename = str_replace(array('%20', '+'), '-', $filename);
	$filename = preg_replace('/[\r\n\t -]+/', '-', $filename);
	$filename = trim($filename, '.-_');

	/*if ( false === strpos( $filename, '.' ) ) {
		$mime_types = wp_get_mime_types();
		$filetype = wp_check_filetype( 'test.' . $filename, $mime_types );
		if ( $filetype['ext'] === $filename ) {
			$filename = 'unnamed-file.' . $filetype['ext'];
		}
	}*/

	// Split the filename into a base and extension[s]
	//$parts = explode('.', $filename);

	// Return if only one extension
	/*if ( count( $parts ) <= 2 ) {
		/**
		 * Filters a sanitized filename string.
		 *
		 * @since 2.8.0
		 *
		 * @param string $filename     Sanitized filename.
		 * @param string $filename_raw The filename prior to sanitization.
		 */
	/*    return apply_filters( 'sanitize_file_name', $filename, $filename_raw );
	}*/

	// Process multiple extensions
	/*$filename = array_shift($parts);
	$extension = array_pop($parts);
	$mimes = get_allowed_mime_types();

	/*
	 * Loop over any intermediate extensions. Postfix them with a trailing underscore
	 * if they are a 2 - 5 character long alpha string not in the extension whitelist.
	 */
	/*foreach ( (array) $parts as $part) {
		$filename .= '.' . $part;

		if ( preg_match("/^[a-zA-Z]{2,5}\d?$/", $part) ) {
			$allowed = false;
			foreach ( $mimes as $ext_preg => $mime_match ) {
				$ext_preg = '!^(' . $ext_preg . ')$!i';
				if ( preg_match( $ext_preg, $part ) ) {
					$allowed = true;
					break;
				}
			}
			if ( !$allowed )
				$filename .= '_';
		}
	}
	$filename .= '.' . $extension;
	/** This filter is documented in wp-includes/formatting.php */
	//return apply_filters('sanitize_file_name', $filename, $filename_raw);
	return $filename;
}

function kreawoo_download_image($image, $product, &$error_message)
{
	dol_syslog(__METHOD__ . ': image=' . implode(',', $image) . ' product_id=' . $product->id, LOG_DEBUG);
	global $db, $conf, $maxwidthsmall, $maxheightsmall, $maxwidthmini, $maxheightmini;

	if ($product->type != Product::TYPE_PRODUCT && $product->type != Product::TYPE_SERVICE) {
		$error_message = "Error the product is not a product or service type";
		dol_syslog(__METHOD__ . ': ' . $error_message, LOG_ERR);
		return false;
	}

	$entity = isset($product->entity) ? $product->entity : $conf->entity;

	// Set upload directory
	if (!empty($conf->global->PRODUCT_USE_OLD_PATH_FOR_PHOTO)) {    // For backward compatiblity, we scan also old dirs
		if ($product->type == Product::TYPE_PRODUCT) {
			$upload_dir = $conf->product->multidir_output[$entity] . '/' . substr(substr("000" . $product->id, -2), 1, 1) . '/' . substr(substr("000" . $product->id, -2), 0, 1) . '/' . $product->id . "/photos";
		} else {
			$upload_dir = $conf->service->multidir_output[$entity] . '/' . substr(substr("000" . $product->id, -2), 1, 1) . '/' . substr(substr("000" . $product->id, -2), 0, 1) . '/' . $product->id . "/photos";
		}
	} else {
		if (version_compare(DOL_VERSION, "13.0.0") >= 0) {
			if ($product->type == Product::TYPE_PRODUCT) {
				$upload_dir = $conf->product->multidir_output[$entity] . '/' . get_exdir(0, 0, 0, 1, $product, 'product');
			} else {
				$upload_dir = $conf->service->multidir_output[$entity] . '/' . get_exdir(0, 0, 0, 1, $product, 'product');
			}
		} else {
			if ($product->type == Product::TYPE_PRODUCT) {
				$upload_dir = $conf->product->multidir_output[$entity] . '/' . get_exdir(0, 0, 0, 0, $product, 'product') . dol_sanitizeFileName($product->ref);
			} else {
				$upload_dir = $conf->service->multidir_output[$entity] . '/' . get_exdir(0, 0, 0, 0, $product, 'product') . dol_sanitizeFileName($product->ref);
			}
		}
	}

	// Define $destpath (path to file including filename) and $destfile (only filename)
	$file_name = dol_sanitizeFileName($image['filename']); //basename(parse_url($image['url'], PHP_URL_PATH));
	$destpath = $upload_dir . "/" . $file_name;
	$destfile = $file_name;

	// lowercase extension
	$info = pathinfo($destpath);
	$destpath = $info['dirname'] . '/' . $info['filename'] . '.' . strtolower($info['extension']);
	$info = pathinfo($destfile);
	$destfile = $info['filename'] . '.' . strtolower($info['extension']);

	// Security:
	// Disallow file with some extensions. We rename them.
	// Because if we put the documents directory into a directory inside web root (very bad), this allows to execute on demand arbitrary code.
	if (preg_match('/\.htm|\.html|\.php|\.pl|\.cgi|\.exe$/i', $destfile) && empty($conf->global->MAIN_DOCUMENT_IS_OUTSIDE_WEBROOT_SO_NOEXE_NOT_REQUIRED)) {
		$destfile .= '.noexe';
		$destpath .= '.noexe';
	}

	$destpath = dol_sanitizePathName($destpath);

	// Check if image is modified
	if (file_exists($destpath)) {
		$local_image_date = new DateTime();
		$local_image_date->setTimestamp(filectime($destpath));
		$remote_image_date = new DateTime($image['date_modified']);

		if ($local_image_date >= $remote_image_date) {
			return true;
		}
	}

	dol_syslog(__METHOD__ . ': upload_dir=' . $upload_dir . ' image=' . implode(',', $image) . ' product_id=' . $product->id . ' dest_path=' . $destpath, LOG_DEBUG);

	if (dol_mkdir($upload_dir) < 0) {
		$error_message = "Error create product images directory ($upload_dir)";
		dol_syslog(__METHOD__ . ': ' . $error_message, LOG_ERR);
		return false;
	}

	$error = 0;

	// Get file
	$timeout = !empty($conf->global->ECOMMERCE_DOWNLOAD_TIMEOUT) ? $conf->global->ECOMMERCE_DOWNLOAD_TIMEOUT : 30;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $image['url']);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
	$userAgent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)';
	if (!empty($conf->global->ECOMMERCE_USER_AGENT)) $userAgent = $conf->global->ECOMMERCE_USER_AGENT;
	curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

	if (!empty($conf->global->ECOMMERCE_CURL_VERBOSE)) {
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		$verbose = fopen(DOL_DATA_ROOT . '/curl_verbose.txt', 'w+');
		curl_setopt($ch, CURLOPT_STDERR, $verbose);
	}

	$data = curl_exec($ch);
	if (curl_errno($ch) || $data === false) {
		$error_message = "CURL - " . curl_error($ch);
		dol_syslog(__METHOD__ . ': ' . $error_message, LOG_ERR);
		$error++;
	} else {
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($code != 200) {
			$error_message = "CURL - HTTP code: $code";
			dol_syslog(__METHOD__ . ': ' . $error_message, LOG_ERR);
			$error++;
		}
	}
	curl_close($ch);
	if (!empty($conf->global->ECOMMERCE_CURL_VERBOSE) && isset($verbose)) {
		@fclose($verbose);
	}

	if ($error) {
		return false;
	}

	// Get in temporary file name
	if (version_compare(phpversion(), '5.2.1', '<')) {
		if ($conf->global->ECOMMERCE_DOWNLOAD_TMP_DIRECTORY_PATH) {
			$tmp_path = $conf->global->ECOMMERCE_DOWNLOAD_TMP_DIRECTORY_PATH;
		} else {
			$error_message = "Error ECOMMERCE_DOWNLOAD_TMP_DIRECTORY_PATH not defined";
			dol_syslog(__METHOD__ . ': ' . $error_message, LOG_ERR);
			return false;
		}
	} else {
		$tmp_path = sys_get_temp_dir();
	}

	if (dol_mkdir($tmp_path) < 0) {
		$error_message = "Error create download temporary directory ($tmp_path)";
		dol_syslog(__METHOD__ . ': ' . $error_message, LOG_ERR);
		return false;
	}

	// Save temporary file
	$temp_file = tempnam($tmp_path, $destfile);
	$fh = @fopen($temp_file, "w");
	if ($fh === false) {
		$error_message = "Error open temporary file ($temp_file)";
		dol_syslog(__METHOD__ . ': ' . $error_message, LOG_ERR);
		return false;
	}
	$ret = fwrite($fh, $data);
	if ($ret === false) {
		$error_message = "Error write data in temporary file ($temp_file)";
		dol_syslog(__METHOD__ . ': ' . $error_message, LOG_ERR);
		return false;
	}
	$ret = fclose($fh);
	if ($ret === false) {
		$error_message = "Error close temporary file ($temp_file)";
		dol_syslog(__METHOD__ . ': ' . $error_message, LOG_ERR);
		return false;
	}

	// If we need to make a virus scan
	if (empty($disablevirusscan) && file_exists($temp_file) && !empty($conf->global->MAIN_ANTIVIRUS_COMMAND)) {
		if (!class_exists('AntiVir')) {
			require_once DOL_DOCUMENT_ROOT . '/core/class/antivir.class.php';
		}
		$antivir = new AntiVir($db);
		$result = $antivir->dol_avscan_file($temp_file);
		if ($result < 0)    // If virus or error, we stop here
		{
			$error_message = 'Error file is infected with a virus: ' . join(',', $antivir->errors);
			dol_syslog('Files.lib::dol_move_uploaded_file File "' . $temp_file . '" (target name "' . $temp_file . '") KO with antivirus: result=' . $result . ' errors=' . join(',', $antivir->errors), LOG_ERR);
			return false;
		}
	}

	if (!dol_move($temp_file, $destpath)) {
		unlink($temp_file);
		$error_message = "Error move temporary file ($temp_file) in product image directory ($destpath)";
		dol_syslog(__METHOD__ . ': ' . $error_message, LOG_ERR);
		return false;
	}

	if (image_format_supported($destpath) == 1) {
		global $maxwidthsmall, $maxheightsmall, $maxwidthmini, $maxheightmini, $quality;

		// Fix Define size of logo small and mini
		$maxwidthsmall = $maxwidthsmall ?? 480;
		$maxheightsmall = $maxheightsmall ?? 270; // Near 16/9eme
		$maxwidthmini = $maxwidthmini ?? 128;
		$maxheightmini = $maxheightmini ?? 72; // 16/9eme
		$quality = $quality ?? 80;

		// Create thumbs
		// We can't use $object->addThumbs here because there is no $object known

		// Used on logon for example
		$imgThumbSmall = vignette($destpath, $maxwidthsmall, $maxheightsmall, '_small', 50, "thumbs");
		// Create mini thumbs for image (Ratio is near 16/9)
		// Used on menu or for setup page for example
		$imgThumbMini = vignette($destpath, $maxwidthmini, $maxheightmini, '_mini', 50, "thumbs");
	}

	return true;
}

function kreawoo_remove_obsolete_image($product, $images, &$error_message)
{
	$images = is_array($images) ? $images : array();

	dol_syslog(__METHOD__ . ': product_id=' . $product->id . ' images=' . json_encode($images), LOG_DEBUG);
	global $db, $conf;

	if ($product->type != Product::TYPE_PRODUCT && $product->type != Product::TYPE_SERVICE) {
		$error_message = "Error the product is not a product or service type";
		dol_syslog(__METHOD__ . ': ' . $error_message, LOG_ERR);
		return false;
	}

	$entity = isset($product->entity) ? $product->entity : $conf->entity;

	// Set upload directory
	if (!empty($conf->global->PRODUCT_USE_OLD_PATH_FOR_PHOTO)) {    // For backward compatiblity, we scan also old dirs
		if ($product->type == Product::TYPE_PRODUCT) {
			$upload_dir = $conf->product->multidir_output[$entity] . '/' . substr(substr("000" . $product->id, -2), 1, 1) . '/' . substr(substr("000" . $product->id, -2), 0, 1) . '/' . $product->id . "/photos/";
		} else {
			$upload_dir = $conf->service->multidir_output[$entity] . '/' . substr(substr("000" . $product->id, -2), 1, 1) . '/' . substr(substr("000" . $product->id, -2), 0, 1) . '/' . $product->id . "/photos/";
		}
	} else {
		if (version_compare(DOL_VERSION, "13.0.0") >= 0) {
			if ($product->type == Product::TYPE_PRODUCT) {
				$upload_dir = $conf->product->multidir_output[$entity] . '/' . get_exdir(0, 0, 0, 1, $product, 'product') . '/';
			} else {
				$upload_dir = $conf->service->multidir_output[$entity] . '/' . get_exdir(0, 0, 0, 1, $product, 'product') . '/';
			}
		} else {
			if ($product->type == Product::TYPE_PRODUCT) {
				$upload_dir = $conf->product->multidir_output[$entity] . '/' . get_exdir(0, 0, 0, 0, $product, 'product') . dol_sanitizeFileName($product->ref) . '/';
			} else {
				$upload_dir = $conf->service->multidir_output[$entity] . '/' . get_exdir(0, 0, 0, 0, $product, 'product') . dol_sanitizeFileName($product->ref) . '/';
			}
		}
	}

	$images_name = [];
	foreach ($images as $image) {
		// Define $destpath (path to file including filename) and $destfile (only filename)
		$file_name = dol_sanitizeFileName($image['filename']); //basename(parse_url($image['url'], PHP_URL_PATH));
		$destfile = $file_name;

		// lowercase extension
		$info = pathinfo($destfile);
		$destfile = $info['filename'] . '.' . strtolower($info['extension']);

		// Security:
		// Disallow file with some extensions. We rename them.
		// Because if we put the documents directory into a directory inside web root (very bad), this allows to execute on demand arbitrary code.
		if (preg_match('/\.htm|\.html|\.php|\.pl|\.cgi|\.exe$/i', $destfile) && empty($conf->global->MAIN_DOCUMENT_IS_OUTSIDE_WEBROOT_SO_NOEXE_NOT_REQUIRED)) {
			$destfile .= '.noexe';
		}

		$images_name[] = $destfile;
	}

	dol_syslog(__METHOD__ . ': upload_dir=' . $upload_dir . ' images=' . implode(',', $images_name) . ' product_id=' . $product->id, LOG_DEBUG);

	$photos = $product->liste_photos($upload_dir);
	foreach ($photos as $index => $photo) {
		if (!in_array($photo['photo'], $images_name, true)) {
			unlink($upload_dir . $photo['photo']);
		}
	}

	return true;
}

function kreawoo_add_extrafields($db, $langs, $extrafields, &$error)
{
	$result = 1;

	$efields = new ExtraFields($db);
	foreach ($extrafields as $extrafield) {
		$result = $efields->addExtraField(
			$extrafield['attrname'],
			$langs->trans($extrafield['label']),
			$extrafield['type'],
			$extrafield['pos'],
			$extrafield['size'],
			$extrafield['elementtype'],
			$extrafield['unique'],
			$extrafield['required'],
			$extrafield['default_value'],
			$extrafield['param'],
			$extrafield['alwayseditable'],
			$extrafield['perms'],
			$extrafield['list']
		);
		if ($result <= 0) {
			$error = $efields->error;
			return -1;
		}
	}

	return $result;
}

function kreawoo_update_woocommerce_attribute($db, $site)
{
	global $conf, $langs;
	$langs->load('kreawoo@kreawoo');

	$db->begin();

	$kreawooRemoteAccessWoocommerce = new kreawooRemoteAccessWoocommerce($db, $site);

	if (!$kreawooRemoteAccessWoocommerce->connect()) {
		setEventMessages('', $kreawooRemoteAccessWoocommerce->errors, 'errors');
		$db->rollback();
		return false;
	}

	$attributes = $kreawooRemoteAccessWoocommerce->getAllWoocommerceAttributes();
	if ($attributes === false) {
		setEventMessages('', $kreawooRemoteAccessWoocommerce->errors, 'errors');
		$db->rollback();
		return false;
	}

	$kreawooDict = new kreawooDict($db, MAIN_DB_PREFIX . 'c_kreawoo_attribute');

	// Get all attributes in dictionary for this entity and site
	$dict_attributes = $kreawooDict->search(['entity' => ['value' => $conf->entity], 'site_id' => ['value' => $site->id]]);

	// Disable attribute not found in woocommerce
	foreach ($dict_attributes as $line) {
		if (!isset($attributes[$line['attribute_id']])) {
			// Disable attribute
			$result = $kreawooDict->update(['active' => ['value' => 0]], ['rowid' => ['value' => $line['rowid']]]);
			if ($result == false) {
				setEventMessage($langs->trans('kreawooWoocommerceErrorDisableDictAttribute', $line['attribute_slug'], $db->error()), 'errors');
				$db->rollback();
				return false;
			}
		} else {
			$attribute = $attributes[$line['attribute_id']];
			$result = $kreawooDict->update([
				'attribute_name' => ['value' => $attribute['name'], 'type' => 'string'],
				'attribute_slug' => ['value' => $attribute['slug'], 'type' => 'string'],
				'attribute_type' => ['value' => $attribute['type'], 'type' => 'string'],
				'attribute_order_by' => ['value' => $attribute['order_by'], 'type' => 'string'],
				'attribute_has_archives' => ['value' => $attribute['has_archives'] ? 1 : 0],
			], ['rowid' => ['value' => $line['rowid']]]);
			if ($result == false) {
				setEventMessage($langs->trans('kreawooWoocommerceErrorUpdateDictAttribute', $line['attribute_slug'], $db->error()), 'errors');
				$db->rollback();
				return false;
			}
			$attributes[$line['attribute_id']]['founded'] = true;
		}
	}

	// Add new attribute from woocommerce
	foreach ($attributes as $attribute) {
		if (!isset($attribute['founded'])) {
			// Add new attribute
			$result = $kreawooDict->insert(['site_id', 'attribute_id', 'attribute_name', 'attribute_slug', 'attribute_type', 'attribute_order_by', 'attribute_has_archives', 'entity', 'active'], ['site_id' => ['value' => $site->id],
				'attribute_id' => ['value' => $attribute['id']],
				'attribute_name' => ['value' => $attribute['name'], 'type' => 'string'],
				'attribute_slug' => ['value' => $attribute['slug'], 'type' => 'string'],
				'attribute_type' => ['value' => $attribute['type'], 'type' => 'string'],
				'attribute_order_by' => ['value' => $attribute['order_by'], 'type' => 'string'],
				'attribute_has_archives' => ['value' => $attribute['has_archives'] ? 1 : 0],
				'entity' => ['value' => $conf->entity], 'active' => ['value' => 1]]);
			if ($result == false) {
				setEventMessage($langs->trans('kreawooWoocommerceErrorAddDictAttribute', $attribute['slug'], $attribute['name'], $db->error()), 'errors');
				$db->rollback();
				return false;
			}
		}
	}

	$db->commit();
	return true;
}

function kreawoo_update_woocommerce_dict_tax($db, $site)
{
	global $conf, $langs;
	$langs->load('kreawoo@kreawoo');

	$db->begin();

	$kreawooRemoteAccessWoocommerce = new kreawooRemoteAccessWoocommerce($db, $site);

	if (!$kreawooRemoteAccessWoocommerce->connect()) {
		setEventMessages('', $kreawooRemoteAccessWoocommerce->errors, 'errors');
		$db->rollback();
		return false;
	}

	$taxClasses = $kreawooRemoteAccessWoocommerce->getAllWoocommerceTaxClass();
	if ($taxClasses === false) {
		setEventMessages('', $kreawooRemoteAccessWoocommerce->errors, 'errors');
		$db->rollback();
		return false;
	}

	$kreawooDict = new kreawooDict($db, MAIN_DB_PREFIX . 'c_kreawoo_tax_class');

	// Get all tax class in dictionary for this entity and site
	$dict_tax_classes = $kreawooDict->search(['entity' => ['value' => $conf->entity], 'site_id' => ['value' => $site->id]]);

	// Desactive code not found in woocommerce
	foreach ($dict_tax_classes as $line) {
		if (!isset($taxClasses[$line['code']])) {
			// Desactive code
			$result = $kreawooDict->update(['active' => ['value' => 0]], ['rowid' => ['value' => $line['rowid']]]);
			if ($result == false) {
				setEventMessage($langs->trans('kreawooWoocommerceErrorDisableDictTaxClass', $line['code'], $db->error()), 'errors');
				$db->rollback();
				return false;
			}
		} else {
			$result = $kreawooDict->update(['label' => ['value' => $taxClasses[$line['code']]['name'], 'type' => 'string']], ['rowid' => ['value' => $line['rowid']]]);
			if ($result == false) {
				setEventMessage($langs->trans('kreawooWoocommerceErrorUpdateDictTaxClass', $line['code'], $db->error()), 'errors');
				$db->rollback();
				return false;
			}
			$taxClasses[$line['code']]['founded'] = true;
		}
	}

	// Add new code from woocommerce
	foreach ($taxClasses as $taxClass) {
		if (!isset($taxClass['founded'])) {
			// Add new tax class code
			$result = $kreawooDict->insert(['site_id', 'code', 'label', 'entity', 'active'], ['site_id' => ['value' => $site->id], 'code' => ['value' => $taxClass['slug'], 'type' => 'string'], 'label' => ['value' => $taxClass['name'], 'type' => 'string'], 'entity' => ['value' => $conf->entity], 'active' => ['value' => 1]]);
			if ($result == false) {
				setEventMessage($langs->trans('kreawooWoocommerceErrorAddDictTaxClass', $taxClass['slug'], $taxClass['name']) . ' ' . $db->error(), 'errors');
				$db->rollback();
				return false;
			}
		}
	}

	$taxRates = $kreawooRemoteAccessWoocommerce->getAllWoocommerceTaxRate();
	if ($taxRates === false) {
		setEventMessages('', $kreawooRemoteAccessWoocommerce->errors, 'errors');
		$db->rollback();
		return false;
	}

	$kreawooDict = new kreawooDict($db, MAIN_DB_PREFIX . 'c_kreawoo_tax_rate');

	// Get all tax class in dictionary for this entity and site
	$dict_tax_rates = $kreawooDict->search(['entity' => ['value' => $conf->entity], 'site_id' => ['value' => $site->id]]);

	// Desactive code not found in woocommerce
	foreach ($dict_tax_rates as $line) {
		if (!isset($taxRates[$line['tax_id']])) {
			// Desactive code
			$result = $kreawooDict->update(['active' => ['value' => 0]], ['rowid' => ['value' => $line['rowid']]]);
			if ($result == false) {
				setEventMessage($langs->trans('kreawooWoocommerceErrorDisableDictTaxRate', $line['tax_id'], $db->error()), 'errors');
				$db->rollback();
				return false;
			}
		} else {
			$taxRate = $taxRates[$line['tax_id']];
			$rate = price2num($taxRate['rate']);
			if (strpos((string) $rate, '.') === false) $rate = $rate . '.0';
			$result = $kreawooDict->update(['tax_country' => ['value' => $taxRate['country'], 'type' => 'string'], 'tax_state' => ['value' => $taxRate['state'], 'type' => 'string'],
				'tax_postcode' => ['value' => $taxRate['postcode'], 'type' => 'string'], 'tax_city' => ['value' => $taxRate['city'], 'type' => 'string'], 'tax_rate' => ['value' => $rate, 'type' => 'string'],
				'tax_name' => ['value' => $taxRate['name'], 'type' => 'string'], 'tax_priority' => ['value' => $taxRate['priority']], 'tax_compound' => ['value' => $taxRate['compound'] ? 1 : 0],
				'tax_shipping' => ['value' => $taxRate['shipping'] ? 1 : 0], 'tax_order' => ['value' => $taxRate['order']], 'tax_class' => ['value' => $taxRate['class'], 'type' => 'string']], ['rowid' => ['value' => $line['rowid']]]);
			if ($result == false) {
				setEventMessage($langs->trans('kreawooWoocommerceErrorUpdateDictTaxRate', $line['tax_id'], $db->error()), 'errors');
				$db->rollback();
				return false;
			}
			$taxRates[$line['tax_id']]['founded'] = true;
		}
	}

	// Add new tax rate from woocommerce
	foreach ($taxRates as $taxRate) {
		if (!isset($taxRate['founded'])) {
			$rate = price2num($taxRate['rate']);
			if (strpos((string) $rate, '.') === false) $rate = $rate . '.0';
			// Add new tax rate
			$result = $kreawooDict->insert(['site_id', 'tax_id', 'tax_country', 'tax_state', 'tax_postcode', 'tax_city', 'tax_rate', 'tax_name', 'tax_priority', 'tax_compound', 'tax_shipping', 'tax_order', 'tax_class', 'entity', 'active'],
				['site_id' => ['value' => $site->id], 'tax_id' => ['value' => $taxRate['id']], 'tax_country' => ['value' => $taxRate['country'], 'type' => 'string'], 'tax_state' => ['value' => $taxRate['state'], 'type' => 'string'],
					'tax_postcode' => ['value' => $taxRate['postcode'], 'type' => 'string'], 'tax_city' => ['value' => $taxRate['city'], 'type' => 'string'], 'tax_rate' => ['value' => $rate, 'type' => 'string'],
					'tax_name' => ['value' => $taxRate['name'], 'type' => 'string'], 'tax_priority' => ['value' => $taxRate['priority']], 'tax_compound' => ['value' => $taxRate['compound'] ? 1 : 0],
					'tax_shipping' => ['value' => $taxRate['shipping'] ? 1 : 0], 'tax_order' => ['value' => $taxRate['order']], 'tax_class' => ['value' => $taxRate['class'], 'type' => 'string']
					, 'entity' => ['value' => $conf->entity], 'active' => ['value' => 1]]);
			if ($result == false) {
				setEventMessage($langs->trans('kreawooWoocommerceErrorAddDictTaxRate', $taxRate['slug'], $db->error()), 'errors');
				$db->rollback();
				return false;
			}
		}
	}

	$db->commit();
	return true;
}

function kreawoo_update_payment_gateways($db, $site)
{
	global $conf, $langs;
	$langs->load('kreawoo@kreawoo');

	dol_include_once('/kreawoo/class/business/kreawooSynchro.class.php');
	$synchro = new kreawooSynchro($db, $site, 0, 0);

	dol_syslog("site.php Try to connect to kreawoo site " . $site->name);
	$synchro->connect();
	if (count($synchro->errors)) {
		setEventMessages($synchro->error, $synchro->errors, 'errors');
		return false;
	}

	$paymentGateways = $synchro->getAllPaymentGateways();
	if ($paymentGateways === false) {
		setEventMessages($synchro->error, $synchro->errors, 'errors');
		return false;
	}

	// Get all payment gateways
	dol_include_once('/kreawoo/class/data/kreawooPaymentGateways.class.php');
	$pay_gateways = new kreawooPaymentGateways($db);
	$currentPaymentGateways = $pay_gateways->get_all($site->id);
	if (!is_array($currentPaymentGateways) && $currentPaymentGateways < 0) {
		setEventMessages('', $pay_gateways->errors, 'errors');
		return false;
	}

	$payment_gateways = array();
	foreach ($paymentGateways as $id => $label) {
		$payment_gateways[$id] = array(
			'payment_gateway_label' => $label,
			'payment_mode_id' => $currentPaymentGateways[$id]['payment_mode_id'] > 0 ? $currentPaymentGateways[$id]['payment_mode_id'] : 0,
			'bank_account_id' => $currentPaymentGateways[$id]['bank_account_id'] > 0 ? $currentPaymentGateways[$id]['bank_account_id'] : 0,
			'supplier_id' => $currentPaymentGateways[$id]['supplier_id'] > 0 ? $currentPaymentGateways[$id]['supplier_id'] : 0,
			'create_invoice_payment' => $currentPaymentGateways[$id]['create_invoice_payment'] > 0 ? $currentPaymentGateways[$id]['create_invoice_payment'] : 0,
			'mail_model_for_send_invoice' => $currentPaymentGateways[$id]['mail_model_for_send_invoice'],
			'product_id_for_fee' => $currentPaymentGateways[$id]['product_id_for_fee'] > 0 ? $currentPaymentGateways[$id]['product_id_for_fee'] : 0,
			'create_supplier_invoice_payment' => $currentPaymentGateways[$id]['create_supplier_invoice_payment'] > 0 ? $currentPaymentGateways[$id]['create_supplier_invoice_payment'] : 0
		);
	}

	$result = $pay_gateways->set($site->id, $payment_gateways);
	if ($result < 0) {
		setEventMessages($pay_gateways->error, $pay_gateways->errors, 'errors');
		return false;
	}

	return true;
}

function kreawoo_update_remote_warehouses($db, $site)
{
	global $conf, $langs;
	$langs->load('kreawoo@kreawoo');

	if (empty($site->parameters['enable_warehouse_plugin_support'])) {
		return 1;
	}

	dol_include_once('/kreawoo/class/business/kreawooSynchro.class.php');
	$synchro = new kreawooSynchro($db, $site, 0, 0);

	dol_syslog("site.php Try to connect to kreawoo site " . $site->name);
	$result = $synchro->connect();
	if (!$result) {
		setEventMessages($synchro->error, $synchro->errors, 'errors');
		return false;
	}

	$remote_warehouses_list = $synchro->getAllRemoteWarehouses();
	if ($remote_warehouses_list === false) {
		setEventMessages($synchro->error, $synchro->errors, 'errors');
		return false;
	}

	// Get all payment gateways
	dol_include_once('/kreawoo/class/data/kreawooRemoteWarehouses.class.php');
	$remote_warehouses = new kreawooRemoteWarehouses($db);
	$currentRemoteWarehouses = $remote_warehouses->get_all($site->id);
	if (!is_array($currentRemoteWarehouses) && $currentRemoteWarehouses < 0) {
		setEventMessages($remote_warehouses->error, $remote_warehouses->errors, 'errors');
		return false;
	}

	$finalRemoteWarehouses = array();

	// Add remotes warehouses
	foreach ($remote_warehouses_list as $remote_warehouse_id => $infos) {
		$finalRemoteWarehouses[$remote_warehouse_id] = array(
			'remote_id' => $infos['remote_id'],
			'remote_code' => $infos['remote_code'],
			'remote_name' => $infos['name'],
			'warehouse_id' => $currentRemoteWarehouses[$remote_warehouse_id]['warehouse_id'] > 0 ? $currentRemoteWarehouses[$remote_warehouse_id]['warehouse_id'] : 0,
			'set_even_if_empty_stock' => $currentRemoteWarehouses[$remote_warehouse_id]['set_even_if_empty_stock'] > 0 ? $currentRemoteWarehouses[$remote_warehouse_id]['set_even_if_empty_stock'] : 0,
			'old_entry' => 0,
		);
	}

	// Add current warehouses who has been deleted
	foreach ($currentRemoteWarehouses as $remote_warehouse_id => $infos) {
		if (!isset($finalRemoteWarehouses[$remote_warehouse_id])) {
			$finalRemoteWarehouses[$remote_warehouse_id] = array(
				'remote_id' => $infos['remote_id'],
				'remote_code' => $infos['remote_code'],
				'remote_name' => $infos['name'],
				'warehouse_id' => $infos['warehouse_id'],
				'set_even_if_empty_stock' => $infos['set_even_if_empty_stock'],
				'old_entry' => 1,
			);
		}
	}

	$result = $remote_warehouses->set($site->id, $finalRemoteWarehouses);
	if ($result < 0) {
		setEventMessages($remote_warehouses->error, $remote_warehouses->errors, 'errors');
		return false;
	}

	return true;
}

function kreawoo_update_remote_shipping_zone_methods($db, $site)
{
	global $conf, $langs;
	$langs->load('kreawoo@kreawoo');

	if (empty($site->parameters['enable_warehouse_depending_on_shipping_zone_method'])) {
		return 1;
	}

	dol_include_once('/kreawoo/class/business/kreawooSynchro.class.php');
	$synchro = new kreawooSynchro($db, $site, 0, 0);

	dol_syslog("site.php Try to connect to kreawoo site " . $site->name);
	$result = $synchro->connect();
	if (!$result) {
		setEventMessages($synchro->error, $synchro->errors, 'errors');
		return false;
	}

	$remote_shipping_zones_list = $synchro->getAllRemoteShippingZones();
	if ($remote_shipping_zones_list === false) {
		setEventMessages($synchro->error, $synchro->errors, 'errors');
		return false;
	}

	// Get all shipping zone methods
	dol_include_once('/kreawoo/class/data/kreawooRemoteShippingZoneMethods.class.php');
	$remote_shipping_zone_methods = new kreawooRemoteShippingZoneMethods($db);
	$currentRemoteShippingZoneMethods = $remote_shipping_zone_methods->get_all($site->id);
	if (!is_array($currentRemoteShippingZoneMethods) && $currentRemoteShippingZoneMethods < 0) {
		setEventMessages($remote_shipping_zone_methods->error, $remote_shipping_zone_methods->errors, 'errors');
		return false;
	}

	$finalRemoteShippingZoneMethods = array();

	// Add remotes shipping modes
	foreach ($remote_shipping_zones_list as $key1 => $remote_shipping_zone_infos) {
		$finalRemoteShippingZoneMethods[$key1] = array(
			'remote_id' => $remote_shipping_zone_infos['remote_id'],
			'remote_name' => $remote_shipping_zone_infos['name'],
			'remote_order' => $remote_shipping_zone_infos['order'],
			'old_entry' => 0,
		);

		$remote_shipping_zone_methods_list = $synchro->getAllRemoteShippingZoneMethods($remote_shipping_zone_infos['remote_id']);
		if ($remote_shipping_zone_methods_list === false) {
			setEventMessages($synchro->error, $synchro->errors, 'errors');
			return false;
		}

		foreach ($remote_shipping_zone_methods_list as $key2 => $infos) {
			$current_method = isset($currentRemoteShippingZoneMethods[$key1]['methods'][$key2]) ? $currentRemoteShippingZoneMethods[$key1]['methods'][$key2] : [];

			$finalRemoteShippingZoneMethods[$key1]['methods'][$key2] = array(
				'remote_zone_id' => $remote_shipping_zone_infos['remote_id'],
				'remote_instance_id' => $infos['instance_id'],
				'remote_title' => $infos['title'],
				'remote_order' => $infos['order'],
				'remote_enabled' => !empty($infos['enabled']),
				'remote_method_id' => $infos['method_id'],
				'remote_method_title' => $infos['method_title'],
				'remote_method_description' => $infos['method_description'],
				'warehouse_id' => !empty($current_method['warehouse_id']) && $current_method['warehouse_id'] > 0 ? $current_method['warehouse_id'] : 0,
				'old_entry' => 0,
			);
		}
	}

	// Add current shipping zones and shipping zone methods who have been deleted
	foreach ($currentRemoteShippingZoneMethods as $key1 => $remote_shipping_zone_infos) {
		if (!isset($finalRemoteShippingZoneMethods[$key1])) {
			$finalRemoteShippingZoneMethods[$key1] = array(
				'remote_id' => $remote_shipping_zone_infos['remote_id'],
				'remote_name' => $remote_shipping_zone_infos['remote_name'],
				'remote_order' => $remote_shipping_zone_infos['remote_order'],
				'old_entry' => 1,
			);
		}

		foreach ($remote_shipping_zone_infos['methods'] as $key2 => $infos) {
			if (!isset($finalRemoteShippingZoneMethods[$key1]['methods'][$key2])) {
				$finalRemoteShippingZoneMethods[$key1]['methods'][$key2] = array(
					'remote_zone_id' => $infos['remote_zone_id'],
					'remote_instance_id' => $infos['remote_instance_id'],
					'remote_title' => $infos['remote_title'],
					'remote_order' => $infos['remote_order'],
					'remote_enabled' => false,
					'remote_method_id' => $infos['remote_method_id'],
					'remote_method_title' => $infos['remote_method_title'],
					'remote_method_description' => $infos['remote_method_description'],
					'warehouse_id' => $infos['warehouse_id'],
					'old_entry' => 1,
				);
			}
		}
	}

	$result = $remote_shipping_zone_methods->set($site->id, $finalRemoteShippingZoneMethods);
	if ($result < 0) {
		setEventMessages($remote_shipping_zone_methods->error, $remote_shipping_zone_methods->errors, 'errors');
		return false;
	}

	return true;
}

function get_company_by_email($db, $email, $site=0)
{
	$email = $db->escape($email);

	$sql = "SELECT DISTINCT s.rowid FROM " . MAIN_DB_PREFIX . "societe AS s";
	$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "socpeople AS sp ON sp.fk_soc = s.rowid";
	if ($site > 0) $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "kreawoo_societe AS es ON es.fk_societe = s.rowid";
	$sql .= " WHERE (s.email = '$email' OR sp.email = '$email')";
	if ($site > 0) $sql .= " AND es.fk_site = $site";
	$sql .= " AND s.status = 1";
	$sql .= " AND s.entity IN (" . getEntity('societe') . ")";

	$resql = $db->query($sql);
	if ($resql) {
		$num = $db->num_rows($resql);
		if ($num > 1) {
			$result = -2;
		} elseif ($num) {
			$obj = $db->fetch_object($resql);
			$result = $obj->rowid;
		} else {
			$result = 0;
		}

		$db->free($resql);
	} else {
		$result = -1;
	}

	return $result;
}

function kreawooNewToken()
{
	return function_exists('newToken') ? newToken() : $_SESSION['newtoken'];
}
