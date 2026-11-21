<?php
/* Copyright (C) 2026      Kreativität Works             <mail@kreativitat.com>
 * Copyright (C) 2026  Kreativitat  <mail@kreativitat.com>
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
 */

dol_include_once('/kreawoo/class/business/kreawooSynchro.class.php');
dol_include_once("/kreawoo/lib/kreawoo.lib.php");

/**
 *  \file       htdocs/kreawoo/class/actions_kreawoo.class.php
 *  \ingroup    kreawoo
 *  \brief      File for hooks
 */

class ActionsKreaWoo
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error
	 */
	public $error = '';

	/**
	 * @var array Errors
	 */
	public $errors = array();

	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var bool  If the join table for remote id is added to SQL request or not ?
	 */
	public static $join_table_remote_id_set = false;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param	array 			$parameters		Hook metadatas (context, etc...)
	 * @param	CommonObject 	$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action			Current action (if set). Generally create or edit or null
	 * @param	HookManager		$hookmanager	Hook manager propagated to allow calling another hook
	 * @return	int								< 0 on error, 0 on success, 1 to replace standard code
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;
		$langs->load('kreawoo@kreawoo');

		$contexts = explode(':', $parameters['context']);
		$canManageKreawoo = $this->canManageKreawoo($user);
		dol_syslog(
			__METHOD__ . " context=" . $parameters['context']
			. " action=" . $action
			. " element=" . (isset($object->element) ? $object->element : '')
			. " id=" . ((int) (isset($object->id) ? $object->id : 0))
			. " can_manage=" . ($canManageKreawoo ? '1' : '0'),
			LOG_DEBUG
		);

		if (in_array('productdocuments', $contexts) && $action == 'synchronize_images') {
			if ($this->isImageSync($object) && $this->isProductLinkedTokreawoo($object)) {
				$result = $object->call_trigger('PRODUCT_MODIFY', $user);
				if ($result < 0) {
					if (count($object->errors)) setEventMessages($object->error, $object->errors, 'errors');
					else setEventMessages($langs->trans($object->error), null, 'errors');
				} else {
					setEventMessage($langs->trans('kreawooProductImagesSynchronized'));
				}
			}
		} elseif (in_array('productcard', $contexts) && $this->isNativeDolibarrProductCardPage()) {
			$confirm = GETPOST('confirm', 'alpha');

			if ($action == 'update' && $canManageKreawoo && GETPOST('kreawoo_site_data_form', 'int')) {
				$saveResult = $this->saveProductSiteDataFromProductCard($object);
				if ($saveResult < 0) {
					setEventMessages($this->error, $this->errors, 'errors');
				} elseif ($saveResult > 0) {
						// Force one outgoing site metadata sync after manual card edition, even if default directions are e-commerce -> Dolibarr.
					$object->context['kreawoo_force_product_site_data_sync'] = 1;
				}
			}

			if ($action == 'confirm_unlink_product_to_ecommerce' && $confirm == 'yes' && $canManageKreawoo && $this->isProductLinkedTokreawoo($object)) {
				$site_id = GETPOST('siteid', 'int');
				$error = 0;
				$object->db->begin();

				// Delete link to ecommerce
				$kreawooProduct = new kreawooProduct($object->db);
				if ($kreawooProduct->fetchByProductId($object->id, $site_id) > 0) {
					if ($kreawooProduct->delete($user) < 0) {
						setEventMessages($kreawooProduct->error, $kreawooProduct->errors, 'errors');
						$error++;
					}
				}

				// Delete all categories of the ecommerce
				if (!$error && empty($conf->global->ECOMMERCE_DONT_UNSET_CATEGORIE_OF_PRODUCT_WHEN_DELINK)) {
					$kreawooSite = new kreawooSite($object->db);
					if ($kreawooSite->fetch($site_id) > 0) {
						require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
						$cat = new Categorie($object->db);
						$cat_root = $kreawooSite->fk_cat_product;
						$all_cat_full_arbo = $cat->get_full_arbo('product');
						$cats_full_arbo = array();
						foreach ($all_cat_full_arbo as $category) {
							$cats_full_arbo[$category['id']] = $category['fullpath'];
						}
						$categories = $cat->containing($object->id, 'product', 'id');
						foreach ($categories as $cat_id) {
							if (isset($cats_full_arbo[$cat_id]) &&
								(preg_match("/^{$cat_root}$/", $cats_full_arbo[$cat_id]) || preg_match("/^{$cat_root}_/", $cats_full_arbo[$cat_id]) ||
									preg_match("/_{$cat_root}_/", $cats_full_arbo[$cat_id]) || preg_match("/_{$cat_root}$/", $cats_full_arbo[$cat_id])
								)
							) {
								if ($cat->fetch($cat_id) > 0) {
									if ($cat->del_type($object, 'product') < 0) {
										setEventMessages($cat->error, $cat->errors, 'errors');
										$error++;
									}
								}
							}
						}
					}
				}

				$action = '';

				if ($error) {
					$object->db->rollback();
					return -1;
				} else {
					$object->db->commit();
				}
			}
		} elseif (in_array('thirdpartycard', $contexts)) {
			$confirm = GETPOST('confirm', 'alpha');

			if ($action == 'confirm_update_company_from_ecommerce' && $confirm == 'yes' && $canManageKreawoo && $this->isCompanyLinkedTokreawoo($object)) {
				$site_id = GETPOST('siteid', 'int');
				$error = 0;
				$object->db->begin();

				dol_include_once('/kreawoo/class/data/kreawooSite.class.php');
				$siteDb = new kreawooSite($object->db);
				$result = $siteDb->fetch($site_id);
				if ($result == 0) {
					setEventMessage($langs->trans('EcommerceSiteNotFound', $site_id), 'errors');
					$error++;
				} elseif ($result < 0) {
					setEventMessage($langs->trans('EcommerceSiteNotFound', $site_id) . ' : ' . $siteDb->error, 'errors');
					$error++;
				}

				if (!$error) {
					dol_include_once('/kreawoo/class/business/kreawooSynchro.class.php');
					$synchro = new kreawooSynchro($object->db, $siteDb, 0, 0);
					$synchro->connect();
					if (count($synchro->errors)) {
						setEventMessages($synchro->error, $synchro->errors, 'errors');
						$error++;
					}
				}

				if (!$error) {
					$kreawooSociete = new kreawooSociete($object->db);
					$links_list = $kreawooSociete->getAllLinksByFkSociete($object->id, $siteDb->id);
					if (!is_array($links_list)) {
						setEventMessages($kreawooSociete->error, $kreawooSociete->errors, 'errors');
						$error++;
					}

					if (!$error) {
						foreach ($links_list as $link) {
							$result = $synchro->synchronizeCustomers(null, null, array($link->remote_id), 1, false, false, true);
							if ($result <= 0) {
								setEventMessages($synchro->error, $synchro->errors, 'errors');
								$error++;
								break;
							}
						}
					}
				}

				$action = '';

				if ($error) {
					$object->db->rollback();
					return -1;
				} else {
					setEventMessage($langs->trans('EcommerceUpdateCompanyFromkreawooSuccess'));
					$object->db->commit();
				}
			}
		} elseif (in_array('invoicelist', $contexts)) {
			// remove filters
			if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
				$_GET['search_ec_total_delta'] = '';
				$_POST['search_ec_total_delta'] = '';
			}
		}

		return 0; // or return 1 to replace standard code
	}

	/**
	 * Overloading the addMoreActionsButtons function : replacing the parent's function with the one below
	 *
	 * @param	array 			$parameters		Hook metadatas (context, etc...)
	 * @param	CommonObject 	$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action			Current action (if set). Generally create or edit or null
	 * @param	HookManager		$hookmanager	Hook manager propagated to allow calling another hook
	 * @return	int								< 0 on error, 0 on success, 1 to replace standard code
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;
		$langs->load('kreawoo@kreawoo');

		$contexts = explode(':', $parameters['context']);
		$canManageKreawoo = $this->canManageKreawoo($user);
		dol_syslog(
			__METHOD__ . " context=" . $parameters['context']
			. " action=" . $action
			. " element=" . (isset($object->element) ? $object->element : '')
			. " id=" . ((int) (isset($object->id) ? $object->id : 0))
			. " can_manage=" . ($canManageKreawoo ? '1' : '0'),
			LOG_DEBUG
		);

		if (in_array('productcard', $contexts) && $this->isNativeDolibarrProductCardPage()) {
			if ($canManageKreawoo && $action !== 'edit') {
				// Site list
				$sites = $this->getProductLinkedSite($object);
				dol_syslog(__METHOD__ . " productcard linked_sites_count=" . count($sites) . " product_id=" . ((int) $object->id), LOG_DEBUG);
				if (count($sites) > 0) {
					if ($action == 'unlink_product_to_ecommerce') {
						$sites_array = array();
						foreach ($sites as $site) {
							$sites_array[$site->id] = $site->name;
						}

						require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
						$form = new Form($object->db);

						// Define confirmation messages
						$formquestionclone = array(
							array('type' => 'select', 'name' => 'siteid', 'label' => $langs->trans("kreawooSite"), 'values' => $sites_array),
						);
						print $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('kreawooUnlinkProductFromSite'), $langs->trans('kreawooConfirmUnlinkProductFromSite', $object->ref), 'confirm_unlink_product_to_ecommerce', $formquestionclone, 'yes', 1, 250, 600);
					}

					$site_ids = array_keys($sites);
					$params = count($sites) > 1 ? '&action=unlink_product_to_ecommerce' : '&action=confirm_unlink_product_to_ecommerce&confirm=yes&siteid=' . $site_ids[0];
					$button = '<div id="kreawoo-product-unlink-action" class="inline-block divButAction"><a class="butAction" href="'
						. $_SERVER["PHP_SELF"] . '?id=' . $object->id . $params . '&token=' . kreawooNewToken() . '">'
						. $langs->trans("kreawooUnlinkProductFromSite")
						. '</a></div>';
					$this->printButtonInTabsAction($button, 'kreawoo-product-unlink-action');
					dol_syslog(__METHOD__ . " productcard rendered_unlink_in_tabsaction=1 product_id=" . ((int) $object->id), LOG_DEBUG);
				}
			}
		} elseif (in_array('thirdpartycard', $contexts)) {
			if ($canManageKreawoo) {
				// Site list
				$sites = $this->getCompanyLinkedSite($object);
				if (count($sites) > 0) {
					if ($action == 'update_company_from_ecommerce') {
						$sites_array = array();
						foreach ($sites as $site) {
							$sites_array[$site->id] = $site->name;
						}

						require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
						$form = new Form($object->db);

						// Define confirmation messages
						$formquestionclone = array(
							array('type' => 'select', 'name' => 'siteid', 'label' => $langs->trans("kreawooSite"), 'values' => $sites_array),
						);
						print $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('EcommerceUpdateCompanyFromkreawoo'), $langs->trans('EcommerceConfirmUpdateCompanyFromkreawoo', $object->ref), 'confirm_update_company_from_ecommerce', $formquestionclone, 'yes', 1, 250, 600);
					}

					$site_ids = array_keys($sites);
					$params = count($sites) > 1 ? '&action=update_company_from_ecommerce' : '&action=confirm_update_company_from_ecommerce&confirm=yes&siteid=' . $site_ids[0];
					print '<div class="inline-block divButAction"><a class="butAction" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . $params . '&token=' . kreawooNewToken() . '">' . $langs->trans("EcommerceUpdateCompanyFromkreawoo") . '</a></div>';
				}
			}
		}

		return 0; // or return 1 to replace standard code
	}

	/**
	 * Overloading the formObjectOptions function : replacing the parent's function with the one below
	 *
	 * @param	array 			$parameters		Hook metadatas (context, etc...)
	 * @param	CommonObject 	$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action			Current action (if set). Generally create or edit or null
	 * @param	HookManager		$hookmanager	Hook manager propagated to allow calling another hook
	 * @return	int								< 0 on error, 0 on success, 1 to replace standard code
	 */
	public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs, $user;
		$langs->load('kreawoo@kreawoo');

		$contexts = explode(':', $parameters['context']);
		if (in_array('productcard', $contexts) && !$this->isNativeDolibarrProductCardPage()) {
			return 0;
		}

		if (in_array('productdocuments', $contexts)) {
			if ($this->isImageSync($object) && $this->isProductLinkedTokreawoo($object)) {
				$buttons = '<div class="tabsAction">';
				$buttons .= '<div class="inline-block divButAction"><a class="butAction" href="' . $_SERVER["PHP_SELF"] . '?action=synchronize_images&amp;id=' . $object->id . '&token=' . kreawooNewToken() . '">' . $langs->trans("kreawooSynchronizeProductImages") . '</a></div>';
				$buttons .= '</div>';

				print '<script type="text/javascript" language="javascript">';
				print '$(document).ready(function () {';
				print '$(\'div.fichecenter\').append("' . str_replace('"', '\\"', $buttons) . '");';
				print '});';
				print '</script>';
			}
		} elseif (!empty(array_intersect(['categorycard', 'productcard', 'thirdpartycard', 'contactcard'], $contexts))) {
			if (!in_array($action, ['create']) && !empty($object->id)) {
				if ($object->element == 'category') {
					$table_nme = 'category';
					$field_name = "fk_category";
				} elseif ($object->element == 'product') {
					$links_to_print = $this->getProductLinksToPrint($object);
					dol_syslog(__METHOD__ . " productcard recovered_links_count=" . count($links_to_print) . " product_id=" . ((int) $object->id), LOG_DEBUG);

					if (!empty($links_to_print) && !($action === 'edit' && $this->canManageKreawoo($user))) {
						print '<tr><td class="titlefield">' . $langs->trans("kreawooLinks") . '</td>';
						print '<td>';
						print implode('<br>', $links_to_print);
						print '</td></tr>';
						dol_syslog(__METHOD__ . " productcard rendered_links_row=1 action=" . $action . " product_id=" . ((int) $object->id), LOG_DEBUG);
					}

					if ($action === 'edit' && $this->canManageKreawoo($user)) {
						$siteFieldsRendered = $this->renderProductSiteDataRows($object, 'edit');
						if ($siteFieldsRendered > 0) {
							dol_syslog(__METHOD__ . " productcard rendered_site_data_rows=" . $siteFieldsRendered . " action=" . $action . " product_id=" . ((int) $object->id), LOG_DEBUG);
						}
						if (!$this->shouldShowProductCardManualPriceVatFields()) {
							$this->hideProductCardManualPriceVatRows();
						}
					} else {
						$this->renderProductSiteStatusRows($object);
						$this->hideProductCardRowsInViewMode();
						if (!$this->shouldShowProductCardManualPriceVatFields()) {
							$this->hideProductCardManualPriceVatRows();
						}
					}

					return 0;
				} elseif ($object->element == 'societe') {
					$table_nme = 'societe';
					$field_name = "fk_societe";
				} else {
					$table_nme = 'socpeople';
					$field_name = "fk_socpeople";
				}

				$sql = "SELECT s.name, l.remote_id";
				$sql .= " FROM " . MAIN_DB_PREFIX . "kreawoo_" . $table_nme . " AS l";
				$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "kreawoo_site AS s ON s.rowid = l.fk_site";
				$sql .= " WHERE l." . $field_name . " = " . ((int) $object->id);
				$sql .= " AND s.entity IN (" . getEntity('kreawoo') . ")";

				$resql = $this->db->query($sql);
				if (!$resql) {
					setEventMessages($this->db->lasterror(), null, 'errors');
				} else {
					$links_to_print = [];

					while ($obj = $this->db->fetch_object($resql)) {
						$links_to_print[] = $obj->name . " : " . $obj->remote_id;
					}
					dol_syslog(__METHOD__ . " loaded " . count($links_to_print) . " Kreawoo links for " . $object->element . " #" . ((int) $object->id), LOG_DEBUG);

					if (!empty($links_to_print)) {
						print '<tr><td class="titlefield">' . $langs->trans("kreawooLinks") . '</td>';
						print '<td>';
						print implode('<br>', $links_to_print);
						print '</td></tr>';
					}
				}
			}
		}

		return 0; // or return 1 to replace standard code
	}

	/**
	 * Overloading the afterODTCreation function : replacing the parent's function with the one below
	 *
	 * @param	array 			$parameters		Hook metadatas (context, etc...)
	 * @param	CommonObject 	$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action			Current action (if set). Generally create or edit or null
	 * @param	HookManager		$hookmanager	Hook manager propagated to allow calling another hook
	 * @return	int								< 0 on error, 0 on success, 1 to replace standard code
	 */
	public function afterODTCreation($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs, $db;

		$contexts = explode(':', $parameters['context']);

		if ((in_array('expeditioncard', $contexts) && ($action == 'confirm_valid' || $action == 'builddoc')) ||
			(in_array('invoicecard', $contexts) && ($action == 'confirm_valid' || $action == 'builddoc'))
		) {
			if (!empty($conf->global->KREAWOO_ENABLE_SEND_FILE_TO_ORDER)) {
				$commande_id = 0;
				$object_src = $parameters['object'];
				$object_src->fetchObjectLinked('', 'commande', $object_src->id, $object_src->element);
				if (!empty($object_src->linkedObjects)) {
					foreach ($object_src->linkedObjects['commande'] as $element) {
						$commande_id = $element->id;
					}
				}

				if ($commande_id > 0) {
					$db->begin();

					$error = 0; // Error counter
					$kreawooSite = new kreawooSite($db);
					$sites = $kreawooSite->listSites('object');

					foreach ($sites as $site) {
						if (!$error) {
							$kreawooCommande = new kreawooCommande($db);
							$kreawooCommande->fetchByCommandeId($commande_id, $site->id); // TODO $kreawooCommande->remote_societe_id a rajouter a la table

							if ($kreawooCommande->remote_id > 0) {
								$kreawooSynchro = new kreawooSynchro($db, $site);
								dol_syslog("Hook ActionsKreaWoo::afterPDFCreation try to connect to kreawoo site " . $site->name);
								$kreawooSynchro->connect();
								if (count($kreawooSynchro->errors)) {
									$error++;
									setEventMessages($kreawooSynchro->error, $kreawooSynchro->errors, 'errors');
								}

								if (!$error) {
									$result = $kreawooSynchro->kreawooRemoteAccess->sendFileForCommande($kreawooCommande->remote_id, $object_src, $parameters['file'], $parameters['outputlangs']);
									if (!$result) {
										$error++;
										$this->errors[] = $kreawooSynchro->kreawooRemoteAccess->error;
									}
								}
							} else {
								dol_syslog("Order with id " . $commande_id . " is not linked to an ecommerce record so we don't send file to it.");
							}
						}
					}

					if ($error) {
						$db->rollback();
						return -1;
					} else {
						$db->commit();
						return 0;
					}
				}
			}
		}

		return 0; // or return 1 to replace standard code
	}

	/**
	 * Overloading the afterPDFCreation function : replacing the parent's function with the one below
	 *
	 * @param	array 			$parameters		Hook metadatas (context, etc...)
	 * @param	CommonObject 	$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action			Current action (if set). Generally create or edit or null
	 * @param	HookManager		$hookmanager	Hook manager propagated to allow calling another hook
	 * @return	int								< 0 on error, 0 on success, 1 to replace standard code
	 */
	public function afterPDFCreation($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs, $db;

		$contexts = explode(':', $parameters['context']);

		if ((in_array('expeditioncard', $contexts) && ($action == 'confirm_valid' || $action == 'builddoc')) ||
			(in_array('invoicecard', $contexts) && ($action == 'confirm_valid' || $action == 'builddoc'))
		) {
			if (!empty($conf->global->KREAWOO_ENABLE_SEND_FILE_TO_ORDER)) {
				$commande_id = 0;
				$object_src = $parameters['object'];
				$object_src->fetchObjectLinked('', 'commande', $object_src->id, $object_src->element);
				if (!empty($object_src->linkedObjects)) {
					foreach ($object_src->linkedObjects['commande'] as $element) {
						$commande_id = $element->id;
					}
				}

				if ($commande_id > 0) {
					$db->begin();

					$error = 0; // Error counter
					$kreawooSite = new kreawooSite($db);
					$sites = $kreawooSite->listSites('object');

					foreach ($sites as $site) {
						if (!$error) {
							$kreawooCommande = new kreawooCommande($db);
							$kreawooCommande->fetchByCommandeId($commande_id, $site->id); // TODO $kreawooCommande->remote_societe_id a rajouter a la table

							if ($kreawooCommande->remote_id > 0) {
								$kreawooSynchro = new kreawooSynchro($db, $site);
								dol_syslog("Hook ActionsKreaWoo::afterPDFCreation try to connect to kreawoo site " . $site->name);
								$kreawooSynchro->connect();
								if (count($kreawooSynchro->errors)) {
									$error++;
									setEventMessages($kreawooSynchro->error, $kreawooSynchro->errors, 'errors');
								}

								if (!$error) {
									$result = $kreawooSynchro->kreawooRemoteAccess->sendFileForCommande($kreawooCommande->remote_id, $object_src, $parameters['file'], $parameters['outputlangs']);
									if (!$result) {
										$error++;
										$this->errors[] = $kreawooSynchro->kreawooRemoteAccess->error;
									}
								}
							} else {
								dol_syslog("Order with id " . $commande_id . " is not linked to an ecommerce record so we don't send file to it.");
							}
						}
					}

					if ($error) {
						$db->rollback();
						return -1;
					} else {
						$db->commit();
						return 0;
					}
				}
			}
		}

		return 0; // or return 1 to replace standard code
	}

	/**
	 * Test if product is linked with a kreawoo
	 *
	 * @param   Product     $object    Product object
	 * @return  bool
	 */
	private function isProductLinkedTokreawoo(&$object)
	{
		$sites = $this->getProductLinkedSite($object);
		return !empty($sites);
	}

	/**
	 * Test if company is linked with a kreawoo
	 *
	 * @param   Societe     $object    Company object
	 * @return  bool
	 */
	private function isCompanyLinkedTokreawoo(&$object)
	{
		dol_include_once('/kreawoo/class/business/kreawooSynchro.class.php');
		dol_include_once('/kreawoo/class/data/kreawooSociete.class.php');
		$isLinkedTokreawoo = false;

		// Get current categories and subcategories
		require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
		$c = new Categorie($object->db);
		$categories = array();
		if (isset($_POST['categories'])) {
			$cats = GETPOST('categories');
		} else {
			$cats = $c->containing($object->id, Categorie::TYPE_CUSTOMER, 'id');
		}
		foreach ($cats as $cat) {
			$c->id = $cat;
			$catslist = $c->get_all_ways();
			if (isset($catslist[0])) {
				foreach ($catslist[0] as $catinfos) {
					$categories[$catinfos->id] = $catinfos->id;
				}
			}
		}

		$kreawooSite = new kreawooSite($object->db);
		$sites = $kreawooSite->listSites('object');
		foreach ($sites as $site) {
			if (in_array($site->fk_cat_societe, $categories)) {
				$kreawooSociete = new kreawooSociete($object->db);
				$links_list = $kreawooSociete->getAllLinksByFkSociete($object->id, $site->id);
				if (is_array($links_list)) {
					foreach ($links_list as $link) {
						if (!empty($link->remote_id)) {
							$isLinkedTokreawoo = true;
							break;
						}
					}
				}
			}
		}

		return $isLinkedTokreawoo;
	}

	/**
	 * Get kreawoo linked to the product
	 *
	 * @param   Product     $object    Product object
	 * @return  array
	 */
	private function getProductLinkedSite(&$object)
	{
		dol_include_once('/kreawoo/class/business/kreawooSynchro.class.php');
		$linkedTokreawoo = array();

		$kreawooSite = new kreawooSite($object->db);
		$sites = $kreawooSite->listSites('object');
		dol_syslog(__METHOD__ . " candidate_sites_count=" . count($sites) . " product_id=" . ((int) $object->id) . " scope=current", LOG_DEBUG);
		$this->appendProductLinkedSitesForScope((int) $object->id, $sites, $linkedTokreawoo, 'current');

		// Fallback for shared/global-site setups where configured site entity differs from current entity.
		if (empty($linkedTokreawoo)) {
			$allSites = $kreawooSite->listSites('object', true);
			dol_syslog(__METHOD__ . " candidate_sites_count=" . count($allSites) . " product_id=" . ((int) $object->id) . " scope=all_entities", LOG_DEBUG);
			$this->appendProductLinkedSitesForScope((int) $object->id, $allSites, $linkedTokreawoo, 'all_entities');
		}

		// Last-resort fallback: render link actions from existing mapping rows even if site row is orphan/moved.
		if (empty($linkedTokreawoo)) {
			$rawLinks = $this->getRawProductRemoteLinks((int) $object->id);
			foreach ($rawLinks as $rawLink) {
				if (!isset($linkedTokreawoo[$rawLink['fk_site']])) {
					$site = new stdClass();
					$site->id = (int) $rawLink['fk_site'];
					$site->name = $rawLink['site_name'];
					$linkedTokreawoo[$site->id] = $site;
				}
			}
			dol_syslog(__METHOD__ . " raw_fallback_sites_count=" . count($linkedTokreawoo) . " product_id=" . ((int) $object->id), LOG_DEBUG);
		}
		dol_syslog(__METHOD__ . " linked " . count($linkedTokreawoo) . " site(s) for product #" . ((int) $object->id), LOG_DEBUG);

		return $linkedTokreawoo;
	}

	/**
	 * Append linked sites for a given candidate site scope.
	 *
	 * @param int      $productId
	 * @param array    $sites
	 * @param array    $linkedTokreawoo
	 * @param string   $scope
	 * @return void
	 */
	private function appendProductLinkedSitesForScope($productId, $sites, &$linkedTokreawoo, $scope)
	{
		$productId = (int) $productId;
		foreach ($sites as $site) {
			if (empty($site) || empty($site->id) || isset($linkedTokreawoo[$site->id])) {
				continue;
			}

			$kreawooProduct = new kreawooProduct($this->db);
			$fetchResult = $kreawooProduct->fetchByProductId($productId, (int) $site->id);

			dol_syslog(
				__METHOD__ . " scope=" . $scope
				. " site_id=" . ((int) $site->id)
				. " fetch_result=" . ((int) $fetchResult)
				. " remote_id=" . (!empty($kreawooProduct->remote_id) ? $kreawooProduct->remote_id : ''),
				LOG_DEBUG
			);

			if ($fetchResult > 0 && !empty($kreawooProduct->remote_id)) {
				$linkedTokreawoo[$site->id] = $site;
			}
		}
	}

	/**
	 * Resolve printable product links from site mappings, with raw mapping fallback.
	 *
	 * @param Product $object Product object
	 * @return array
	 */
	private function getProductLinksToPrint(&$object)
	{
		$linksToPrint = array();
		$sites = $this->getProductLinkedSite($object);
		foreach ($sites as $site) {
			$kreawooProduct = new kreawooProduct($object->db);
			if ($kreawooProduct->fetchByProductId($object->id, $site->id) > 0 && !empty($kreawooProduct->remote_id)) {
				$linksToPrint[] = $site->name . " : " . $kreawooProduct->remote_id;
			}
		}

		if (!empty($linksToPrint)) {
			return $linksToPrint;
		}

		$rawLinks = $this->getRawProductRemoteLinks((int) $object->id);
		foreach ($rawLinks as $rawLink) {
			$linksToPrint[] = $rawLink['site_name'] . " : " . $rawLink['remote_id'];
		}
		dol_syslog(__METHOD__ . " raw_fallback_links_count=" . count($linksToPrint) . " product_id=" . ((int) $object->id), LOG_DEBUG);

		return $linksToPrint;
	}

	/**
	 * Read raw product mapping rows without entity restriction to recover orphan/shared links.
	 *
	 * @param int $productId
	 * @return array
	 */
	private function getRawProductRemoteLinks($productId)
	{
		$productId = (int) $productId;
		$links = array();
		if ($productId <= 0) {
			return $links;
		}

		$sql = "SELECT l.fk_site, l.remote_id, s.name";
		$sql .= " FROM " . MAIN_DB_PREFIX . "kreawoo_product AS l";
		$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "kreawoo_site AS s ON s.rowid = l.fk_site";
		$sql .= " WHERE l.fk_product = " . $productId;
		$sql .= " AND l.remote_id IS NOT NULL AND l.remote_id <> ''";
		$sql .= " ORDER BY s.name ASC, l.rowid ASC";

		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__ . " unable to read raw product links: " . $this->db->lasterror(), LOG_WARNING);
			return $links;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$links[] = array(
				'fk_site' => (int) $obj->fk_site,
				'remote_id' => $obj->remote_id,
				'site_name' => !empty($obj->name) ? $obj->name : ('Site #' . ((int) $obj->fk_site)),
			);
		}
		$this->db->free($resql);

		return $links;
	}

	/**
	 * List sites used for product-card site metadata rendering.
	 *
	 * @return array
	 */
	private function getConfiguredProductSitesForCard()
	{
		$kreawooSite = new kreawooSite($this->db);
		$sites = $kreawooSite->listSites('object');
		if (!empty($sites)) {
			return $sites;
		}

		// Fallback for shared setups where current entity does not directly own the site row.
		return $kreawooSite->listSites('object', true);
	}

	/**
	 * Render read-only product statuses for sites available to the current entity.
	 *
	 * @param Product $object Product being viewed
	 * @return void
	 */
	private function renderProductSiteStatusRows(&$object)
	{
		global $conf, $langs;

		$kreawooSite = new kreawooSite($this->db);
		$sites = $kreawooSite->listSites('object');
		uasort($sites, function ($a, $b) {
			return strcasecmp((string) $a->name, (string) $b->name);
		});

		foreach ($sites as $site) {
			$siteId = (int) $site->id;
			if ($siteId <= 0) {
				continue;
			}

			$siteData = kreawooGetProductSiteData($this->db, (int) $object->id, $siteId, (int) $conf->entity);
			if (empty($siteData) && !empty($site->entity) && (int) $site->entity !== (int) $conf->entity) {
				$siteData = kreawooGetProductSiteData($this->db, (int) $object->id, $siteId, (int) $site->entity);
			}
			$status = isset($siteData['wc_status']) ? (string) $siteData['wc_status'] : '';
			$statusOptions = $this->buildProductStatusOptionsForSite($site, $status);
			$statusLabel = isset($statusOptions[$status]) ? $statusOptions[$status] : $status;

			print '<tr><td class="titlefield">' . dol_escape_htmltag($langs->trans('kreawoongWoocommerceStatus', $site->name)) . '</td>';
			print '<td>' . dol_escape_htmltag($statusLabel) . '</td></tr>';
		}
	}

	/**
	 * Render product-site metadata rows.
	 *
	 * @param Product $object
	 * @param string  $action
	 * @return int Number of rendered rows
	 */
	private function renderProductSiteDataRows(&$object, $action)
	{
		global $conf, $langs;

		if (empty($object->id)) {
			return 0;
		}

		$sites = $this->getConfiguredProductSitesForCard();
		if (empty($sites)) {
			return 0;
		}
		uasort($sites, function ($a, $b) {
			return strcasecmp((string) $a->name, (string) $b->name);
		});

		$isEdit = ($action === 'edit');
		$showManualPriceVatFields = $this->shouldShowProductCardManualPriceVatFields();
		$defaults = kreawooGetProductSiteDataDefaults();
		$siteDataBySite = array();
		$rawRemoteLinks = $this->getRawProductRemoteLinks((int) $object->id);
		$rawRemoteIdBySite = array();
		foreach ($rawRemoteLinks as $rawLink) {
			if (!empty($rawLink['fk_site']) && !isset($rawRemoteIdBySite[(int) $rawLink['fk_site']])) {
				$rawRemoteIdBySite[(int) $rawLink['fk_site']] = (string) $rawLink['remote_id'];
			}
		}

		foreach ($sites as $site) {
			$siteId = (int) $site->id;
			if ($siteId <= 0) {
				continue;
			}

			$row = kreawooGetProductSiteData($this->db, (int) $object->id, $siteId, (int) $conf->entity);
			if (empty($row) && isset($site->entity) && ((int) $site->entity) > 0 && ((int) $site->entity) !== ((int) $conf->entity)) {
				$row = kreawooGetProductSiteData($this->db, (int) $object->id, $siteId, (int) $site->entity);
			}
			if (!is_array($row)) {
				$row = array();
			}
			$siteDataBySite[$siteId] = array_merge($defaults, $row);
		}

		if (empty($siteDataBySite)) {
			return 0;
		}

		$rowsRendered = 0;
		$formMarkerPrinted = false;

		foreach ($sites as $site) {
			$siteId = (int) $site->id;
			if ($siteId <= 0 || !isset($siteDataBySite[$siteId])) {
				continue;
			}

			$siteData = $siteDataBySite[$siteId];
			$statusOptions = $this->buildProductStatusOptionsForSite($site, $siteData['wc_status']);
			$statusLabel = isset($statusOptions[$siteData['wc_status']]) ? $statusOptions[$siteData['wc_status']] : $siteData['wc_status'];
			$stockStatusOptions = $this->buildProductStockStatusOptions();
			$stockStatusLabel = isset($stockStatusOptions[$siteData['wc_stock_status']]) ? $stockStatusOptions[$siteData['wc_stock_status']] : $siteData['wc_stock_status'];
			$taxClassOptions = array();
			$taxClassLabel = '';
			if ($showManualPriceVatFields) {
				$taxClassOptions = $this->loadTaxClassOptionsForSite($siteId, $siteData['tax_class']);
				$taxClassLabel = isset($taxClassOptions[$siteData['tax_class']]) ? $taxClassOptions[$siteData['tax_class']] : $siteData['tax_class'];
			}
			$dateFrom = (string) $siteData['wc_date_on_sale_from'];
			$dateTo = (string) $siteData['wc_date_on_sale_to'];
			$dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}/', $dateFrom) ? substr($dateFrom, 0, 10) : '';
			$dateTo = preg_match('/^\d{4}-\d{2}-\d{2}/', $dateTo) ? substr($dateTo, 0, 10) : '';
			$remoteId = '';
			$kreawooProduct = new kreawooProduct($this->db);
			if ($kreawooProduct->fetchByProductId((int) $object->id, $siteId) > 0 && !empty($kreawooProduct->remote_id)) {
				$remoteId = (string) $kreawooProduct->remote_id;
			} elseif (isset($rawRemoteIdBySite[$siteId])) {
				$remoteId = $rawRemoteIdBySite[$siteId];
			}

			print '<tr class="liste_titre" style="background: transparent !important;"><td colspan="2" style="background: transparent !important;">KreaWoo - ' . dol_escape_htmltag((string) $site->name) . '</td></tr>';
			$rowsRendered++;

			print '<tr><td class="titlefield">' . $langs->trans('kreawoongWoocommerceStatus', $site->name) . '</td><td>';
			if ($isEdit) {
				print '<select class="flat minwidth200" name="kreawoo_wc_status_' . $siteId . '">';
				foreach ($statusOptions as $statusValue => $statusText) {
					$selected = ((string) $siteData['wc_status'] === (string) $statusValue) ? ' selected' : '';
					print '<option value="' . dol_escape_htmltag($statusValue) . '"' . $selected . '>' . dol_escape_htmltag($statusText) . '</option>';
				}
				print '</select>';
			} else {
				print dol_escape_htmltag((string) $statusLabel);
			}
			print '</td></tr>';
			$rowsRendered++;

			if ($showManualPriceVatFields) {
				print '<tr><td class="titlefield">' . $langs->trans('kreawoongWoocommerceTaxClass', $site->name) . '</td><td>';
				if ($isEdit) {
					print '<select class="flat minwidth300" name="kreawoo_tax_class_' . $siteId . '">';
					foreach ($taxClassOptions as $taxValue => $taxText) {
						$selected = ((string) $siteData['tax_class'] === (string) $taxValue) ? ' selected' : '';
						print '<option value="' . dol_escape_htmltag($taxValue) . '"' . $selected . '>' . dol_escape_htmltag($taxText) . '</option>';
					}
					print '</select>';
				} else {
					print dol_escape_htmltag((string) $taxClassLabel);
				}
				print '</td></tr>';
				$rowsRendered++;

				print '<tr><td class="titlefield">' . $langs->trans('kreawoongWoocommerceRegularPrice', $site->name) . '</td><td>';
				if ($isEdit) {
					print '<input type="text" class="flat maxwidth100" name="kreawoo_wc_regular_price_' . $siteId . '" value="' . dol_escape_htmltag((string) $siteData['wc_regular_price']) . '">';
				} else {
					print ($siteData['wc_regular_price'] !== '' && $siteData['wc_regular_price'] !== null) ? price($siteData['wc_regular_price']) : '';
				}
				print '</td></tr>';
				$rowsRendered++;
			}

			print '<tr><td class="titlefield">' . $langs->trans('kreawoongWoocommerceSalePrice', $site->name) . '</td><td>';
			if ($isEdit) {
				print '<input type="text" class="flat maxwidth100" name="kreawoo_wc_sale_price_' . $siteId . '" value="' . dol_escape_htmltag((string) $siteData['wc_sale_price']) . '">';
			} else {
				print ($siteData['wc_sale_price'] !== '' && $siteData['wc_sale_price'] !== null) ? price($siteData['wc_sale_price']) : '';
			}
			print '</td></tr>';
			$rowsRendered++;

			print '<tr><td class="titlefield">' . $langs->trans('kreawoongWoocommerceDateOnSaleFrom', $site->name) . '</td><td>';
			if ($isEdit) {
				print '<input type="date" class="flat maxwidth150" name="kreawoo_wc_date_on_sale_from_' . $siteId . '" value="' . dol_escape_htmltag($dateFrom) . '">';
			} else {
				print dol_escape_htmltag($dateFrom);
			}
			print '</td></tr>';
			$rowsRendered++;

			print '<tr><td class="titlefield">' . $langs->trans('kreawoongWoocommerceDateOnSaleTo', $site->name) . '</td><td>';
			if ($isEdit) {
				print '<input type="date" class="flat maxwidth150" name="kreawoo_wc_date_on_sale_to_' . $siteId . '" value="' . dol_escape_htmltag($dateTo) . '">';
			} else {
				print dol_escape_htmltag($dateTo);
			}
			print '</td></tr>';
			$rowsRendered++;

			print '<tr><td class="titlefield">' . $langs->trans('kreawoongWoocommerceManageStock', $site->name) . '</td><td>';
			if ($isEdit) {
				print '<input type="hidden" name="kreawoo_wc_manage_stock_' . $siteId . '" value="0">';
				print '<input type="checkbox" name="kreawoo_wc_manage_stock_' . $siteId . '" value="1"' . (!empty($siteData['wc_manage_stock']) ? ' checked' : '') . '>';
			} else {
				print $langs->trans(!empty($siteData['wc_manage_stock']) ? 'Yes' : 'No');
			}
			print '</td></tr>';
			$rowsRendered++;

			print '<tr><td class="titlefield">' . $langs->trans('kreawoongWoocommerceStockStatus', $site->name) . '</td><td>';
			if ($isEdit) {
				print '<select class="flat minwidth200" name="kreawoo_wc_stock_status_' . $siteId . '">';
				foreach ($stockStatusOptions as $stockStatusValue => $stockStatusText) {
					$selected = ((string) $siteData['wc_stock_status'] === (string) $stockStatusValue) ? ' selected' : '';
					print '<option value="' . dol_escape_htmltag($stockStatusValue) . '"' . $selected . '>' . dol_escape_htmltag($stockStatusText) . '</option>';
				}
				print '</select>';
			} else {
				print dol_escape_htmltag((string) $stockStatusLabel);
			}
			print '</td></tr>';
			$rowsRendered++;

			print '<tr><td class="titlefield">' . $langs->trans('kreawoongWoocommerceDontUpdateStock', $site->name) . '</td><td>';
			if ($isEdit) {
				print '<input type="hidden" name="kreawoo_wc_dont_update_stock_' . $siteId . '" value="0">';
				print '<input type="checkbox" name="kreawoo_wc_dont_update_stock_' . $siteId . '" value="1"' . (!empty($siteData['wc_dont_update_stock']) ? ' checked' : '') . '>';
			} else {
				print $langs->trans(!empty($siteData['wc_dont_update_stock']) ? 'Yes' : 'No');
			}
			print '</td></tr>';
			$rowsRendered++;

			print '<tr><td class="titlefield">' . $langs->trans("kreawooLinks") . '</td><td>';
			if ($remoteId !== '') {
				print dol_escape_htmltag((string) $site->name . ' : ' . $remoteId);
			}
			print '</td></tr>';
			$rowsRendered++;

			print '<tr><td class="titlefield">' . $langs->trans('kreawoongWoocommerceDescription') . '</td><td>';
			if ($isEdit) {
				if (!$formMarkerPrinted) {
					print '<input type="hidden" name="kreawoo_site_data_form" value="1">';
					$formMarkerPrinted = true;
				}
				print '<textarea class="flat centpercent" rows="3" name="kreawoo_description_' . $siteId . '">' . dol_escape_htmltag((string) $siteData['description']) . '</textarea>';
			} else {
				print nl2br(dol_escape_htmltag((string) $siteData['description']));
			}
			print '</td></tr>';
			$rowsRendered++;

			print '<tr><td class="titlefield">' . $langs->trans('kreawoongWoocommerceShortDescription') . '</td><td>';
			if ($isEdit) {
				print '<textarea class="flat centpercent" rows="2" name="kreawoo_short_description_' . $siteId . '">' . dol_escape_htmltag((string) $siteData['short_description']) . '</textarea>';
			} else {
				print nl2br(dol_escape_htmltag((string) $siteData['short_description']));
			}
			print '</td></tr>';
			$rowsRendered++;
		}

		return $rowsRendered;
	}

	/**
	 * Save product site metadata submitted from product card form.
	 *
	 * @param Product $object
	 * @return int
	 */
	private function saveProductSiteDataFromProductCard(&$object)
	{
		global $conf;

		$productId = (int) $object->id;
		if ($productId <= 0) {
			return 0;
		}

		$sites = $this->getConfiguredProductSitesForCard();
		if (empty($sites)) {
			return 0;
		}

		$stockableProduct = GETPOSTISSET('stockable_product')
			? ((int) !empty(GETPOST('stockable_product', 'int')))
			: (!empty($object->stockable_product) ? 1 : 0);
		$showManualPriceVatFields = $this->shouldShowProductCardManualPriceVatFields();
		$defaults = kreawooGetProductSiteDataDefaults();

		$this->db->begin();
		foreach ($sites as $site) {
			$siteId = (int) $site->id;
			if ($siteId <= 0) {
				continue;
			}

			$targetEntity = !empty($site->entity) ? (int) $site->entity : (int) $conf->entity;
			$currentData = kreawooGetProductSiteData($this->db, $productId, $siteId, $targetEntity);
			if (empty($currentData) && $targetEntity !== (int) $conf->entity) {
				$currentData = kreawooGetProductSiteData($this->db, $productId, $siteId, (int) $conf->entity);
			}
			if (!is_array($currentData)) {
				$currentData = array();
			}
			$currentData = array_merge($defaults, $currentData);
			$taxClassInputName = 'kreawoo_tax_class_' . $siteId;
			$regularPriceInputName = 'kreawoo_wc_regular_price_' . $siteId;

			$data = array(
				'description' => GETPOST('kreawoo_description_' . $siteId, 'none'),
				'short_description' => GETPOST('kreawoo_short_description_' . $siteId, 'none'),
				'wc_status' => GETPOST('kreawoo_wc_status_' . $siteId, 'none'),
				'tax_class' => ($showManualPriceVatFields || GETPOSTISSET($taxClassInputName))
					? GETPOST($taxClassInputName, 'none')
					: $currentData['tax_class'],
				'wc_regular_price' => ($showManualPriceVatFields || GETPOSTISSET($regularPriceInputName))
					? GETPOST($regularPriceInputName, 'none')
					: $currentData['wc_regular_price'],
				'wc_sale_price' => GETPOST('kreawoo_wc_sale_price_' . $siteId, 'none'),
				'wc_date_on_sale_from' => GETPOST('kreawoo_wc_date_on_sale_from_' . $siteId, 'none'),
				'wc_date_on_sale_to' => GETPOST('kreawoo_wc_date_on_sale_to_' . $siteId, 'none'),
				'wc_manage_stock' => GETPOST('kreawoo_wc_manage_stock_' . $siteId, 'int'),
				'wc_stock_status' => GETPOST('kreawoo_wc_stock_status_' . $siteId, 'alpha'),
				'wc_dont_update_stock' => GETPOST('kreawoo_wc_dont_update_stock_' . $siteId, 'int'),
				'stockable_product' => $stockableProduct,
			);

			foreach ($data as $field => $value) {
				$data[$field] = kreawooNormalizeProductSiteDataValue($field, $value);
			}
			if ($data['description'] === null) $data['description'] = '';
			if ($data['short_description'] === null) $data['short_description'] = '';
			if ($data['wc_status'] === null) $data['wc_status'] = '';
			if ($data['tax_class'] === null) $data['tax_class'] = '';
			if ($data['wc_manage_stock'] === null) $data['wc_manage_stock'] = 0;
			if ($data['wc_stock_status'] === null) $data['wc_stock_status'] = 'instock';
			if ($data['wc_dont_update_stock'] === null) $data['wc_dont_update_stock'] = 0;
			if ($data['stockable_product'] === null) $data['stockable_product'] = 1;

			$res = kreawooSaveProductSiteData($this->db, $productId, $siteId, $targetEntity, $data);
			if ($res < 0) {
				$this->db->rollback();
				$this->error = 'Error saving KreaWoo product site data';
				$this->errors[] = $this->db->lasterror();
				dol_syslog(__METHOD__ . " failed to save product site data for product #" . $productId . " site #" . $siteId . ": " . $this->db->lasterror(), LOG_ERR);
				return -1;
			}
		}

		$this->db->commit();
		dol_syslog(__METHOD__ . " saved product site data for product #" . $productId . " on " . count($sites) . " site(s)", LOG_DEBUG);
		return 1;
	}

	/**
	 * Show manual product-card fields for tax class and regular price.
	 * Disabled by default to keep those values sourced from Dolibarr native product data.
	 *
	 * @return bool
	 */
	private function shouldShowProductCardManualPriceVatFields()
	{
		global $conf;

		return !empty($conf->global->KREAWOO_PRODUCT_CARD_SHOW_MANUAL_PRICE_VAT_FIELDS);
	}

	/**
	 * Hide manual product VAT/regular-price rows on product card.
	 *
	 * @return void
	 */
	private function hideProductCardManualPriceVatRows()
	{
		$needles = array(
			'classe de iva para site',
			'classe de iva no site',
			'preço normal do site',
			'preço normal no site',
			'vat class for site',
			'vat class on site',
			'regular price for site',
			'regular price on site',
		);

		print '<script type="text/javascript">';
		print 'jQuery(function(){';
		print 'var needles = ' . json_encode($needles) . ';';
		print 'jQuery("tr").each(function(){';
		print 'var first = jQuery(this).children("td,th").first();';
		print 'if (!first.length) return;';
		print 'var t = first.text().replace(/\\s+/g, " ").trim().toLowerCase();';
		print 'for (var i = 0; i < needles.length; i++) {';
		print 'if (t.indexOf(needles[i]) !== -1) {';
		print 'jQuery(this).hide();';
		print 'break;';
		print '}';
		print '}';
		print '});';
		print '});';
		print '</script>';
	}

	/**
	 * Build selectable product statuses for one site.
	 *
	 * @param object      $site
	 * @param string|null $currentValue
	 * @return array
	 */
	private function buildProductStatusOptionsForSite($site, $currentValue = '')
	{
		global $langs;

		$defaultStatuses = array('draft', 'pending', 'private', 'publish');
		$statuses = $defaultStatuses;
		if (!empty($site->parameters['product_status_supported']) && is_string($site->parameters['product_status_supported'])) {
			$configuredStatuses = array();
			foreach (explode(',', $site->parameters['product_status_supported']) as $status) {
				$status = trim((string) $status);
				if ($status !== '') {
					$configuredStatuses[] = $status;
				}
			}
			$configuredStatuses = array_values(array_unique($configuredStatuses));
			if (!empty($configuredStatuses)) {
				// Backward compatibility: historical default was publish-only.
				if (count($configuredStatuses) === 1 && strtolower($configuredStatuses[0]) === 'publish') {
					$statuses = $defaultStatuses;
				} else {
					$statuses = $configuredStatuses;
				}
			}
		}
		if (!empty($currentValue) && !in_array($currentValue, $statuses, true)) {
			$statuses[] = (string) $currentValue;
		}
		$statuses = array_values(array_unique($statuses));

		$options = array('' => '');
		foreach ($statuses as $status) {
			$key = 'kreawoongWoocommerceStatus' . str_replace(' ', '', ucwords(str_replace(array('-', '_'), ' ', $status)));
			$label = $langs->trans($key);
			if ($label === $key) {
				$label = ucfirst(str_replace(array('-', '_'), ' ', $status));
			}
			$options[$status] = $label;
		}

		return $options;
	}

	/**
	 * Build selectable WooCommerce stock statuses.
	 *
	 * @return array
	 */
	private function buildProductStockStatusOptions()
	{
		global $langs;

		return array(
			'instock' => $langs->trans('kreawoongWoocommerceStockStatusInStock'),
			'outofstock' => $langs->trans('kreawoongWoocommerceStockStatusOutOfStock'),
			'onbackorder' => $langs->trans('kreawoongWoocommerceStockStatusOnBackorder'),
		);
	}

	/**
	 * Load tax class options for one site.
	 *
	 * @param int         $siteId
	 * @param string|null $currentValue
	 * @return array
	 */
	private function loadTaxClassOptionsForSite($siteId, $currentValue = '')
	{
		global $conf;

		$siteId = (int) $siteId;
		$options = array('' => '');
		if ($siteId <= 0) {
			return $options;
		}

		$sql = "SELECT code, label";
		$sql .= " FROM " . MAIN_DB_PREFIX . "c_kreawoo_tax_class";
		$sql .= " WHERE active = 1";
		$sql .= " AND site_id = " . $siteId;
		$sql .= " AND entity = " . ((int) $conf->entity);
		$sql .= " ORDER BY label ASC, code ASC";
		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				if ($obj->code === null) {
					continue;
				}
				$code = (string) $obj->code;
				$options[$code] = !empty($obj->label) ? $obj->label : $code;
			}
			$this->db->free($resql);
		}

		if (!empty($currentValue) && !isset($options[$currentValue])) {
			$options[(string) $currentValue] = (string) $currentValue;
		}

		return $options;
	}

	/**
	 * Get kreawoo linked to the product
	 *
	 * @param   Product     $object    Product object
	 * @return  array
	 */
	private function getCompanyLinkedSite(&$object)
	{
		dol_include_once('/kreawoo/class/business/kreawooSynchro.class.php');
		$linkedTokreawoo = array();

		// Get current categories and subcategories
		require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
		$c = new Categorie($object->db);
		$categories = array();
		if (isset($_POST['categories'])) {
			$cats = GETPOST('categories');
		} else {
			$cats = $c->containing($object->id, Categorie::TYPE_CUSTOMER, 'id');
		}
		foreach ($cats as $cat) {
			$c->id = $cat;
			$catslist = $c->get_all_ways();
			if (isset($catslist[0])) {
				foreach ($catslist[0] as $catinfos) {
					$categories[$catinfos->id] = $catinfos->id;
				}
			}
		}

		$kreawooSite = new kreawooSite($object->db);
		$sites = $kreawooSite->listSites('object');
		foreach ($sites as $site) {
			if (in_array($site->fk_cat_societe, $categories)) {
				$kreawooSociete = new kreawooSociete($object->db);
				$links_list = $kreawooSociete->getAllLinksByFkSociete($object->id, $site->id);
				if (is_array($links_list)) {
					foreach ($links_list as $link) {
						if (!empty($link->remote_id)) {
							$linkedTokreawoo[$site->id] = $site;
							break;
						}
					}
				}
			}
		}

		return $linkedTokreawoo;
	}

	/**
	 * Test if product is linked with a kreawoo
	 *
	 * @param   Product     $object    Product object
	 * @return  bool
	 */
	private function isImageSync(&$object)
	{
		dol_include_once('/kreawoo/class/business/kreawooSynchro.class.php');
		$isImageSync = false;

		$kreawooSite = new kreawooSite($object->db);
		$sites = $kreawooSite->listSites('object');
		foreach ($sites as $site) {
			$productImageSynchDirection = isset($site->parameters['product_synch_direction']['image']) ? $site->parameters['product_synch_direction']['image'] : '';
			if ($productImageSynchDirection == 'dtoe' || $productImageSynchDirection == 'all') {
				$isImageSync = true;
				break;
			}
		}

		return $isImageSync;
	}

	/**
	 * Render an action button inside the card tabsAction container.
	 * This keeps hook output consistent when Dolibarr prints hooks inside form rows.
	 *
	 * @param string $buttonHtml
	 * @param string $buttonId
	 * @return void
	 */
	private function printButtonInTabsAction($buttonHtml, $buttonId)
	{
		$buttonId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $buttonId);
		if (empty($buttonHtml) || empty($buttonId)) {
			return;
		}

		print '<script type="text/javascript">';
		print 'jQuery(function(){';
		print 'var submitArea = jQuery("form[name=formprod] div.center").filter(function(){';
		print 'return jQuery(this).find("input[name=save], input[name=add], input[name=edit], input[name=create], input[name=cancel], button[name=save], button[name=add], button[name=edit], button[name=create], button[name=cancel]").length > 0;';
		print '}).last();';
		print 'var target = null;';
		print 'if (submitArea.length) {';
		print 'target = jQuery("#kreawoo-edit-tabs-action").first();';
		print 'if (!target.length) {';
		print 'target = jQuery("<div class=\"tabsAction\" id=\"kreawoo-edit-tabs-action\"></div>");';
		print 'submitArea.after(target);';
		print '}';
		print '}';
		print 'if (!target || !target.length) target = jQuery("div.tabsAction").first();';
		print 'if (!target.length) return;';
		print 'var existing = jQuery("#' . $buttonId . '").first();';
		print 'if (existing.length) {';
		print 'target.prepend(existing);';
		print 'return;';
		print '}';
		print 'var button = jQuery(' . json_encode($buttonHtml) . ');';
		print 'if (!button.attr("id")) button.attr("id", "' . $buttonId . '");';
		print 'target.prepend(button);';
		print '});';
		print '</script>';
	}

	/**
	 * Hide selected product rows on view mode (show only in edit).
	 *
	 * @return void
	 */
	private function hideProductCardRowsInViewMode()
	{
		$needles = array(
			'classe de iva para site',
			'classe de iva no site',
			'preço normal do site',
			'preço normal no site',
			'preço promocional do site',
			'preço promocional no site',
			'data de início da promoção do site',
			'início do preço promocional no site',
			'data de término da promoção do site',
			'fim do preço promocional no site',
			'gestão de stock do site',
			'ativar gestão de stock no site',
			'estado do stock no site',
			'não atualize o stock com o site',
			'não sincronizar quantidades de stock com o site',
			'stock status on site',
			'sale price on site',
			'sale price start date on site',
			'sale price end date on site',
			'enable stock management on site',
			'do not synchronize stock quantity with site',
			'activar sincronização zsbms',
			'ativar sincronização zsbms',
			'atualizar preço de custo automaticamente',
			'ocultar produto',
			'desmontagem automática (mrp)',
		);

		print '<script type="text/javascript">';
		print 'jQuery(function(){';
		print 'var needles = ' . json_encode($needles) . ';';
		print 'jQuery("td.titlefield").each(function(){';
		print 'var t = jQuery(this).text().replace(/\\s+/g, " ").trim().toLowerCase();';
		print 'for (var i = 0; i < needles.length; i++) {';
		print 'if (t.indexOf(needles[i]) !== -1) {';
		print 'jQuery(this).closest("tr").hide();';
		print 'break;';
		print '}';
		print '}';
		print '});';
		print '});';
		print '</script>';
	}

	/**
	 * Ensure product-card UI customizations are applied only on native Dolibarr product card.
	 *
	 * @return bool
	 */
	private function isNativeDolibarrProductCardPage()
	{
		$phpSelf = isset($_SERVER['PHP_SELF']) ? (string) $_SERVER['PHP_SELF'] : '';
		$requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';

		if (preg_match('#/product/card\\.php$#', $phpSelf)) {
			return true;
		}
		if (strpos($requestUri, '/product/card.php') !== false) {
			return true;
		}

		return false;
	}

	/**
	 * Check if user can execute Kreawoo manual actions.
	 * Fallback keeps admin capable when rights definitions are missing on target DB.
	 *
	 * @param User $user Current user
	 * @return bool
	 */
	private function canManageKreawoo($user)
	{
		if (!empty($user->admin)) {
			return true;
		}
		return !empty($user->rights->kreawoo->write);
	}


	/**
	 * Overloading the printFieldListSearchParam function : replacing the parent's function with the one below
	 *
	 * @param	array 			$parameters		Hook metadatas (context, etc...)
	 * @param	CommonObject 	$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action			Current action (if set). Generally create or edit or null
	 * @param	HookManager		$hookmanager	Hook manager propagated to allow calling another hook
	 * @return	int								< 0 on error, 0 on success, 1 to replace standard code
	 */
	public function printFieldListSearchParam($parameters, &$object, &$action, $hookmanager)
	{
		$contexts = explode(':', $parameters['context']);

		if (in_array('invoicelist', $contexts)) {
			global $arrayfields, $langs;
			$langs->load('kreawoo@kreawoo');

			$arrayfields['ec_total_delta'] = array('label' => $langs->trans('kreawooTotalDelta'), 'checked' => 0, 'position' => 400);

			$search_ec_total_delta = GETPOST('search_ec_total_delta', 'alphanohtml');

			$resPrints = '';
			if ($search_ec_total_delta != '') $resPrints .= '&search_ec_total_delta=' . urlencode($search_ec_total_delta);
			$this->resprints = $resPrints;
		} elseif (!empty(array_intersect([ 'productservicelist', 'thirdpartylist', 'customerlist', 'prospectlist', 'poslist', 'supplierlist', 'contactlist', 'contactprospectlist', 'contactcustomerlist', 'contactsupplierlist', 'contactotherlist' ], $contexts))) {
			$search_ec_remote_id = GETPOST('search_ec_remote_id', 'alphanohtml');

			$resPrints = '';
			if ($search_ec_remote_id != '') $resPrints .= '&search_ec_remote_id=' . urlencode($search_ec_remote_id);
			$this->resprints = $resPrints;
		}

		return 0;
	}

	/**
	 * Overloading the printFieldListSelect function : replacing the parent's function with the one below
	 *
	 * @param	array 			$parameters		Hook metadatas (context, etc...)
	 * @param	CommonObject 	$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action			Current action (if set). Generally create or edit or null
	 * @param	HookManager		$hookmanager	Hook manager propagated to allow calling another hook
	 * @return	int								< 0 on error, 0 on success, 1 to replace standard code
	 */
	public function printFieldListSelect($parameters, &$object, &$action, $hookmanager)
	{
		$contexts = explode(':', $parameters['context']);

		if (in_array('invoicelist', $contexts)) {
			$resPrints = ', ABS(f.total_ttc - (f.total_ht + f.total_tva)) AS ec_total_delta';
			$this->resprints = $resPrints;
		} elseif (!empty(array_intersect([ 'productservicelist', 'thirdpartylist', 'customerlist', 'prospectlist', 'poslist', 'supplierlist', 'contactlist', 'contactprospectlist', 'contactcustomerlist', 'contactsupplierlist', 'contactotherlist' ], $contexts))) {
			global $arrayfields, $langs;
			$langs->load('kreawoo@kreawoo');

			$arrayfields['eri.remote_id'] = array('label' => $langs->trans('kreawooRemoteID'), 'checked' => 0, 'position' => 400);

			$resPrints = ', eri.remote_id AS ec_remote_id';
			$this->resprints = $resPrints;
		}

		return 0;
	}

	/**
	 * Overloading the printFieldListFrom function : replacing the parent's function with the one below
	 *
	 * @param	array 			$parameters		Hook metadatas (context, etc...)
	 * @param	CommonObject 	$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action			Current action (if set). Generally create or edit or null
	 * @param	HookManager		$hookmanager	Hook manager propagated to allow calling another hook
	 * @return	int								< 0 on error, 0 on success, 1 to replace standard code
	 */
	public function printFieldListFrom($parameters, &$object, &$action, $hookmanager)
	{
		$contexts = explode(':', $parameters['context']);

		if (!empty(array_intersect([ 'productservicelist', 'thirdpartylist', 'customerlist', 'prospectlist', 'poslist', 'supplierlist', 'contactlist', 'contactprospectlist', 'contactcustomerlist', 'contactsupplierlist', 'contactotherlist' ], $contexts))) {
			if ($object->element == 'product') {
				$table_nme = 'view_kreawoo_product_remote_ids';
				$field_object = 'p.rowid';
			} elseif ($object->element == 'societe') {
				$table_nme = 'view_kreawoo_thirdparty_remote_ids';
				$field_object = 's.rowid';
			} else {
				$table_nme = 'view_kreawoo_contact_remote_ids';
				$field_object = 'p.rowid';
			}

			$this->resprints = " LEFT JOIN " . MAIN_DB_PREFIX . $table_nme . " AS eri ON eri.fk_object = " . $field_object;
			self::$join_table_remote_id_set = true;
		}

		return 0;
	}

	/**
	 * Overloading the printFieldListWhere function : replacing the parent's function with the one below
	 *
	 * @param	array 			$parameters		Hook metadatas (context, etc...)
	 * @param	CommonObject 	$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action			Current action (if set). Generally create or edit or null
	 * @param	HookManager		$hookmanager	Hook manager propagated to allow calling another hook
	 * @return	int								< 0 on error, 0 on success, 1 to replace standard code
	 */
	public function printFieldListWhere($parameters, &$object, &$action, $hookmanager)
	{
		global $sql, $conf;
		$contexts = explode(':', $parameters['context']);

		if (in_array('invoicelist', $contexts)) {
			$search_ec_total_delta = GETPOST('search_ec_total_delta', 'alphanohtml');

			$out = '';
			if ($search_ec_total_delta) $out .= natural_search('ABS(f.total_ttc - (f.total_ht + f.total_tva))', $search_ec_total_delta, 1);

			$this->resprints = $out;
		} elseif (!empty(array_intersect([ 'productservicelist', 'thirdpartylist', 'customerlist', 'prospectlist', 'poslist', 'supplierlist', 'contactlist', 'contactprospectlist', 'contactcustomerlist', 'contactsupplierlist', 'contactotherlist' ], $contexts))) {
			$search_ec_remote_id = GETPOST('search_ec_remote_id', 'alphanohtml');

			if (!self::$join_table_remote_id_set) {
				if (in_array('productservicelist', $contexts)) {
					$sql = str_replace(" FROM " . MAIN_DB_PREFIX . "product as p", " FROM " . MAIN_DB_PREFIX . "product as p LEFT JOIN " . MAIN_DB_PREFIX . "view_kreawoo_product_remote_ids AS eri ON eri.fk_object = p.rowid", $sql);
				} elseif (count(array_intersect(['contactlist', 'contactprospectlist', 'contactcustomerlist', 'contactsupplierlist', 'contactotherlist'], $contexts)) > 0) {
					$sql = str_replace(" FROM " . MAIN_DB_PREFIX . "socpeople as p", " FROM " . MAIN_DB_PREFIX . "socpeople as p LEFT JOIN " . MAIN_DB_PREFIX . "view_kreawoo_contact_remote_ids AS eri ON eri.fk_object = p.rowid", $sql);
				} elseif (count(array_intersect(['thirdpartylist', 'customerlist', 'prospectlist', 'poslist', 'supplierlist'], $contexts)) > 0) {
					$sql = str_replace(" FROM " . MAIN_DB_PREFIX . "societe as s", " FROM " . MAIN_DB_PREFIX . "societe as s LEFT JOIN " . MAIN_DB_PREFIX . "view_kreawoo_thirdparty_remote_ids AS eri ON eri.fk_object = s.rowid", $sql);
				}
			}

			$out = '';
			if ($search_ec_remote_id) {
				if ($object->element == 'product') {
					$table_nme = 'product';
					$field_name = "fk_product";
					$field_object = 'p.rowid';
				} elseif ($object->element == 'societe' || (empty($object->element) && count(array_intersect(['thirdpartylist', 'customerlist', 'prospectlist', 'poslist', 'supplierlist'], $contexts)) > 0)) {
					$table_nme = 'societe';
					$field_name = "fk_societe";
					$field_object = 's.rowid';
				} else {
					$table_nme = 'socpeople';
					$field_name = "fk_socpeople";
					$field_object = 'p.rowid';
				}

				$out = " AND EXISTS (";
				$out .= " SELECT el.rowid";
				$out .= " FROM " . MAIN_DB_PREFIX . "kreawoo_" . $table_nme . " AS el";
				$out .= " LEFT JOIN " . MAIN_DB_PREFIX . "kreawoo_site AS es ON es.rowid = el.fk_site";
				$out .= " WHERE el." . $field_name . " = " . $field_object;
				$out .= natural_search('el.remote_id', $search_ec_remote_id);
				$out .= ")";
			}

			$this->resprints = $out;
		}

		return 0;
	}

	/**
	 * Overloading the printFieldListOption function : replacing the parent's function with the one below
	 *
	 * @param	array 			$parameters		Hook metadatas (context, etc...)
	 * @param	CommonObject 	$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action			Current action (if set). Generally create or edit or null
	 * @param	HookManager		$hookmanager	Hook manager propagated to allow calling another hook
	 * @return	int								< 0 on error, 0 on success, 1 to replace standard code
	 */
	public function printFieldListOption($parameters, &$object, &$action, $hookmanager)
	{
		$contexts = explode(':', $parameters['context']);

		if (in_array('invoicelist', $contexts)) {
			$arrayfields = $parameters['arrayfields'];

			if (!empty($arrayfields['ec_total_delta']['checked'])) {
				$search_ec_total_delta = GETPOST('search_ec_total_delta', 'alphanohtml');
				print '<td class="liste_titre right">';
				print '<input class="flat" type="text" name="search_ec_total_delta" size="8" value="' . dol_escape_htmltag($search_ec_total_delta) . '">';
				print '</td>';
			}
		} elseif (!empty(array_intersect([ 'productservicelist', 'thirdpartylist', 'customerlist', 'prospectlist', 'poslist', 'supplierlist', 'contactlist', 'contactprospectlist', 'contactcustomerlist', 'contactsupplierlist', 'contactotherlist' ], $contexts))) {
			$arrayfields = $parameters['arrayfields'];

			if (!empty($arrayfields['eri.remote_id']['checked'])) {
				$search_ec_remote_id = GETPOST('search_ec_remote_id', 'alphanohtml');
				print '<td class="liste_titre">';
				print '<input class="flat" type="text" name="search_ec_remote_id" size="8" value="' . dol_escape_htmltag($search_ec_remote_id) . '">';
				print '</td>';
			}
		}

		return 0;
	}

	/**
	 * Overloading the printFieldListTitle function : replacing the parent's function with the one below
	 *
	 * @param	array 			$parameters		Hook metadatas (context, etc...)
	 * @param	CommonObject 	$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action			Current action (if set). Generally create or edit or null
	 * @param	HookManager		$hookmanager	Hook manager propagated to allow calling another hook
	 * @return	int								< 0 on error, 0 on success, 1 to replace standard code
	 */
	public function printFieldListTitle($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;
		$contexts = explode(':', $parameters['context']);

		if (in_array('invoicelist', $contexts)) {
			$param = $parameters['param'];
			$sortfield = $parameters['sortfield'];
			$sortorder = $parameters['sortorder'];
			$arrayfields = $parameters['arrayfields'];

			if (!empty($arrayfields['ec_total_delta']['checked'])) {
				print_liste_field_titre($arrayfields['ec_total_delta']['label'], $_SERVER['PHP_SELF'], '', '', $param, '', $sortfield, $sortorder, 'right ');
			}
		} elseif (!empty(array_intersect([ 'productservicelist', 'thirdpartylist', 'customerlist', 'prospectlist', 'poslist', 'supplierlist', 'contactlist', 'contactprospectlist', 'contactcustomerlist', 'contactsupplierlist', 'contactotherlist' ], $contexts))) {
			$param = $parameters['param'];
			$sortfield = $parameters['sortfield'];
			$sortorder = $parameters['sortorder'];
			$arrayfields = $parameters['arrayfields'];

			if (!empty($arrayfields['eri.remote_id']['checked'])) {
				print_liste_field_titre($arrayfields['eri.remote_id']['label'], $_SERVER['PHP_SELF'], 'eri.remote_id', '', $param, '', $sortfield, $sortorder);
			}
		}

		return 0;
	}

	/**
	 * Overloading the printFieldListValue function : replacing the parent's function with the one below
	 *
	 * @param	array 			$parameters		Hook metadatas (context, etc...)
	 * @param	CommonObject 	$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action			Current action (if set). Generally create or edit or null
	 * @param	HookManager		$hookmanager	Hook manager propagated to allow calling another hook
	 * @return	int								< 0 on error, 0 on success, 1 to replace standard code
	 */
	public function printFieldListValue($parameters, &$object, &$action, $hookmanager)
	{
		$contexts = explode(':', $parameters['context']);

		if (in_array('invoicelist', $contexts)) {
			$obj = $parameters['obj'];
			$arrayfields = $parameters['arrayfields'];

			if (!empty($arrayfields['ec_total_delta']['checked'])) {
				print '<td class="right">';
				print price(price2num($obj->ec_total_delta));
				print '</td>';
			}
		} elseif (!empty(array_intersect([ 'productservicelist', 'thirdpartylist', 'customerlist', 'prospectlist', 'poslist', 'supplierlist', 'contactlist', 'contactprospectlist', 'contactcustomerlist', 'contactsupplierlist', 'contactotherlist' ], $contexts))) {
			$obj = $parameters['obj'];
			$arrayfields = $parameters['arrayfields'];

			if (!empty($arrayfields['eri.remote_id']['checked'])) {
				print '<td>';
				print $obj->ec_remote_id;
				print '</td>';
			}
		}

		return 0;
	}

	/**
	 * Overloading the stockMovementCreate function : replacing the parent's function with the one below
	 *
	 * @param	array 			$parameters		Hook metadatas (context, etc...)
	 * @param	CommonObject 	$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action			Current action (if set). Generally create or edit or null
	 * @param	HookManager		$hookmanager	Hook manager propagated to allow calling another hook
	 * @return	int								< 0 on error, 0 on success, 1 to replace standard code
	 */
	public function stockMovementCreate($parameters, &$object, &$action, $hookmanager)
	{
		$contexts = explode(':', $parameters['context']);

		if (in_array('mouvementstock', $contexts)) {
			$fk_product = $parameters['fk_product'];

			if ($fk_product > 0) {
				require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
				$product = new Product($this->db);

				if (!property_exists($product, 'stockable_product')) {
					$result = $product->fetch($fk_product);
					if ($result < 0) {
						$this->errors[] = $product->errorsToString();
						return -1;
					} elseif ($result == 0) {
						return 1;
					}

					if (isset($product->array_options['options_kreawoo_stockable_product']) &&
						$product->array_options['options_kreawoo_stockable_product'] == 0
					) {
						return 1;
					}
				}
			}
		}

		return 0;
	}
}
