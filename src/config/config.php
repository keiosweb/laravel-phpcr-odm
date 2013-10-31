<?php

return array(

	/**
	 * jackrabbit
	 * doctrine          You have to install the package
	 */
	'default' => 'doctrine',

	'connections' => array(

		'jackrabbit' => array(
			'url'         => 'http://127.0.0.1:8888/server/',
			'username'    => 'admin',
			'password'    => 'admin',
			'workspace'   => 'default',
		),

		'doctrine' => array(
			'driver'    => 'pdo_mysql',
		    'host'      => 'localhost',
		    'user'      => 'root',
		    'password'  => 'cms',
		    'dbname'    => 'dbname',
    		'path'      => '', // for SQLite
    		'workspace'   => 'default',
		),

	),

	'proxy' => array(
		'directory'     => __DIR__.'/../../../../../app/storage/proxies',
		'namespace'     => 'Proxies',
		'auto_generate' => true,
	),
	
);