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
 *    Stored in Hash
 *    paste:$id.body        string  the paste
 *    paste:$id.note        string  the deck of the paste
 *    paste:$id.date        string  timestamp paste was created
 *    paste:$id.lang        string  language the paste is in
 *    paste:$id.size        int     size of paste in bytes
 *    
 *    comments:$id.author   string  name of the commenter
 *    comments:$id.body     string  body of the comment
 *    comments:$id.date     string  timestamp of the comment
 *    
 *    Store in List
 *    paste:$id:comments    list    ids of comments on the paste
 *  
 *
 *    search:[\w] => set of paste ids to
 *
 * @extends Predis_Client
 */

date_default_timezone_set('America/Los_Angeles');

require 'predis/Predis.php';
class RedisPaste extends Predis_Client {

    /* SETTINGS */
    const REDIS_HOST = '127.0.0.1';
    const REDIS_PORT = 6379;
    const REDIS_DB = 1;
    const REDIS_PASSWORD = null;
    const URL_PATH = '/index.php';
    /* END SETINGS */
    
    
    /**
     * An array of values that are associated with a paste
     * used when gathering the keys to get a paste
     * @var array $pasteAttrs
     * @access public
     */                       
    public $pasteAttrs = array(
        'body', 'note', 'date',
        'lang', 'size', 'comments'
    );
    
    /**
     * Set to true when catching an exception
     */
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
     * @param mixed Array $skipKeys keys to omit
     * $return string 'key1 key 2 key 3'
     */
    public function buildPasteHashKeys(Array $skipKeys)
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
     * in the $ids array, and then gets them
     * $skipKeys is a blacklist array for data that you don't want.
     *
     * @access public
     * @param mixed Array $ids
     * @param mixed Array $skipKeys. (default: array()
     * @return array the array of pastes
     */
    public function getPastes(Array $ids, Array $skipKeys = null) {
    
        if(empty($ids) || !is_array($ids)) {
            return null;
        }
        
        try {
            foreach($ids as $k) {
                $pastes[$k] = $this->getPaste($k, (array)$skipKeys);
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
    public function getPaste($id, $skipKeys = array())
    {
        $pasteKeys = $this->buildPasteHashKeys($skipKeys);
        
        try {
            //get bare paste data
            $pasteData = $this->hmget('paste:' . $id, $pasteKeys);
            //get comment list
            if(in_array('comments', $pasteKeys)) {
                $commentIds = $this->lrange('paste:' . $id . ':comments', 0, -1);
                if(!empty($commentIds)) {
                    $key = array_search('comments', $pasteKeys);
                    $pasteData[$key] = $this->__getPasteComments($commentIds);
                }
            }

        } catch (Exception $e) {
            $this->redisError = $e->getMessage() . ' - Trouble with ' . $id;
            return;
        }
        
        $paste = array_combine($pasteKeys, $pasteData);
        $paste['id'] = $id;

        /* If there are comments on the paste, get em */
        
        
        return $paste;
    }

    /**
     * get the comments by the passed array of ids
     * @param  Array  $ids a list of comment ids
     * @return Array  the comments
     */
    private function __getPasteComments(Array $ids) 
    {
        foreach($ids as $id) {
            $comments[] = $this->hgetall('comment:' . $id);
        }
        return $comments;
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
            //TODO - the htmlentification currently make this real weird
            //$searchSlug = strtolower($pasteBody . ' ' . $pasteNote);
            $searchSlug = $data['note'];
            $searchWords = array();
            preg_match_all('/\b[^\b]([a-zA-Z0-9]{3,})\b/', $searchSlug, $searchWords);
            foreach($searchWords[0] as $w) {
                $this->sadd('search:' . trim($w), $pasteId);
            }
        } catch (Exception $e) {
            $this->redisError = $e->getMessage() . ' - Save Suceeded, Search Population Failed';
            return;
        }    
             
        return $pasteId;
    }

    public function saveComment(Array $data)
    {
        try {
            $commentId = $this->incr('global:commentIds');
            
            $commentBody = trim(htmlentities(stripslashes($data['body']),ENT_QUOTES));
            $commentAuthor = trim(htmlentities(stripslashes($data['author']),ENT_QUOTES));
            $commentData = array(
                'body' => $commentBody,
                'author' => $commentAuthor,
                'date' => time(),
            );
            
            $this->hmset('comment:' . $commentId, $commentData);
            
            //add to paste's comment list
            $this->rpush('paste:' . $data['paste_id'] . ':comments', $commentId);

            //remember the commenter's name
            setcookie('rp_name', $commentAuthor, time()+60*60*24*365, '/');
            $_COOKIE['rp_name'] = $commentAuthor;

            
        } catch (Exception $e) {
            $this->redisError = $e->getMessage() . ' - Save Failed';
            return;
        }
    }
}