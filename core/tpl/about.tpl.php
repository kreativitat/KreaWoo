<?php
/* Copyright (C) 2025		Lionel Vessiller		<lvessiller@open-dsi.fr>
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
 * @var Conf $conf
 * @var Translate $langs
 * @var User $user
 *
 * @var DolibarrModules $modClass
 *
 */

if (empty($user) || !is_object($user)) {
	print "Error, template page can't be called as URL";
	exit(1);
} elseif (empty($modClass) || !is_object($modClass)) {
	print "Error, template page can't be called as URL";
	exit(1);
}

if (!function_exists('dolPrintHTML')) {
    function dolPrintHTML($s)
    {
        // Original DLB version. dol_htmlwithnojs() is long .
        // return dol_escape_htmltag(dol_htmlwithnojs(dol_string_onlythesehtmltags(dol_htmlentitiesbr($s), 1, 1, 1)), 1, 1, 'common', 0, 1);
        return dol_escape_htmltag(dol_string_onlythesehtmltags(dol_htmlentitiesbr($s), 1, 1, 1), 1, 1, 'common', 0, 1);
    }
}

$editorCommercialEmail = 'info@kreativitat.com';
$editorCommercialTel = '';
$editorName = $modClass->editor_name;
$editorSupportCreateTicketUrl = 'http://kreativitat.com';
$editorStoreLink = '<a href="https://www.dolistore.com/index.php?search_query=kreativitat" target="_blank">Dolistore</a>';

$supportMessage = "/*****"."<br>";
$supportMessage .= " * ".$langs->trans('kreawooAboutSupportLineModule')." : ".$langs->trans('Module'.$modClass->numero.'Name')."<br>";
$supportMessage .= " * ".$langs->trans('kreawooAboutSupportLineModuleVersion')." : ".(!empty($modClass->getVersion()) ? $modClass->getVersion() : 'NC')."<br>";
$supportMessage .= " * ".$langs->trans('kreawooAboutSupportLineDolibarrVersion')." : ".DOL_VERSION."<br>";
$supportMessage .= " * ".$langs->trans('kreawooAboutSupportLineDolibarrInitialVersion')." : ".getDolGlobalString('MAIN_VERSION_LAST_INSTALL')."<br>";
$supportMessage .= " * ".$langs->trans('kreawooAboutSupportLineCheckboxLeftColumn')." : ".getDolGlobalInt('MAIN_CHECKBOX_LEFT_COLUMN', 0)."<br>";
$supportMessage .= " * ".$langs->trans('kreawooAboutSupportLinePhpVersion')." : ".PHP_VERSION."<br>";
$supportMessage .= " *****/"."<br>";
$supportMessage .= $langs->trans('kreawooAboutSupportIssueDescription')."<br>";

?>

<!-- BEGIN TEMPLATE -->
<div class="about-tab">

    <?php if (version_compare(DOL_VERSION, '14.0.0') >= 0): ?>
    <script>
        $().ready(() => {
            $('.clipboardCPValue').hide()
        })
    </script>
    <?php endif; ?>

	<div class="about-header">
		<img class="brand-logo" src="../img/object_kreawoo.png" alt="<?php echo $editorName; ?>">
		<div class="right">
			<img class="preferred-partner-logo" src="../img/dolibarr_preferred_partner.png" alt="Preferred Partner">
		</div>
	</div>
	<div class="about-body">
		<h2><?php echo $langs->trans('kreawooAboutModuleDevelopedBy', $editorName); ?></h2>
		<p><?php echo $langs->trans('kreawooAboutModuleDesignedBy', $editorName); ?></p>
		<p><?php echo $langs->trans('kreawooAboutCommitment'); ?></p>
		<p class="editor-preferred-partner"><?php echo $langs->trans('kreawooAboutPreferredPartner', $editorName); ?></p>

		<h2><?php echo $langs->trans('kreawooAboutTechnicalSupportTitle'); ?></h2>
		<p><?php echo $langs->trans('kreawooAboutTechnicalSupportDesc'); ?></p>
		<div class="center">
			<form id="ticket" method="POST" target="_blank" action="<?php echo $editorSupportCreateTicketUrl; ?>">
				<input name="message" type="hidden" value="<?php echo $supportMessage; ?>" />
				<input name="email" type="hidden" value="<?php echo $user->email; ?>" />
				<div class="center">
					<button class="support" type="submit"><?php echo $langs->trans('kreawooAboutAccessSupport'); ?></button>
					<div class="btn-copy-clipboard"><?php print showValueWithClipboardCPButton(dolPrintHTML($supportMessage), 0, $langs->trans('kreawooAboutCopyTechnicalInfo')); ?></div>
				</div>
			</form>
		</div>

		<br />
		<div class="center">

		</div>

		<h2><?php echo $langs->trans('kreawooAboutCommercialContactTitle'); ?></h2>
		<p><?php echo $langs->trans('kreawooAboutCommercialContactDesc'); ?></p>
		<ul>
			<li><a href="<?php echo "mailto:".$editorCommercialEmail; ?>"><?php echo $editorCommercialEmail; ?></a></li>
			<?php if (!empty($editorCommercialTel)) { ?>
			<li><?php echo $editorCommercialTel; ?></li>
			<?php } ?>
		</ul>
	</div>
	<div class="about-footer">
		<h2><?php echo $langs->trans('kreawooAboutDiscoverModulesTitle'); ?></h2>
		<p><?php echo $langs->trans('kreawooAboutDiscoverModulesDesc', $editorStoreLink); ?></p>
	</div>
</div>
<!-- END TEMPLATE -->
