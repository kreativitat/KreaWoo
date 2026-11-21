<?php
/* Copyright (C) 2010 Franck Charpentier - Auguria <franck.charpentier@auguria.net>
 * Copyright (C) 2013 Laurent Destailleur          <eldy@users.sourceforge.net>
 * Copyright (C) 2026 Kreativität Works <mail@kreativitat.com>
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


/**
 * Class for update menus
 */

dol_include_once('/kreawoo/core/modules/modKreaWoo.class.php');
dol_include_once('/kreawoo/class/data/kreawooSite.class.php');

class kreawooMenu
{
	private $module;
	private $db;
	private $siteDb;

    function __construct($db, $siteDb=null, $modkreawoo=null)
    {
    	$this->db = $db;

    	if ($siteDb==null)
    		$this->siteDb = new kreawooSite($this->db);
    	else
    		$this->siteDb = $siteDb;

    	if ($modkreawoo!=null)
        	$this->module = $modkreawoo;

        return 1;
    }
    /**
     * @return array menu
     */
    function getMenu()
    {
    	$menu = array();

    	//define top menu
//    	$menu[0]=array(	'fk_menu'=>'',
//    					'type'=>'top',
//    					'titre'=>'kreawooMenu',
//    					'mainmenu'=>'kreawoo',
//    					'leftmenu'=>'kreawoo',
//    					'url'=>'/kreawoo/index.php',
//    					'langs'=>'kreawoo@kreawoo',
//    					'position'=>100,
//    					'enabled'=>'!empty($conf->kreawoo->enabled)',
//    					'perms'=>'!empty($user->rights->kreawoo->read)',
//    					'target'=>'',
//    					'user'=>2);
    	//define main left menu
        $menu[0]=array(	'fk_menu'=>'fk_mainmenu=tools',
       					'type'=>'left',
       					'titre'=>'kreawooMenuMain',
       	                'leftmenu'=>'kreawoo',
       					'url'=>'/kreawoo/index.php',
       					'langs'=>'kreawoo@kreawoo',
							'position'=>100,
							'enabled'=>'!empty($conf->kreawoo->enabled)',
							'perms'=>'!empty($user->rights->kreawoo->read)',
       					'target'=>'',
       					'user'=>2);

        //define left menu
        $menu[1]=array(	'fk_menu'=>'fk_mainmenu=tools,fk_leftmenu=kreawoo',
       					'type'=>'left',
       					'titre'=>'kreawooMenuSites',
      	                'leftmenu'=>'kreawoo_sites',
       					'url'=>'/kreawoo/index.php',
       					'langs'=>'kreawoo@kreawoo',
							'position'=>110,
							'enabled'=>'!empty($conf->kreawoo->enabled)',
							'perms'=>'!empty($user->rights->kreawoo->read)',
       					'target'=>'',
       					'user'=>2);

    	//add link to configuration
    	$menu[2]=array(	'fk_menu'=>'fk_mainmenu=tools,fk_leftmenu=kreawoo',
    					'type'=>'left',
    					'titre'=>'kreawooMenuSetup',
    	                'leftmenu'=>'kreawoo_setup',
    					'url'=>'/kreawoo/admin/setup.php',
    					'langs'=>'kreawoo@kreawoo',
							'position'=>120,
							'enabled'=>'!empty($conf->kreawoo->enabled)',
							'perms'=>'!empty($user->rights->kreawoo->site)',
    					'target'=>'',
    					'user'=>2);

		// Add links for webhooks
		$menu[3] = array( 'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=kreawoo',
						  'type' => 'left',
						  'titre' => 'kreawooMenuWebHooks',
						  'leftmenu' => 'kreawoo_webhooks',
						  'url' => '/kreawoo/webhookslist.php',
						  'langs' => 'kreawoo@kreawoo',
						  'position' => 130,
						  'enabled' => '!empty($conf->kreawoo->enabled)',
						  'perms' => '!empty($user->rights->kreawoo->read)',
						  'target' => '',
						  'user' => 2);

    	//add submenu foreach site
    	/* We can't return content of menu that depends on tables, because we must know menus entries when we activate menu, and this must not change.
    	$sites = $this->siteDb->listSites();
    	if (count($this->siteDb))
    		foreach ($sites as $site)
    			$menu[]=array(	'fk_menu'=>'fk_mainmenu=tools,fk_leftmenu=kreawoo',
    							'type'=>'left',
    							'titre'=>$site['name'],
    							'mainmenu'=>'kreawoo',
    			                'leftmenu'=>'kreawoo_site'.$site['id'],
    							'url'=>'/kreawoo/site.php?id='.$site['id'],
    							'langs'=>'kreawoo@kreawoo',
    							'position'=>130,
									'enabled'=>'!empty($conf->kreawoo->enabled)',
									'perms'=>'!empty($user->rights->kreawoo->read)',
    							'target'=>'',
    							'user'=>2);
    	*/
    	return $menu;
    }

    /**
     * Update menu into database
     */
    function updateMenu()
    {
    	if ($this->module == null)
        	$this->module = new modKreaWoo($this->db);

    	$this->module->menu = $this->getMenu();
    	$this->module->db->begin();
		$this->module->delete_menus();
		$this->module->insert_menus();
		$this->module->db->commit();
		return 1;
    }
}
