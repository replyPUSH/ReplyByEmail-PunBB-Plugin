<?php if (!defined('FORUM')) exit;
    
function reply_by_email_load($class){
    global $ext_info;
    $match = array();
    if(preg_match('`^reply_by_email_(.*)`',$class, $match)){
        $file = strtolower(preg_replace('`_domain$`','',$match[1]));
        include_once($ext_info['path'].'/class.'.$file.'.php');
    }
}

// auto load worker/domain classes.
spl_autoload_register('reply_by_email_load');

// Initialise loader to be use by various libraries an architecture
reply_by_email_utility::init_load();

// auto load replypush model
reply_by_email_utility::register_load_map('`^replypush_model$`','models','class.replypushmodel.php');

// auto load replypush api class
reply_by_email_utility::register_load_map('`^(ReplyPush)$`','library','class.{$matches[0]}.php');

/**
 *  @@ reply_by_email @@
 *
 *  The plugin class which is referenced by
 *  Garden's pluggable interface.
 *
 *  The plugin hook uses workers, which often
 *  collaborate together on the tasks in hand.
 */

class reply_by_email extends reply_by_email_notify_domain{
    
    public static $handler;
    public static $ext_info;
    
    public static function default_configs(){
        return array(
            'account_no'      => '',
            'secret_id'       => '',
            'secret_key'      => '',
            'hash_method'     => '',
            'uri'             => uniqid(),
            'replypush_email' => 'post@replypush.com',
        );
    }
    
    public static function handler(){
        global $ext_info;
        
        if(!self::$handler)
            self::$handler = new reply_by_email();
        return self::$handler;
    }
    
    function __construct() {
        global $ext_info;
        self::$ext_info = $ext_info;
    }
    
    public function on_get_subscribers($type, $object_info, $object_id, &$query){
        $this->api()->process_messages_init($type, $object_info, $object_id, $query);
    }
    
    public function on_subscriptions_send($subscribers){
        $this->api()->process_messages($subscribers);
    }
    
    public function on_before_send(&$headers){
        $this->api()->mail_headers($headers);
    }
    
    public function url_scheme(){
        global $forum_url, $forum_config;
        $forum_url['admin_settings_reply_by_email'] = 'admin/settings.php?section=reply-by-email';
        
        if(isset($forum_config['o_sef']) && $forum_config['o_sef']!='Default'){
            $forum_url['reply_by_email_url'] = 'replypush/notify/$1/';
        }else{
            $forum_url['reply_by_email_url'] = 'extensions/reply_by_email/notify.php?url=$1';
        }
    }
    
    public function rewrite(){
        global $forum_rewrite_rules, $forum_config;
        $forum_rewrite_rules['`^replypush/notify/('.$forum_config['reply_by_email_uri'].')/?$`i'] = 'extensions/reply_by_email/notify.php?uri=$1';
    }
    
    public static function setup(){
        
        global $forum_db, $forum_config;
        
        if(!$forum_db->table_exists('replypush_log')){
            $forum_db->create_table('replypush_log',
                array(
                    'FIELDS' => array(
                        'message_id' => array('datatype' => 'VARCHAR(36)'),
                        'message' => array('datatype' => 'TEXT')
                    ),
                    'PRIMARY KEY'   => array('message_id')
                )
            );
        }
        
        if(!$forum_db->table_exists('replypush_ref')){
            $forum_db->create_table('replypush_ref',
                array(
                    'FIELDS' => array(
                        'ref_hash' => array('datatype' => 'VARCHAR(32)'),
                        'ref' => array('datatype' => 'TEXT')
                    ),
                    'PRIMARY KEY'   => array('ref_hash')
                )
            );
        }
        foreach (self::default_configs() as $name => $value) {
            if (!array_key_exists('reply_by_email_'.$name, $forum_config)){
                //set uri
                if($name=='notify_uri')
                    $value=uniqid();
                
                $query = array(
                    'INSERT'    => 'conf_name, conf_value',
                    'INTO'      => 'config',
                    'VALUES'    => '\'reply_by_email_'.$forum_db->escape($name).'\',\''.$forum_db->escape($value).'\''
                );
                
                $result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
            }
        }

    }
    
}
