<?php
namespace Zulfajuniadi\Watch;

use \RecursiveIteratorIterator;
use \RecursiveDirectoryIterator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\HTML;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Event;
use Illuminate\Console\Command;

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

  public $watcher_enabled = false;
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
    HTML::macro('watcherScript', function(
      $timeout = 3000,
      Array $additionalFolders = array(),
      Array $otherConfigs = array()
    ) use ($watcher) {
      $otherConfigsString = '';
      foreach ($otherConfigs as $key => $value) {
        if(is_array($value)) {
          $value = serialize($value);
        }
        $otherConfigsString .= ' data-' . $key . '=\'' . $value . '\'';
      }
      if($watcher->watcher_enabled)
        return '<script src="/watchpoller.js" id="pollscript" data-additionalfolders=\'' . serialize($additionalFolders) . '\' data-timeout="' . $timeout . '" ' . $otherConfigsString . '></script>';
    });
  }

  private function register_utils()
  {
    Route::get('/watchpoller.js', function(){
      return Response::make(file_get_contents(__DIR__ . '/poll.js'), 200, array('content-type' => 'application/javascript'));
    });
    Route::get('/_watcherforcereload', function(){
      return Event::fire('watcher:reload');
    });
  }

  public function cleanup($obj) {
    if($obj) {
      foreach ($obj as $key => $value) {
        if(is_string($value) && strtolower($value) !== 'false') {
          @$result = unserialize($value);
          if($result !== false) {
            $value = $result;
          }
        }
        $obj->{$key} = $value;
      }
    }
    return $obj;
  }

  private function check_modified($directory, $timestamp)
  {
    $result = array();
    foreach (new RecursiveIteratorIterator (new RecursiveDirectoryIterator ($directory)) as $x) {
      if(!$x->isDir() && $x->getCTime() > $timestamp)
        $result[] = $x->getCTime();
    }
    return $result;
  }

  private function register_watcher()
  {
    $watcher = $this;
    Route::get('/_watcher', function() use ($watcher) {
      clearstatcache();
      $input = $watcher->cleanup(json_decode(Input::Get('query')));
      Event::fire('watcher:check', array($input));
      $response = array('do' => 'NOOP');
      if($input !== null && $input->timestamp) {
        $timestamp = strtotime($input->timestamp);
        $viewBase = app_path() . '/views/';
        $controllerBase = app_path() . '/controllers/';
        $modelBase = app_path() . '/models/';
        $views = $watcher->check_modified($viewBase, $timestamp);
        $controllers = $watcher->check_modified($controllerBase, $timestamp);
        $models = $watcher->check_modified($modelBase, $timestamp);
        $otherDirs = array();
        if(isset($input->additionalfolders)) {
          $additionalfolders = $input->additionalfolders;
          if(is_array($additionalfolders)) {
            foreach ($additionalfolders as $folder) {
              $otherBase = base_path() . DS . $folder;
              if(is_dir($otherBase)) {
                $otherDirs += $watcher->check_modified($controllerBase, $timestamp);
              }
            }
          }
        }
        if(count($views) > 0 || count($controllers) > 0 || count($models) > 0 || count($otherDirs) > 0) {
          $response = array('do' => 'RELOAD');
        }
        if(isset($input->css)) {
          foreach ($input->css as $cssFile) {
            $file = public_path() . $cssFile;
            if(file_exists($file) && filemtime($file) > $timestamp) {
              $response = array('do' => 'RELOAD');
            }
          }
        }
        if(isset($input->js)) {
          foreach ($input->js as $jsFile) {
            $file = public_path() . $jsFile;
            if(file_exists($file) && filemtime($file) > $timestamp) {
              $response = array('do' => 'RELOAD');
            }
          }
        }
        if(filemtime($watcher->watcher_reload_file) > $timestamp) {
          $response = array('do' => 'RELOAD');
        }
        return Response::json($response);
      }
      return Response::json($response);
    });
  }

  public function register()
  {
    $status_file = $this->status_file = storage_path()  . '/meta/.watcher_enabled';
    $reload_file = $this->watcher_reload_file = storage_path()  . '/meta/.watcher_reload';
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