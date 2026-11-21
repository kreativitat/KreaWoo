<?php
/* Copyright (C) 2024-2026  Kreativitat  <mail@kreativitat.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *      \file       htdocs/kreawoo/admin/category.php
 *      \ingroup    kreawoo
 *      \brief      Page to configure category correspondences.
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include '../../main.inc.php';
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include '../../../main.inc.php';
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
dol_include_once('/kreawoo/lib/kreawoo.lib.php');
dol_include_once('/kreawoo/class/business/kreawooSynchro.class.php');

/**
 * Flatten WooCommerce category tree for display.
 *
 * @param   array   $remoteCategory  Remote category node
 * @param   array   $flat            Flat map indexed by remote id
 * @param   array   $options         Select options list
 * @param   int     $depth           Current depth
 * @return  void
 */
function kreawooFlattenRemoteCategoryTree($remoteCategory, &$flat, &$options, $depth = 0)
{
	if (empty($remoteCategory['category_id'])) {
		return;
	}

	$remoteId = (int) $remoteCategory['category_id'];
	$flat[$remoteId] = $remoteCategory;

	$label = str_repeat('> ', max(0, (int) $depth));
	$label .= (string) $remoteCategory['name'] . ' (#' . $remoteId . ')';
	$options[$remoteId] = $label;

	if (!empty($remoteCategory['children']) && is_array($remoteCategory['children'])) {
		foreach ($remoteCategory['children'] as $child) {
			kreawooFlattenRemoteCategoryTree($child, $flat, $options, $depth + 1);
		}
	}
}

/**
 * Detect WooCommerce "cannot view/list resources" permission errors.
 *
 * @param   array|string|null  $errors  Error list
 * @return  bool
 */
function kreawooHasWooCannotViewError($errors)
{
	$messages = is_array($errors) ? $errors : array($errors);
	foreach ($messages as $message) {
		if (!is_scalar($message)) {
			continue;
		}

		$text = (string) $message;
		if (
			stripos($text, 'woocommerce_rest_cannot_view') !== false ||
			stripos($text, 'cannot list resources') !== false ||
			stripos($text, 'cannot view resources') !== false
		) {
			return true;
		}
	}

	return false;
}

/**
 * Load cached remote categories from site parameters.
 *
 * @param   kreawooSite  $site        Site object
 * @param   array        $categories  Cached categories map (output)
 * @param   array        $options     Cached select options (output)
 * @param   string       $loadedAt    Cache update date (output)
 * @return  void
 */
function kreawooLoadRemoteCategoryCache($site, &$categories, &$options, &$loadedAt)
{
	$categories = array();
	$options = array();
	$loadedAt = '';

	if (empty($site->parameters['category_remote_cache']) || !is_array($site->parameters['category_remote_cache'])) {
		return;
	}

	$cache = $site->parameters['category_remote_cache'];
	$loadedAt = !empty($cache['loaded_at']) ? $cache['loaded_at'] : '';

	if (!empty($cache['categories']) && is_array($cache['categories'])) {
		foreach ($cache['categories'] as $remoteId => $remoteCategory) {
			$categoryId = (int) $remoteId;
			if ($categoryId <= 0 && is_array($remoteCategory) && !empty($remoteCategory['category_id'])) {
				$categoryId = (int) $remoteCategory['category_id'];
			}
			if ($categoryId <= 0) {
				continue;
			}

			$categories[$categoryId] = array(
				'category_id' => $categoryId,
				'parent_id' => !empty($remoteCategory['parent_id']) ? (int) $remoteCategory['parent_id'] : 0,
				'name' => !empty($remoteCategory['name']) ? (string) $remoteCategory['name'] : '',
				'description' => !empty($remoteCategory['description']) ? (string) $remoteCategory['description'] : '',
				'other_data' => (!empty($remoteCategory['other_data']) && is_array($remoteCategory['other_data'])) ? $remoteCategory['other_data'] : array(),
			);
		}
	}

	if (!empty($cache['options']) && is_array($cache['options'])) {
		foreach ($cache['options'] as $remoteId => $label) {
			$categoryId = (int) $remoteId;
			if ($categoryId > 0) {
				$options[$categoryId] = (string) $label;
			}
		}
	}

	if (empty($options) && !empty($categories)) {
		foreach ($categories as $categoryId => $remoteCategory) {
			$label = !empty($remoteCategory['name']) ? $remoteCategory['name'] : ('#' . $categoryId);
			$options[$categoryId] = $label . ' (#' . $categoryId . ')';
		}
		asort($options, SORT_NATURAL | SORT_FLAG_CASE);
	}
}

/**
 * Check CSRF token with compatibility fallback for old Dolibarr versions.
 *
 * @return  bool
 */
function kreawooCheckTokenCompat()
{
	if (function_exists('checkToken')) {
		return checkToken();
	}

	$token = GETPOST('token', 'alphanohtml');
	if ($token === '') {
		return false;
	}

	if (!empty($_SESSION['newtoken']) && hash_equals((string) $_SESSION['newtoken'], (string) $token)) {
		return true;
	}
	if (!empty($_SESSION['token']) && hash_equals((string) $_SESSION['token'], (string) $token)) {
		return true;
	}

	return false;
}

/**
 * Load saved correspondences for a site.
 *
 * @param   DoliDB  $db        Database handler
 * @param   int     $siteId    Site id
 * @return  array
 */
function kreawooGetCategoryCorrespondences($db, $siteId)
{
	$list = array();

	$sql = "SELECT kc.rowid, kc.fk_category, kc.remote_id, kc.remote_parent_id, kc.label, kc.description, kc.last_update";
	$sql .= " FROM " . MAIN_DB_PREFIX . "kreawoo_category AS kc";
	$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "categorie AS c ON c.rowid = kc.fk_category";
	$sql .= " WHERE kc.fk_site = " . ((int) $siteId);
	$sql .= " AND kc.type = " . ((int) Categorie::TYPE_PRODUCT);
	$sql .= " AND c.entity IN (" . getEntity('category') . ")";

	$resql = $db->query($sql);
	if (!$resql) {
		return $list;
	}

	while ($obj = $db->fetch_object($resql)) {
		$list[(int) $obj->fk_category] = array(
			'rowid' => (int) $obj->rowid,
			'fk_category' => (int) $obj->fk_category,
			'remote_id' => (int) $obj->remote_id,
			'remote_parent_id' => (int) $obj->remote_parent_id,
			'label' => $obj->label,
			'description' => $obj->description,
			'last_update' => $obj->last_update,
		);
	}

	$db->free($resql);

	return $list;
}

/**
 * Save one category correspondence.
 *
 * @param   DoliDB  $db             Database handler
 * @param   int     $siteId         Site id
 * @param   array   $localCategory  Local category infos
 * @param   array   $remoteCategory Remote category infos
 * @return  int                     >0 if OK, <0 if KO
 */
function kreawooSaveCategoryCorrespondence($db, $siteId, $localCategory, $remoteCategory)
{
	$fkCategory = (int) $localCategory['id'];
	$remoteId = (int) $remoteCategory['category_id'];
	$remoteParentId = isset($remoteCategory['parent_id']) ? (int) $remoteCategory['parent_id'] : 0;
	$label = isset($localCategory['label']) ? $localCategory['label'] : '';
	$description = isset($localCategory['description']) ? $localCategory['description'] : '';

	$otherDataSql = 'NULL';
	if (!empty($remoteCategory['other_data']) && is_array($remoteCategory['other_data'])) {
		$otherDataSql = "'" . $db->escape(json_encode($remoteCategory['other_data'])) . "'";
	}

	$sql = "DELETE FROM " . MAIN_DB_PREFIX . "kreawoo_category";
	$sql .= " WHERE fk_site = " . ((int) $siteId);
	$sql .= " AND type = " . ((int) Categorie::TYPE_PRODUCT);
	$sql .= " AND (fk_category = " . $fkCategory . " OR remote_id = " . $remoteId . ")";
	$resql = $db->query($sql);
	if (!$resql) {
		return -1;
	}

	$sql = "INSERT INTO " . MAIN_DB_PREFIX . "kreawoo_category(";
	$sql .= " label, type, description, fk_category, fk_site, remote_id, remote_parent_id, last_update, other_data";
	$sql .= ") VALUES (";
	$sql .= " '" . $db->escape($label) . "'";
	$sql .= ", " . ((int) Categorie::TYPE_PRODUCT);
	$sql .= ", '" . $db->escape($description) . "'";
	$sql .= ", " . $fkCategory;
	$sql .= ", " . ((int) $siteId);
	$sql .= ", " . $remoteId;
	$sql .= ", " . $remoteParentId;
	$sql .= ", '" . $db->idate(dol_now()) . "'";
	$sql .= ", " . $otherDataSql;
	$sql .= ")";
	$resql = $db->query($sql);
	if (!$resql) {
		return -1;
	}

	return 1;
}

$langs->loadLangs(array("admin", "products", "categories", "kreawoo@kreawoo", "kreativitat@kreawoo"));

if (!$user->admin && empty($user->rights->kreawoo->site)) {
	accessforbidden();
}

$id = GETPOST('id', 'int');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');

include dol_buildpath('/kreawoo/admin/actions_selectsite.inc.php');

if (in_array($action, array('download_remote_categories', 'set_category_correspondence', 'delete_category_correspondence', 'confirm_delete_category_correspondence', 'create_remote_category'), true) && !kreawooCheckTokenCompat()) {
	accessforbidden('Invalid token');
}

$object = new kreawooSite($db);
if (!($id > 0)) {
	$sites = $object->listSites();
	if (!empty($sites)) {
		$id = (int) array_values($sites)[0]['id'];
	}
	$action = '';
}
if ($id > 0) {
	$result = $object->fetch($id);
	if ($result < 0) {
		accessforbidden($object->errorsToString());
	} elseif ($result == 0) {
		$langs->load('errors');
		accessforbidden($langs->trans('ErrorRecordNotFound'));
	}
} else {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
if ((int) $object->entity !== (int) $conf->entity) {
	accessforbidden();
}
if (empty($conf->product->enabled)) {
	accessforbidden($langs->trans('ModuleDisabled'));
}
if (empty($conf->categorie->enabled)) {
	accessforbidden($langs->trans('ModuleDisabled') . ' : ' . $langs->trans('Categories'));
}

$categoryStatic = new Categorie($db);
$allCategories = $categoryStatic->get_full_arbo(Categorie::TYPE_PRODUCT);
$allCategories = is_array($allCategories) ? $allCategories : array();
$rootCategoryId = (int) $object->fk_cat_product;

$siteCategories = array();
$siteCategoryLabels = array();
$siteCategoryOptions = array();
if ($rootCategoryId > 0) {
	foreach ($allCategories as $category) {
		if (!preg_match('/_' . $rootCategoryId . '(_|$)/', $category['fullpath'])) {
			continue;
		}

		$categoryId = (int) $category['id'];
		$siteCategories[$categoryId] = $category;
		$siteCategoryLabels[$categoryId] = !empty($category['fulllabel']) ? $category['fulllabel'] : $category['label'];

		if ($categoryId !== $rootCategoryId) {
			$depth = max(0, ((int) $category['level']) - 1);
			$siteCategoryOptions[$categoryId] = str_repeat('> ', $depth) . $category['label'];
		}
	}
}

$remoteCategories = array();
$remoteCategoryOptions = array();
$remoteCacheLoadedAt = '';
kreawooLoadRemoteCategoryCache($object, $remoteCategories, $remoteCategoryOptions, $remoteCacheLoadedAt);

$correspondences = kreawooGetCategoryCorrespondences($db, $object->id);
$unmappedSiteCategoryOptions = array();
foreach ($siteCategoryOptions as $categoryId => $label) {
	if (empty($correspondences[$categoryId])) {
		$unmappedSiteCategoryOptions[$categoryId] = $label;
	}
}

/*
 * Actions
 */
if ($action === 'download_remote_categories') {
	$error = 0;

	if ($rootCategoryId <= 0) {
		setEventMessage($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("kreawooCatProduct")), 'errors');
		$error++;
	}

	$downloadedCategories = array();
	$downloadedOptions = array();
	if (!$error) {
		$synchro = new kreawooSynchro($db, $object);
		$result = $synchro->connect();
		if ($result < 0 || !empty($synchro->errors)) {
			setEventMessages($langs->trans("kreawooCategoryRemoteUnavailable"), $synchro->errors, 'errors');
			$error++;
		} else {
			$remoteCategoriesTree = $synchro->kreawooRemoteAccess->getRemoteCategoryTree();
			if ($remoteCategoriesTree === false) {
				setEventMessages($langs->trans("kreawooCategoryRemoteUnavailable"), $synchro->kreawooRemoteAccess->errors, 'errors');
				$error++;
			} elseif (!empty($remoteCategoriesTree['children']) && is_array($remoteCategoriesTree['children'])) {
				foreach ($remoteCategoriesTree['children'] as $remoteCategory) {
					kreawooFlattenRemoteCategoryTree($remoteCategory, $downloadedCategories, $downloadedOptions, 0);
				}
			}
		}
	}

	if (!$error) {
		if (!is_array($object->parameters)) {
			$object->parameters = array();
		}
		$object->oldcopy = clone $object;
		$object->parameters['category_remote_cache'] = array(
			'loaded_at' => dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S'),
			'categories' => $downloadedCategories,
			'options' => $downloadedOptions,
		);

		$result = $object->update($user);
		if ($result < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		} else {
			setEventMessage($langs->trans("kreawooCategoryRemoteCacheUpdated", count($downloadedCategories)));
			header("Location: " . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
			exit;
		}
	}
} elseif ($action === 'set_category_correspondence') {
	$localCategoryId = GETPOST('local_category_id', 'int');
	$remoteCategoryId = GETPOST('remote_category_id', 'int');
	$error = 0;

	if ($rootCategoryId <= 0) {
		setEventMessage($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("kreawooCatProduct")), 'errors');
		$error++;
	}
	if (empty($siteCategories[$localCategoryId]) || $localCategoryId === $rootCategoryId) {
		setEventMessage($langs->trans("kreawooCategoryNotInSiteTree"), 'errors');
		$error++;
	}
	if ($remoteCategoryId <= 0) {
		setEventMessage($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("kreawooRemoteID")), 'errors');
		$error++;
	}

	$remoteCategory = !empty($remoteCategories[$remoteCategoryId]) ? $remoteCategories[$remoteCategoryId] : array();
	$remoteReadLimited = false;
	if (!$error && empty($remoteCategory)) {
		$synchro = new kreawooSynchro($db, $object);
		$result = $synchro->connect();
		if ($result < 0 || !empty($synchro->errors)) {
			setEventMessages($langs->trans("kreawooCategoryRemoteUnavailable"), $synchro->errors, 'errors');
			$error++;
		} else {
			$remoteCategory = $synchro->kreawooRemoteAccess->getCategoryData($remoteCategoryId);
			if ($remoteCategory === false) {
				if (kreawooHasWooCannotViewError($synchro->kreawooRemoteAccess->errors)) {
					$remoteReadLimited = true;
					$remoteCategory = array(
						'category_id' => $remoteCategoryId,
						'parent_id' => 0,
						'other_data' => array(),
					);
				} else {
					setEventMessages($langs->trans("kreawooCategoryRemoteUnavailable"), $synchro->kreawooRemoteAccess->errors, 'errors');
					$error++;
				}
			} elseif (empty($remoteCategory['category_id'])) {
				if (kreawooHasWooCannotViewError($synchro->kreawooRemoteAccess->errors)) {
					$remoteReadLimited = true;
					$remoteCategory = array(
						'category_id' => $remoteCategoryId,
						'parent_id' => 0,
						'other_data' => array(),
					);
				} else {
					setEventMessage($langs->trans("kreawooCategoryRemoteNotFound"), 'errors');
					$error++;
				}
			}
		}
	}

	if (!$error) {
		$db->begin();
		$result = kreawooSaveCategoryCorrespondence($db, $object->id, $siteCategories[$localCategoryId], $remoteCategory);
		if ($result < 0) {
			$db->rollback();
			setEventMessages($db->lasterror(), null, 'errors');
		} else {
			$db->commit();
			if ($remoteReadLimited) {
				setEventMessage($langs->trans("kreawooCategoryRemoteReadPermissionLimited"), 'warnings');
			}
			setEventMessage($langs->trans("kreawooCategoryCorrespondenceSaved"));
			header("Location: " . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
			exit;
		}
	}
	} elseif ($action === 'confirm_delete_category_correspondence' && ($confirm === 'yes' || $confirm === 'no')) {
		$linkId = GETPOST('link_id', 'int');
		$deleteRemote = ($confirm === 'yes') ? 1 : 0;
		$error = 0;
		$remoteCategoryId = 0;
		if ($linkId <= 0) {
			setEventMessage($langs->trans("ErrorBadParameters"), 'errors');
		} else {
			$sql = "SELECT remote_id FROM " . MAIN_DB_PREFIX . "kreawoo_category";
			$sql .= " WHERE rowid = " . $linkId;
			$sql .= " AND fk_site = " . ((int) $object->id);
			$sql .= " AND type = " . ((int) Categorie::TYPE_PRODUCT);
			$resql = $db->query($sql);
			if (!$resql) {
				setEventMessages($db->lasterror(), null, 'errors');
				$error++;
			} elseif ($db->num_rows($resql) <= 0) {
				setEventMessage($langs->trans("ErrorRecordNotFound"), 'errors');
				$error++;
			} else {
				$objLink = $db->fetch_object($resql);
				$remoteCategoryId = (int) $objLink->remote_id;
			}
			if ($resql) {
				$db->free($resql);
			}

			if (!$error && $deleteRemote > 0) {
				if ($remoteCategoryId <= 0) {
					setEventMessage($langs->trans("kreawooCategoryRemoteDeleteFailed"), 'errors');
					$error++;
				} else {
					$synchro = new kreawooSynchro($db, $object);
					$result = $synchro->connect();
					if ($result < 0 || !empty($synchro->errors)) {
						setEventMessages($langs->trans("kreawooCategoryRemoteDeleteFailed"), $synchro->errors, 'errors');
						$error++;
					} else {
						$result = $synchro->kreawooRemoteAccess->deleteRemoteCategory($remoteCategoryId, true);
						if ($result === false) {
							setEventMessages($langs->trans("kreawooCategoryRemoteDeleteFailed"), $synchro->kreawooRemoteAccess->errors, 'errors');
							$error++;
						}
					}
				}
			}

			if (!$error) {
				$db->begin();
				$sql = "DELETE FROM " . MAIN_DB_PREFIX . "kreawoo_category";
				$sql .= " WHERE rowid = " . $linkId;
				$sql .= " AND fk_site = " . ((int) $object->id);
				$sql .= " AND type = " . ((int) Categorie::TYPE_PRODUCT);
				$resql = $db->query($sql);
				if (!$resql) {
					$db->rollback();
					setEventMessages($db->lasterror(), null, 'errors');
				} else {
					$db->commit();
					if ($deleteRemote > 0) {
						setEventMessage($langs->trans("kreawooCategoryCorrespondenceDeletedWithRemote"));
					} else {
						setEventMessage($langs->trans("kreawooCategoryCorrespondenceDeleted"));
					}
					header("Location: " . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
					exit;
				}
			}
		}
	} elseif ($action === 'create_remote_category') {
	$localCategoryId = GETPOST('local_category_id', 'int');
	$remoteCategoryName = trim(strip_tags((string) GETPOST('remote_category_name', 'none')));
	$error = 0;

	if ($rootCategoryId <= 0) {
		setEventMessage($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("kreawooCatProduct")), 'errors');
		$error++;
	}
	if (empty($siteCategories[$localCategoryId]) || $localCategoryId === $rootCategoryId) {
		setEventMessage($langs->trans("kreawooCategoryNotInSiteTree"), 'errors');
		$error++;
	}
	if (!empty($correspondences[$localCategoryId])) {
		setEventMessage($langs->trans("kreawooCategoryAlreadyMapped"), 'warnings');
		header("Location: " . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
		exit;
	}

	$batch = array();
	if (!$error) {
		$visited = array();
		$currentCategoryId = $localCategoryId;
		while ($currentCategoryId > 0 && empty($visited[$currentCategoryId])) {
			$visited[$currentCategoryId] = 1;
			if ($currentCategoryId === $rootCategoryId) {
				break;
			}
			if (empty($siteCategories[$currentCategoryId])) {
				$error++;
				setEventMessage($langs->trans("kreawooCategoryNotInSiteTree"), 'errors');
				break;
			}
			if (empty($correspondences[$currentCategoryId])) {
				$batch[$currentCategoryId] = $siteCategories[$currentCategoryId];
			}
			$currentCategoryId = (int) $siteCategories[$currentCategoryId]['fk_parent'];
		}

		if (!$error && $currentCategoryId !== $rootCategoryId) {
			setEventMessage($langs->trans("kreawooCategoryNotInSiteTree"), 'errors');
			$error++;
		}
		if (!$error && empty($batch)) {
			setEventMessage($langs->trans("kreawooCategoryNoUnmapped"), 'warnings');
			header("Location: " . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
			exit;
		}
		if (!$error && $remoteCategoryName !== '' && !empty($batch[$localCategoryId])) {
			$batch[$localCategoryId]['label'] = $remoteCategoryName;
		}
	}

	$createdCategories = array();
	if (!$error) {
		$synchro = new kreawooSynchro($db, $object);
		$result = $synchro->connect();
		if ($result < 0 || !empty($synchro->errors)) {
			setEventMessages($langs->trans("kreawooCategoryRemoteUnavailable"), $synchro->errors, 'errors');
			$error++;
		} else {
			$createdCategories = $synchro->kreawooRemoteAccess->createRemoteCategories($batch);
			if ($createdCategories === false) {
				setEventMessages($langs->trans("kreawooCategoryRemoteCreateFailed"), $synchro->kreawooRemoteAccess->errors, 'errors');
				$error++;
			} elseif (empty($createdCategories[$localCategoryId])) {
				$errorsToPrint = !empty($synchro->kreawooRemoteAccess->errors) ? $synchro->kreawooRemoteAccess->errors : null;
				setEventMessages($langs->trans("kreawooCategoryRemoteCreateFailed"), $errorsToPrint, 'errors');
				$error++;
			}
		}
	}

	if (!$error) {
		$db->begin();
		$saved = 0;
		$saveError = 0;
		foreach ($createdCategories as $createdCategoryId => $remoteInfos) {
			if (empty($siteCategories[$createdCategoryId])) {
				continue;
			}

			$remoteCategory = array(
				'category_id' => (int) $remoteInfos['remote_id'],
				'parent_id' => (int) $remoteInfos['remote_parent_id'],
				'other_data' => array(),
			);
			$result = kreawooSaveCategoryCorrespondence($db, $object->id, $siteCategories[$createdCategoryId], $remoteCategory);
			if ($result < 0) {
				$saveError++;
				break;
			}
			$saved++;
		}

		if ($saveError) {
			$db->rollback();
			setEventMessages($db->lasterror(), null, 'errors');
		} else {
			$db->commit();
			setEventMessage($langs->trans("kreawooCategoryRemoteCreatedCount", $saved));
			header("Location: " . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
			exit;
		}
	}
}

/*
 * View
 */
$form = new Form($db);
kreawooLoadRemoteCategoryCache($object, $remoteCategories, $remoteCategoryOptions, $remoteCacheLoadedAt);

$correspondences = kreawooGetCategoryCorrespondences($db, $object->id);
$unmappedSiteCategoryOptions = array();
foreach ($siteCategoryOptions as $categoryId => $label) {
	if (empty($correspondences[$categoryId])) {
		$unmappedSiteCategoryOptions[$categoryId] = $label;
	}
}

$formconfirm = '';
if ($action === 'delete_category_correspondence') {
	$linkId = GETPOST('link_id', 'int');
	if ($linkId <= 0) {
		setEventMessage($langs->trans("ErrorBadParameters"), 'errors');
	} else {
		$formconfirm = $form->formconfirm(
			$_SERVER["PHP_SELF"] . '?id=' . $object->id . '&link_id=' . $linkId,
			$langs->trans("Delete"),
			$langs->trans("kreawooCategoryDeleteAskRemote"),
			'confirm_delete_category_correspondence',
			'',
			'no',
			2,
			220,
			700
		);
	}
}

$wikihelp = 'EN:kreawooNg_En|FR:kreawooNg_Fr|ES:kreawooNg_Es';
llxHeader('', $langs->trans("kreawooSetup"), $wikihelp);
print $formconfirm;

$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">' . $langs->trans("BackToModuleList") . '</a>';
print load_fiche_titre($langs->trans("kreawooSetup"), $linkback, 'title_setup');

include dol_buildpath('/kreawoo/admin/tpl/selectsite.tpl.php');

$head = kreawooConfigSitePrepareHead($object);
print dol_get_fiche_head($head, 'category', $langs->trans("Module107100Name"), 0, 'kreawoo@kreawoo');

if ($rootCategoryId > 0) {
	print '<div class="tabsAction tabsActionNoBottom">';
	print '<a class="butAction" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&amp;action=download_remote_categories&amp;token=' . kreawooNewToken() . '">' . $langs->trans("kreawooCategoryDownloadRemoteList") . '</a>';
	print '</div>';
}

if ($rootCategoryId <= 0) {
	print '<div class="warning">' . $langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("kreawooCatProduct")) . '</div>';
} else {
	if (!empty($remoteCacheLoadedAt)) {
		print '<div class="opacitymedium">' . $langs->trans("kreawooCategoryRemoteCacheLoadedAt", dol_print_date($db->jdate($remoteCacheLoadedAt), 'dayhourtext')) . '</div>';
	} else {
		print '<div class="warning">' . $langs->trans("kreawooCategoryRemoteCacheEmpty") . '</div>';
	}

	print '<div id="category_correspondence"></div>';
	print load_fiche_titre($langs->trans("kreawooCategoryCorrespondence"), '', '');
	print '<p>' . $langs->trans("kreawooCategoryCorrespondenceDescription") . '</p>';

	print '<form method="post" action="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '#category_correspondence">';
	print '<input type="hidden" name="token" value="' . kreawooNewToken() . '">';
	print '<input type="hidden" name="action" value="set_category_correspondence">';

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td class="20p">' . $langs->trans("Parameters") . '</td>';
	print '<td>' . $langs->trans("Description") . '</td>';
	print '<td class="right">' . $langs->trans("Value") . '</td>';
	print '</tr>';

	print '<tr class="oddeven">';
	print '<td class="fieldrequired">' . $langs->trans("kreawooCategoryLocal") . '</td>';
	print '<td>' . $langs->trans("kreawooCategoryLocalDescription") . '</td>';
	print '<td class="right">';
	print $form->selectarray('local_category_id', $siteCategoryOptions, GETPOST('local_category_id', 'int'), 1, 0, 0, '', 0, 0, 0, '', 'minwidth200 centpercent');
	print '</td></tr>';

	print '<tr class="oddeven">';
	print '<td class="fieldrequired">' . $langs->trans("kreawooCategoryRemote") . '</td>';
	print '<td>' . $langs->trans("kreawooCategoryRemoteDescription") . '</td>';
	print '<td class="right">';
	if (!empty($remoteCategoryOptions)) {
		print $form->selectarray('remote_category_id', $remoteCategoryOptions, GETPOST('remote_category_id', 'int'), 1, 0, 0, '', 0, 0, 0, '', 'minwidth300 centpercent');
	} else {
		print '<input type="number" class="flat centpercent" name="remote_category_id" min="1" value="' . ((int) GETPOST('remote_category_id', 'int')) . '">';
		print '<br><span class="opacitymedium">' . $langs->trans("kreawooCategoryRemoteManualInputHint") . '</span>';
	}
	print '</td></tr>';

	print '</table>';
	print '<br><div class="center">';
	print '<input type="submit" class="button" value="' . $langs->trans("kreawooCategorySetCorrespondence") . '">';
	print '</div>';
	print '</form>';

	print '<br>';
	print '<div id="create_remote_category"></div>';
	print load_fiche_titre($langs->trans("kreawooCategoryCreateRemote"), '', '');
	print '<p>' . $langs->trans("kreawooCategoryCreateRemoteDescription") . '</p>';

	print '<form method="post" action="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '#create_remote_category">';
	print '<input type="hidden" name="token" value="' . kreawooNewToken() . '">';
	print '<input type="hidden" name="action" value="create_remote_category">';

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td class="20p">' . $langs->trans("Parameters") . '</td>';
	print '<td>' . $langs->trans("Description") . '</td>';
	print '<td class="right">' . $langs->trans("Value") . '</td>';
	print '</tr>';

	print '<tr class="oddeven">';
	print '<td class="fieldrequired">' . $langs->trans("kreawooCategoryLocal") . '</td>';
	print '<td>' . $langs->trans("kreawooCategoryCreateRemoteSelectDescription") . '</td>';
	print '<td class="right">';
	if (!empty($unmappedSiteCategoryOptions)) {
		print $form->selectarray('local_category_id', $unmappedSiteCategoryOptions, GETPOST('local_category_id', 'int'), 1, 0, 0, '', 0, 0, 0, '', 'minwidth200 centpercent');
	} else {
		print '<span class="opacitymedium">' . $langs->trans("kreawooCategoryNoUnmapped") . '</span>';
	}
	print '</td></tr>';

	print '<tr class="oddeven">';
	print '<td>' . $langs->trans("kreawooCategoryCreateRemoteName") . '</td>';
	print '<td>' . $langs->trans("kreawooCategoryCreateRemoteNameDescription") . '</td>';
	print '<td class="right">';
	print '<input type="text" class="flat minwidth300 centpercent" name="remote_category_name" value="' . dol_escape_htmltag(GETPOST('remote_category_name', 'none')) . '">';
	print '</td></tr>';
	print '</table>';

	print '<br><div class="center">';
	print '<input type="submit" class="button" value="' . $langs->trans("kreawooCategoryCreateRemote") . '"' . (empty($unmappedSiteCategoryOptions) ? ' disabled' : '') . '>';
	print '</div>';
	print '</form>';

	print '<br>';
	print '<div id="category_correspondence_list"></div>';
	print load_fiche_titre($langs->trans("kreawooCategoryCorrespondenceList"), '', '');

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>' . $langs->trans("kreawooCategoryLocal") . '</td>';
	print '<td>' . $langs->trans("kreawooCategoryRemote") . '</td>';
	print '<td class="center">' . $langs->trans("kreawooRemoteID") . '</td>';
	print '<td class="center">' . $langs->trans("DateModification") . '</td>';
	print '<td class="right"></td>';
	print '</tr>';

	$rows = array_values($correspondences);
	usort($rows, function ($a, $b) use ($siteCategoryLabels) {
		$labelA = isset($siteCategoryLabels[$a['fk_category']]) ? $siteCategoryLabels[$a['fk_category']] : ('#' . $a['fk_category']);
		$labelB = isset($siteCategoryLabels[$b['fk_category']]) ? $siteCategoryLabels[$b['fk_category']] : ('#' . $b['fk_category']);
		return strnatcasecmp($labelA, $labelB);
	});

	if (empty($rows)) {
		print '<tr class="oddeven"><td colspan="5"><span class="opacitymedium">' . $langs->trans("None") . '</span></td></tr>';
	} else {
		foreach ($rows as $row) {
			$localLabel = isset($siteCategoryLabels[$row['fk_category']]) ? $siteCategoryLabels[$row['fk_category']] : ('#' . $row['fk_category']);
			$remoteLabel = '-';
			if (!empty($remoteCategories[$row['remote_id']]['name'])) {
				$remoteLabel = $remoteCategories[$row['remote_id']]['name'];
			} elseif (!empty($row['label'])) {
				$remoteLabel = $row['label'];
			}
			$lastUpdate = '';
			if (!empty($row['last_update'])) {
				$lastUpdate = dol_print_date($db->jdate($row['last_update']), 'dayhourtext');
			}

			print '<tr class="oddeven">';
			print '<td>' . dol_escape_htmltag($localLabel) . '</td>';
			print '<td>' . dol_escape_htmltag($remoteLabel) . '</td>';
			print '<td class="center">' . ((int) $row['remote_id']) . '</td>';
			print '<td class="center">' . dol_escape_htmltag($lastUpdate) . '</td>';
			print '<td class="right">';
			print '<a href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&amp;action=delete_category_correspondence&amp;link_id=' . ((int) $row['rowid']) . '&amp;token=' . kreawooNewToken() . '">';
			print img_delete($langs->trans("Delete"));
			print '</a>';
			print '</td>';
			print '</tr>';
		}
	}

	print '</table>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();
