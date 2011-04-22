<?php
/**
 * NavigationPanel for Nette 2.0. Fast navigation for developers.
 *
 * @author Mikuláš Dítě
 * @license MIT
 */

namespace Panel;

use Nette\Object;
use Nette\Diagnostics\IBarPanel;
use Nette\Diagnostics\Debugger;
use Nette\Templating\FileTemplate;
use Nette\Utils\Finder;
use Nette\Utils\Strings as String;
use Nette\Utils\SafeStream;
use Nette\Latte\Engine;


class Navigation extends Object implements IBarPanel
{

    	/**
	 * Renders HTML code for custom tab
	 * IDebugPanel
	 * @return void
	 */
	public function getTab()
	{
		return '<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAaBJREFUeNqkUz1rAkEQnT0XK8FYCpLCNFYWISgi/oRA2vSSgAhWJgQsDEkvlv6EXFqrNGIhSEAQKwsbueLAwsIPRE+97Jtwx5moETIwDDM7++bN7Kwol8vXRBSmE2S73e74tm2bVCqV7u0Tpd1u7/jFYvFO22w2QKKr9wuu0Gq1qNPpuAofceRYlsW2VquxnU6nJFVQIOHt8oMTk8nkXupCCFqtVnwxm82ynUwmJNfrNQMMBgOKRCIH+9c0jWazGdXrdTdmGAZpoOVQbDQaNBwO6fPpgdAa/G63S/1+n3OWyyWFQiFXx+MxSUWLGUAzmQwjh59fGSCdTv96AcS9wjNA0NFDghnsA9C8DJrNJvf1qJ9xIvxer8fqZeAtJlVfLoBD+eVmRDhPpVJ/t+AwwBCPteAAqFfbBQADXAwEArw00WiUdF2nfD5PavN42pB4PM4WLJHv8/m+AebzuUD1WCzmouZyOa6YSCS8e88xMCgUClStVgEkpGmaEkvi9/uPfiTkLBYLtpVKha3aRCmCweCtQj4/8TdaP0ANpv8f+RJgAMs5a/v6pdj7AAAAAElFTkSuQmCC">' .
			'Navigation';
	}



	/**
	 * Renders HTML code for custom panel
	 * IDebugPanel
	 * @return void
	 */
	function getPanel()
	{
		ob_start();
		$template = new FileTemplate(dirname(__FILE__) . '/bar.navigation.panel.latte');
		$template->registerFilter(new Engine());
		$template->tree = $this->getPresenters();
		$template->render();
		return $cache['output'] = ob_get_clean();
	}



	/**
	 * Returns panel ID
	 * IDebugPanel
	 * @return string
	 */
	function getId()
	{
		return __CLASS__;
	}



	/**
	 * Registers panel to Debug bar
	 */
	static function register()
	{
		Debugger::addPanel(new self);
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

		foreach (Finder::findFiles('*.latte', '*.phtml')->from(APP_DIR) as $path => $file) {
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
		$match = String::match($file->getRealPath(), '~(?:(?P<module>[A-z0-9_-]+)Module)?/templates/(?P<presenter>[A-z0-9_-]+)/(?P<action>[A-z0-9_-]+)\.(latte|phtml)$~m');

		if (!$match) {
			return FALSE;
		}

		$module = $match['module'];
		$presenter = $match['presenter'];
		$action = $match['action'];

		return array($module, $presenter, $action);
	}
}
