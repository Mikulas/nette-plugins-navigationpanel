<?php
/**
 * Description of PresenterDebugger
 *
 * @author Mikuláš Dítě
 * @license MIT
 *
 * @todo Add phpDoc
 * @todo Add caching
 * @todo Fix link from ?module=bar&presenter=foo to ?presenter=bar:foo
 */

/*namespace Nette;*/
class PresenterTreePanel extends /*Nette\*/Object implements /*Nette\*/IDebugPanel
{
    	/**
	 * Renders HTML code for custom tab.
	 * @return void
	 */
	function getTab()
	{
		return /*Nette\*/Environment::getApplication()->getPresenter()->backlink();
	}

	/**
	 * Renders HTML code for custom panel.
	 * @return void
	 */
	function getPanel()
	{
		ob_start();
		$template = new /*Nette\Templates\*/Template(dirname(__FILE__) . '/bar.presentertree.panel.phtml');
		$template->links = $this->generate();
		$template->render();
		return ob_get_clean();
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
			
			$reflection = new ReflectionClass($fileinfo['filename']);
			if ($reflection->isInstantiable()) {
				$modules = $this->getPresenterModules($fileinfo['filename']);
				$link = '';
				if ($modules !== FALSE) {
					$link .= implode(':', $modules);
				}
				$link .= ':';
				preg_match('/(?:[A-z0-9]+?_)*([A-z0-9]+)Presenter/m', $reflection->getName(), $match);
				$presenter = $match[1];
				$link .= $presenter;

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
				}
				if (count($actions) == 0) {
					$actions['Default']['arguments']['required'] = array();
					$actions['Default']['arguments']['optional'] = array();
				}
				foreach ($actions as $action => $info) {
					$label = $link . ':' . $action;
					$links[$label]['location']['action'] = $action; //faster when before location.presenter
					if ($modules !== false) {
						$links[$label]['location']['presenter'] = substr(implode('_', $modules), 1) . ':' . $presenter;
					} else {
						$links[$label]['location']['presenter'] = $presenter;
					}
					$links[$label]['arguments'] = $info['arguments'];
				}
			}
		}
		return $links;
	}

	/**
	 * @param string $filename
	 * @return array|string all modules given presenter file is under
	 */
	private function getPresenterModules($filename)
	{
		$modules = explode('_', $filename);
		if (count($modules) === 1) {
			return false;
		} else {
			unset($modules[count($modules) - 1]); //remove presenter name
			return $modules;
		}
	}
}