<?php if (!defined('FORUM')) exit;

/**
 *  @@ reply_by_email_utility_domain @@
 *
 *  Links utility worker to the worker collection
 *  and retrieves it. Auto initialising.
 *
 *  Provides a simple way for other workers, or
 *  the plugin file to call it method and access its
 *  properties.
 *
 *  It also is a special domain that holds the workers
 *  collection, and link_worker method.
 *
 *  A worker will reference the utility work like so:
 *  $this->plgn->utility()
 *
 *  The plugin file can access it like so:
 *  $this->utility()
 *
 *  @abstract
 */

abstract class reply_by_email_utility_domain {

   /**
    * Holds a collection of workers
    * @var array[string]class $workers
    */
    protected $workers = array();

   /**
    * The unique identifier to look up worker
    * @var string $worker_name
    */
    private $worker_name = 'utility';
  
  
    public function get_plugin_index(){
        return 'reply_by_email_';
    }

   /**
    *  @@ utility @@
    *
    *  utility worker domain address,
    *  links and retrieves
    *
    *  @return void
    */

    public function utility(){
        $worker_name = $this->worker_name;
        $worker_class = $this->get_plugin_index().$worker_name;
        return $this->link_worker($worker_name,$worker_class);
    }

   /**
    *  @@ link_worker @@
    *
    *  This method is used by the domain class to
    *  Link the worker to the worker group, and
    *  retrieve. Auto-initialises the class
    *
    *  @param string $worker_name
    *  @param string $worker_class
    *  @param mixed args.* any extra params to be passed to worker constructor.
    *
    *  @return void
    */

    public function link_worker($worker_name,$worker_class){
        if(array_key_exists($worker_name, $this->workers))
            return $this->workers[$worker_name];
        $args = func_get_args();
        switch(count($args)){
            case 2;
                $worker = new $worker_class();
                break;
            case 3:
                $worker = new $worker_class($args[2]);
                break;
            case 4:
                $worker = new $worker_class($args[2],$args[3]);
                break;
            case 5:
                $worker = new $worker_class($args[2],$args[3],$args[4]);
                break;
            default:
                $ref = new ReflectionClass($worker_class);
                $worker = $ref->newInstanceArgs($args);
                break;
        }
        $worker->plgn = $this;
        return $this->workers[$worker_name] = $worker;

    }

}

/**
 *  @@ reply_by_email_utility @@
 *
 *  the worker provided utility methods,
 *  and general useful stuff for plugin dev
 *
 */

class reply_by_email_utility {

    private static $load_maps = array();


   /**
    *  @@ register_load_map @@
    *
    *  A simple way for registering Classes for auto-loading of class files
    *
    *  @param string $matches the class name pattern to match
    *  @param string $folder the folder name (can use sub-match substitution format)
    *  @param string $file the file name (can use sub-match substitution format)
    *  @param bool $lowercase_matches default TRUE mean they will be inserted in string lowercased
    *
    *  @return void
    */

    public static function register_load_map($match,$folder,$file,$lowercase_matches=TRUE){
        self::$load_maps[] = array(
            'match' => $match,
            'folder' => $folder,
            'file' => $file,
            'lowercase_matches' => $lowercase_matches
        );
    }

   /**
    *  @@ load_map_parse @@
    *
    *  Used by load to replace strings with
    *  sub-patterns from class name match
    *
    *  e.g. ${Matches[n]} where n is the
    *  sub-pattern
    *
    *  @param array[]string $matches
    *  @param string $str the string for parsing
    *
    *  @return string
    */

    private static function load_map_parse($matches,$str){
        foreach ($matches As $match_i => $match_v){
            $str = preg_replace('`\{?\$\{?matches\['.$match_i.'\]\}?`',$match_v,$str);
        }
        return $str;
    }

   /**
    *  @@ load @@
    *
    *  auto-load function which employs
    *  reg-exp pattern matching
    *
    *  @param string $class name of class
    *
    *  @return void
    */

    public static function load($class){

        $maps = self::$load_maps;
        foreach ($maps As $map){
          $matches = array();

          if(preg_match($map['match'],$class,$matches)){

            if($map['lowercase_matches'])
                $matches = array_map('strtolower',$matches);

            $map['folder'] = self::load_map_parse($matches,$map['folder']);
            $map['file'] = self::load_map_parse($matches,$map['file']);
            require_once(reply_by_email::$ext_info['path'].($map['folder'] ? '/'.$map['folder']: '').'/'.$map['file']);
            break;
          }
        }
    }

   /**
    *  @@ init_load @@
    *
    *  register auto-load function
    *
    *  @return void
    */

    public static function init_load(){
        spl_autoload_register('reply_by_email_utility::load');
    }

}
