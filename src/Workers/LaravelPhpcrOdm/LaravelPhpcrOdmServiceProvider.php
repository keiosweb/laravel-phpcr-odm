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

		$this->app->bind('phpcr.manager', function()
		{
			// Prepare configuration
			$config = new \Doctrine\ODM\PHPCR\Configuration();
			$config->setProxyDir($this->app['config']->get('laravel-phpcr-odm::proxy.directory'));
			$config->setProxyNamespace($this->app['config']->get('laravel-phpcr-odm::proxy.namespace', 'Proxies'));
			$config->setAutoGenerateProxyClasses($this->app['config']->get('laravel-phpcr-odm::proxy.auto_generate', true));

			$chain = new DriverChain();
			$this->app['events']->fire('phpcr-odm.drivers.chain.creating', array($chain));

			$config->setMetadataDriverImpl($chain);

			$documentManager = \Doctrine\ODM\PHPCR\DocumentManager::create($this->app->make('phpcr.session'), $config);

			return $documentManager;
		});

		$this->app['events']->listen('artisan.start', function($artisan)
		{
			$dm = $this->app['phpcr.manager'];

			$helperSet = new \Symfony\Component\Console\Helper\HelperSet(array(
			    'dm' => new \Doctrine\ODM\PHPCR\Tools\Console\Helper\DocumentManagerHelper(null, $dm)
			));
			$artisan->setHelperSet($helperSet);
		});

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
		AnnotationRegistry::registerFile(__DIR__.'/../../../../../doctrine/phpcr-odm/lib/Doctrine/ODM/PHPCR/Mapping/Annotations/DoctrineAnnotations.php');

		$reader = new \Doctrine\Common\Annotations\AnnotationReader();
		$paths = array();

		$this->app['events']->fire('phpcr-odm.drivers.annotation.creating', array($paths));

		$driver = new \Doctrine\ODM\PHPCR\Mapping\Driver\AnnotationDriver($reader, $paths);

		return $driver;
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