<?php if (!defined('FORUM')) exit;

class replypush_model{
    
    public $sql;
    public static $ref = array();
    
    public function  __construct(){
        global $forum_db;
        $this->sql = $forum_db;
    }
    
    public function log_transaction($notification){
        $this->sql->start_transaction();
        $query  = array(
            'INSERT'    => 'message_id, message',
            'INTO'      => 'replypush_log',
            'VALUES'    => '\''.$this->sql->escape($notification['msg_id']).'\',\''.$this->sql->escape(serialize($notification)).'\''
        );
        $result = $this->sql->query_build($query) or error(__FILE__, __LINE__);
        $this->sql->fetch_assoc($result);
        $this->sql->end_transaction();
    }
    
    public function get_transaction($message_id){
        $query = array(
            'SELECT'    => 'l.message_id',
            'FROM'      => 'replypush_log AS l',
            'WHERE'     => 'l.message_id=\''.$this->sql->escape($message_id).'\''
        );
        $result = $this->sql->query_build($query) or error(__FILE__, __LINE__);
        $this->sql->fetch_assoc($result);
        return $this->sql->fetch_assoc($result);
    }
    
    public function get_ref($ref_hash){
        if(array_key_exists($ref_hash,self::$ref))
            return self::$ref[$ref_hash];
        
        $query = array(
            'SELECT'    => 'r.ref',
            'FROM'      => 'replypush_ref AS r',
            'WHERE'     => 'r.ref_hash=\''.$this->sql->escape($ref_hash).'\''
        );
        $result = $this->sql->query_build($query) or error(__FILE__, __LINE__);
        $ref = $this->sql->fetch_assoc($result);
        if(!$ref)
            return '';
        return $ref['ref'];
    }
    
    public function save_ref($ref_hash, $ref){
        if(!$ref_hash || !$ref)
            return;
        if($this->get_ref($ref_hash)){
            $query  = array(
                'UPDATE' => 'replypush_ref',
                'SET' => 'ref=\''.$this->sql->escape($ref).'\'',
                'WHERE'     => 'ref_hash = \''.$this->sql->escape($ref_hash).'\''
            );
            $result = $this->sql->query_build($query) or error(__FILE__, __LINE__);
            $this->sql->fetch_assoc($result);
            self::$ref[$ref_hash] = $ref;
        }else{
            $query  = array(
                'INSERT'    => 'ref_hash, ref',
                'INTO'      => 'replypush_ref',
                'VALUES'    => '\''.$this->sql->escape($ref_hash).'\',\''.$this->sql->escape($ref).'\''
            );
            $result = $this->sql->query_build($query) or error(__FILE__, __LINE__);
            $this->sql->fetch_assoc($result);
        }
    }
}
