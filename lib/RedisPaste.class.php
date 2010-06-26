<?php
/**
 * RedisPaste class. Extending Redisent because
 * it's less typing than $this->Redisent->foo,
 * and Redisent's lightweight design lends itself
 * to this kind of use
 *
 * REDIS SCHEMA
 *
 *    global:pasteIds = string int -- INCR on each paste creation
 *    paste:history => list of all paste ids
 *
 *    paste:$id:body => string $text --the paste
 *    paste:$id:note => string $text --the deck of the paste
 *    paste:$id:date => string timestamp
 *    paste:$id:lang => string language
 *    paste:$id:size => int size of paste in bytes
 *
 * @extends Redisent
 */
require 'redisent/redisent.php';
class RedisPaste extends Redisent {

    /* SETTINGS */
    const REDIS_HOST = '127.0.0.1';
    const REDIS_PORT = 6379;
    const REDIS_DB = 1;
    const URL_PATH = '/~ethan/code/redispaste/public/index.php';
    /* END SETINGS */

    /**
     * The languanges to put at the top of the pulldown
     * in the format where the key is nice for people
     * to read, and the value is what matches the 
     * syntaxHighlighter's name for the lang.
     * @var array $mainLangs
     * @access public
     */
    public $mainLangs = array(
        'PHP' => 'php',
        'CSS' => 'css',
        'JavaScript' => 'javascript',
        'HTML / XML' => 'xml',
        'Text' => 'text'
    );
    
   /**
    * Less important languages :-) in the same format as
    * the above $mainLangs.
    * @var array $otherLangs
    * @access public
    */                         
    public $otherLangs = array(
        /*'ActionScript3' => 'actionscript3',*/
        'Bash' => 'bash',
        'Perl' => 'perl',
        'Ruby' => 'ruby',
        'Python' => 'python',
        'SQL' => 'sql',
        'XML' => 'xml'
    ); 
    
    /**
     * The array of values that are associated with a paste
     * used when gathering the keys to get a paste
     * @var array $pasteAttrs
     * @access public
     */                       
    public $pasteAttrs = array(
        'body', 'note', 'date',
        'lang', 'size'
    );

    /**
     * __construct function.
     * 
     * @access public
     * @param mixed $redisServer
     * @param mixed $redisPort. (default: 6379)
     * @return void
     */
    public function __construct() {
        parent::__construct(self::REDIS_HOST, self::REDIS_PORT);
        $this->SELECT(self::REDIS_DB);
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
        
        $noteKeys = $this->KEYS('paste:*:note');
        //older versions of redis KEYS was a string not multi reply
        if (!is_array($noteKeys)) {
            $noteKeys = explode(' ', $noteKeys);
        }
        $noteData = $this->MGET(implode(' ', $noteKeys));
        $pasteIds = array();
        foreach($noteData as $k => $d)
        {
            if(stripos($d, $searchWord) !== false)
            {
               $keyArr = explode(':', $noteKeys[$k]);
               $pasteIds[] = $keyArr[1];
            }
        }
        $pastes = $this->getPastes($pasteIds, array('body'));
        
        return $pastes;
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
        foreach($ids as $k) {
            foreach($this->pasteAttrs as $a) {
                if(empty($skipKeys) || !in_array($a, $skipKeys)) {
                    $keys .= ' paste:' . $k . ':' . $a;
                }
            }
        } 
        $keys = ltrim($keys);
        $pasteData = $this->MGET($keys);
        /* loop the keys to transform the flat $pasteData
           into  maningful array of pastes */
        foreach(explode(' ', $keys) as $k => $v) {
            $ksub = explode(':', $v);
            $pastes[$ksub[1]][$ksub[2]] = $pasteData[$k];
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
    public function getPaste($id, Array $skipKeys = array()) {
    
        $pasteKeys = $this->KEYS('paste:'. $id .':*');
        //older versions of redis KEYS was a string not multi reply
        if (!is_array($pasteKeys)) {
            $pasteKeys = explode(' ', $pasteKeys);
        }
        try {
        $pasteData = $this->MGET(implode(' ', $pasteKeys));
        } catch (RedisException $e) {
            //paste no aqui
            header('Location: ' . self::URL_PATH);
            die;
        }
        /* loop the keys to transform the flat $pasteData
           into  maningful array of pastes, */
        foreach($pasteKeys as $k => $v) {
            $var = substr($v, strrpos($v,':') + 1);
             if(empty($skip_keys) || !in_array($a, $skipKeys)) {
                $paste[$var] = $pasteData[$k];
            }
        }
        $paste['id'] = $id;
        return $paste;
    }

    /**
     * getLangNice function.
     * Given a base name for a language, get a prettier version
     * for people to read. uses the above $mainLang & $otherLangs
     * 
     * @access public
     * @param mixed $lang
     * @return string The pretty lang
     */
    public function prettyLang($lang) {
    
        $res = array_search($lang, array_merge($this->mainLangs, $this->otherLangs));
        return $res;
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
    
        //pastes can be edited, so respect the id if it's passed.
        $pasteId = isset($data['id']) ? $data['id'] : $this->INCR('global:pasteIds');

        $pasteBody = htmlentities(stripslashes($data['body']),ENT_QUOTES);
        $this->SET('paste:' . $pasteId . ':body', trim($pasteBody));
        $this->SET('paste:' . $pasteId . ':note', htmlentities(stripslashes($data['note']),ENT_QUOTES) );
        $this->SET('paste:' . $pasteId . ':date', time() );
        $this->SET('paste:' . $pasteId . ':lang', htmlentities(stripslashes($data['lang']),ENT_QUOTES) );
        $this->SET('paste:' . $pasteId . ':size', mb_strlen($pasteBody, 'latin1'));
        if(!isset($data['id'])) {
           $this->LPUSH('paste:history', $pasteId);
      }     
        return $pasteId;
    }
}