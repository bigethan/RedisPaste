<?php
/**
 * RedisPaste class. Extending Predis because
 * it's less typing.
 *
 * REDIS SCHEMA
 *
 *    global:pasteIds = string int -- INCR on each paste creation
 *    paste:history => list of all paste ids
 *
 *    Stored in Redis Hashes
 *    paste:$id body => string $text --the paste
 *    paste:$id note => string $text --the deck of the paste
 *    paste:$id date => string timestamp
 *    paste:$id lang => string language the paste is in (PHP, Javascript, etc)
 *    paste:$id size => int size of paste in bytes
 *
 *    search:[\w] => set of paste ids to
 *
 * @extends Predis_Client
 */
require 'predis/Predis.php';
class RedisPaste extends Predis_Client {

    /* SETTINGS */
    const REDIS_HOST = '127.0.0.1';
    const REDIS_PORT = 6379;
    const REDIS_DB = 1;
    const REDIS_PASSWORD = null;
    const URL_PATH = '/~ethan/code/redispaste/public/index.php';
    /* END SETINGS */
    
    
    /**
     * An array of values that are associated with a paste
     * used when gathering the keys to get a paste
     * @var array $pasteAttrs
     * @access public
     */                       
    public $pasteAttrs = array(
        'body', 'note', 'date',
        'lang', 'size'
    );
    
    public $redisError = false;

    /**
     * __construct function.
     * 
     * @access public
     * @param mixed $redisServer
     * @param mixed $redisPort. (default: 6379)
     * @return void
     */
    public function __construct() {
        try {
            parent::__construct(
                array(
                    'host' => self::REDIS_HOST,
                    'port' => self::REDIS_PORT,
                    'database' => self::REDIS_DB,
                    'password' => self::REDIS_PASSWORD
                )                
            );
        } catch (Exception $e) {
            $this->redisError = $e->getMessage() . '- Initial Conection';
        }
    }                         

    /**
     * searchPastes function.
     * searches the paste descriptions for matches
     * doesn't search the actual pastes.
     *
     * @access public
     * @param mixed Array $ids
     * @param mixed Array $skipKeys. (default: array()
     * @return array the array of pastes
     */
    public function searchPastes($searchWord) {
    
        if(empty($searchWord)) {
            return null;
        }
        
        //explode the words in the search
        $words = explode(' ', $searchWord);
        
        foreach($words as $w) {
            $searchKeys[$w]= 'search:' . $w;
        }

        try {
            $pasteIds = $this->sunion($searchKeys);
        } catch (Exception $e) {
            $this->redisError = $e->getMessage() . ' - Search Failed';
            return;
        }

        $pastes = $this->getPastes($pasteIds, array('body'));
        
        return $pastes;
    }
    
    /**
     * builds the string of hash keys for redis
     *
     * @access public
     * @param mixed Array $skipKeys (default = array())
     * $return string 'key1 key 2 key 3'
     */
    public function buildPasteHashKeys($skipKeys = array())
    {
        foreach($this->pasteAttrs as $a) {
            if(empty($skipKeys) || !in_array($a, $skipKeys)) {
                $keys[] = $a;
            }
        }
        return $keys;
    }

    /**
     * getPastes function.
     * builds the total list of keys for all the pastes 
     * in the $ids array, and then gets them with a single call
     * $skipKeys is a blacklist array for data that you don't want.
     *
     * @access public
     * @param mixed Array $ids
     * @param mixed Array $skipKeys. (default: array()
     * @return array the array of pastes
     */
    public function getPastes(Array $ids, Array $skipKeys = array()) {
    
        if(empty($ids) || !is_array($ids)) {
            return null;
        }
        
        /* build key list for multi get */
        $keys = $this->buildPasteHashKeys($skipKeys);
        
        try {
            foreach($ids as $k) {
                $pastes[$k] = $this->getPaste($k, null, $keys);
            } 
        } catch (Exception $e) {
            $this->redisError = $e->getMessage() . " - Couldn't get pastes";
            return;
        }
        return $pastes;
    }

    /**
     * getPaste function.
     * gets the keys for the specific paste and then get those
     * keys with a single call. $skipKeys is a blacklist array
     * for data that you don't want.
     * 
     * @access public
     * @param mixed $id
     * @param mixed Array $skipKeys. (default: array()
     * @return array the array of data for the paste
     */
    public function getPaste($id, $skipKeys = array(), $rawKeyList = null)
    {
        
        if($rawKeyList) {
            $pasteKeys = $rawKeyList;
        } else {
            $pasteKeys = $this->buildPasteHashKeys($skipKeys);
        }
        
        try {
            $pasteData = $this->hmget('paste:' . $id, $pasteKeys);
        } catch (Exception $e) {
            $this->redisError = $e->getMessage() . ' - Paste ' . $id . 'not found';
            return;
        }
        
        $paste = array_combine($pasteKeys, $pasteData);
        $paste['id'] = $id;
        
        return $paste;
    }

    /**
     * savePaste function.
     * Takes data from the form and creates the new paste
     * 
     * @access public
     * @param mixed Array $data
     * @return Integer the id of the new paste
     */
    public function savePaste(Array $data) {
    
        try {
            //pastes can be edited, so respect the id if it's passed.
            $pasteId = isset($data['id']) ? $data['id'] : $this->incr('global:pasteIds');
            
            $pasteBody = trim(htmlentities(stripslashes($data['body']),ENT_QUOTES));
            $pasteNote = trim(htmlentities(stripslashes($data['note']),ENT_QUOTES));
            $pasteData = array(
                'body' => $pasteBody,
                'note' => $pasteNote,
                'date' => time(),
                'lang' => htmlentities(stripslashes($data['lang']),ENT_QUOTES),
                'size' => mb_strlen($pasteBody, 'latin1'),
            );
            
            $this->hmset('paste:' . $pasteId, $pasteData);
            
            //add to history
            if(!isset($data['id'])) {
               $this->lpush('paste:history', $pasteId);
            }
        } catch (Exception $e) {
            $this->redisError = $e->getMessage() . ' - Save Failed';
            return;
        }
        try{
            //make search keys for bits that have at least
            //3 letters or numbers
            $searchSlug = strtolower($pasteBody . ' ' . $pasteNote);
            $searchWords = array();
            preg_match_all('/\b[^\b](\w{3,})\b/', $searchSlug, $searchWords);
            foreach($searchWords[0] as $w) {
                $this->sadd('search:' . trim($w), $pasteId);
            }
        } catch (Exception $e) {
            $this->redisError = $e->getMessage() . ' - Save Suceeded, Search Population Failed';
            return;
        }    
             
        return $pasteId;
    }
}