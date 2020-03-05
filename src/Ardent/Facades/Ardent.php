<?php namespace LaravelArdent\Ardent\Facades;

use Illuminate\Support\Facades\Facade;

class Ardent extends Facade {

	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 */
	protected static function getFacadeAccessor() { return 'ardent'; }

}