<?php
/* Copyright (C) 2010 Franck Charpentier - Auguria <franck.charpentier@auguria.net>
 * Copyright (C) 2013 Laurent Destailleur          <eldy@users.sourceforge.net>
 * Copyright (C) 2024-2026  Kreativitat  <mail@kreativitat.com>
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


class kreawooProduct // extends CommonObject
{
	public $db;							//!< To store db handler
	public $error;							//!< To return error code (or message)
	public $errors=array();				//!< To return several error codes (or messages)
	//public $element='kreawoo_product';			//!< Id that identify managed objects
	//public $table_element='kreawoo_product';	//!< Name of table without prefix where object is stored

	public $id;
	public $fk_product;
	public $fk_site;
	public $remote_id;
	public $lang;
	public $last_update;
	public $last_update_stock;
	public $other_data;

	/**
	 *    Constructor
	 *    @param      DoliDB			$db      	Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		return 1;
	}


	/**
	 *      Create in database
	 *
	 *      @param      User    $user        	User that create
	 *      @param      int     $notrigger	    0=launch triggers after, 1=disable triggers
	 *      @return     int                    	<0 if KO, Id of created object if OK
	 */
	public function create($user, $notrigger = 0)
	{
		global $conf, $langs;
		$error = 0;
		$inserted = false;

		// Clean parameters
		if (isset($this->fk_product)) $this->fk_product = intval($this->fk_product);
		if (isset($this->fk_site)) $this->fk_site = intval($this->fk_site);
		if (isset($this->remote_id)) $this->remote_id = trim($this->remote_id);
		if (isset($this->lang)) $this->lang = trim($this->lang);
		if (isset($this->last_update)) $this->last_update = trim($this->last_update);
		if (isset($this->last_update_stock)) $this->last_update_stock = trim($this->last_update_stock);

		// Check parameters
		// Put here code to add control on parameters values

		// Insert request
		$sql = "INSERT INTO " . MAIN_DB_PREFIX . "kreawoo_product(";
		$sql .= "fk_product,";
		$sql .= "fk_site,";
		$sql .= "remote_id,";
		$sql .= "lang,";
		$sql .= "last_update,";
		$sql .= "last_update_stock,";
		$sql .= "other_data";
		$sql .= ") VALUES (";
		$sql .= " " . (isset($this->fk_product) ? intval($this->fk_product) : 0) . ",";
		$sql .= " " . (isset($this->fk_site) ? intval($this->fk_site) : 0) . ",";
		$sql .= " " . (isset($this->remote_id) ? "'" . $this->db->escape($this->remote_id) . "'" : "") . ",";
		$sql .= " " . (!empty($this->lang) ? "'" . $this->db->escape($this->lang) . "'" : "null") . ",";
		$sql .= " " . (isset($this->last_update) ? "'" . $this->last_update . "'" : 'null') . ",";
		$sql .= " " . (isset($this->last_update_stock) ? "'" . $this->last_update_stock . "'" : 'null') . ",";
		$sql .= " " . (!empty($this->other_data) ? "'" . $this->db->escape(json_encode($this->other_data)) . "'" : "NULL");
		$sql .= ")";

		$this->db->begin();

		dol_syslog(get_class($this) . "::create sql=" . $sql, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$databaseError = $this->db->lasterror();
			$databaseErrorCode = $this->db->lasterrno();
			$expectedRemoteId = $this->remote_id;

			// A webhook emitted by WooCommerce product creation can be processed
			// concurrently and store this exact link before this insert completes.
			if ($databaseErrorCode === 'DB_ERROR_RECORD_ALREADY_EXISTS' && $this->fetchByProductId($this->fk_product, $this->fk_site, true) > 0 && (string) $this->remote_id === (string) $expectedRemoteId) {
				dol_syslog(get_class($this) . "::create reused concurrent product link rowid=" . ((int) $this->id), LOG_INFO);
			} else {
				$error++;
				$this->errors[] = "Error " . $databaseError;
			}
		} else {
			$inserted = true;
		}

		if (!$error) {
			if ($inserted) {
				$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX . "kreawoo_product");
			}

			if (!$notrigger) {
				// Uncomment this and change MYOBJECT to your own tag if you
				// want this action call a trigger.

				//// Call triggers
				//include_once(DOL_DOCUMENT_ROOT . "/core/interfaces.class.php");
				//$interface=new Interfaces($this->db);
				//$result=$interface->run_triggers('MYOBJECT_CREATE',$this,$user,$langs,$conf);
				//if ($result < 0) { $error++; $this->errors=$interface->errors; }
				//// End call triggers
			}
		}

		// Commit or rollback
		if ($error) {
			foreach ($this->errors as $errmsg) {
				dol_syslog(get_class($this) . "::create " . $errmsg, LOG_ERR);
				$this->error .= ($this->error ? ', ' . $errmsg : $errmsg);
			}
			$this->db->rollback();
			return -1 * $error;
		} else {
			$this->db->commit();
			return $this->id;
		}
	}

	/**
	 *    Load object in memory from database
	 *
	 *    @param      int				$id          id object
	 *    @return     int         					 <0 if KO, >0 if OK
	 */
	public function fetch($id)
	{
		global $langs;
		$sql = "SELECT";
		$sql .= " t.rowid,";
		$sql .= " t.fk_product,";
		$sql .= " t.fk_site,";
		$sql .= " t.remote_id,";
		$sql .= " t.lang,";
		$sql .= " t.last_update,";
		$sql .= " t.last_update_stock,";
		$sql .= " t.other_data";
		$sql .= " FROM " . MAIN_DB_PREFIX . "kreawoo_product as t";
		$sql .= " WHERE t.rowid = " . $id;

		dol_syslog(get_class($this) . "::fetch sql=" . $sql, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql) {
			if ($this->db->num_rows($resql)) {
				$obj = $this->db->fetch_object($resql);

				$this->id = $obj->rowid;
				$this->fk_product = $obj->fk_product;
				$this->fk_site = $obj->fk_site;
				$this->remote_id = $obj->remote_id;
				$this->lang = $obj->lang;
				$this->last_update = $obj->last_update;
				$this->last_update_stock = $obj->last_update_stock;
				$this->other_data = !empty($obj->other_data) ? json_decode($obj->other_data, true) : [];
				return 1;
			}
			$this->db->free($resql);
			return 0;
		} else {
			$this->error = "Error " . $this->db->lasterror();
			dol_syslog(get_class($this) . "::fetch " . $this->error, LOG_ERR);
			return -1;
		}
	}

	/**
	 *    Update database
	 *
	 *    @param      User  $user        	User that modify
	 *    @param      int   $notrigger	    0=launch triggers after, 1=disable triggers
	 *    @return     int                	<0 if KO, >0 if OK
	 */
	public function update($user = 0, $notrigger = 0)
	{
		global $conf, $langs;
		$error = 0;

		// Clean parameters
		if (isset($this->fk_product)) $this->fk_product = intval($this->fk_product);
		if (isset($this->fk_site)) $this->fk_site = intval($this->fk_site);
		if (isset($this->remote_id)) $this->remote_id = trim($this->remote_id);
		if (isset($this->lang)) $this->lang = trim($this->lang);
		if (isset($this->last_update)) $this->last_update = trim($this->last_update);
		if (isset($this->last_update_stock)) $this->last_update_stock = trim($this->last_update_stock);

		// Check parameters
		// Put here code to add control on parameters values

		// Update request
		$sql = "UPDATE " . MAIN_DB_PREFIX . "kreawoo_product SET";

		$sql .= " fk_product=" . (isset($this->fk_product) ? intval($this->fk_product) : 0) . ",";
		$sql .= " fk_site=" . (isset($this->fk_site) ? intval($this->fk_site) : 0) . ",";
		$sql .= " remote_id='" . $this->db->escape($this->remote_id) . "',";
		$sql .= " lang=" . (!empty($this->lang) ? "'" . $this->db->escape($this->lang) . "'" : 'null') . ",";
		$sql .= " last_update=" . (isset($this->last_update) ? $this->db->ifsql("'" . $this->last_update . "' > last_update", "'" . $this->last_update . "'", 'last_update') : 'null') . ",";
		$sql .= " last_update_stock=" . (isset($this->last_update_stock) ? $this->db->ifsql("'" . $this->last_update_stock . "' > last_update", "'" . $this->last_update_stock . "'", 'last_update') : 'null') . ",";
		$sql .= " other_data=" . (!empty($this->other_data) ? "'" . $this->db->escape(json_encode($this->other_data)) . "'" : "NULL") . "";

		$sql .= " WHERE rowid=" . $this->id;

		$this->db->begin();

		dol_syslog(get_class($this) . "::update sql=" . $sql, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$error++;
			$this->errors[] = "Error " . $this->db->lasterror();
		}

		if (!$error) {
			if (!$notrigger) {
				// Uncomment this and change MYOBJECT to your own tag if you
				// want this action call a trigger.

				//// Call triggers
				//include_once(DOL_DOCUMENT_ROOT . "/core/interfaces.class.php");
				//$interface=new Interfaces($this->db);
				//$result=$interface->run_triggers('MYOBJECT_MODIFY',$this,$user,$langs,$conf);
				//if ($result < 0) { $error++; $this->errors=$interface->errors; }
				//// End call triggers
			}
		}

		// Commit or rollback
		if ($error) {
			foreach ($this->errors as $errmsg) {
				dol_syslog(get_class($this) . "::update " . $errmsg, LOG_ERR);
				$this->error .= ($this->error ? ', ' . $errmsg : $errmsg);
			}
			$this->db->rollback();
			return -1 * $error;
		} else {
			$this->db->commit();
			return 1;
		}
	}

	/**
	 *  Delete object in database
	 *
	 *	@param      User    $user        	User that delete
	 *  @param      int     $notrigger	    0=launch triggers after, 1=disable triggers
	 *	@return		int				        <0 if KO, >0 if OK
	 */
	public function delete($user, $notrigger = 0)
	{
		global $conf, $langs;
		$error = 0;

		$sql = "DELETE FROM " . MAIN_DB_PREFIX . "kreawoo_product";
		$sql .= " WHERE rowid=" . $this->id;

		$this->db->begin();

		dol_syslog(get_class($this) . "::delete sql=" . $sql);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$error++;
			$this->errors[] = "Error " . $this->db->lasterror();
		}

		if (!$error) {
			if (!$notrigger) {
				// Uncomment this and change MYOBJECT to your own tag if you
				// want this action call a trigger.

				//// Call triggers
				//include_once(DOL_DOCUMENT_ROOT . "/core/interfaces.class.php");
				//$interface=new Interfaces($this->db);
				//$result=$interface->run_triggers('MYOBJECT_DELETE',$this,$user,$langs,$conf);
				//if ($result < 0) { $error++; $this->errors=$interface->errors; }
				//// End call triggers
			}
		}

		// Commit or rollback
		if ($error) {
			foreach ($this->errors as $errmsg) {
				dol_syslog(get_class($this) . "::delete " . $errmsg, LOG_ERR);
				$this->error .= ($this->error ? ', ' . $errmsg : $errmsg);
			}
			$this->db->rollback();
			return -1 * $error;
		} else {
			$this->db->commit();
			return 1;
		}
	}

	/**
	 * Get the last date of the last updated product
	 *
	 * @param  int         $siteId         kreawooSite id | * for each sites
	 * @return int
	 */
	public function getLastUpdate($siteId)
	{
		global $langs;
		$sql = "SELECT MAX(t.last_update) as lastdate FROM " . MAIN_DB_PREFIX . "kreawoo_product as t";
		$sql .= " WHERE t.fk_site = " . $siteId;
		dol_syslog(get_class($this) . "::getLastUpdate sql=" . $sql, LOG_DEBUG);

		$lastdate = null;

		$resql = $this->db->query($sql);
		if ($resql) {
			if ($this->db->num_rows($resql)) {
				$obj = $this->db->fetch_object($resql);
				if ($obj->lastdate != null)
					$lastdate = $this->db->jdate($obj->lastdate);
			}
			$this->db->free($resql);
		} else {
			$this->error = "Error " . $this->db->lasterror();
			dol_syslog(get_class($this) . "::getLastUpdate " . $this->error, LOG_ERR);
		}
		return $lastdate;
	}

	/**
	 *    Load object in memory from database by remote_id
	 *
	 *    @param	int    $remoteId       string remote_id
	 *    @param	int    $siteId         int fk_site
	 *    @return	int                    <0 if KO, >0 if OK
	 */
	public function fetchByRemoteId($remoteId, $siteId)
	{
		global $langs;
		$sql = "SELECT";
		$sql .= " t.rowid,";
		$sql .= " t.fk_product,";
		$sql .= " t.fk_site,";
		$sql .= " t.remote_id,";
		$sql .= " t.lang,";
		$sql .= " t.last_update,";
		$sql .= " t.last_update_stock,";
		$sql .= " t.other_data";
		$sql .= " FROM " . MAIN_DB_PREFIX . "kreawoo_product as t";
		$sql .= " WHERE t.fk_site = " . $siteId;
		$sql .= " AND (t.remote_id LIKE '" . $this->db->escape($remoteId) . "'";
		$sql .= " OR JSON_SEARCH(t.other_data, 'one', '" . $this->db->escape($remoteId) . "', NULL, '$.translations_ids') IS NOT NULL)";
		dol_syslog(get_class($this) . "::fetchByRemoteId sql=" . $sql, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			if ($num == 1) {
				$obj = $this->db->fetch_object($resql);
				$this->id = $obj->rowid;
				$this->fk_product = $obj->fk_product;
				$this->fk_site = $obj->fk_site;
				$this->remote_id = $obj->remote_id;
				$this->lang = $obj->lang;
				$this->last_update = $obj->last_update;
				$this->last_update_stock = $obj->last_update_stock;
				$this->other_data = !empty($obj->other_data) ? json_decode($obj->other_data, true) : [];
				$this->db->free($resql);
				return 1;
			} elseif ($num > 1) {
				$ids = array();
				while ($obj = $this->db->fetch_object($resql)) {
					$ids[] = $obj->fk_product;
				}
				$langs->load('kreawoo@kreawoo');
				$this->error = $langs->trans('kreawooErrorTooManyProductLinkToTheRemoteID', implode(', ', $ids), $remoteId, $siteId);
				dol_syslog(get_class($this) . "::fetchByRemoteId " . $this->error, LOG_ERR);
			}
			$this->db->free($resql);
			return -1;
		} else {
			$this->error = "Error " . $this->db->lasterror();
			dol_syslog(get_class($this) . "::fetchByRemoteId " . $this->error, LOG_ERR);
			return -1;
		}
	}

	/**
	 *    Load object in memory from database by remote_id
	 *
	 *    @param	int    $productId  Product ID
	 *    @param	int    $siteId     Site ID
	 *    @param	bool   $forUpdate  Lock the unique site/product key until the current transaction ends
	 *    @return	int                <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetchByProductId($productId, $siteId, $forUpdate = false)
	{
		global $langs;
		$sql = "SELECT";
		$sql .= " t.rowid,";
		$sql .= " t.fk_product,";
		$sql .= " t.fk_site,";
		$sql .= " t.remote_id,";
		$sql .= " t.lang,";
		$sql .= " t.last_update,";
		$sql .= " t.last_update_stock,";
		$sql .= " t.other_data";
		$sql .= " FROM " . MAIN_DB_PREFIX . "kreawoo_product as t";
		$sql .= " WHERE t.fk_site = " . ((int) $siteId);
		$sql .= " AND t.fk_product = " . ((int) $productId);
		if ($forUpdate) $sql .= " FOR UPDATE";
		dol_syslog(get_class($this) . "::fetchByProductId sql=" . $sql, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql) {
			if ($this->db->num_rows($resql) == 1) {
				$obj = $this->db->fetch_object($resql);
				$this->id = $obj->rowid;
				$this->fk_product = $obj->fk_product;
				$this->fk_site = $obj->fk_site;
				$this->remote_id = $obj->remote_id;
				$this->lang = $obj->lang;
				$this->last_update = $obj->last_update;
				$this->last_update_stock = $obj->last_update_stock;
				$this->other_data = !empty($obj->other_data) ? json_decode($obj->other_data, true) : [];
				$this->db->free($resql);
				return 1;
			}
			$this->db->free($resql);
			return 0;
		} else {
			$this->error = "Error " . $this->db->lasterror();
			dol_syslog(get_class($this) . "::fetchByProductId " . $this->error, LOG_ERR);
			return -1;
		}
	}

	/**
	 * 		Select all the ids from kreawooProduct for a site
	 *
	 * 		@param int		$siteId    Id site
	 * 		@return array	           synchObject ids for this site
	 */
	public function getAllkreawooProductIds($siteId)
	{
		global $langs;
		$sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "kreawoo_product";
		$sql .= " WHERE fk_site = " . $siteId;
		dol_syslog(get_class($this) . "::getAllkreawooProductIds sql=" . $sql, LOG_DEBUG);
		$resql = $this->db->query($sql);

		if ($resql) {
			$idsArray = array();
			$num = $this->db->num_rows($resql);
			$ii = 0;
			while ($ii < $num) {
				$obj = $this->db->fetch_object($resql);
				$idsArray[] = intval($obj->rowid);
				$ii++;
			}
			$this->db->free($resql);
			return $idsArray;
		} else {
			$this->error = "Error " . $this->db->lasterror();
			dol_syslog(get_class($this) . "::getAllkreawooProductIds " . $this->error, LOG_ERR);
			return -1;
		}
	}

	/**
	 * Method to output saved errors
	 *
	 * @param   string      $separator      Separator between each error
	 * @return	string		                String with errors
	 */
	public function errorsToString($separator = ', ')
	{
		return $this->error . (is_array($this->errors) ? (!empty($this->error) ? $separator : '') . join($separator, $this->errors) : '');
	}
}
