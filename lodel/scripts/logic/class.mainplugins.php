<?php
/**	
 * Logique des plugins
 *
 * PHP version 5
 *
 * LODEL - Logiciel d'Edition ELectronique.
 *
 * Home page: http://www.lodel.org
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
 * @copyright 2001-2002, Ghislain Picard, Marin Dacos
 * @copyright 2003, Ghislain Picard, Marin Dacos, Luc Santeramo, Nicolas Nutten, Anne Gentil-Beccot
 * @copyright 2004, Ghislain Picard, Marin Dacos, Luc Santeramo, Anne Gentil-Beccot, Bruno C�nou
 * @copyright 2005, Ghislain Picard, Marin Dacos, Luc Santeramo, Gautier Poupeau, Jean Lamy, Bruno C�nou
 * @copyright 2006, Marin Dacos, Luc Santeramo, Bruno C�nou, Jean Lamy, Mika�l Cixous, Sophie Malafosse
 * @copyright 2007, Marin Dacos, Bruno C�nou, Sophie Malafosse, Pierre-Alain Mignot
 * @licence http://www.gnu.org/copyleft/gpl.html
 * @since Fichier ajout� depuis la version 0.9
 * @version CVS:$Id: class.mainplugins.php 4646 2009-01-28 14:53:49Z mignot $
 */

/**
 * Classe de logique des plugins
 * 
 * @package lodel/logic
 * @author Ghislain Picard
 * @author Jean Lamy
 * @author Pierre-Alain Mignot
 * @copyright 2001-2002, Ghislain Picard, Marin Dacos
 * @copyright 2003, Ghislain Picard, Marin Dacos, Luc Santeramo, Nicolas Nutten, Anne Gentil-Beccot
 * @copyright 2004, Ghislain Picard, Marin Dacos, Luc Santeramo, Anne Gentil-Beccot, Bruno C�nou
 * @copyright 2005, Ghislain Picard, Marin Dacos, Luc Santeramo, Gautier Poupeau, Jean Lamy, Bruno C�nou
 * @copyright 2006, Marin Dacos, Luc Santeramo, Bruno C�nou, Jean Lamy, Mika�l Cixous, Sophie Malafosse
 * @copyright 2007, Marin Dacos, Bruno C�nou, Sophie Malafosse, Pierre-Alain Mignot
 * @licence http://www.gnu.org/copyleft/gpl.html
 * @since Classe ajout� depuis la version 0.9
 * @see logic.php
 */
class MainPluginsLogic extends Logic
{

	/**
	* generic equivalent assoc array
	*/
	public $g_name;

	protected $plugin; // current plugin

	protected $_triggers; // triggers list

	/**
	 * Constructor
	 */
	public function __construct($logic=null)
	{
		// only lodel admin can do something with plugins
		$lodeladminActions = array('list', 'enable', 'disable', 'activate', 'desactivate', 'edit', 'enableall', 'disableall');
		if(!C::get('adminlodel', 'lodeluser') && ((!isset($logic) && in_array(C::get('do'), $lodeladminActions)) || 
			(isset($logic) && in_array(C::get('do'), array('enable', 'disable'))))) 
				trigger_error('You don\'t have the rights to do that !', E_USER_ERROR);
		parent::__construct(isset($logic) ? $logic : 'mainplugins');

		$this->_triggers = Plugins::$triggers;

		C::set('triggers', $this->_triggers);
	}

	/**
	 * Wrapper for plugin method/func hook
	 */
	public function factory(&$context, &$error, $name, $return=false) 
	{
		$name = explode('_', $name); // $name[0] == classname, $name[1] == funcname

		$path = C::get('sharedir', 'cfg').'/plugins/custom/';
		if(!file_exists($path.$name[0].'/'.$name[0].'.php'))
			trigger_error('Unknown plugin '.$name[0], E_USER_ERROR);

		$hook = C::get($name[0].'.hooktype', 'triggers');

		if(!$hook)
		{
			include_once $path.$name[0].'/'.$name[0].'.php';
			if(function_exists($name[1]))
			{
				return $name[1]($context,$error);
			}
			else
			{
				if(!class_exists($name[0], false) || get_parent_class($name[0]) !== 'plugins' || !method_exists($name[0], $name[1]))
				{
					if($return) return false;
					trigger_error('ERROR: no way to find the plugin', E_USER_ERROR);
				}
				return call_user_func_array(array($name[0], $name[1]), array(&$context,&$error));
			}
		}
		elseif('class' === $hook)
		{
			if(!class_exists($name[0], false))
			{
				include $path.$name[0].'/'.$name[0].'.php';

				if(get_parent_class($name[0]) !== 'plugins')
				{
					if($return) return false;
					trigger_error('ERROR: the plugin '.$name[0].' does not extends class "plugins"', E_USER_ERROR);
				}

				call_user_func(array($name[0], 'init'), $name[0]);
			}

			if(!method_exists($name[0], $name[1]))
			{
				if($return) return false;
				trigger_error('ERROR: the function '.$name[1].' does not exist', E_USER_ERROR);
			}
			// return $name[0]::$name[1]($context,$error); // PHP 5.3
			return call_user_func_array(array($name[0], $name[1]), array(&$context,&$error));
		}
		else
		{
			include_once $path.$name[0].'/'.$name[0].'.php';
			if(!function_exists($name[1]))
			{
				if($return) return false;
				trigger_error('ERROR: Invalid hook/function name '.$name[1].' for plugin '.$name[0], E_USER_ERROR);
			}
			return $name[1]($context,$error);
		}
	}

	/**
	 * Liste des plugins
	 *
	 * @param array &$context le contexte pass� par r�f�rence
	 * @param array &$error le tableau des erreurs �ventuelles pass� par r�f�rence
	 */
	public function listAction(&$context, &$error)
	{
		global $db;
		$plugins = array();
		
		$context['errors'] = array();
		$context['plugins'] = array();
		
		if(!defined('backoffice-lodeladmin')) // we are in a site
		{ // get enabled plugins from lodeladmin
			if(isset($context['name']))
			{
				$query = lq('SELECT * FROM #_MTP_mainplugins WHERE status>0 AND name="'.addslashes($context['name']).'"');
			}
			else $query = lq('SELECT * FROM #_MTP_mainplugins WHERE status>0');

			$plugObj = $db->Execute($query)	or trigger_error($db->ErrorMsg(), E_USER_ERROR);
			$query = lq('SELECT status FROM #_TP_plugins');
			while(!$plugObj->EOF)
			{
				$plugin = $plugObj->fields;
				// plugin already enabled ?
				$plugin['status'] = $db->GetOne($query.' WHERE name="'.addslashes($plugin['name']).'"');
				$plugin['config'] = unserialize($plugin['config']);
				$context['plugins'][$plugin['name']] = $plugin;
				unset($context['plugins'][$plugin['name']]['name']);
				$plugObj->MoveNext();
			}
			$plugObj->Close();
		}
		else
		{
			$path = C::get('sharedir', 'cfg').'/plugins/custom/';
			$fd = @opendir($path) or trigger_error("Impossible d'ouvrir $path", E_USER_ERROR);
	
			while (($file = readdir($fd)) !== false) 
			{
				if ($file{0} == ".") continue;
	
				$file = $path.$file;
				if(!is_dir($file) || !file_exists($file.'/config.xml') || 
					(isset($context['name']) && substr($file, - strlen($context['name'])) != $context['name'])) 
					continue;
	
				$errors = array();
				$pName = basename($file);
				if(!preg_match('/^[a-zA-Z0-9_\-]+$/', $pName)) continue;
	
				$plugin = $db->GetRow(lq('SELECT * FROM #_MTP_mainplugins where name="'.addslashes($pName).'"'));
				
				if($plugin)
				{ // already parsed
					$plugin['config'] = unserialize($plugin['config']); // default configuration
					unset($plugin['name']);
					$context['plugins'][$pName] = $plugin;
					continue;
				}
				$plugin = array();
				$reader = new XMLReader();
				if(!@$reader->open($file.'/config.xml', 'UTF-8'))
				{
					$errors[] = 'Invalid XML for plugin '.$pName;
					continue;
				}
				$reader->read();
	
				while($reader->read())
				{
					if(XMLReader::ELEMENT !== $reader->nodeType) continue;
					switch($reader->localName)
					{
						case 'sql':
							$localName = $reader->localName;
							$reader->read();
							if(!$reader->hasValue)
							{
								$errors[] = 'Missing sql for plugin '.$pName;
								break 2;
							}
							$sql = (bool)$reader->value;
							if($sql)
							{
								if(!file_exists($file.'/dao.php'))
								{
									$errors[] = 'Missing dao file for plugin '.$pName;
									break 2;
								}
							}
							$plugin[$localName] = $sql;
							break;
						case 'description':
						case 'title': 
							$localName = $reader->localName;
							$reader->read();
							if(!$reader->hasValue)
							{
								$errors[] = 'Missing '.$localName.' for plugin '.$pName;
								break 2;
							}
							$plugin[$localName] = $reader->value;
							if('_' === $plugin[$localName]{0})
								$plugin[$localName] = getlodeltextcontents(substr($plugin[$localName], 1),'lodeladmin');
							break;
						case 'hookType':
							$localName = $reader->localName;
							$reader->read();
							if(!$reader->hasValue)
							{
								$errors[] = 'Missing hooktype for plugin '.$pName;
								break 2;
							}
							if($reader->value != 'class' && $reader->value != 'func')
							{
								$errors[] = 'Invalid hookType '.$reader->value;
								break 2;
							}
							$plugin['hooktype'] = $reader->value;
							break;
						case 'triggers': 
							$localName = $reader->localName;
							$reader->read();
							if(!$reader->hasValue)
							{
								$errors[] = 'Missing triggers for plugin '.$pName;
								break 2;
							}
							$triggers = explode(',', $reader->value);
							foreach($this->_triggers as $trigger)
							{
								$plugin['trigger_'.$trigger] = (int)in_array($trigger, $triggers); // mysql needs 0 or 1
							}
							
							break;
						case 'parameters':
							$reader->read();
							$i=0;
							while($reader->read() && 'parameters' !== $reader->localName)
							{
								$param = array();
								$reader->moveToFirstAttribute();
								do
								{
									switch($reader->localName)
									{
										case 'name':
											if(!$reader->hasValue)
											{
												$errors[] = 'Missing attribute name for parameter for plugin '.$pName;
												break 5;
											}
											$param['name'] = $reader->value;
										break;
	
										case 'type':
											if(!$reader->hasValue)
											{
												$errors[] = 'Missing attribute type for parameter for plugin '.$pName;
												break 5;
											}
											$param['type'] = $reader->value;
											if(!in_array($param['type'], array('boolean', 'text', 'int', 'email', 'lang', 'date', 'mltext', 'datetime', 'file', 'url', 'image', 'number', 'select', 'multipleselect')))
											{
												$error[] = 'Bad type '.$param['type'].' for plugin '.$pName;
											}
										break;
	
										case 'defaultValue':
											$param['defaultValue'] = $reader->value;
										break;
										
										case 'title':
											if(!$reader->hasValue)
											{
												$errors[] = 'Missing attribute title for parameter for plugin '.$pName;
												break 5;
											}
											$param['title'] = $reader->value;
											if('_' === $param['title']{0})
												$param['title'] = getlodeltextcontents(substr($param['title'], 1),'lodeladmin');
										break;
	
										case 'required':
											$param['required'] = $reader->value == 'true' ? true : false;
										break;
	
										case 'allowedValues':
											$param['allowedValues'] = explode(',', $reader->value);
										break;
										default: break;
									}
								} while($reader->moveToNextAttribute());
	
								if(!isset($param['name']) || !isset($param['type']) 
								|| !isset($param['defaultValue']) || !isset($param['required'])
								|| !isset($param['title']) || 
								(($param['type'] == 'select' || $param['type'] == 'multipleselect') &&
								!isset($param['allowedValues'])))
								{
									$errors[] = 'Missing attributes for parameter in plugin '.$pName;
									break 3;
								}
								$name = $param['name'];
								unset($param['name']);
								$plugin['config'][$name] = $param;
								
								++$i;
								$reader->read();
							}
							
							break;
	
						default: break;
					}
				}
				$reader->close();

				if(!isset($plugin['hooktype'])) // only really needed param
					$errors[] = 'Missing hook type in plugin '.$pName;

				if(empty($errors))
				{
					$dao = $this->_getMainTableDao();
					$vo = $dao->createObject();
					$vo->name = $pName;
					$vo->status = 0;
					$vo->config = @serialize($plugin['config']);
					$vo->hooktype = $plugin['hooktype'];
					$vo->title = (isset($plugin['title']) ? $plugin['title'] : "");
					$vo->description = (isset($plugin['description']) ? $plugin['description'] : "");
					foreach($this->_triggers as $trigger)
					{
						$vo->{'trigger_'.$trigger} = $plugin['trigger_'.$trigger];
					}
					
					$context['plugins'][$pName] = $plugin;
					$context['plugins'][$pName]['status'] = 0;
					$context['plugins'][$pName]['id'] = $dao->save($vo, true);
				}
				else $error = array_unique(array_merge((array)$error, $errors));
				
			}
			closedir($fd);
		}

		if(!empty($error)) return '_error';

		if(isset($context['name'])) // surely inside call
		{
			if(!isset($context['plugins'][$context['name']]))
			{
				$error[] = 'No plugin found';
				return '_error';
			}
			$context['plugin'] = $this->_plugin = $context['plugins'][$context['name']];
			$this->_plugin['name'] = $context['name'];
			unset($context['plugins']);
		}
		else
		{
			ksort($context['plugins']);
		}
		
		return '_ok';
	}


	/**
	 * Affichage d'un objet
	 *
	 * @param array &$context le contexte pass� par r�f�rence
	 * @param array &$error le tableau des erreurs �ventuelles pass� par r�f�rence
	 */
	public function viewAction(&$context, &$error)
	{
		if(empty($context['name']))
		{
			return '_location:index.php?lo='.$this->maintable.'&do=list';
		}
		$err = null;
		$this->listAction($context, $error); // get the plugin config
		if($error)
			return '_error';

		$vo = $this->_getMainTableDAO()->find('name="'.addslashes($context['name']).'"');
		if($vo) 
		{// plugin found, means that the plugin has already been enabled or is actually in use
			$context['plugin']['config'] = unserialize($vo->config);
			$context['id'] = $vo->id;
		}
		else
		{
			$context['plugin']['config'] = $this->_plugin['config'];
		}

		unset($context['plugin']['config']['sql']);

		foreach($context['plugin']['config'] as $k=>$v)
		{
			if(!isset($v['value'])) 
			{
				$context['plugin']['config'][$k]['value'] = isset($v['defaultValue']) ? $v['defaultValue'] : null;
			}
		}

		return '_ok';
	}

	/**
	 * Ajout d'un nouvel objet ou Edition d'un objet existant
	 *
	 *
	 * @param array &$context le contexte pass� par r�f�rence
	 * @param array &$error le tableau des erreurs �ventuelles pass� par r�f�rence
	 */
	public function editAction(&$context,&$error)
	{
		if(empty($context['name']))
		{
			return '_location:index.php?lo='.$this->maintable.'&do=list';
		}

		if(!isset($context['edit']) || !$context['edit'])
			return '_error';

		$err = null;
		$this->listAction($context, $error); // get the plugin config
		if($error)
			return '_error';

		if(!$this->validateFields($context, $error))
			return '_error';

		$dao = $this->_getMainTableDao();

		$new = false;
		if(!$context['id']) // never enabled
		{
			$new = true;
			$vo = $dao->createObject();
			$vo->status = 0; // disabled by default
			$vo->config = $context['plugin']['config'];
		}
		else
		{
			$vo = $dao->getById($context['id']);
			if(!$vo) trigger_error('ERROR: invalid id', E_USER_ERROR);
		}

		$this->_populateObject($vo, $context);

		$context['id'] = $dao->save($vo, $new);

		clearcache();

		return '_back';
	}

	/**
	 * Enable a plugin. Must be configured before (== has already an entry in the database)
	 *
	 * @param array &$context le contexte pass� par r�f�rence
	 * @param array &$error le tableau des erreurs �ventuelles pass� par r�f�rence
	 */
	public function activateAction(&$context, &$error)
	{
		if(empty($context['name']))
		{
			trigger_error('You need to specify the name of the plugin to activate', E_USER_ERROR);
		}

		$dao = $this->_getMainTableDao();
		$vo = $dao->find('name="'.addslashes($context['name']).'"');
		if(!$vo)
		{
			$error[] = 'Cannot find the plugin '.$context['name'];
			return '_error';
		}
		
		if($vo->status > 0) return '_back';

		$vo->status = 1;
		$dao->save($vo);

		clearcache();

		return '_back';
	}

	/**
	 * Disable a plugin. Must be configured before (== has already an entry in the database)
	 *
	 * @param array &$context le contexte pass� par r�f�rence
	 * @param array &$error le tableau des erreurs �ventuelles pass� par r�f�rence
	 */
	public function desactivateAction(&$context, &$error)
	{
		if(empty($context['name']))
		{
			trigger_error('You need to specify the name of the plugin to activate', E_USER_ERROR);
		}

		$dao = $this->_getMainTableDao();
		$vo = $dao->find('name="'.addslashes($context['name']).'"');
		if(!$vo)
		{
			$error[] = 'Cannot find the plugin '.$context['name'];
			return '_error';
		}
		
		if($vo->status == 0) return '_back';

		$vo->status = 0;
		$dao->save($vo);

		clearcache();

		return '_back';	
	}

	/**
	 * Enable a plugin for all sites. The plugins must be configured before (== has already an entry in the database)
	 *
	 * @param array &$context le contexte pass� par r�f�rence
	 * @param array &$error le tableau des erreurs �ventuelles pass� par r�f�rence
	 */
	public function enableallAction(&$context, &$error)
	{
		global $db;

		if(empty($context['name']))
		{
			trigger_error('You need to specify the name of the plugin to activate', E_USER_ERROR);
		}

		$dao = $this->_getMainTableDao();
		$vo = $dao->find('name="'.addslashes($context['name']).'"');
		if(!$vo)
		{
			$error[] = 'Cannot find the plugin '.$context['name'];
			return '_error';
		}

		$vo->status = 1;
		$dao->save($vo);
		unset($dao,$vo);

		$daosites = getDao('sites');
		$vos = $daosites->findMany('status>0', 'id', 'name');
		if(!$vos) return '_back'; // nothing to do
		$plogic = getLogic('plugins');
		$home = C::get('home', 'cfg');
		$database = C::get('database', 'cfg');
		foreach($vos as $vo)
		{
			$db->SelectDB($database.'_'.$vo->name) or trigger_error($db->ErrorMsg(), E_USER_ERROR);
			$plogic->enableAction($context,$error);
			if($error) return '_error';
			@unlink($home.'../../'.$vo->name.'/CACHE/triggers'); // remove cache triggers
		}
		$db->SelectDB($database) or trigger_error($db->ErrorMsg(), E_USER_ERROR);
		clearcache();
		return '_back';
	}

	/**
	 * Disable a plugin for all sites. The plugins must be configured before (== has already an entry in the database)
	 *
	 * @param array &$context le contexte pass� par r�f�rence
	 * @param array &$error le tableau des erreurs �ventuelles pass� par r�f�rence
	 */
	public function disableallAction(&$context, &$error)
	{
		global $db;

		if(empty($context['name']))
		{
			trigger_error('You need to specify the name of the plugin to activate', E_USER_ERROR);
		}

		$dao = $this->_getMainTableDao();
		$vo = $dao->find('name="'.addslashes($context['name']).'"');
		if(!$vo)
		{
			$error[] = 'Cannot find the plugin '.$context['name'];
			return '_error';
		}

		$vo->status = 0;
		$dao->save($vo);
		unset($dao,$vo);

		$daosites = getDao('sites');
		$vos = $daosites->findMany('status>0', 'id', 'name');
		if(!$vos) return '_back'; // nothing to do
		$plogic = getLogic('plugins');
		$home = C::get('home', 'cfg');
		$database = C::get('database', 'cfg');
		foreach($vos as $vo)
		{
			$db->SelectDB($database.'_'.$vo->name) or trigger_error($db->ErrorMsg(), E_USER_ERROR);
			$plogic->disableAction($context,$error);
			if($error) return '_error';
			@unlink($home.'../../'.$vo->name.'/CACHE/triggers'); // remove cache triggers
		}
		$db->SelectDB($database) or trigger_error($db->ErrorMsg(), E_USER_ERROR);
		clearcache();
		return '_back';
	}

	/**
	 * Validate the fields
	 * @return boolean true if no errors
	 */
	public function validateFields(&$context, &$error) 
	{
		if(!function_exists('validfield')) include 'validfunc.php';

		$filemask = 0777 & octdec(C::get('filemask', 'cfg'));

		foreach($this->_plugin['config'] as $name=>$param)
		{
			if('sql' == $name) continue;
			// is empty ?
			$empty = $param['type'] != "boolean" && (// boolean are always true or false
				!isset ($context['data'][$name]) || // not set
				$context['data'][$name] === ""); // or empty string

			if($empty && $param['required'])
			{
				$error[$name] = '+';
				continue;
			}

			if($param['type'] == 'file' || $param['type'] == 'image')
			{
				if(!file_exists(SITEROOT.'upload/plugins/'))
				{
					mkdir(SITEROOT.'upload/plugins/', $filemask);
					@chmod(SITEROOT.'upload/plugins/', $filemask);
				}
				if(!file_exists(SITEROOT.'upload/plugins/'.$this->_plugin['name']))
				{
					mkdir(SITEROOT.'upload/plugins/'.$this->_plugin['name'], $filemask);
					@chmod(SITEROOT.'upload/plugins/'.$this->_plugin['name'], $filemask);
				}
			}

			$err = validfield($context['data'][$name], $param['type'], $param['defaultValue'], $name, 'data', SITEROOT.'upload/plugins/'.$this->_plugin['name']);

			if(true !== $err)
				$error[$name] = $err;
		}

		return empty($error);
	}

	/**
	 * Populate the object from the context.
	 * @private
	 */
	protected function _populateObject(&$vo, &$context) 
	{
		$vo->name = $this->_plugin['name'];
		if(is_string($vo->config))
			$vo->config = unserialize($vo->config);
		foreach($vo->config as $k=>$v)
		{
			$vo->config[$k]['value'] = isset($context['data'][$k]) ? $context['data'][$k] : '';
		}
		$vo->config['sql'] = isset($this->_plugin['sql']) ? $this->_plugin['sql'] : false;
		$vo->config = serialize($vo->config);
	}

	/**
	 * Construction des balises select HTML pour cet objet
	 *
	 * @param array &$context le contexte, tableau pass� par r�f�rence
	 * @param string $var le nom de la variable du select
	 * @param string $edittype le type d'�dition
	 */
	public function makeSelect(&$context, $var, $edittype)
	{
		if('lang' == $edittype)
		{
			$var = isset($context['plugin']['config'][$context['varname']]['value']) ? 
				$context['plugin']['config'][$context['varname']]['value'] :
				$context['plugin']['config'][$context['varname']]['defaultValue'];
			makeSelectLangs($var);
			return;
		}

		foreach($context['plugin']['config'] as $name=>$value)
		{
			if($name != $context['varname']) continue;
			$current = $value;
			break;	
		}
		if(!isset($current)) trigger_error('Invalid parameter '.$varname, E_USER_ERROR);

		foreach($current['allowedValues'] as $k=>$v)
		{
			$current['allowedValues'][$v] = $v;
			unset($current['allowedValues'][$k]);
		}
		// if not defined get the default value
		$value = isset($current['value']) ? $current['value'] : $current['defaultValue'];

		switch($context['varname'])
		{
			case 'userrights':
				if(!function_exists('makeSelectUserRights'))
					include 'commonselect.php';
				makeSelectUserRights($value, true, $current['allowedValues']);
			break;
			default:
				renderOptions($current['allowedValues'], $value);
			break;
		}
	}

	// begin{publicfields} automatic generation  //

	/**
	 * Retourne la liste des champs publics
	 * @access private
	 */
	protected function _publicfields() 
	{
		return array('config' => array('longtext', '+'),
									'name' => array('text', '+'),
									'title' => array('text', ''),
									'description' => array('longtext', ''),
									'hooktype' => array('tinytext', ''));
	}
	// end{publicfields} automatic generation  //

	// begin{uniquefields} automatic generation  //

	/**
	 * Retourne la liste des champs uniques
	 * @access private
	 */
	protected function _uniqueFields() 
	{ 
		return array(array('name'), );
	}
	// end{uniquefields} automatic generation  //


} // class 

?>