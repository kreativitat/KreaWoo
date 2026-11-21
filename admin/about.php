<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2025		T. Negre <tnegre@opendsi.fr>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2024-2026  Kreativitat  <mail@kreativitat.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    kreawoo/admin/about.php
 * \ingroup kreawoo
 * \brief   About page of module KreaWoo.
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

// Libraries
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once '../lib/kreawoo.lib.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

// Translations
$langs->loadLangs(array("errors", "admin", "kreawoo@kreawoo", "kreativitat@kreawoo"));

// Access control
if (!$user->admin) {
	accessforbidden();
}

// Parameters
$id = GETPOST('id', 'int');
$backtopage = GETPOST('backtopage', 'alpha');

$object = new kreawooSite($db);
if (!($id > 0)) {
	$sites = $object->listSites();
	if (!empty($sites)) {
		$id = (int) array_values($sites)[0]['id'];
	}
}
if ($id > 0) {
	$result = $object->fetch($id);
	if ($result < 0) {
		accessforbidden($object->errorsToString());
	} elseif ($result == 0) {
		$langs->load('errors');
		accessforbidden($langs->trans('ErrorRecordNotFound'));
	}
}

/*
 * Actions
 */

// None


/*
 * View
 */

$form = new Form($db);

$help_url = '';
$title = "kreawooSetup";

llxHeader('', $langs->trans($title), $help_url, '', 0, 0, '', ['/kreawoo/css/about.css'], '', 'mod-kreawoo page-admin_about');

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

// Configuration header
$head = kreawooConfigSitePrepareHead($object);
print dol_get_fiche_head($head, 'about', $langs->trans($title), 0, 'kreawoo@kreawoo');

dol_include_once('/kreawoo/core/modules/modKreaWoo.class.php');
$tmpmodule = new modKreaWoo($db);

print '<div class="moduledesclong">';
print '<h2>Disclaimer</h2>';
print '<p>This module is provided \"as is\", without warranties of any kind, express or implied, including merchantability and fitness for a particular purpose. The user is solely responsible for configuration quality, data validation, and legal/accounting compliance in their jurisdiction.</p>';
print '</div>';

// Custom about block with branding and key facts
$logoUrl = dol_buildpath('/custom/kreaproducts/img/logo.png', 1);
$moduleName = $tmpmodule->getName();
$moduleVersion = $tmpmodule->getVersion();
$editorName = $tmpmodule->editor_name;
$editorUrl = $tmpmodule->editor_url;
$supportEmail = 'mail@kreativitat.com';

print '<div class="fichecenter" style="margin-top: 18px; display:flex; gap:24px; align-items:flex-start; flex-wrap:wrap;">';

print '<div style="flex:1 1 260px; max-width:320px; text-align:center;">';
print '<img src="' . $logoUrl . '" alt="' . dol_escape_htmltag($moduleName) . '" style="max-width: 260px; height: auto;">';
print '</div>';

print '<div style="flex:2 1 340px; min-width:320px;">';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield">' . $langs->trans('KreapAboutModuleLabel') . '</td><td>' . dol_escape_htmltag($moduleName) . '</td></tr>';
print '<tr><td class="titlefield">' . $langs->trans('KreapAboutVersionLabel') . '</td><td>' . dol_escape_htmltag($moduleVersion) . '</td></tr>';
print '<tr><td class="titlefield">' . $langs->trans('KreapAboutEditorLabel') . '</td><td><a href="' . dol_escape_htmltag($editorUrl) . '" target="_blank" rel="noopener noreferrer">' . dol_escape_htmltag($editorName) . '</a></td></tr>';
print '<tr><td class="titlefield">' . $langs->trans('KreapAboutLicenseLabel') . '</td><td>' . $langs->trans('KreapAboutLicenseValue') . '</td></tr>';
print '<tr><td class="titlefield">' . $langs->trans('KreapAboutSupportLabel') . '</td><td><a href="mailto:' . dol_escape_htmltag($supportEmail) . '">' . dol_escape_htmltag($supportEmail) . '</a></td></tr>';
print '<tr><td class="titlefield">' . $langs->trans('KreapAboutWebsiteLabel') . '</td><td><a href="' . dol_escape_htmltag($editorUrl) . '" target="_blank" rel="noopener noreferrer">' . dol_escape_htmltag($editorUrl) . '</a></td></tr>';
print '</table>';
print '</div>';
print '</div>';

// Page end
print dol_get_fiche_end();
llxFooter();
$db->close();
