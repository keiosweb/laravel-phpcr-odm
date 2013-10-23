# Integration of Doctrine-ODM

## Introduction

This package includes doctrine odm

## Installation

You must have an implementation of phpcr installed. So first go [here](https://github.com/Workers/laravel-phpcr-jackrabbit) and install it.

in ```/app/config/app.php```add this to the ```providers``` Array.
```php
'Workers\LaravelPhpcrJackrabbit\LaravelPhpcrOdmServiceProvider',
```

We have to inform Jackrabbit about node-types. 

## Configuration

Publish the configuration if you need other parametes
```php artisan config:publish workers/laravel-phpcr-odm```

## Usage

Add a driver and namespace/folder to scan for your models

```php
Event::listen('phpcr-odm.drivers.chain.creating', function($chain) use($app)
{
	$chain->addDriver($app->make('phpcr.drivers.annotation'), 'MyNamespaceToUse');
});
```

Then create a model. Example:

```php
<?php namespace MyNamespace;

use Doctrine\ODM\PHPCR\Mapping\Annotations as PHPCR;

/**
 * @PHPCR\Document(referenceable=true)
 */
class Post
{

	/**
	 * @PHPCR\Uuid()
	 */
	protected $uuid;

	/**
	 * @PHPCR\Id()
	 */
	protected $slug;

	/**
	 * @PHPCR\ParentDocument()
	 */
	protected $parent;

	/**
	 * @PHPCR\NodeName
	 */
	protected $title;

	public function setParent($parent)
	{
		$this->parent = $parent;
		return $this;
	}

	public function setTitle($title)
	{
		$this->title = $title;
		return $this;
	}

	public function getTitle()
	{
		return $this->title;
	}

}
```

Now you can use the document manager to manage your data:

```php
// Get the document manager
$dm = $app->make('phpcr.manager');

// get the root node
$root = $dm->find(null, '/');

// create a post
$post = new Post();

$post->setParent($root);
$post->setTitle('My Post');

$dm->persist($page);
$dm->flush();

// Yeah...
$post = $dm->find(null, '/Post/Post 2');

```

## Events

### phpcr-odm.drivers.chain.creating

#### Parameters

1. $chain \Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain

#### Example

```php
Event::listen('phpcr-odm.drivers.chain.creating', function($chain) use($app)
{
	$chain->addDriver($app->make('phpcr.drivers.annotation'), 'MyNamespaceToUse');
});
```

### phpcr-odm.drivers.annotation.creating

#### Parameters

1. $paths Array an array of paths to load

#### Example

```php
Event::listen('phpcr-odm.drivers.annotation.creating', function($paths)
{
	$paths[] = 'my/path/to/load'
});
```