<?php
/**
 * Fichier de la classe context.
 *
 * PHP 5
 *
 * LODEL - Logiciel d'Edition ELectronique.
 *
 * Copyright (c) 2001-2002, Ghislain Picard, Marin Dacos
 * Copyright (c) 2003, Ghislain Picard, Marin Dacos, Luc Santeramo, Nicolas Nutten, Anne Gentil-Beccot
 * Copyright (c) 2004, Ghislain Picard, Marin Dacos, Luc Santeramo, Anne Gentil-Beccot, Bruno C�nou
 * Copyright (c) 2005, Ghislain Picard, Marin Dacos, Luc Santeramo, Gautier Poupeau, Jean Lamy, Bruno C�nou
 * Copyright (c) 2006, Marin Dacos, Luc Santeramo, Bruno C�nou, Jean Lamy, Mika�l Cixous, Sophie Malafosse
 * Copyright (c) 2007, Marin Dacos, Bruno C�nou, Sophie Malafosse, Pierre-Alain Mignot
 *
 * Home page: http://www.lodel.org
 *
 * E-Mail: lodel@lodel.org
 *
 * All Rights Reserved
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 *
 * @author Pierre-Alain Mignot
 * @copyright 2005, Ghislain Picard, Marin Dacos, Luc Santeramo, Gautier Poupeau, Jean Lamy, Bruno C�nou
 * @copyright 2006, Marin Dacos, Luc Santeramo, Bruno C�nou, Jean Lamy, Mika�l Cixous, Sophie Malafosse
 * @copyright 2007, Marin Dacos, Bruno C�nou, Sophie Malafosse, Pierre-Alain Mignot
 * @licence http://www.gnu.org/copyleft/gpl.html
 * @since Fichier ajout� depuis la version 0.9
 */


/**
 * Classe g�rant les acc�s en lecture/�criture dans le context
 * This class manages the read/write from/into the context
 * 
 * Exemples :
 * Examples :
 * <code>
 * // la configuration ne peut �tre mise qu'une seule fois 
 * // et DOIT absolument doit �tre appell�e afin que l'objet s'instancie
 * // configuration can only be set one time and HAS to be called first, it inits the object
 * $conf = array('dbusername'=>'user', 'dbpasswd'=>'passwd');
 * C::setCfg($conf); 
 * // setter
 * C::set('charset', 'utf-8');
 * // getter
 * echo C::get('charset'); // outputs 'utf-8'
 * echo C::get('dbusername', 'cfg'); // outputs 'user'
 * // set and get user vars
 * $lodeluser = array('rights', LEVEL_ADMINLODEL);
 * C::setUser($lodeluser); // will set the user and also copy values into the context
 * echo C::get('rights', 'lodeluser');
 * </code>
 *
 * @package lodel
 * @author Pierre-Alain Mignot
 * @copyright 2005, Ghislain Picard, Marin Dacos, Luc Santeramo, Gautier Poupeau, Jean Lamy, Bruno C�nou
 * @copyright 2006, Marin Dacos, Luc Santeramo, Bruno C�nou, Jean Lamy, Mika�l Cixous, Sophie Malafosse
 * @copyright 2007, Marin Dacos, Bruno C�nou, Sophie Malafosse, Pierre-Alain Mignot
 * @licence http://www.gnu.org/copyleft/gpl.html
 * @since Classe ajout�e depuis la version 0.9
 */

class C
{
	/**
	 * Class instance
	 * @var object 
	 */
	static private $_instance;
	/**
	 * HTMLPurifier instance
	 * @var object 
	 */
	static public $filter;
	/** 
	 * array containing configuration values
	 * @var array
	 */
	static private $_cfg;
	/** 
	 * array containing user informations (rights, session, etc..)
	 * @var array
	 */
	static private $_lodeluser;
	/** 
	 * array containing the current request
	 * @var array
	 */
    	static public $_context;
	/** 
	 * backup of the current request if re-setting context
	 * @var array
	 */
        static private $_backupC;
	/** 
	 * array containing all necessary informations about triggers
	 * @var array
	 */
	static private $_triggers;

	/**
	 * Private constructor called by self::setCfg
	 * Will instantiate the class and set the config values
	 *
	 * @param array $cfg the config vars passed by reference
	 */
	private function __construct(array &$cfg)
	{
		header("Content-Type: text/html; charset=UTF-8");
        	self::$filter = null;
		self::$_lodeluser = array();
		self::$_context = array();
		self::$_cfg = $cfg; // set the config vars
		self::$_triggers = array();
		self::$_triggers['included'] = array();
        	self::$_backupC = array();
		$GLOBALS['tp'] = $GLOBALS['tableprefix'] = $cfg['tableprefix'];
        	if(!defined('SITEROOT')) define('SITEROOT', '');
       		if(!function_exists('checkCacheDir')) include 'cachefunc.php';
	}

	/**
	 * Cloning is not allowed :-)
	 */
	public function __clone()
	{
		trigger_error('Cloning the context is not allowed.', E_USER_ERROR);
	}

	/**
	 * Class instantiation (singleton mode)
	 * 
	 * @param array $cfg the config vars passed by reference
	 */
	static public function setCfg(array &$cfg=array())
	{
		if(!isset(self::$_instance))
		{
			$c = __CLASS__;
			self::$_instance = new $c($cfg);
			$cfg = null; // reset
		}
		else trigger_error('Config already has been set !', E_USER_ERROR);
	}

	/**
	 * This function set self::$_backupC as current context
	 * It is used by the controller if called by a script
	 */
	static public function reset()
	{
		self::$_context = self::$_backupC;
		$GLOBALS['context'] =& self::$_context;
	}

	/**
	 * This function set the current request into the context after cleaning it
	 *
	 * @param array $request the request (can be null if inside call, see auth.php)
	 * @param boolean $controller is it a script call or an inside call ? (by default, inside call)
	 */
	static public function setRequest(array &$request=array(), $controller = false)
	{
		if($controller)
		{
			// inside call, backup the original context for the following
			self::$_backupC = self::$_context;
		}
		else
		{
			$uri = '';
			if(!empty($_GET))
			{
				foreach($_GET as $k=>$v)
				{
					if('clearcache' !== $k && !('id' === $k && (int)$v === 0))
					$uri .= $k."=".$v.'&';
				}
			}
		
			self::$_cfg['qs'] = $uri; // query string for template engine
			unset($uri);
		}
		
		self::$_context = $GLOBALS['context'] = array(); // (re)init context
        
		if(empty($request))
		{
			// POST only
			unset($_GET['login'], $_GET['passwd'], $_GET['passwd2'], $_GET['old_passwd']);

			// ids. Warning: don't remove this, the security in the following rely on these ids are real int !!
			foreach (array('id', 'idgroup', 'idclass', 'idparent', 'idtype', 'identity') as $var) 
			{
				if(isset($_POST[$var]))
				{
					self::$_context[$var] =& $_POST[$var];
					unset($_GET[$var]);
				}
				elseif(isset($_GET[$var]))
				{
					self::$_context[$var] =& $_GET[$var];
				}
				else
				{
					self::$_context[$var] = 0;
					continue;
				}
				self::$_context[$var] = (int)self::$_context[$var];
			}

			self::clean($_GET);
			foreach($_GET as $k=>&$v)
			{
				self::$_context[$k] =& $v;
			}

			if (!empty($_POST)) 
            		{
				self::$_cfg['isPost'] = true; // needed for template engine (save or not calculed page)
				self::clean($_POST);
				foreach($_POST as $k=>&$v)
				{
					self::$_context[$k] =& $v;
				}
			} 
		}
		else
		{
			// ids. Warning: don't remove this, the security in the following rely on these ids are real int !!
			foreach (array('id', 'idgroup', 'idclass', 'idparent', 'idtype', 'identity') as $var) 
			{
				if(isset($request[$var]))
				{
					self::$_context[$var] =& $request[$var];
				}
				else
				{
					self::$_context[$var] = 0;
					continue;
				}
				self::$_context[$var] = (int)self::$_context[$var];
			}

			self::clean($request);
			foreach($request as $k=>&$v)
			{
				self::$_context[$k] =& $v;
			}
		}

		// valid the request
		if (isset(self::$_context['do']))
		{
			if(!preg_match("/^([a-zA-Z]+_)?[a-zA-Z]+$/", self::$_context['do'])) 
				trigger_error("ERROR: invalid action", E_USER_ERROR);
		}

		foreach (array('class', 'classtype', 'type', 'textgroups') as $var) 
		{
			if (isset(self::$_context[$var]))
			{
				// get the various common parameters
				if(!function_exists('validfield')) include 'validfunc.php';
				if(!validfield(self::$_context[$var], $var)) 
					trigger_error("ERROR: a valid $var name is required", E_USER_ERROR);
			}
		}

		// dir
		if (isset(self::$_context['dir']) && !(self::$_context['dir'] == 'up' || self::$_context['dir'] == 'down' || is_numeric(self::$_context['dir'])
            	|| self::$_context['dir'] == 'asc' || self::$_context['dir'] == 'desc'))
		{
			unset(self::$_context['dir']);
		}
		
		if(isset(self::$_context['url_retour']))
			self::$_context['url_retour'] = strip_tags(html_entity_decode(self::$_context['url_retour'], ENT_COMPAT, 'UTF-8'));

		if(!$controller)
		{ // if not called by the controller, let's init template needed vars
			self::$_context['version'] = self::get('version', 'cfg');
			self::$_context['shareurl'] = self::get('shareurl', 'cfg');
			self::$_context['extensionscripts'] = self::get('extensionscripts', 'cfg');
			self::$_context['currenturl'] = 'http://'. $_SERVER['SERVER_NAME']. ($_SERVER['SERVER_PORT']!=80 ? ':'. $_SERVER['SERVER_PORT'] : ''). $_SERVER['REQUEST_URI'];
			self::$_context['siteroot'] = (defined('SITEROOT') ? SITEROOT : '');
			self::$_context['site'] = self::get('site', 'cfg');
			self::$_context['sharedir'] = self::get('sharedir', 'cfg');
			self::$_context['tp'] = self::$_context['tableprefix'] = self::get('tableprefix', 'cfg');
			self::$_context['base_rep'] = array();
			// get all the triggers in self::$_triggers
			self::_getTriggers();
		}

		$GLOBALS['context'] =& self::$_context; // needed by template engine
	}

	/**
	 * Get all the triggers and set self::$_triggers
	 * Will try to reach the cache
	 */
	static private function _getTriggers()
	{
		if(defined('backoffice-lodeladmin')) return true; // no plugins in lodeladmin
    
		if(!(self::$_triggers = getFromCache('triggers')))
		{
			if(!defined('INC_CONNECT')) include 'connect.php';
			global $db;
			$triggers = Plugins::$triggers;
			self::$_triggers = array();
			foreach($triggers as $trigger)
			{
            			self::$_triggers['trigger_'.$trigger] = array();
			}
			
			$trigObj = $db->Execute(lq('
				SELECT * 
					FROM #_MTP_mainplugins
					WHERE status > 0')) 
                		or trigger_error($db->ErrorMsg(), E_USER_ERROR);
			while(!$trigObj->EOF)
			{
				$plug = $db->getOne(lq('
				SELECT config
					FROM #_TP_plugins
					WHERE name="'.addslashes($trigObj->fields['name']).'" AND status > 0'));
				
				if($db->ErrorNo()) trigger_error($db->ErrorMsg(), E_USER_ERROR);

				if($plug)
				{
					$trigger = $trigObj->fields;
					unset($trigger['upd']); // dont care about that
					self::$_triggers[$trigger['name']] = $trigger;
					foreach($triggers as $trig)
					{
						if(!empty($trigger['trigger_'.$trig]))
							self::$_triggers['trigger_'.$trig][$trigger['name']] = $trigger['hooktype'];
					}
					self::$_triggers[$trigger['name']]['config'] = unserialize($plug);
				}
				$trigObj->MoveNext();
			}
            		$trigObj->Close();
            		writeToCache('triggers', self::$_triggers);
		}
	}

	/**
	 * Call all plugins that have the trigger $name
	 *
	 * @param string $name the trigger name
	 */
	static public function trigger($name)
	{
		// no plugins in lodeladmin
		if(defined('backoffice-lodeladmin') || empty(self::$_triggers['trigger_'.$name])) return true;

		foreach(self::$_triggers['trigger_'.$name] as $trigger=>$hooktype)
		{
			if(!isset(self::$_triggers['included'][$trigger]))
			{
				include self::$_cfg['sharedir'].'/plugins/custom/'.$trigger.'/'.$trigger.'.php';
				if('class' === $hooktype) 
				{
					call_user_func(array($trigger, 'init'), $trigger);
				}
				self::$_triggers['included'][$trigger] = true;
			}
            
			switch($hooktype)
			{
				case 'class':
					if(!method_exists($trigger, $name))
						trigger_error('Invalid trigger : '.$trigger.'::'.$name, E_USER_ERROR);
					// important here : we need to pass the context by reference
					// only possible with call_user_func_array
					// until people go to 5.3
					call_user_func_array(array($trigger, $name), array(&self::$_context));
					// return $trigger::$name(self::$_context); // PHP 5.3
					break;
				
				case 'func':
					$func = $trigger.'_'.$name;
					if(!function_exists($func))
						trigger_error('Invalid trigger : '.$func, E_USER_ERROR);
					$func(self::$_context);
					break;
			}
		}
	}

	/**
	 * Gets a value.
	 * This function supports multi-dimensionnal arrays : echo C::get('options.language'); // == self::$_context['options']['language']
	 *
	 * @param string $v the name of the var to get
	 * @param string $arr the array to search into
	 */
	static public function get($v=null, $arr=null)
	{
		if(isset($arr))
		{
			if(!isset(self::${"_{$arr}"})) return false;
			if(!isset($v))	return self::${"_{$arr}"};

			if(false === strpos($v, '.')) return isset(self::${"_{$arr}"}[$v]) ? self::${"_{$arr}"}[$v] : false;

			$vars = explode('.', $v);
			$return = self::${"_{$arr}"};
			foreach($vars as $var)
			{
				if(!isset($return[$var])) return false;
				$return = $return[$var];
			}

			return $return;
		}
		elseif(isset($v))
		{
			if(false === strpos($v, '.')) return isset(self::$_context[$v]) ? self::$_context[$v] : false;

			$vars = explode('.', $v);
			$return = self::$_context;
			foreach($vars as $var)
			{
				if(!isset($return[$var])) return false;
				$return = $return[$var];
			}

			return $return;
		}
		
		return self::$_context;
	}

	/**
	 * Gets the context.
	 * Warning : the returned array is a reference to self::$_context
	 */
	static public function &getC()
	{
		return self::$_context;
	}

	/**
	 * Merge the passed array into the current context
	 * Warning : we assume that the values have already been sanitized !!
	 *
	 * @param array $datas
	 */
	static public function mergeC(array &$datas)
	{
		return (self::$_context = array_merge($datas, self::$_context));
	}

	/**
	 * Sets a value ONLY into self::$_context.
	 * This function supports multi-dimensionnal arrays : C::set('options.language', 'fr'); // == self::$_context['options']['language'] = 'fr';
	 *
	 * @param string $n the name of the var to set
	 * @param mixed $v the value
	 */
	static public function set($n, $v)
	{
		if(false === strpos($n, '.')) return ('lodeluser' !== (string)$n ? (self::$_context[$n] = $v) : false);

		$vars = explode('.', $n);
		if('lodeluser' === (string)$vars[0]) return false; // haha
		
		$set =& self::$_context;
		foreach($vars as $var)
		{
			if(!isset($set[$var])) $set[$var] = array();
			$set =& $set[$var];	
		}
		
		return ($set = $v);
	}

	/**
	 * Set the user vars
	 *
	 * @param array $v the values
	 * @param string $n the array to set
	 */
	static public function setUser($v=null, $n=null)
	{
		if(!isset($n))
		{
			if(!isset($v)) return (self::$_lodeluser = self::$_context['lodeluser'] = null);
			elseif(empty(self::$_lodeluser))
			{
				self::$_lodeluser = self::$_context['lodeluser'] = $v;
                		// don't want to have access to the session id or name in templates
				unset(self::$_context['lodeluser']['session'],self::$_context['lodeluser']['idsession']);
				return true;
			}
			else return false;
		}

		$n = (string)$n;
        
		if(false === strpos($n, '.'))
		{
			if(isset(self::$_lodeluser[$n]))
			{
				return (!isset($v) ? (self::$_lodeluser[$n] = self::$_context['lodeluser'][$n] = $v) : false);
			}
			else 
			{
				self::$_lodeluser[$n] = array();
				self::$_context['lodeluser'][$n] = array();
				self::$_lodeluser[$n] = $v;
				// don't want to have access to the session id or name in templates
				return (('idsession' === $n || 'session' === $n) ? true : self::$_context['lodeluser'][$n] = $v);
			}
		}
		else
		{
			$vars = explode('.', $n);
			$set =& self::$_lodeluser;
			foreach($vars as $var)
			{
				if(!isset($set[$var])) $set[$var] = array();
				$set =& $set[$var];	
			}
			
			return ($set = $v);
		}
		return false;
	}

	/**
	 * Public function to clean input datas
	 *
	 * @param mixed $data the value to sanitize (can be either a string or an array)
	 */
	static public function clean(&$data)
	{
		if(is_array($data)) 
		{
			array_walk_recursive($data, array('self', '_sanitize'));
		}
		else 
		{
			self::_sanitize($data);
		}
        
		return $data;
	}

	/**
	 * Private function to clean input datas
	 * Uses HTMLPurifier
	 *
	 * @param mixed $data the value to sanitize (has to be string)
	 */	
	static private function _sanitize(&$data)
	{
		if(!is_string($data)) return true; // useless on boolean, integer, objects or other types..
        
		if(!isset(self::$filter))
		{
			checkCacheDir('htmlpurifier');
		
			if(!class_exists('HTMLPurifier', false))
				include 'htmlpurifier/HTMLPurifier.standalone.php';
			$config = HTMLPurifier_Config::createDefault();
		
			// custom Lodel filters
			if(file_exists(self::$_cfg['home'].'htmlpurifierFilters.php')) include 'htmlpurifierFilters.php';
			// custom personnal filters
			if(file_exists(self::$_cfg['home'].'htmlpurifierFilters_local.php')) include 'htmlpurifierFilters_local.php';
			$config->set('Core', 'Encoding', 'UTF-8');
			$config->set('HTML', 'TidyLevel', 'heavy' );
			$config->set('Attr', 'EnableID', true);
			$config->set('Cache', 'SerializerPath', realpath('./CACHE/htmlpurifier/') );
			$config->set('HTML', 'Doctype', 'XHTML 1.0 Strict'); // replace with your doctype
			$config->set('HTML', 'DefinitionID', 'r2r:ml no namespaces allowed');
			$config->set('HTML', 'DefinitionRev', 1);
			$config->set('HTML', 'SafeObject', true);
			$config->set('HTML', 'SafeEmbed', true);
			$def = $config->getHTMLDefinition(true);
			$r2r = $def->addElement(
			'r2r',   // name
			'Block',  // content set
			'Flow', // allowed children
			'IL8N', // attribute collection
			array( // attributes
			'lang' => 'CDATA')
			);
			$r2r->excludes = array('r2r' => true);
			
			self::$filter = new HTMLPurifier($config);
			unset($config, $def, $r2r);
		}
	
		// htmlpurifier does not support namespaces
		$data = (get_magic_quotes_gpc() ? strtr(trim(stripslashes($data)), array('<r2r:ml '=>'<r2r ', '</r2r:ml>'=>'</r2r>')) : 
						  strtr(trim($data), array('<r2r:ml '=>'<r2r ', '</r2r:ml>'=>'</r2r>')));
		$data = self::$filter->purify($data);
		$data = strtr($data, array('<r2r '=>'<r2r:ml ', '</r2r>'=>'</r2r:ml>'));
		return true;
	}
}

/**
 * Autoload of internal classes
 *
 * @param string $class the class name
 */
function __autoload($class)
{
	if(class_exists($class, false)) return true;
	
	$c = strtolower($class);

	if('logic' !== $c && 'genericlogic' !== $c && FALSE !== strpos($c,'logic')) // logic
	{
		$file = C::get('home', 'cfg').'logic'.DIRECTORY_SEPARATOR.'class.'.substr($c, 0, -5).'.php';
	}
	elseif('genericdao' !== $c && 'dao' !== $c && FALSE !== strpos($c, 'dao')) // dao
	{
		$file = C::get('home', 'cfg').'dao'.DIRECTORY_SEPARATOR.'class.'.substr($c, 0, -3).'.php';
	}
	elseif('vo' == substr($c, -2)) // VO (=dao)
	{
		$file = C::get('home', 'cfg').'dao'.DIRECTORY_SEPARATOR.'class.'.substr($c, 0, -2).'.php';
	}
	else
	{
		$count = 0;
		$file = C::get('home', 'cfg').str_replace('_', DIRECTORY_SEPARATOR, $class, $count).'.php';
		if(!$count) $file = strtolower($file);
	}

	$file = realpath($file);

	if(!$file) return false;

	include $file;
	
	return class_exists($class, false);
}
?>