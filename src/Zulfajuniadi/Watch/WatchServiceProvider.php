<?php
namespace Zulfajuniadi\Watch;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Command;
use \Route;
use \HTML;
use \Response;
use \Input;
use \Event;
use \Queue;
use \RecursiveIteratorIterator;
use \RecursiveDirectoryIterator;

class EnableWatch extends Command
{
  protected $name = 'watch:enable';
  protected $description = 'Start watching for file changes';

  public function __construct($status_file)
  {
    parent::__construct();
    $this->status_file = $status_file;
  }

  public function fire()
  {
    if(!file_exists($this->status_file)){
      $this->info('Watcher Enabled.');
      file_put_contents($this->status_file, date('Y-m-d H:i:s'));
    } else {
      $this->info('Watcher Already Enabled.');
    }
  }
}

class DisableWatch extends Command
{
  protected $name = 'watch:disable';
  protected $description = 'Stop watching for file changes';

  public function __construct($status_file)
  {
    parent::__construct();
    $this->status_file = $status_file;
  }

  public function fire()
  {
    if(!file_exists($this->status_file)){
      $this->info('Watcher Already Disabled.');
    } else {
      $this->info('Watcher Disabled.');
      unlink($this->status_file);
    }
  }
}

class WatchStatus extends Command
{
  protected $name = 'watch:status';
  protected $description = 'Checks the status of the watcher.';

  public function __construct($status_file)
  {
    parent::__construct();
    $this->status_file = $status_file;
  }

  public function fire()
  {
    if(!file_exists($this->status_file)){
      $this->info('Watcher Disabled.');
    } else {
      $this->info('Watcher Enabled: ' . file_get_contents($this->status_file));
    }
  }
}

class WatchServiceProvider extends ServiceProvider {

	protected $defer = false;

  private $watcher_enabled = false;
  private $status_file;

  private function register_event_listener()
  {
    $watch =& $this;
    Event::listen('watcher:reload', function() use ($watch){
      touch($watch->watcher_reload_file);
    });
  }

  private function register_view_macro()
  {
    $watcher = $this;
    HTML::macro('watcherScript', function($timeout = 3000) use ($watcher) {
      if($this->watcher_enabled)
        return '<script src="watchpoller.js" id="pollscript">' . $timeout . '</script>';
    });
  }

  private function register_utils()
  {
    Route::get('/watchpoller.js', function(){
      return Response::make(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'poll.js'), 200, array('content-type' => 'application/javascript'));
    });
    Route::get('/_watcherforcereload', function(){
      return Event::fire('watcher:reload');
    });
  }

  private function register_watcher()
  {
    Route::get('/_watcher', function(){
      clearstatcache();
      $input = json_decode(Input::Get('query'));
      $response = 'NOOP';
      if($input !== null && $input->timestamp) {
        $timestamp = strtotime($input->timestamp);
        $viewBase = app_path() . '/views/';
        $views = array();
        foreach (new RecursiveIteratorIterator (new RecursiveDirectoryIterator ($viewBase)) as $x) {
          if(!$x->isDir() && $x->getCTime() > $timestamp)
            $views[] = $x->getCTime();
        }
        if(count($views) > 0) {
          $response = 'RELOAD';
        }
        if(isset($input->css)) {
          foreach ($input->css as $cssFile) {
            if(filemtime(public_path() . $cssFile) > $timestamp) {
              $response = 'RELOAD';
            }
          }
        }
        if(isset($input->js)) {
          foreach ($input->js as $jsFile) {
            if(filemtime(public_path() . $jsFile) > $timestamp) {
              $response = 'RELOAD';
            }
          }
        }
        if(filemtime($this->watcher_reload_file) > $timestamp) {
          $response = 'RELOAD';
        }
        return Response::make($response, 200, array('Last-Modified' => date('r')));
      }
      return Response::make($response, 200, array('Last-Modified' => date('r')));
    });
  }

	public function register()
	{
    $status_file = $this->status_file = storage_path() . DIRECTORY_SEPARATOR . 'meta' . DIRECTORY_SEPARATOR . '.watcher_enabled';
    $reload_file = $this->watcher_reload_file = storage_path() . DIRECTORY_SEPARATOR . 'meta' . DIRECTORY_SEPARATOR . '.watcher_reload';
    if(!file_exists($reload_file)){
      touch($reload_file);
    }
    $this->app['watch:enable'] = $this->app->share(function() use ($status_file) {
      return new EnableWatch($status_file);
    });
    $this->app['watch:disable'] = $this->app->share(function() use ($status_file) {
      return new DisableWatch($status_file);
    });
    $this->app['watch:status'] = $this->app->share(function() use ($status_file) {
      return new WatchStatus($status_file);
    });
    $this->commands(array('watch:enable', 'watch:disable', 'watch:status'));
  }

  public function boot()
  {
    $enabled = $this->watcher_enabled = file_exists($this->status_file);
    $this->register_view_macro();
    if($enabled) {
      $this->register_event_listener();
      $this->register_watcher();
      $this->register_utils();
    }
  }
}