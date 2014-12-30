<?php if (!defined('FORUM')) exit;

/**
 *  @@ reply_by_email_api_domain @@
 *
 *  Links api worker to the worker collection
 *  and retrieves it. Auto initialising.
 *
 *  Provides a simple way for other workers, or
 *  the plugin file to call it method and access its
 *  properties.
 *
 *  A worker will reference the api work like so:
 *  $this->plgn->api()
 *
 *  The plugin file can access it like so:
 *  $this->api()
 *
 *  @abstract
 */

abstract class reply_by_email_api_domain extends reply_by_email_utility_domain {

    /**
     * The unique identifier to look up worker
     * @var string $worker_name
     */

    private $worker_name = 'api';

    /**
     *  @@ api @@
     *
     *  api worker domain address,
     *  links and retrieves
     *
     *  @return void
     */

    public function api(){
        $worker_name = $this->worker_name;
        $worker_class = $this->get_plugin_index().$worker_name;
        return $this->link_worker($worker_name,$worker_class);
    }

}


/**
 *  @@ reply_by_email_api @@
 *
 *  The worker used for the internals
 *
 *  Also can be access by other plugin by
 *  hooking reply_by_email_loaded_Handler
 *  and accessing $Sender->plgn->api();
 *
 */

class reply_by_email_api {
    
    /**
     * A lookup of long and short mail type codes
     * short used for ReplyPush API, and long for local
     * 
     * @var array[string]string
     */
    
    protected $mail_types = array(
        'topic' => 'topic',
        'post'  => 'post'
    );
    
    /**
     * A lookup of long for mail template names
     * 
     * @var array[string]string
     */
    
    protected $mail_templates = array(
        'topic' => 'new_topic',
        'post'  => 'new_post'
    );
    
    
    /**
     * A lookup of long for mail template names
     * 
     * @var array[string]array[string]
     */
    protected $mail_from_user = array(
        'topic' => array('id' =>'poster_id', 'username' => 'poster', 'email' => 'poster_email'),
        'post'  => array('id' =>'poster_id', 'username' => 'poster', 'email' => 'poster_email')
    );
    
    /**
     * Used to pass
     * 
     * @var array[string]string
     */
    protected $message_process = array();
    
    /**
     * Used to add/modify email headers
     * 
     * @var array[string]string
     */
    protected $email_headers = array();
    
    /**
     * @@ process_messages_init @@
     * 
     * Init processing of messages
     * 
     * @param string $type
     * @param array[string]string $object_info
     * @param int $object_id
     * @param array[string]mixed &$query
     * 
     * @return void
     */
    
    public function process_messages_init($type, $object_info, $object_id, &$query){
        global $forum_config, $new_pid;
        $secret_id   =   isset($forum_config['reply_by_email_secret_id']) ? $forum_config['reply_by_email_secret_id'] : FALSE;
        $secret_key  =   isset($forum_config['reply_by_email_secret_key']) ? $forum_config['reply_by_email_secret_key'] : FALSE;
        $account_no  =   isset($forum_config['reply_by_email_account_no']) ? $forum_config['reply_by_email_account_no'] : FALSE;
        
        //no credentials? Then don't process
        if(!($secret_id && $secret_key && $account_no))
            return;
        
        $query['SELECT'] = str_ireplace('u.language', 'CONCAT(u.language,\'/mail_templates\') AS language', $query['SELECT']);//to bypass normal subscriber processing. 
        $query['SELECT'] .= ', u.username, u.realname';
        
        
        
        if(defined('FORUM_DEBUG') && defined('RBE_DEBUG')){
            //test different users online with the same IP
            $query['WHERE'] = str_ireplace('COALESCE(o.logged, u.last_visit)>','0<', $query['WHERE']);
        }
        //ensure ids in object
        switch($type){
            case 'topic':
                $object_info['topic_id'] = $object_id;
                $object_info['id'] = $new_pid;
                break; 
            case 'post':
                $object_info['id'] = $object_id;
                break;
        }

        $this->message_process = array('type' => $type, 'object_info' => $object_info, 'object_id' => $object_id);
    }
    
    /**
     * @@ get_language_file @@
     * 
     * Detects and returns language file or default
     * 
     * @param string $language
     * 
     * @return string
     */
    
    public function get_language_file($language){
        $lang_file = reply_by_email::$ext_info['path'].'/lang/'.$language.'/main.php';
        if(!file_exists($lang_file))
            $lang_file = reply_by_email::$ext_info['path'].'/lang/Default/main.php';
        
        return $lang_file;
    }
    
    /**
     * @@ get_language_template @@
     * 
     * Detects and returns language template or default
     * 
     * @param string $language
     * @param string $template
     * 
     * @return string
     */
    
    public function get_language_template($language, $template){
        $lang_file = reply_by_email::$ext_info['path'].'/lang/'.$language.'/'.$template.'.tpl';
        
        if(!file_exists($lang_file))
            $lang_file = reply_by_email::$ext_info['path'].'/lang/Default/mail_templates/'.$template.'.tpl';
        return $lang_file;
    }
    
    /**
     * @@ subscription_messages @@
     * 
     * Helper to get subscription email  
     * messages in each language.
     * 
     * @param string $type
     * @param array[int]array[string]string $subscribers
     * @param array[string]string $object_info
     * @param int $object_id
     * 
     * @return array[string]mixed
     */
    
    public function subscription_messages($type, $subscribers, $object_info, $object_id){
        global $forum_url, $forum_config,$cur_posting;
        $subscription_emails = array();
        $errors = array();
        
        foreach($subscribers as $subscriber){
            if (!array_key_exists($subscriber['language'], $subscription_emails)){
                $message_file = $this->get_language_template($subscriber['language'], $this->mail_templates[$type]);
                if($message_file){
                    $sig_file = $this->get_language_template($subscriber['language'], 'sig');
                    $sig = '';
                    if($sig_file){
                        $sig = file_get_contents($sig_file);
                    }
                    $message = file_get_contents($message_file);
                    
                    $lang_main_file = $this->get_language_file($subscriber['language']);
                    
                    include($lang_main_file);

                    switch($type){
                        case 'topic':
                            $subject = str_replace('<site>', $_SERVER['HTTP_HOST'], $lang_main['topic_subject_format']);
                            $subject = str_replace('<topic_subject>', $object_info['subject'], $subject);
                            $subject = str_replace('<topic_id>', $object_info['topic_id'], $subject);
                            $message = str_replace('<sig>', $sig, $message);
                            $message = str_replace('<forum_name>', $cur_posting['forum_name'], $message);
                            $message = str_replace('<poster>', $object_info['poster'], $message);
                            $message = str_replace('<topic_subject>', $object_info['subject'], $message);
                            $message = str_replace('<post_url>', forum_link($forum_url['topic'], $object_id), $message);
                            $message = str_replace('<unsubscribe_url>', forum_link($forum_url['forum_unsubscribe'], array($object_info['forum_id'], generate_form_token('forum_unsubscribe'.$object_info['forum_id'].$subscriber['id']))), $message);
                            $message = str_replace('<board_mailer>', $forum_config['o_board_title'], $message);
                            $message = str_replace('<message>', parse_message($object_info['message'], true), $message);
                            break;
                        case 'post':
                            $subject = str_replace('<site>', $_SERVER['HTTP_HOST'], $lang_main['topic_subject_format']);
                            $subject = str_replace('<topic_subject>', $object_info['subject'], $subject);
                            $subject = str_replace('<topic_id>', $object_info['topic_id'], $subject);
                            $message = str_replace('<sig>', $sig, $message);
                            $message = str_replace('<topic_subject>', $object_info['subject'], $message);
                            $message = str_replace('<forum_name>', $cur_posting['forum_name'], $message);
                            $message = str_replace('<poster>', $object_info['poster'], $message);
                            $message = str_replace('<post_url>', forum_link($forum_url['post'], $object_id), $message);
                            $message = str_replace('<unsubscribe_url>', forum_link($forum_url['unsubscribe'], array($object_info['topic_id'], generate_form_token('unsubscribe'.$object_info['topic_id'].$subscriber['id']))), $message);
                            $message = str_replace('<board_mailer>', $forum_config['o_board_title'], $message);
                            $message = str_replace('<message>', parse_message($object_info['message'], true), $message);
                            break;
                    }
                    $from_user_id = intval($object_info[$this->mail_from_user[$type]['id']]);
                    $from_user_name = $object_info[$this->mail_from_user[$type]['username']];
                    $from_user_email = $object_info[$this->mail_from_user[$type]['email']];
                    $message = str_replace('<from_id>', $from_user_id, $message);
                    $message = str_replace('<from_name>', $from_user_name, $message);
                    $message = str_replace('<from_email>', $from_user_email, $message);
                    $message = str_replace('<rp_tag>','<a href="http://replypush.com#rp-sig"></a>', $message);
                    
                    $message = '<a href="http://replypush.com#rp-message"></a>'.$message;
                    $subscription_emails[$subscriber['language']] = array('subject' => $subject, 'message' => $message, 'lang_main' => $lang_main);
                    
                }
            }
        }
        
        return $subscription_emails;
    }
    
    /**
     * @@ process_messages @@
     * 
     * Get object meta generically based on type
     * 
     * @param string $type
     * @param array[string]string $object_info
     * 
     * @return array[string]mixed
     */
    
    public function object_meta($type, $object_info){
        $object_meta = array(
            'user_id'=>null,
            'username'=>null,
            'record_id' => null,
            'content_id'=>null
        );
        switch($type){
            case 'topic':
            case 'post':
                $object_meta['user_id'] = $object_info['poster_id'];
                $object_meta['username'] = $object_info['poster'];
                $object_meta['content_id'] = $object_info['topic_id'];
                $object_meta['record_id'] = $object_info['id'];
                break;
        }
        return $object_meta;
    }
    
    /**
     * @@ process_messages @@
     * 
     * Processes outgoing mail
     * 
     * @param array[int]array[string]string $subscribers
     * 
     * @return void
     */
    
    public function process_messages($subscribers){
        if(!empty($this->message_process) && $this->message_process['type'] && array_key_exists($this->message_process['type'], $this->mail_types)){
            global $forum_config;
            $secret_id   =   $forum_config['reply_by_email_secret_id'];
            $secret_key  =   $forum_config['reply_by_email_secret_key'];
            $account_no  =   $forum_config['reply_by_email_account_no'];
            
            $subscription_emails = $this->subscription_messages($this->message_process['type'], $subscribers, $this->message_process['object_info'], $this->message_process['object_id']);
            
            $reply_to_email = $forum_config['reply_by_email_replypush_email'] ? $forum_config['reply_by_email_replypush_email']: 'post@replypush.com';
            
            //get post activity neutral object meta
            $object_meta = $this->object_meta($this->message_process['type'], $this->message_process['object_info']);
            
            foreach($subscribers as $subscriber){
                $reply_to_name = $subscription_emails[$subscriber['language']]['lang_main']['reply_to_format'];
            
                $reply_to_name  =  str_replace('<name>', 'noreply', $reply_to_name);
                $reply_to_name  =  str_replace('<site>',  $_SERVER['HTTP_HOST'], $reply_to_name);
            
                //meta assciated with all notifications
                $user_id = intval($subscriber['id']);
                $user_name = $subscriber['username'];
                $user_email = $subscriber['email'];
                $user_realname = $subscriber['realname'];
                
                $time_stamp = time();
                
                //chose the best available hash method (within reason)
                $hash_method = isset($forum_config['reply_by_email_hash_method']) ? $forum_config['reply_by_email_hash_method'] : in_array('sha1',hash_algos()) ? 'sha1': 'md5';
                
                //custom 40 byte custom data (account_no and hash_method will be prepended by ReplyPush class to make 56 bytes)
                //comprises of 8 bytes sections, with verifiable data inserted
                $data = sprintf("%08x%08x%-8s%08x%08x", $object_meta['user_id'], $object_meta['record_id'], $this->message_process['type'], $object_meta['content_id'], $time_stamp);
                
                //use API class to create reference 
                $ReplyPush = new ReplyPush($account_no, $secret_id, $secret_key, $user_email, $data, $hash_method);
                
                $message_id = $ReplyPush->reference();
                $this->add_email_header('Message-ID: '.$message_id);
                
                if($forum_config['o_webmaster_email']==$user_email){
                    $this->email_from_email = isset($forum_config['reply_by_email_replypush_email']) ? $forum_config['reply_by_email_replypush_email']: 'post@replypush.com';
                }
                
                $replypush_model = new replypush_model();
                
                //get special reference key for threading
                $ref_hash = $this->reference_key($this->message_process['type'], $object_meta['record_id'], $object_meta['content_id'], $user_email);
                
                //get historic Reference for threading
                $ref = $replypush_model->get_ref($ref_hash);
                
                //add headers if historic refernces
                if($ref){
                    $this->add_email_header("References: {$ref}");
                    $this->add_email_header("In-Reply-To: {$ref}");
                }
                
                //save current message_id as Ref
                $replypush_model->save_ref($ref_hash, $message_id);

                $subscription_message = $subscription_emails[$subscriber['language']]['message'];
                $subscription_subject = $subscription_emails[$subscriber['language']]['subject'];
                
                $subscription_message = str_replace('<rp_sigid>', mt_rand(), $subscription_message);
                $subscription_message = str_replace('<username>', $user_name, $subscription_message);
                $subscription_message = str_replace('<realname>', $user_realname ? $user_realname : $user_name, $subscription_message);
                
                $this->add_email_header('Content-Type: text/html; charset=utf-8');
                
                $from_name  = str_replace('<site_title>', $forum_config['o_board_title'], $subscription_emails[$subscriber['language']]['lang_main']['from_name_format']);
                $from_email = $forum_config['o_webmaster_email']; 
                
                $from = "=?UTF-8?B?".base64_encode($from_name)."?=".' <'.$from_email.'>';
                
                $this->add_email_header('From: '.$from);
                
                forum_mail($user_email, $subscription_subject, $subscription_message, $reply_to_email, $reply_to_name);
                $this->clear_all_headers();
            } 
        }
    }
    
    
    /**
     * @@ reference_key @@
     * 
     * reference_key for email threading
     * 
     * @param string $type
     * @param int $record_id
     * @param int $content_id
     * @param string $email
     * 
     * @return string
     */
    
    public function reference_key($type, $record_id, $content_id, $email){        
        return md5($type.$record_id.$email);

    }
    
    
    /**
     * @@ process_incoming_notification @@
     * 
     * Processes, authenticates and verifies
     * POST notification from replypus.com
     * 
     * @return void
     */
    
    public function process_incoming_notification(){
        $notification = $_POST;
        if(!empty($notification)){
            global $forum_db, $forum_user, $forum_config;
            //no message id no message
            if(!array_key_exists('msg_id',$notification)){
                $this->denied();
            }
            
            $replypush_model = new replypush_model();
            
            //check for duplicate message id
            if($replypush_model->get_transaction($notification['msg_id'])){
                return;//ignore
            }
            

            $secret_id   =   isset($forum_config['reply_by_email_secret_id']) ? $forum_config['reply_by_email_secret_id'] : FALSE;
            $secret_key  =   isset($forum_config['reply_by_email_secret_key']) ? $forum_config['reply_by_email_secret_key'] : FALSE;
            $account_no  =   isset($forum_config['reply_by_email_account_no']) ? $forum_config['reply_by_email_account_no'] : FALSE;
            
             //no credentials? Then don't process
            if(!($secret_id && $secret_key && $account_no))
                return;
            
            //the user the notification reply come from
            $query = array(
                'SELECT'    => 'u.*, g.*, o.logged, o.idle, o.csrf_token, o.prev_url',
                'FROM'      => 'users AS u',
                'JOINS'     => array(
                    array(
                        'INNER JOIN'    => 'groups AS g',
                        'ON'            => 'g.g_id=u.group_id'
                    ),
                    array(
                        'LEFT JOIN'     => 'online AS o',
                        'ON'            => 'o.user_id=u.id'
                    )
                ),
                'WHERE' => 'u.email=\''.$forum_db->escape($notification['from']).'\''
            );

            $result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
            $forum_user = $forum_db->fetch_assoc($result);
            
            if(!$forum_user)
                $this->denied();
                
            require_once FORUM_ROOT.'lang/'.$forum_user['language'].'/post.php';
            
            //use api class to check reference
            $ReplyPush = new ReplyPush($account_no, $secret_id, $secret_key, $forum_user['email'], $notification['in_reply_to']);

            if($ReplyPush->hashCheck()){
                //split 56 bytes into 8 byte components and process
                $message_data = str_split($ReplyPush->referenceData,8);
                $from_user_id = hexdec($message_data[2]);
                $record_id = hexdec($message_data[3]);
                $type = trim($message_data[4]);
                $content_id = trim($message_data[5]);
                
                //get special reference key for threading
                $ref_hash = $this->reference_key($type, $record_id, $content_id, $forum_user['email']);
                
                //get historic reference for threading
                $ref = $replypush_model->get_ref($ref_hash);
                
                
                //save current message_id as ref
                $replypush_model->save_ref($ref_hash, $notification['from_msg_id']);
                
                //handle error notifications without inserting anything.
                if(isset($notification['error'])){
                    $this->process_incoming_error($notification['error'], $forum_user, $notification['subject'], $ref);
                    return;
                }
                
                $mail_type = array_search($type, $this->mail_types);

                $errors = array();
                switch($mail_type){
                    case 'topic':
                    case 'post':
                        $query = array(
                            'SELECT'    => 'p.*',
                            'FROM'      => 'posts AS p',
                            'WHERE'     => 'p.id='.intval($record_id) 
                        );
                        
                        $result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
                        $post = $forum_db->fetch_assoc($result);
                        
                        if($post['poster_id']!=$from_user_id){
                            $this->denied();
                        }else{
                            $query = array(
                                'SELECT'    => 'f.id, f.forum_name, f.moderators, f.redirect_url, fp.post_replies, fp.post_topics, t.subject, t.closed, s.user_id AS is_subscribed',
                                'FROM'      => 'topics AS t',
                                'JOINS'     => array(
                                    array(
                                        'INNER JOIN'    => 'forums AS f',
                                        'ON'            => 'f.id=t.forum_id'
                                    ),
                                    array(
                                        'LEFT JOIN'     => 'forum_perms AS fp',
                                        'ON'            => '(fp.forum_id=f.id AND fp.group_id='.$forum_user['g_id'].')'
                                    ),
                                    array(
                                        'LEFT JOIN'     => 'subscriptions AS s',
                                        'ON'            => '(t.id=s.topic_id AND s.user_id='.$forum_user['id'].')'
                                    )
                                ),
                                'WHERE' => '(fp.read_forum IS NULL OR fp.read_forum=1) AND t.id='.$post['topic_id']
                            );
                            
                            $result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
                            $forum = $forum_db->fetch_assoc($result);

                            $subscribe = 1;
                            $is_subscribed = $forum['is_subscribed'];
                            
                            if (!$forum || isset($post['redirect_url']) && $post['redirect_url'] != ''){
                                $this->denied();
                            }
                            
                            // moderating?
                            $mods_array = ($forum['moderators'] != '') ? unserialize($forum['moderators']) : array();
                            $forum_user['is_admmod'] = ($forum_user['g_id'] == FORUM_ADMIN || ($forum_user['g_moderator'] == '1' && array_key_exists($forum_user['username'], $mods_array))) ? true : false;

                            // permission to post?
                            if ((($post['topic_id'] && (($forum['post_replies'] == '' && $forum_user['g_post_replies'] == '0') || $forum['post_replies'] == '0')) ||
                                ($forum['id'] && (($forum['post_topics'] == '' && $forum_user['g_post_topics'] == '0') || $forum['post_topics'] == '0')) ||
                                (isset($forum['closed']) && $forum['closed'] == '1')) &&
                                !$forum_user['is_admmod']){
                                    $this->denied();
                            }
                            
                            $message = isset($notification['content']['text/html']) ? $notification['content']['text/html'] : $notification['content']['text/plain'];
                            $message = $this->parse_message($message, $errors);
                            $post_info = array(
                                'is_guest'      => false,
                                'poster'        => $forum_user['username'],
                                'poster_id'     => $forum_user['id'],
                                'poster_email'  => null,
                                'subject'       => $notification['subject'],
                                'message'       => $message,
                                'hide_smilies'  => '0',
                                'posted'        => time(),
                                'subscr_action' => ($forum_config['o_subscriptions'] == '1' && $subscribe && !$is_subscribed) ? 1 : (($forum_config['o_subscriptions'] == '1' && !$subscribe && $is_subscribed) ? 2 : 0),
                                'topic_id'      => $post['topic_id'],
                                'forum_id'      => $forum['id'],
                                'update_user'   => true,
                                'update_unread' => true
                            );

                            add_post($post_info, $new_pid);
    
                        }
                        $content_id = $post['id'];
                        break;
                }
                
                //if there was errors inserting then reply email them back 
                if(count($errors)){
                    $subect = $notification['subject'];
                    $this->send_reply_error($forum_user, $errors, $subject);
                }
                
            }else{
                $this->denied();
            }
            //don't save actual message
            unset($notification['content']);
            
            $replypush_model->log_transaction($notification);
        }
    }
    
    /**
     * @@ parse_message @@
     * 
     * Parse message (post) for sending/saving
     * 
     * @param string $message 
     * @param array[int]string &$errors
     * 
     * @return string
     */
    
    public function parse_message($message, &$errors){
        global $forum_config, $lang_post, $forum_user;
        $message = preg_replace("`[\n\t]`", '', $message);
        $message = preg_replace("`<((br( .*?)?/?)|(p( .*?)?/))>`i", "\n", $message);
        $message = strip_tags($message);
        $message = forum_linebreaks(forum_trim($message));
        
        

        if (strlen($message) > FORUM_MAX_POSTSIZE_BYTES)
            $errors[] = sprintf($lang_post['Too long message'], forum_number_format(strlen($message)), forum_number_format(FORUM_MAX_POSTSIZE_BYTES));
        else if ($forum_config['p_message_all_caps'] == '0' && check_is_all_caps($message) && !$forum_user['is_admmod'])
            $errors[] = $lang_post['All caps message'];

        // Validate BBCode syntax
        if ($forum_config['p_message_bbcode'] == '1' || $forum_config['o_make_links'] == '1'){
            if (!defined('FORUM_PARSER_LOADED'))
                require FORUM_ROOT.'include/parser.php';

            $message = preparse_bbcode($message, $errors);
        }

        if ($message == '')
            $errors[] = $lang_post['No message'];
            
        return $message;
    }
    
    /**
     * @@ denied @@
     * 
     * Deny invalid notifications
     * 
     * @return void
     */

    public function denied(){
        header("HTTP/1.0 403 Denied");
        exit();
    }
    
    /**
     * @@ process_incoming_error @@
     * 
     * Get long error message and send error email
     * 
     * @param string $error the short hand error message
     * @param object $user 
     * @param string $subject
     * 
     * @return void
     */
     
    public function process_incoming_error($error, $user, $subject, $ref=''){
        $this->send_reply_error($user, $error, $subject, true);
    }
    
    /**
     * @@ send_reply_error @@
     * 
     * Send error email
     * 
     * @param string $email
     * @param string $name
     * @param string $error_message
     * @param string $subject
     * @param string $ref
     * @param bool  $translate
     * 
     * @return void
     */
    
    public function send_reply_error($user, $error, $subject, $ref='', $translate=false){
        global $forum_config, $forum_user;
        $user_name = $user['username'];
        $user_email = $user['email'];
        $user_realname = $user['realname'];
        
        include($this->get_language_file($user['language']));
        
        $error_message = file_get_contents($this->get_language_template($user['language'], 'error'));
        $error_message = str_replace('<rp_sigid>', mt_rand(), $error_message);
        $error_message = str_replace('<username>', $user_name, $error_message);
        $error_message = str_replace('<realname>', $user_realname ? $user_realname : $user_name, $error_message);
        $error_message = str_replace('<error_message>', $translate ? $lang_main['error_'.$error] : $error, $error_message);
        
        
        if($forum_config['o_webmaster_email']==$user_email){
            $this->email_from_email = isset($forum_config['reply_by_email_replypush_email']) ? $forum_config['reply_by_email_replypush_email']: 'post@replypush.com';
        }
        
        //add headers if historic refernces
        if($ref){
            $this->add_email_header("References: {$ref}");
            $this->add_email_header("In-Reply-To: {$ref}");
        }
    
        $reply_to_email = $forum_config['reply_by_email_replypush_email'] ? $forum_config['reply_by_email_replypush_email']: 'post@replypush.com';
        $reply_to_name  = $lang_main['reply_to_format'];
        $reply_to_name  =  str_replace('<name>', 'noreply', $reply_to_name);
        $reply_to_name  =  str_replace('<site>',  $_SERVER['HTTP_HOST'], $reply_to_name);
        
        $from_name  = str_replace('<site_title>', $forum_config['o_board_title'], $lang_main['from_name_format']);
        $from_email = $forum_config['o_webmaster_email']; 
        $from = "=?UTF-8?B?".base64_encode($from_name)."?=".' <'.$from_email.'>';
        $this->add_email_header('From: '.$from);
        
        forum_mail($user_email, $subject, $error_message, $reply_to_email, $reply_to_name);
    }
    
    /**
     * @@ add_email_header @@
     * 
     * Store email head for later processing
     * 
     * @param string $email_header
     * 
     * @return void
     */
    
    public function add_email_header($email_header){
        $this->email_headers[] = $email_header;
    }
    
    /**
     * @@ clear_all_headers @@
     * 
     * clear any custom headers
     * 
     * @return void
     */
    
    public function clear_all_headers(){
        $this->email_headers = array();
    }

    /**
     * @@ mail_headers @@
     * 
     * Process header by replacing existing
     * headers with custom values or adding
     * new headers
     * 
     * @param string &$headers
     * 
     * @return void
     */
     
    public function mail_headers(&$headers){
        $not_replaced = array();
        foreach($this->email_headers as $header){
            list($header_name, $header_value) = explode(':',$header);
            $count = 0;
            $headers = preg_replace('`\b'.$header_name.'\s*:.*?\r\n`i', $header."\r\n", $headers,-1, $count);
            if(!$count){
                $not_replaced[] = $header;
            }
        }

        if(count($not_replaced)){
            $headers .= "\r\n".join("\r\n", $not_replaced);
        }
    }
    
}

