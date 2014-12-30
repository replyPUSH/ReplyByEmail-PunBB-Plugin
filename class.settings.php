<?php if (!defined('FORUM')) exit;
/**
 *  @@ reply_by_email_settings_domain @@
 *
 *  Links settings Worker to the worker collection
 *  and retrieves it. Auto initialising.
 *
 *  Provides a simple way for other workers, or
 *  the plugin file to call it method and access its
 *  properties.
 *
 *  A worker will reference the settings work like so:
 *  $this->plgn->settings()
 *
 *  The plugin file can access it like so:
 *  $this->settings()
 *
 *  @abstract
 */

abstract class reply_by_email_settings_domain extends reply_by_email_api_domain {
/**
 * The unique identifier to look up Worker
 * @var string $worker_name
 */

  private $worker_name = 'settings';

  /**
   *  @@ settings @@
   *
   *  settings Worker Domain address,
   *  links and retrieves
   *
   *  @return void
   */

  public function settings(){
    $worker_name = $this->worker_name;
    $worker_class = $this->get_plugin_index().$worker_name;
    return $this->link_worker($worker_name,$worker_class);
  }
}

/**
 *  @@ reply_by_email_settings @@
 *
 *  The worker used to handle the backend
 *  settings interactions.
 *
 */

class reply_by_email_settings {
    
    public function menu_link(&$forum_page){
        if(FORUM_PAGE_SECTION != 'settings')
            return;
        global $forum_user, $forum_url;
        
        $lang_main_file = $this->plgn->api()->get_language_file($forum_user['language']);
        include($lang_main_file);
        
        $forum_page['admin_submenu']['reply-by-email'] = '<li class="'.((FORUM_PAGE == 'admin-settings-reply-by-email') ? 'active' : 'normal').((empty($forum_page['admin_submenu'])) ? ' first-item' : '').'"><a href="'.forum_link($forum_url['admin_settings_reply_by_email']).'">'.$lang_main['settings_heading'].'</a></li>';
    }
    
    public function controller(){
        global $section, $form, $forum_page, $lang_admin_common, $lang_common, $forum_url, $forum_user, $forum_loader, $forum_config, $forum_flash, $forum_db, $forum_updates, $tpl_main, $base_url;
        //ours?
        if($section!='reply-by-email')
            return;
        
        //post back
        if (isset($form)){
            //save config value
            foreach ($form as $key => $value){
                if (array_key_exists('reply_by_email_'.$key, $forum_config) && $forum_config['p_'.$key] != $value){
                    $query = array(
                        'UPDATE'    => 'config',
                        'SET'       => 'conf_value=\''.$forum_db->escape($value).'\'',
                        'WHERE'     => 'conf_name=\'reply_by_email_'.$forum_db->escape($key).'\''
                    );

                    $forum_db->query_build($query) or error(__FILE__, __LINE__);
                }
            }
            
            //clear form
            $form = array();
            
            //use underscores or redirect won't work
            $section = 'reply_by_email';
            
            //exit early to trigger redirect. 
            return;
        }
        
        $lang_main_file = $this->plgn->api()->get_language_file($forum_user['language']);
        include($lang_main_file);
        
        
        // breadcrumbs
        $forum_page['crumbs'] = array(
            array($forum_config['o_board_title'], forum_link($forum_url['index'])),
            array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
            array($lang_admin_common['Settings'], forum_link($forum_url['admin_settings_setup'])),
            array($lang_main['settings_heading'], forum_link($forum_url['admin_settings_reply_by_email']))
        );
        define('FORUM_PAGE_SECTION', 'settings');
        define('FORUM_PAGE', 'admin-settings-reply-by-email');

        require FORUM_ROOT.'header.php';
        
        // Setup the form
        $forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;
        ob_start();
        ?>
        <div class="main-content frm parted">
            <form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_settings_reply_by_email']) ?>">
                <div class="hidden">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_settings_reply_by_email'])) ?>" />
                    <input type="hidden" name="form_sent" value="1" />
                </div>
                <div class="content-head">
                    <h2 class="hn"><span><?php echo $lang_main['settings_heading'] ?></span></h2>
                </div>
                <fieldset class="frm-group group<?php echo ++$forum_page['group_count'] ?>">
                    <legend class="group-legend"><strong><?php echo $lang_main['api_legend'] ?></strong></legend>
                    <div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
                        <div class="sf-box text">
                            <label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_main['account_no'] ?></span><small><?php echo $lang_main['account_no_description'] ?></small></label><br />
                            <span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[account_no]" size="35" maxlength="100" value="<?php echo forum_htmlencode($forum_config['reply_by_email_account_no']) ?>" /></span>
                        </div>
                    </div>
                    <div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
                        <div class="sf-box text">
                            <label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_main['secret_id'] ?></span><small><?php echo $lang_main['secret_id_description'] ?></small></label><br />
                            <span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[secret_id]" size="35" maxlength="100" value="<?php echo forum_htmlencode($forum_config['reply_by_email_secret_id']) ?>" /></span>
                        </div>
                    </div>
                    <div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
                        <div class="sf-box text">
                            <label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_main['secret_key'] ?></span><small><?php echo $lang_main['secret_key_description'] ?></small></label><br />
                            <span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[secret_key]" size="35" maxlength="100" value="<?php echo forum_htmlencode($forum_config['reply_by_email_secret_key']) ?>" /></span>
                        </div>
                    </div>
                    <div class="sf-set set<?php echo ++$forum_page['item_count'] ?>">
                        <div class="sf-box text">
                            <label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_main['notify_url'] ?></span><small><?php echo $lang_main['notify_url_description'] ?></small></label><br />
                            <span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="form[notify_url]" size="80" maxlength="100" readonly="readonly" value="<?php echo forum_link($forum_url['reply_by_email_url'],forum_htmlencode($forum_config['reply_by_email_uri'])) ?>" /></span>
                        </div>
                    </div>
                </fieldset>
                <div class="frm-buttons">
                    <span class="submit primary"><input type="submit" name="save" value="<?php echo $lang_admin_common['Save changes'] ?>" /></span>
                </div>
            </form>
        </div>
        <?php
    }
    
    //Direct admin to settings
    public function set_up_msg(){
        global $forum_config, $forum_user, $forum_url, $main_elements;
        
        //not admin? Then don't display message
        if($forum_user['g_id'] != FORUM_ADMIN)
            return;

        $secret_id   =   isset($forum_config['reply_by_email_secret_id']) ? $forum_config['reply_by_email_secret_id'] : FALSE;
        $secret_key  =   isset($forum_config['reply_by_email_secret_key']) ? $forum_config['reply_by_email_secret_key'] : FALSE;
        $account_no  =   isset($forum_config['reply_by_email_account_no']) ? $forum_config['reply_by_email_account_no'] : FALSE;
        
        //has credentials? Then don't display message
        if($secret_id && $secret_key && $account_no)
            return;
            
        $lang_main_file = $this->plgn->api()->get_language_file($forum_user['language']);
        include($lang_main_file);
        
        $main_elements['<!-- forum_crumbs_top -->'] = 
        '<div id="brd-announcement" class="gen-content">'.
            '<h1 class="hn"><span>'.$lang_main['not_set_up_header'].'</span></h1>'.
            '<div class="content">'.str_replace('<settings_url>',forum_link($forum_url['admin_settings_reply_by_email']),$lang_main['not_set_up_text']).'</div>'.
        '</div>'.
        (isset($main_elements['<!-- forum_crumbs_top -->']) ? $main_elements['<!-- forum_crumbs_top -->'] : '');
    }
}
