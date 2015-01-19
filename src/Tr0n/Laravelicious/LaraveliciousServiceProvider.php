<?php namespace Tr0n\Laravelicious;

use Illuminate\Support\ServiceProvider;

class LaraveliciousServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = true;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot() {
		$this->package('tr0n/laravelicious');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register() {
		// Register any bindings
		$this->registerBindings();
	}

	/**
	 * Register the application bindings that are required.
	 */
	private function registerBindings()
	{
		// Bind to the "Asset" section
		$this->app->singleton('laravelicious', function() {
			return new Laravelicious();
		});
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides() {
		return array('laravelicious');
	}

}
