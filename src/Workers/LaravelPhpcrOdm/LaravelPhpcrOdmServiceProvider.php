<?php namespace Workers\LaravelPhpcrOdm;

use Illuminate\Support\ServiceProvider;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain as DriverChain;

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
		// Register the package configuration with the loader.
		$this->app['config']->package('workers/laravel-phpcr-odm', __DIR__.'/../../config');

		$this->app->bind('phpcr.drivers.annotation', function()
		{
			return $this->getAnnotationDriver();
		});

		$this->app->bind('phpcr.connections.doctrine', function()
		{
			return \Doctrine\DBAL\DriverManager::getConnection($this->app['config']->get('laravel-phpcr-odm::connections.doctrine'));
		});

		$this->app->bind('phpcr.manager', function()
		{
			// Prepare configuration
			$config = new \Doctrine\ODM\PHPCR\Configuration();
			$config->setProxyDir($this->app['config']->get('laravel-phpcr-odm::proxy.directory'));
			$config->setProxyNamespace($this->app['config']->get('laravel-phpcr-odm::proxy.namespace'));
			$config->setAutoGenerateProxyClasses($this->app['config']->get('laravel-phpcr-odm::proxy.auto_generate'));

			$chain = new DriverChain();
			$this->app['events']->fire('phpcr-odm.drivers.chain.creating', array($chain));

			$config->setMetadataDriverImpl($chain);

			$session = $this->getPhpcrSession();

			$this->app['events']->fire('phpcr-odm.manager.creating', array($session, $config));

			$documentManager = \Doctrine\ODM\PHPCR\DocumentManager::create($session, $config);

			$this->app['events']->fire('phpcr-odm.manager.created', array($documentManager));

			return $documentManager;
		});

		$this->app['events']->listen('artisan.start', function($artisan)
		{
			$dm = $this->app['phpcr.manager'];
			$doctrineConnection = $this->app['config']->get('laravel-phpcr-odm::connections.doctrine');

			$helpers = array();
			$helpers['dm'] = new \Doctrine\ODM\PHPCR\Tools\Console\Helper\DocumentManagerHelper(null, $dm);
			$helpers['jackalope-doctrine-dbal'] = new \Jackalope\Tools\Console\Helper\DoctrineDbalHelper($this->app['phpcr.connections.doctrine']);

			$helperSet = new \Symfony\Component\Console\Helper\HelperSet($helpers);

			$artisan->setHelperSet($helperSet);
		});

		// Add commands to artisan
		$this->addCommands();
	}

	/**
	 * Get the annotation driver
	 *
	 * @todo  use Driver chain instead
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

		$reader = new \Doctrine\Common\Annotations\AnnotationReader();
		$paths = array();

		$this->app['events']->fire('phpcr-odm.drivers.annotation.creating', array($paths));

		$driver = new \Doctrine\ODM\PHPCR\Mapping\Driver\AnnotationDriver($reader, $paths);

		return $driver;
	}

	public function getPhpcrSession()
	{
		$driver = $this->app['config']->get('laravel-phpcr-odm::default');

		if($driver === 'doctrine')
		{
			return $this->getDoctrineSession();
		}
		elseif('jackrabbit')
		{
			return $this->getJackRabbitSession();
		}
	}

	/**
	 * Get a PHPCR Session
	 */
	public function getJackRabbitSession()
	{
		$url = $this->app['config']->get('laravel-phpcr-odm::connections.jackrabbit.url');
		$user = $this->app['config']->get('laravel-phpcr-odm::connections.jackrabbit.username');
		$password = $this->app['config']->get('laravel-phpcr-odm::connections.jackrabbit.password');
		$workspace = $this->app['config']->get('laravel-phpcr-odm::connections.jackrabbit.workspace');

		$factory = new \Jackalope\RepositoryFactoryJackrabbit;
		$repository = $factory->getRepository(
			array("jackalope.jackrabbit_uri" => $url)
		);

		$credentials = new \PHPCR\SimpleCredentials($user, $password);

		$session = $repository->login($credentials, $workspace);

		return $session;
	}

	public function getDoctrineSession()
	{
		$workspace = $this->app['config']->get('laravel-phpcr-odm::connections.doctrine.workspace');

		$factory = new \Jackalope\RepositoryFactoryDoctrineDBAL;
		$repository = $factory->getRepository(
		    array('jackalope.doctrine_dbal_connection' => $this->app['phpcr.connections.doctrine'])
		);

		// dummy credentials to comply with the API
		$credentials = new \PHPCR\SimpleCredentials(null, null);
		$session = $repository->login($credentials, $workspace);

		return $session;
	}

	public function addCommands()
	{
		$this->app->bind('phpcr.commands.workspace.create', '\PHPCR\Util\Console\Command\WorkspaceCreateCommand');
		$this->app->bind('phpcr.commands.workspace.export', '\PHPCR\Util\Console\Command\WorkspaceExportCommand');
		$this->app->bind('phpcr.commands.workspace.import', '\PHPCR\Util\Console\Command\WorkspaceImportCommand');
		$this->app->bind('phpcr.commands.workspace.list', '\PHPCR\Util\Console\Command\WorkspaceListCommand');
		$this->app->bind('phpcr.commands.workspace.import', '\PHPCR\Util\Console\Command\WorkspaceImportCommand');
		$this->app->bind('phpcr.commands.workspace.purge', '\PHPCR\Util\Console\Command\WorkspacePurgeCommand');
		$this->app->bind('phpcr.commands.workspace.query', '\PHPCR\Util\Console\Command\WorkspaceQueryCommand');
		$this->app->bind('phpcr.commands.nodetype.register', '\PHPCR\Util\Console\Command\NodeTypeRegisterCommand');
		$this->app->bind('phpcr.commands.node.dump', '\PHPCR\Util\Console\Command\NodeDumpCommand');
		$this->app->bind('phpcr.commands.nodetype.register', '\PHPCR\Util\Console\Command\NodeTypeRegisterCommand');
		$this->app->bind('phpcr.commands.nodetype.register.system', '\Doctrine\ODM\PHPCR\Tools\Console\Command\RegisterSystemNodeTypesCommand');
		$this->app->bind('phpcr.commands.querybuilder.dump', '\Doctrine\ODM\PHPCR\Tools\Console\Command\DumpQueryBuilderReferenceCommand');

		$this->app->bind('phpcr.commands.dbal.init', '\Jackalope\Tools\Console\Command\InitDoctrineDbalCommand');


		$this->commands(array(
			'phpcr.commands.workspace.create',
			'phpcr.commands.workspace.export',
			'phpcr.commands.workspace.import',
			'phpcr.commands.workspace.list',
			'phpcr.commands.workspace.import',
			'phpcr.commands.workspace.purge',
			'phpcr.commands.workspace.query',
			'phpcr.commands.nodetype.register',
			'phpcr.commands.node.dump',
			'phpcr.commands.nodetype.register',
			'phpcr.commands.nodetype.register.system',
			'phpcr.commands.querybuilder.dump',

			'phpcr.commands.dbal.init',
		));
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