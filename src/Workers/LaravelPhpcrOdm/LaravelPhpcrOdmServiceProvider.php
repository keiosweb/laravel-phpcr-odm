<?php namespace Workers\LaravelPhpcrOdm;

use Illuminate\Support\ServiceProvider;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain as DriverChain;
use Jackalope\Tools\Console\Helper\DoctrineDbalHelper;
use \Doctrine\ODM\PHPCR\Tools\Console\Helper\DocumentManagerHelper;
use \Doctrine\Common\Annotations\AnnotationReader;
use \Doctrine\ODM\PHPCR\Mapping\Driver\AnnotationDriver;
use \Symfony\Component\Console\Helper\HelperSet;
use \Doctrine\ODM\PHPCR\Configuration;
use \Doctrine\ODM\PHPCR\DocumentManager;
use \Jackalope\RepositoryFactoryDoctrineDBAL;
use \PHPCR\SimpleCredentials;

class LaravelPhpcrOdmServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	public function boot()
	{

	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$app = $this->app;

		// Register the package configuration with the loader.
		$app['config']->package('workers/laravel-phpcr-odm', __DIR__.'/../../config');

		// Get the doctrine connection
		$app['doctrine.connection'] = $app['db']->connection()
												->getDoctrineConnection();

		//// Prepare configuration
		$app['phpcr.config'] = new Configuration();
		$app['phpcr.config']->setProxyDir($app['config']->get('laravel-phpcr-odm::proxy.directory'));
		$app['phpcr.config']->setProxyNamespace($app['config']->get('laravel-phpcr-odm::proxy.namespace'));
		$app['phpcr.config']->setAutoGenerateProxyClasses($app['config']->get('laravel-phpcr-odm::proxy.auto_generate'));

		$chain = new DriverChain();
		// Bind the annotaion driver
		$chain->addDriver($this->getAnnotationDriver(), 'App');

		// Event: manipulate the driver chain
		$app['events']->fire('phpcr.drivers.chain.creating', array($chain));

		// Use the driver chain
		$app['phpcr.config']->setMetadataDriverImpl($chain);
		
		$this->addPhpcrSession();
		$this->addDocumentManager($app['phpcr.session'], $app['phpcr.config']);
		$this->addHelpersToArtisan();
		$this->addCommandsToArtisan();
	}

	/**
	 * Get the annotation driver
	 * 
	 * @return \Doctrine\ODM\PHPCR\Mapping\Driver\AnnotationDriver(
	 */
	public function getAnnotationDriver()
	{
		$file = $this->app['path.base'].'/vendor/doctrine/phpcr-odm/lib/Doctrine/ODM/PHPCR/Mapping/Annotations/DoctrineAnnotations.php';

		if(! file_exists($file))
		{
			// if we are in workbench we need a different path
			$file = __DIR__.'/../../../vendor/doctrine/phpcr-odm/lib/Doctrine/ODM/PHPCR/Mapping/Annotations/DoctrineAnnotations.php';
		}

		AnnotationRegistry::registerFile($file);

		$reader = new AnnotationReader();
		$paths = array();

		// Event: Manipulate the paths the Annotation-Driver should look for classes
		$this->app['events']->fire('phpcr.drivers.annotation.creating', array($paths));

		$driver = new AnnotationDriver($reader, $paths);

		return $driver;
	}

	public function addDocumentManager($session, $config)
	{
		$app = $this->app;

		$app['events']->fire('phpcr.manager.creating', array($session, $config));
		$app['phpcr.manager'] = DocumentManager::create($session, $config);
		$app['events']->fire('phpcr.manager.created', array($app['phpcr.manager']));
	}

	/**
	 * [getPhpcrSession description]
	 * @return [type] [description]
	 */
	public function addPhpcrSession()
	{
		$app = $this->app;

		$factory = new RepositoryFactoryDoctrineDBAL;
		$repository = $factory->getRepository(
		    array('jackalope.doctrine_dbal_connection' => $app['doctrine.connection'])
		);

		// Event: Manipulate the PHPCR repository
		$app['events']->fire('phpcr.repository.creating', array($repository));

		// dummy credentials to comply with the API
		$credentials = new SimpleCredentials(null, null);
		$app['phpcr.session'] = $repository->login($credentials, $app['config']->get('laravel-phpcr-odm::workspace'));
	}

	public function addCommandsToArtisan()
	{
		$app = $this->app;

		$app['commands.phpcr.workspace.create'] = new \PHPCR\Util\Console\Command\WorkspaceCreateCommand;
		$app['commands.phpcr.workspace.export'] = new \PHPCR\Util\Console\Command\WorkspaceExportCommand;
		$app['commands.phpcr.workspace.import'] = new \PHPCR\Util\Console\Command\WorkspaceImportCommand;
		$app['commands.phpcr.workspace.list'] = new \PHPCR\Util\Console\Command\WorkspaceListCommand;
		$app['commands.phpcr.workspace.import'] = new \PHPCR\Util\Console\Command\WorkspaceImportCommand;
		$app['commands.phpcr.workspace.purge'] = new \PHPCR\Util\Console\Command\WorkspacePurgeCommand;
		$app['commands.phpcr.workspace.query'] = new \PHPCR\Util\Console\Command\WorkspaceQueryCommand;
		$app['commands.phpcr.nodetype.register'] = new \PHPCR\Util\Console\Command\NodeTypeRegisterCommand;
		$app['commands.phpcr.node.dump'] = new \PHPCR\Util\Console\Command\NodeDumpCommand;
		$app['commands.phpcr.nodetype.register'] = new \PHPCR\Util\Console\Command\NodeTypeRegisterCommand;
		$app['commands.phpcr.nodetype.register.system'] = new \Doctrine\ODM\PHPCR\Tools\Console\Command\RegisterSystemNodeTypesCommand;
		$app['commands.phpcr.querybuilder.dump'] = new \Doctrine\ODM\PHPCR\Tools\Console\Command\DumpQueryBuilderReferenceCommand;
		$app['commands.phpcr.dbal.init'] = new \Jackalope\Tools\Console\Command\InitDoctrineDbalCommand;


		$this->commands(array(
			'commands.phpcr.workspace.create',
			'commands.phpcr.workspace.export',
			'commands.phpcr.workspace.import',
			'commands.phpcr.workspace.list',
			'commands.phpcr.workspace.import',
			'commands.phpcr.workspace.purge',
			'commands.phpcr.workspace.query',
			'commands.phpcr.nodetype.register',
			'commands.phpcr.node.dump',
			'commands.phpcr.nodetype.register',
			'commands.phpcr.nodetype.register.system',
			'commands.phpcr.querybuilder.dump',

			'commands.phpcr.dbal.init',
		));
	}

	public function addHelpersToArtisan()
	{
		$app = $this->app;

		$helpers = array(
			'dialog' => new \Symfony\Component\Console\Helper\DialogHelper(),
	        'phpcr' => new \PHPCR\Util\Console\Helper\PhpcrHelper($app['phpcr.session']),
	        'phpcr_console_dumper' => new \PHPCR\Util\Console\Helper\PhpcrConsoleDumperHelper(),
	        'dm' => new DocumentManagerHelper(null, $app['phpcr.manager']),
	        'jackalope-doctrine-dbal' => new DoctrineDbalHelper($app['doctrine.connection']),
		);

		$helperSet = new HelperSet($helpers);

		// Add the helperset to artisan
		$app['events']->listen('artisan.start', function($artisan) use($helperSet){
			$artisan->setHelperSet($helperSet);
		});
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}

}