<?php
/**
 * Description of PresenterDebugger
 *
 * @author Mikuláš Dítě
 * @license MIT
 *
 * @todo Add alll 5.3 namespaces
 * @todo Add phpDoc
 * @todo Add caching
 * @todo Fix link from ?module=bar&presenter=foo to ?presenter=bar:foo
 */
class PresenterTreePanel extends Object implements IDebugPanel
{
	const PRESENTER_DIR = 'presenters';
    	/**
	 * Renders HTML code for custom tab.
	 * @return void
	 */
	function getTab()
	{
		return Environment::getApplication()->getPresenter()->backlink();
		return $s;
	}

	/**
	 * Renders HTML code for custom panel.
	 * @return void
	 */
	function getPanel()
	{
		ob_start();
		$template = new Template(dirname(__FILE__) . '/bar.presentertree.panel.phtml');
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

	public function generate()
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
						$pattern = '/Method \[.*? ' . $action . ' \].*? (?:Parameters .*? \{.*?Parameter #\d+ \[(.*?)\].*?\})? }/ms';
						foreach ($action->getParameters() as $arg) {
							if ($arg->isOptional()) {
								$actions[$name[2]]['arguments']['optional'][$arg->getName()] = $arg->getDefaultValue();
							} else {
								$actions[$name[2]]['arguments']['required'][$arg->getName()] = NULL;
							}
						}
					}
				}
				if (count($actions) == 0) {
					$actions[] = 'Default';
				}
				foreach ($actions as $action => $info) {
					$label = $link . ':' . $action;
					if ($modules !== false) {
						$links[$label]['location']['modules'] = substr(implode(':', $modules), 1);
					} else {
						$links[$label]['location']['modules'] = NULL;
					}
					$links[$label]['location']['presenter'] = $presenter;
					$links[$label]['location']['action'] = $action;
					$links[$label]['arguments'] = $info['arguments'];
				}
			}
		}
		return $links;
	}

	private function getPresenterModules($filename)
	{
		$modules = explode('_', $filename);
		if (count($modules) === 1) {
			return false;
		} else {
			unset($modules[count($modules) - 1]);
			return $modules;
		}
	}
}