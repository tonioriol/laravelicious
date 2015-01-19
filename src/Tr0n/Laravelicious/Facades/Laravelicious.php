<?php namespace Tr0n\Laravelicious\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Illuminate\View\Environment
 */
class Laravelicious extends Facade {

	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 */
	protected static function getFacadeAccessor() {
		return 'laravelicious';
	}

}
