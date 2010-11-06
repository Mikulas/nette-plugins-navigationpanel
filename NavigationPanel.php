<?php
/**
 * NavigationPanel for Nette 2.0. Fast navigation for developers.
 *
 * @author Mikuláš Dítě
 * @license MIT
 */

namespace Panel;
use Nette\Object;
use Nette\IDebugPanel;
use Nette\Debug;
use Nette\Templates\FileTemplate;
use Nette\Finder;
use Nette\String;
use Nette\SafeStream;
use Nette\Templates\LatteFilter;


class NavigationPanel extends Object implements IDebugPanel
{

    	/**
	 * Renders HTML code for custom tab.
	 * @return void
	 */
	function getTab()
	{
		return 'Navigation';
	}



	/**
	 * Renders HTML code for custom panel.
	 * @return void
	 */
	function getPanel()
	{
		ob_start();
		$template = new FileTemplate(dirname(__FILE__) . '/bar.navigation.panel.phtml');
		$template->registerFilter(new LatteFilter());
		$template->tree = $this->getPresenters();
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
	 * Registeres panel to Debug bar
	 */
	static function register()
	{
		Debug::addPanel(new self);
	}



	/**
	 * @return array
	 */
	private function getPresenters()
	{
		@SafeStream::register(); //intentionally @ (prevents multiple registration warning)

		$tree = array();

		foreach (Finder::findFiles('*Presenter.php')->from(APP_DIR) as $path => $file) {
			$data = $this->processPresenter($file);
			if ($data === FALSE) {
				continue;
			}
			
			list($module, $presenter, $actions) = $data;
			$tree[$module][$presenter] = $actions;
		}

		foreach (Finder::findFiles('*.phtml')->from(APP_DIR) as $path => $file) {
			$data = $this->processTemplate($file);
			if ($data === FALSE) {
				continue;
			}

			list($module, $presenter, $action) = $data;

			if (!isset($tree[$module][$presenter])) {
				$tree[$module][$presenter] = array();
			}
			if (array_search($action, $tree[$module][$presenter]) === FALSE) {
				$tree[$module][$presenter][] = $action;
			}
		}
		
		$tree = $this->removeSystemPresenters($tree);
		return $tree;
	}



	/**
	 * @param array $array
	 * @return int
	 */
	public static function getRowspan(array $array)
	{
		$size = 0;
		foreach ($array as $content) {
			$size += count($content);
		}
		return $size;
	}



	/**
	 * Removes presenters such as Error etc.
	 * @param array $tree
	 */
	public function removeSystemPresenters($tree)
	{
		unset($tree[NULL]['Error']);
		return $tree;
	}



	/**
	 * @param \SplFileInfo $file
	 */
	private function processPresenter($file)
	{
		$stream = fopen("safe://" . $file->getRealPath(), 'r');
		$content = fread($stream, filesize("safe://" . $file->getRealPath()));
		fclose($stream);

		$module = String::match($content, '~(^|;)\s*namespace (?P<name>[A-z0-9_-]+)Module;~m');
		$module = $module['name'];

		$presenter = String::match($content, '~(^|;)\s*class (?P<name>[A-z0-9_-]+)Presenter(\s|$)~m');
		if ($presenter === NULL || $presenter['name'] === 'Error') {
			return FALSE;
		}
		$presenter = $presenter['name'];

		$actions = array();
		foreach(String::matchAll($content, '~function (action|render)(?P<name>[A-z0-9_-]+)(\s|\\()~') as $action) {
			$action = lcfirst($action['name']);
			if (array_search($action, $actions) === FALSE) {
				$actions[] = $action;
			}
		}
		return array($module, $presenter, $actions);
	}



	/**
	 * @param \SplFileInfo $file
	 */
	public function processTemplate($file)
	{
		$match = String::match($file->getRealPath(), '~(?:(?P<module>[A-z0-9_-]+)Module)?/templates/(?P<presenter>[A-z0-9_-]+)/(?P<action>[A-z0-9_-]+)\.phtml$~m');

		if (!$match) {
			return FALSE;
		}

		$module = $match['module'];
		$presenter = $match['presenter'];
		$action = $match['action'];

		return array($module, $presenter, $action);
	}
}
