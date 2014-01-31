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
    HTML::macro('watcherScript', function($timeout = 3000, Array $additionalFolders = array(), Array $otherConfigs = array()) use ($watcher) {
      $otherConfigsString = '';
      foreach ($otherConfigs as $key => $value) {
        if(is_array($value)) {
          $value = serialize($value);
        }
        $otherConfigsString .= ' data-' . $key . '=\'' . $value . '\'';
      }
      if($this->watcher_enabled)
        return '<script src="watchpoller.js" id="pollscript" data-additionalfolders=\'' . serialize($additionalFolders) . '\' data-timeout="' . $timeout . '" ' . $otherConfigsString . '></script>';
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

  private function cleanup($obj) {
    foreach ($obj as $key => $value) {
      if(is_string($value) && strtolower($value) !== 'false') {
        @$result = unserialize($value);
        if($result !== false) {
          $value = $result;
        }
      }
      $obj->{$key} = $value;
    }
    return $obj;
  }

  private function register_watcher()
  {
    Route::get('/_watcher', function(){
      clearstatcache();
      $input = $this->cleanup(json_decode(Input::Get('query')));
      Event::fire('watcher:check', array($input));
      $response = array('do' => 'NOOP');
      if($input !== null && $input->timestamp) {
        $timestamp = strtotime($input->timestamp);
        $viewBase = app_path() . '/views/';
        $views = array();
        foreach (new RecursiveIteratorIterator (new RecursiveDirectoryIterator ($viewBase)) as $x) {
          if(!$x->isDir() && $x->getCTime() > $timestamp)
            $views[] = $x->getCTime();
        }
        if(isset($input->additionalfolders)) {
          $additionalfolders = $input->additionalfolders;
          if(is_array($additionalfolders)) {
            foreach ($additionalfolders as $folder) {
              $viewBase = base_path() . '/' . $folder;
              if(is_dir($viewBase)) {
                foreach (new RecursiveIteratorIterator (new RecursiveDirectoryIterator ($viewBase)) as $x) {
                  if(!$x->isDir() && $x->getCTime() > $timestamp)
                    $views[] = $x->getCTime();
                }
              }
            }
          }
        }
        if(count($views) > 0) {
          $response = array('do' => 'RELOAD');
        }
        if(isset($input->css)) {
          foreach ($input->css as $cssFile) {
            if(filemtime(public_path() . $cssFile) > $timestamp) {
              $response = array('do' => 'RELOAD');
            }
          }
        }
        if(isset($input->js)) {
          foreach ($input->js as $jsFile) {
            if(filemtime(public_path() . $jsFile) > $timestamp) {
              $response = array('do' => 'RELOAD');
            }
          }
        }
        if(filemtime($this->watcher_reload_file) > $timestamp) {
          $response = array('do' => 'RELOAD');
        }
        return Response::json($response);
      }
      return Response::json($response);
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