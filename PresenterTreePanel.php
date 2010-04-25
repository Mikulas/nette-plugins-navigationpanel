<?php
/**
 * PresenterTree panel for Nette 1.0+. Displays all presenters and their required and optional parameters.
 *
 * @author Mikuláš Dít?
 * @license MIT
 */

/*
use Nette\Templates\
namespace Nette;
*/
class PresenterTreePanel extends Object implements IDebugPanel
{
	/**
	 *
	 * @throws NotImplementedException if other than TRUE
	 */
	const MODULES_DISABLED = TRUE;



	public function __construct()
	{
		if (self::MODULES_DISABLED !== TRUE) {
			throw new NotImplementedException();
		}
	}



    	/**
	 * Renders HTML code for custom tab.
	 * @return void
	 */
	function getTab()
	{
		return 'Presenters';
	}



	/**
	 * Renders HTML code for custom panel.
	 * @return void
	 */
	function getPanel()
	{
		ob_start();
		$template = new Template(dirname(__FILE__) . '/bar.presentertree.panel.phtml');
		$template->tree = $this->generate();
		$template->render();
		return $cache['output'] = ob_get_clean();
	}



	/**
	 * Returns panel ID.
	 * @return string
	 */
	function getId()
	{
		return __CLASS__;
	}



	/**
	 * Iterates through all presenters and returns their actions with backlinks and arguments
	 * @return array
	 */
	private function generate()
	{
		$links = array();

		$iterator = new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_DIR)), '/Presenter\.(php|PHP)$/m', RecursiveRegexIterator::GET_MATCH);
		foreach ($iterator as $path => $match) {
			$fileinfo = pathinfo($path);
			$reflection = new ReflectionClass($this->getClassNameFromPath($path));
			if ($reflection->isInstantiable()) {
				$modules = $this->getModulesFromName($reflection->name);
				$link = ':';
				if ($modules !== FALSE) {
					$link .= implode(':', $modules) . ':';
				}
				preg_match('/(?:[A-z0-9]+?_)*([A-z0-9]+)Presenter/m', $reflection->getName(), $match);
				$presenter = $match[1];
				$link .= $presenter;

				$persistent = array();
				foreach ($reflection->getProperties() as $property) {
					foreach (AnnotationsParser::getAll($property) as $annotation => $value) {
						if ($annotation == 'persistent') {
							$persistent[] = $property;
						}
					}
				}

				$actions = array();
				foreach ($reflection->getMethods() as $action) {
					if (preg_match('/^(action|render)(.*)$/m', $action->getName(), $name) && !in_array($name[2], $actions)) {
						$action_name = lcfirst($name[2]);
						$pattern = '/Method \[.*? ' . $action . ' \].*? (?:Parameters .*? \{.*?Parameter #\d+ \[(.*?)\].*?\})? }/ms';
						$set_required = FALSE;
						$set_optional = FALSE;
						foreach ($action->getParameters() as $arg) {
							if (!$arg->isOptional()) {
								$actions[$action_name]['arguments']['required'][$arg->getName()] = NULL;
								$set_required = TRUE;
							} else {
								$actions[$action_name]['arguments']['optional'][$arg->getName()] = $arg->getDefaultValue();
								$set_optional = TRUE;
							}
						}
						if (!$set_required) {
							$actions[$action_name]['arguments']['required'] = array();
						}
						if (!$set_optional) {
							$actions[$action_name]['arguments']['optional'] = array();
						}
					}
					$actions[$action_name]['arguments']['persistent'] = $persistent;
				}
				if (count($actions) == 0) {
					$actions['Default']['arguments']['required'] = array();
					$actions['Default']['arguments']['optional'] = array();
					$actions['Default']['arguments']['persistent'] = array();
				}
				foreach ($actions as $action => $info) {
					$label = $link . ':' . $action;

					if (Environment::getApplication()->getPresenter() instanceof Presenter) {
						d($label);
						$links[$label]['link'] = Environment::getApplication()->getPresenter()->link($label);
					} else {
						$links[$label]['link'] = 'false';
					}

					$links[$label]['action'] = $action;
					$links[$label]['presenter'] = $presenter;
					$links[$label]['modules'] = $modules;
					$links[$label]['arguments'] = $info['arguments'];
				}
			}
		}
		return $this->categorize($links);
	}



	/**
	 * @param array $links
	 * @return array
	 */
	private function categorize(array $links) {
		$tree = array();
		foreach($links as $link) {
			$action = array($link['action'] => array('__link' => $link['link'], '__arguments' => $link['arguments']));

			if ($link['modules'] === FALSE) {
				if (!isset($tree[$link['presenter']])) {
					$tree[$link['presenter']] = $action;
				} else {
					$tree[$link['presenter']] = array_merge_recursive($tree[$link['presenter']], $action);
				}
			} elseif (self::MODULES_DISABLED) {
				$link['presenter'] = implode(':', $link['modules']) . ':' . $link['presenter'];
				if (!isset($tree[$link['presenter']])) {
					$tree[$link['presenter']] = $action;
				} else {
					$tree[$link['presenter']] = array_merge_recursive($tree[$link['presenter']], $action);
				}
			} else {
				$tree = array_merge_recursive($this->array_hierarchy($link['modules']), $tree);
				$this->array_keysFromArray($tree, $link['modules'], array($link['presenter'] => $action));
			}
		}
		return $tree;
	}



	/**
	 * Returns array with all nodes as child of previous one
	 * array(1, 2, 3) => array(1 => array(2 => array(3 => array())))
	 * @param array $array
	 * @return array
	 */
	private function array_hierarchy(array $array)
	{
		$key = $array[0];
		unset($array[0]);
		sort($array);
		$hierarchy = array();
		if (is_array($array) && isset($array[0])) {
			$hierarchy[$key] = $this->array_hierarchy($array);
		} else {
			$hierarchy[$key] = array();
		}
		return $hierarchy;
	}



	/**
	 * Access array with keys stored in array
	 * @example
	 *	* $array = ('one' => array('two' => 'value'))
	 *	* $keys = ('one', 'two')]
	 *	* would return 'value' from $array
	 * @param array $array
	 * @param arary $keys
	 * @param mixed $value
	 * @param bool $append
	 * @return array
	 */
	private function array_keysFromArray(&$array, $keys, $value = NULL, $append = TRUE)
	{
		$key = $keys[0];
		unset($keys[0]);
		sort($keys);
		if (isset($keys[0]) && is_array($array[$key])) {
			return $this->array_keysFromArray($array[$key], $keys, $value, $append);
		} else {
			if ($value !== NULL) {
				if ($append) {
					$array[$key] = array_merge_recursive($array[$key], $value);
				} else {
					$array[$key] = $value;
				}
			}
			return $array[$key];
		}
	}



	private function getClassNameFromPath($path)
	{
		$path = realpath($path);
		preg_match('~((?:(?:\\\\|/)[^\\\\/]+Module)*)(?:\\\\|/)presenters~', $path, $modules);
		if (!empty($modules[1])) {
			$modules = explode('Module', $modules[1]);
			unset($modules[count($modules) - 1]);
			$modules = str_replace('\\', '', $modules);
			$modules = str_replace('/', '', $modules);
			$pathInfo = pathinfo($path);
			return implode('_', $modules) . '_' . $pathInfo['filename'];
		} else {
			$pathInfo = pathinfo($path);
			return $pathInfo['filename'];
		}
	}



	/**
	 * @param string $className
	 * @return array|string all modules given presenter file is under
	 */
	private function getModulesFromName($className)
	{
		$modules = explode('_', $className);
		if (count($modules) === 1) {
			return false;
		} else {
			unset($modules[count($modules) - 1]); //remove presenter name
			return $modules;
		}
	}
}