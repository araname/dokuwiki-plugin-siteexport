<?php
if(!defined('DOKU_INC')) define('DOKU_INC',fullpath(dirname(__FILE__).'/../../../').'/');

@include_once(DOKU_INC . 'inc/plugincontroller.class.php');

class preload_plugin_siteexport {

	function __register_template() {
	
		if ( !empty($_REQUEST['q']) ) {

			require_once( DOKU_INC . 'inc/JSON.php');
			$json = new JSON();
			$tempREQUEST = (array)$json->dec(stripslashes($_REQUEST['q']));

		} else if ( !empty( $_REQUEST['template'] ) ) {
			$tempREQUEST = $_REQUEST;
		} else {
			return;
		}

		// define Template baseURL
		if ( empty($tempREQUEST['template']) ) { return; }
		$tplDir = DOKU_INC.'lib/tpl/'.$tempREQUEST['template'].'/';

		if ( !file_exists($tplDir) ) { return; }
		
		// Set hint for Dokuwiki_Started event
		if (!defined('SITEEXPORT_TPL'))		define('SITEEXPORT_TPL', $tempREQUEST['template']);

		// define baseURL
		// This should be DEPRECATED - as it is in init.php which suggest tpl_basedir and tpl_incdir
		/* **************************************************************************************** */
		if(!defined('DOKU_REL')) define('DOKU_REL',getBaseURL(false));
		if(!defined('DOKU_URL')) define('DOKU_URL',getBaseURL(true));
		if(!defined('DOKU_BASE')){
			if($conf['canonical']){
				define('DOKU_BASE',DOKU_URL);
			}else{
				define('DOKU_BASE',DOKU_REL);
			}
		}

		// This should be DEPRECATED - as it is in init.php which suggest tpl_basedir and tpl_incdir
		if (!defined('DOKU_TPL'))			define('DOKU_TPL', (empty($tempREQUEST['base']) ? DOKU_BASE : $tempREQUEST['base']) . 'lib/tpl/'.$tempREQUEST['template'].'/');
		if (!defined('DOKU_TPLINC'))		define('DOKU_TPLINC', $tplDir);
		/* **************************************************************************************** */
	}

	function __temporary_disable_plugins() {

		// Check for siteexport - otherwise this does not matter.
		if ( empty($_REQUEST['do']) || $_REQUEST['do'] != 'siteexport' ) {
			return;
		}

		// check for css and js as well ...
		if ( !preg_match("/(js|css)\.php$/", $_SERVER['SCRIPT_NAME']) ) {
			return;
		}

		//		print "removing plugins ";
		$_GET['purge'] = 'purge'; //activate purging
		$_POST['purge'] = 'purge'; //activate purging
		$_REQUEST['purge'] = 'purge'; //activate purging
		
		$_SERVER['HTTP_HOST'] = 'siteexport.js'; // fake everything in here
		
		require_once(DOKU_INC.'inc/plugincontroller.class.php'); // Have to get the pluginutils already
		require_once(DOKU_INC.'inc/pluginutils.php'); // Have to get the pluginutils already
		$this->__disablePlugins();
	}

	function __disablePlugins() {
		global $plugin_controller_class, $plugin_controller;
		
		$plugin_controller_class = 'preload_plugin_siteexport_controller';	
	}

	function __create_preload_function() {

		$PRELOADFILE = DOKU_INC.'inc/preload.php';
		$CURRENTFILE = 'DOKU_INC' . " . 'lib/plugins/siteexport/preload.php'";
		$CONTENT = <<<OUTPUT
/* SITE EXPORT *********************************************************** */
	if ( file_exists($CURRENTFILE) ) {
		include_once($CURRENTFILE);
		\$siteexport_preload = new preload_plugin_siteexport();
		\$siteexport_preload->__register_template();
		\$siteexport_preload->__temporary_disable_plugins();
		unset(\$siteexport_preload);
	}
/* SITE EXPORT END *********************************************************** */

OUTPUT;

		if ( file_exists($PRELOADFILE) ) {

			if ( ! is_readable($PRELOADFILE) ) {
				msg("Preload File locked. It exists, but it can't be read.", -1);
				return false;
			}

			if ( !is_writeable($PRELOADFILE) ) {
				msg("Preload File locked. It exists and is readable, but it can't be written.", -1);
				return false;
			}

			$fileContent = file($PRELOADFILE);
			if ( !strstr(implode("", $fileContent), $CONTENT) ) {

				$fp = fopen($PRELOADFILE, "a");
				fputs($fp, "\n".$CONTENT);
				fclose($fp);
			}

			return true;

		} else if ( is_writeable(DOKU_INC . 'inc/') ) {

			$fp = fopen($PRELOADFILE,"w");
			fputs($fp, "<?php\n/*\n * Dokuwiki Preload File\n * Auto-generated by Site Export plugin \n * Date: ".date('Y-m-d H:s:i')."\n */\n");
			fputs($fp, $CONTENT);
			fputs($fp, "// end auto-generated content\n\n");
			fclose($fp);

			return true;
		}

		msg("Could not create/modify preload.php. Please check the write permissions for your DokuWiki/inc directory.", -1);
		return false;
	}

}

// return a custom plugin list
class preload_plugin_siteexport_controller extends Doku_Plugin_Controller {

	function getList($type='',$all=false){
		
		$allPlugin = parent::getList(null, true);
		$oldPluginsEnabled = parent::getList(null, false);
		$currentPluginsDisabled = empty($_REQUEST['diPlu']) ? array() : $_REQUEST['diPlu'];
		$pluginsDisabledInverse = !empty($_REQUEST['diInv']);

		if ( !$this->list_enabled ) {
			$this->list_enabled = array();
		}

		// All plugins that are not already disabled are to be disabled
		$toDisable = !$pluginsDisabledInverse ? array_diff($currentPluginsDisabled, array_diff($allPlugin, $oldPluginsEnabled)) : array_diff(array_diff($allPlugin, $currentPluginsDisabled), array_diff($allPlugin, $oldPluginsEnabled));
		
	    foreach ( $toDisable as $plugin ) {
	    
			if ( !in_array($plugin, $allPlugin) ) { continue; }
			$this->list_enabled = array_diff($this->tmp_plugins, array($plugin));
			$this->list_disabled[] = $plugin;
		}

	    foreach($this->list_enabled as $plugin ) {
	    	// check for CSS or JS
	    	if ( !file_exists(DOKU_PLUGIN."$plugin/script.js") && !file_exists(DOKU_PLUGIN."$plugin/style.css") ) {
	    		unset($this->tmp_plugins[$plugin]);
				$this->list_disabled[] = $plugin;
	    	}
	    }
	    
	    return parent::getList($type,$all);
	}
}


?>