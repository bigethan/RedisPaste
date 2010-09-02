<?php
/* Start it up */
require_once 'RedisPaste.class.php';
$redisPaste = new RedisPaste($redisHost);

/* A New Paste? */
if($_POST && !$redisPaste->redisError) {
    $newId = $redisPaste->savePaste($_POST);    
    /* redir to the new paste */
    if(!$redisPaste->redisError) {
        header('Location: ' . $urlPath . '?paste=' . $newId);
        exit;
    }
}

/* An Old Paste?  */
if($_GET['paste'] && !$redisPaste->redisError) {
    $paste = $redisPaste->getPaste($_GET['paste']);
}

/* Show searched pastes, or recent pastes? */
if($_GET['rpq'] && !$redisPaste->redisError) {
   $searchedPastes = true;
   $pastPastes = $redisPaste->searchPastes(urldecode($_GET['rpq']));
   $totalPastes = sizeOf($pastPastes);
} else if (!$redisPaste->redisError) {

   /* Past Pastes */
   $totalPastes = $redisPaste->llen('paste:history');
   
   /* # of past pastes to show per page */
   $offset = 12;
   $page = (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
   $totalPages = ceil($totalPastes / $offset);
   $showingEnd = $page * $offset;
   
   /* if the page requested is beyond scope, reset */
   if($showingEnd - $offset > $totalPastes) {
       $page = 1;
   }
   
   /* should there be next & prev links? */
   $nextPage = $totalPastes > $showingEnd + 1 ? $page + 1 : null;
   $prevPage = $page > 1 ? $page - 1 : null;
   
   /* get the range of ids */
   $pastStart = ($page - 1) * $offset;
   $pastPasteKeys = $redisPaste->lrange('paste:history', 
                                            $pastStart, // start of the range
                                            $pastStart + $offset - 1 // end of the range
                                           );
                                  
   /* Don't need the body data for the history display */
   $pastPastes = $redisPaste->getPastes((array)$pastPasteKeys, array('body'));
}

/* the language options for the pulldown */
/**
 * The languanges to put at the top of the pulldown
 * The key is for humans, and the value 
 * is what matches the  syntaxHighlighter's name 
 * for the lang.
 */
$mainLangs = array(
    'PHP' => 'php',
    'CSS' => 'css',
    'JavaScript' => 'javascript',
    'HTML / XML' => 'xml',
    'Text' => 'text'
);

/**
* Less important languages :-) in the same format as
* the above $mainLangs.
*/                         
$otherLangs = array(
    'ActionScript3' => 'actionscript3',
    'Bash' => 'bash',
    'Perl' => 'perl',
    'Ruby' => 'ruby',
    'Python' => 'python',
    'SQL' => 'sql',
    'XML' => 'xml'
); 
