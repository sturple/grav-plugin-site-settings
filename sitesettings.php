<?php
namespace Grav\Plugin;

use Grav\Common\Page\Collection;
use Grav\Common\Page\Page;
use Grav\Common\Plugin;


class SiteSettingsPlugin extends Plugin
{
    /**
     * @return array
     */
	protected $settings = array();
	protected $less = null;
	protected $config;
	
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
        ];
    }

    /**
     * Activate plugin if path matches to the configured one.
     */
    public function onPluginsInitialized()
    {
		
        if ($this->isAdmin() )  {
            $this->active = false;
            return;
        }
		if ($this->config->get('plugins.cssless.compile') == 0){
			$this->enable(['onTwigTemplateVariables' => ['onTwigTemplateVariables', 0]]);
			return;
		}
		// get current theme directory
		//$dir = THEMES_DIR . $eventData['config']['theme'] .'/';
		

		

        $this->enable(['onTwigSiteVariables' => ['onTwigSiteVariables', 0]]);		
	}
	
	public function onTwigTemplateVariables()
	{
		$this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
	}
	
	
	
	public function onTwigSiteVariables()
	{
		
		$this->grav['debugger']->addMessage($this->config->get('plugins.cssless.googleFonts')) ;
		$google_fonts_string = $this->config->get('plugins.cssless.googleFonts');
		$this->grav['twig']->twig_vars['googlefonts'] = implode(',', array_map(function ($str){ return sprintf("'%s'", $str);},explode(',', $google_fonts_string)));
		
		// create new instance of less
		require_once __DIR__ . '/vendor/autoload.php';
		$this->grav['debugger']->addMessage($this->config->get('plugins.cssless'));
		$this->less = new \lessc;
		
		$this->less->registerFunction('twig', function($arg){
			$twigCompiled = '';
			list($type, $value) =$arg;
			
			$twig = new \Twig_Environment(new \Twig_Loader_String());
			if ($type == 'keyword'){
				$twigString = '{{'.$value.'|raw}}';
			}		
			
			$twigCompiled = $twig->render($twigString,$this->config->get('plugins.cssless'));
			$this->grav['debugger']->addMessage('twig function() ' . print_R($arg,true) .$twigCompiled);
			return $twigCompiled;
		});
		//enable comments or not		
		$comments = false;
		$comments = ($this->config->get('plugins.cssless.comments') === 1) ? true : false;
		$this->less->setPreserveComments($comments);
		
		// setting input file and setting up cache file		
		$inputFile = $this->config->get('plugins.cssless.filepath');
		$cacheFile = $inputFile.".cache";
		
		if (!file_exists($inputFile))
		{
			throw new \RuntimeException("Input less file {$inputFile} not found.");
		}				
		// outupt file
		$outputFile = $this->config->get('plugins.cssless.outputfile');
		
		//setting the formatter comparing to insure it is a valid choice.
		if (in_array($this->config->get('plugins.cssless.formatter'),  array('lessjs','compressed','classic'))){
			$this->less->setFormatter($this->config->get('plugins.cssless.formatter'));			
		}				
	
		//operation to write to output and cache file.
		if (file_exists($cacheFile) && ($this->config->get('plugins.cssless.forcecompile') === 0))
		{
			
			$cache = unserialize(file_get_contents($cacheFile));
		}
		else
		{
			$cache = $inputFile;			
		}		
		
		// create new cache
		try {
			$newCache = $this->less->cachedCompile($cache);
		} catch (Exception $ex){
			throw new \RuntimeException ("Compile Error: ". $ex->getMessage());
		}
		
		
		// update files if cache has changed
		if (!is_array($cache) || $newCache["updated"] > $cache["updated"]) {
			
			if (!file_put_contents($cacheFile, serialize($newCache))){
				throw new \Exception ("Could not write to cache file {$cacheFile}.");
			}
			$this->grav['debugger']->addMessage('updating file '. $newCache['updated']);
			if (!file_put_contents($outputFile, $newCache['compiled'])){
				throw new \Exception ("Could not write to output file {$outputFile}.");
			}			
		}		
		$this->grav['assets']->addCss('plugin://cssless/assets/css/style.css');
		
	}
	
	public function lessphpCompileTwig($data){
		return $data;
	}
	

    
}