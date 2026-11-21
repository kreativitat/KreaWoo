<?php
llxHeader();

dol_include_once("/kreawoo/lib/kreawoo.lib.php");
$form = new Form($db);

$linkback='';
print load_fiche_titre($langs->trans("kreawooDashboard"), $linkback, 'title_setup');

print '<br>';

if (count($sites)): ?>
	<div class="div-table-responsive">
	<table class="noborder" width="100%">
		<tr class="liste_titre">
			<td><?php print $langs->trans('kreawooSite') ?></td>
			<td><?php print $langs->trans('kreawooLastUpdate') ?></td>
			<td colspan="2">&nbsp;</td>
		</tr>
<?php
$var=!$var;
	foreach ($sites as $site)
	{ ?>
		<tr <?php print $bc[$var] ?>>
			<td><?php print $site->name ?></td>
			<td>
				<?php
					if ($site->last_update)
					{
					    //print $site['last_update'];
						print dol_print_date($site->last_update, 'dayhour');
					}
					else
						print $langs->trans('kreawooNoUpdateSite');
				?>
			</td>
			<td><div style="inline-block">
				<form class="inline-block" style="margin-right: 10px" name="form_index" id="form_detailed" action="<?php print dol_buildpath('/kreawoo/site.php',1); ?>?id=<?php echo $site->id ?><?php echo GETPOST('to_date','int')?'&to_date='.GETPOST('to_date','int'):''; ?>" method="post">
					<input type="hidden" name="token" value="<?php print kreawooNewToken() ?>">
					<input type="hidden" name="id" value="<?php print $site->id ?>">
					<input class="button" type="submit" name="submit_detailed" value="<?php print $langs->trans('kreawooUpdateSite') ?>">
				</form>
				<form class="inline-block" name="form_index" id="form_global" action="<?php print dol_buildpath('/kreawoo/site.php', 1); ?>?id=<?php echo $site->id; ?><?php echo GETPOST('to_date','int')?'&to_date='.GETPOST('to_date','int'):''; ?>" method="post">
					<input type="hidden" name="token" value="<?php print kreawooNewToken() ?>">
					<input type="hidden" name="id" value="<?php print $site->id ?>">
					<input class="button" type="submit" name="submit_synchro_all" value="<?php print $langs->trans('kreawooUpdateAll') ?>">
				</form>
				</div>
			</td>
			<td>
				<?php $url=$site->getFrontUrl(); print $langs->trans("FrontOffice").': <a href="'.$url.'" target="_frontsite">'.$url.'</a>'; ?>
				<?php $url=$site->getBackUrl();
				print '<br>';
				print $form->textwithpicto($langs->trans("BackOffice"), $langs->trans("SeekreawooConfFileIfKo",'app/etc/local.xml'));
				print ': <a href="'.$url.'" target="_backsite">'.$url.'</a>'; ?>
			</td>
		</tr>
	<?php } ?>
	</table>
	</div>
<?php else: ?>
<p><?php $langs->trans('kreawooNoSite') ?></p>
<?php endif;
llxFooter();