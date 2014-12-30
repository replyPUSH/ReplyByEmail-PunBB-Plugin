<?php if (!defined('FORUM')) exit;

/**
 *  @@ reply_by_email_domain @@
 *
 *  Links Notify worker to the worker collection
 *  and retrieves it. Auto initialising.
 *
 *  Provides a simple way for other workers, or
 *  the plugin file to call it method and access its
 *  properties.
 *
 *  A worker will reference the Notify work like so:
 *  $this->plgn->notify()
 *
 *  The plugin file can access it like so:
 *  $this->notify()
 *
 *  @abstract
 */

abstract class reply_by_email_notify_domain extends reply_by_email_settings_domain {
    /**
     * The unique identifier to look up worker
     * @var string $worker_name
     */

    private $worker_name = 'notify';

    /**
     *  @@ Notify @@
     *
     *  Notify worker domain address,
     *  links and retrieves
     *
     *  @return void
     */

    public function notify(){
        $worker_name = $this->worker_name;
        $worker_class = $this->get_plugin_index().$worker_name;
        return $this->link_worker($worker_name,$worker_class);
    }
}


/**
 *  @@ reply_by_email_notify @@
 *
 *  The worker used to handle the main
 *  interactions.
 *
 */

class reply_by_email_notify {
    //where replypush.com nofications are sent to for processing
    public function replypush_controller(){
        global $forum_config;
        if(array_key_exists('uri', $_GET) && $_GET['uri']==$forum_config['reply_by_email_uri']){
            $this->plgn->api()->process_incoming_notification();
        }else{
            $this->plgn->api()->denied();
        }
    }
}
