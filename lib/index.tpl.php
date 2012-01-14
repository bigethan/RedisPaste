<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        
        <title>Redis Paste</title>
        
        <script type="text/javascript" src="syntaxhighlighter/src/shCore.js"></script>
        <script type="text/javascript" src="syntaxhighlighter/scripts/shBrushBash.js"></script>
        <script type="text/javascript" src="syntaxhighlighter/scripts/shBrushCpp.js"></script>
        <script type="text/javascript" src="syntaxhighlighter/scripts/shBrushCSharp.js"></script>
        <script type="text/javascript" src="syntaxhighlighter/scripts/shBrushCss.js"></script>
        <script type="text/javascript" src="syntaxhighlighter/scripts/shBrushDelphi.js"></script>
        <script type="text/javascript" src="syntaxhighlighter/scripts/shBrushDiff.js"></script>
        <script type="text/javascript" src="syntaxhighlighter/scripts/shBrushGroovy.js"></script>
        <script type="text/javascript" src="syntaxhighlighter/scripts/shBrushJava.js"></script>
        <script type="text/javascript" src="syntaxhighlighter/scripts/shBrushJScript.js"></script>
        <script type="text/javascript" src="syntaxhighlighter/scripts/shBrushPerl.js"></script>
        <script type="text/javascript" src="syntaxhighlighter/scripts/shBrushPhp.js"></script>
        <script type="text/javascript" src="syntaxhighlighter/scripts/shBrushPlain.js"></script>
        <script type="text/javascript" src="syntaxhighlighter/scripts/shBrushPython.js"></script>
        <script type="text/javascript" src="syntaxhighlighter/scripts/shBrushRuby.js"></script>
        <script type="text/javascript" src="syntaxhighlighter/scripts/shBrushScala.js"></script>
        <script type="text/javascript" src="syntaxhighlighter/scripts/shBrushSql.js"></script>
        <script type="text/javascript" src="syntaxhighlighter/scripts/shBrushVb.js"></script>
        <script type="text/javascript" src="syntaxhighlighter/scripts/shBrushXml.js"></script>
        <script type="text/javascript">
 
            /**
             * Grows the paste textarea depending on the
             * size of the content pasted
             */
            function FitToContent(el, maxHeight)
            {
               var adjustedHeight = el.clientHeight;
               if ( !maxHeight || maxHeight > adjustedHeight )
               {
                  adjustedHeight = Math.max(el.scrollHeight, adjustedHeight);
                  if ( maxHeight )
                     adjustedHeight = Math.min(maxHeight, adjustedHeight);
                  if ( adjustedHeight > el.clientHeight )
                     el.style.height = adjustedHeight + "px";
               }
            }
            
            /**
             * toggles the past editing interface
             */
            function pasteEditInterface()
            {
               document.getElementById('edit_paste').style.display = document.getElementById('edit_paste').style.display == 'block' ? 'none' : 'block';
               FitToContent( document.getElementById('edit_paste_body'), 1000 )
               
            }
            
            /**
             * start wtching the paste textarea 
             */
            window.onload = function() {
                var tas, i;

                tas = document.getElementsByTagName("textarea");
                
                for(i = 0; tas.length > i; i++){
                    tas[i].onkeyup = function() {
                      FitToContent( this, 1000 )
                    }
                }
            }

            SyntaxHighlighter.config.clipboardSwf = 'js/syntaxhighlighter/scripts/clipboard.swf';
            SyntaxHighlighter.config.stripBrs = true;
            SyntaxHighlighter.all();
            var noteCleared = false;
            var pasteCleared = false; 
        </script>
        
        <link rel="stylesheet" href="css/blueprint/screen.css" type="text/css" media="screen, projection">
        <link rel="stylesheet" href="css/blueprint/print.css" type="text/css" media="print">
        <link type="text/css" rel="stylesheet" href="syntaxhighlighter/styles/shCore.css">
        <link type="text/css" rel="stylesheet" href="syntaxhighlighter/styles/shThemeDefault.css">
        <link type="text/css" rel="stylesheet" href="css/RedisPaste.css">
    </head>
    
    <body>
        <div class="container">
            <?php if($redisPaste->redisError) { ?>
                <div id="trouble">
                    <h3>Something Has Gone Wrong</h3>
                    <p>
                    <?php echo $redisPaste->redisError ?>
                    </p>
                    <p>
                    <a href="<?php echo $redisPaste->URL_PATH ?>">Paste Home</a>
                    </p>
                </div>
            <?php } else { ?>
                <?php if(!empty($paste)) { ?>
                    <div id="the_paste" class="section">
                        <h2>The Paste You're Looking for</h2>
                        <p id="the_paste_date"><?php echo array_search($paste['lang'], array_merge($mainLangs, $otherLangs)) ?> pasted at <?php echo date('g:ia \o\n l, F j', $paste['date']) ?> [<a href="#" onclick="pasteEditInterface()">Edit This Paste</a>]</p>
                        <div id="edit_paste" class="section">
                            <h2>Edit This Paste</h2>
                            <form method="post">
                            <input type="hidden" name="id" value="<?php echo $paste['id']; ?>">
                            <input type="hidden" name="paste_action" value="1">
                            <div>
                                Note about this Paste:<br>
                                <textarea name="note" id="edit_paste_note" tabindex="11"><?php echo $paste['note'] ?></textarea>
                            </div>
                            <div>
                                The Paste is in 
                                <select name="lang" tabindex="12">
                                    <?php foreach($mainLangs as $k =>$l) { ?>
                                        <option value="<?php echo $l ?>"<?php if( $paste['lang'] == $l){ echo ' selected="selected"'; } ?>><?php echo $k ?></option>
                                    <?php } ?>
                                        <optgroup label="Lesser Langs"></optgroup>
                                    <?php foreach($otherLangs as $k => $l) { ?>
                                        <option value="<?php echo $l ?>"<?php if( $paste['lang'] == $l){ echo ' selected="selected"'; } ?>><?php echo $k ?></option>
                                    <?php } ?>
                                </select>: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="submit" value="Save Edit" tabindex="15"><br>
                                <textarea name="body" id="edit_paste_body" tabindex="14"><?php echo $paste['body'] ?></textarea>
                            </div>
                            
                            </form>
                        </div>
                        <div id="the_paste">
                            <p id="the_paste_note" class="success"><?php echo $paste['note'] ?></p>
                            <?php if (!empty($paste['comments'])) { ?>
                            <ul id="paste_comments">
                                <?php foreach ($paste['comments'] as $comment) { ?>
                                <li>
                                <?php echo $comment['author'] ?> noted:<br> 
                                <pre><?php echo $comment['body'] ?></pre>
                                </li> 
                                <?php } ?>
                            </ul>
                            <?php } ?>
                            <pre class="brush: <?php echo $paste['lang']; ?>"><?php echo $paste['body'] ?>
                            </pre>
                        </div>
                        <div id="paste_comment">
                            <p>Comment On This Paste</p>
                            <form method="post">
                            <input type="hidden" name="paste_id" value="<?php echo $paste['id']; ?>">
                            <input type="hidden" name="comment_action" value="1">
                            Your Name: <input type="text" name="author" value="<?php echo $commenter ?>"> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="submit" value="Save Comment">
                            <textarea id="comment_body" name="body"></textarea>
                            </form>
                        </div>
                    </div>
                <?php } ?>
                
                
                <div id="new_paste" class="section">
                    <h2>Create a New Paste of Your Own</h2>
                    <form method="post">
                    <input type="hidden" name="paste_action" value="1">
                        <div>
                            Note about this Paste:<br>
                            <textarea name="note" id="paste_note" tabindex="1" onfocus="if(!noteCleared){this.innerHTML = ''; noteCleared = true;};">I'm too lazy to add a description.</textarea>
                        </div>
                        <div>
                            The Paste is in 
                            <select name="lang" tabindex="2">
                                <?php foreach($mainLangs as $k =>$l) { ?>
                                    <option value="<?php echo $l ?>"><?php echo $k ?></option>
                                <?php } ?>
                                    <optgroup label="Lesser Langs"></optgroup>
                                <?php foreach($otherLangs as $k => $l) { ?>
                                    <option value="<?php echo $l ?>"><?php echo $k ?></option>
                                <?php } ?>
                            </select>: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="submit" value="Save Paste" tabindex="5"><br>
                            <textarea name="body" id="paste_body" tabindex="4" onfocus="if(!pasteCleared){this.innerHTML = ''; pasteCleared = true;};">Paste Goes Here</textarea>
                        </div>
                        
                    </form>
                </div>
                
                <a name="oldies"></a>
                <?php if(!empty($pastPastes) || $searchedPastes) { ?>
                    <div id="old_paste" class="section">
                            <div class="right_tool">
                               <form action="" method="GET" style="margin-right: 1em; padding-right: 1em; border-right: 4px solid #ccc; float: left;">
                                     <input type="text" name="rpq" value="<?php echo htmlentities(urldecode($_GET['rpq']))?>">
                                     <input type="submit" value="search descriptions">
                                  </form>
                                <?php if (!is_null($nextPage) || !is_null($prevPage)) { ?>
                                    <?php if (!is_null($prevPage)) { ?>
                                        <a href="?page=<?php echo $prevPage; ?>#oldies">&larr; prev</a> &nbsp;&nbsp;&nbsp;
                                    <?php } ?>
                                    <?php if (!is_null($nextPage)) { ?>
                                        <a href="?page=<?php echo $nextPage; ?>#oldies">next &rarr;</a>
                                    <?php } ?>
                                <?php } ?>
                            </div>
                        <h2>
                        <?php echo $totalPastes ?> Copy Pastas 
                        <?php if ($searchedPastes) { ?>
                        Containing: <?php echo htmlentities(urldecode($_GET['rpq'])); ?>
                        <?php } else { ?>
                        From the Past (Page <?php echo $page ?> of <?php echo $totalPages ?>)
                        <?php } ?>
                        </h2>
                        <?php foreach((array)$pastPastes as $k => $p) { ?>
                            <div class="oldie">
                                <p><?php echo array_search($p['lang'], array_merge($mainLangs, $otherLangs)) ?> pasted on <?php echo date('F j', $p['date']) ?> [<a href="?paste=<?php echo $k; ?>&page=<?php echo $page; ?>">linky</a>]</p>
                                <p><?php if(!$p['note']){ $p['note'] = 'No Description Given'; } echo $p['note'] ?> [<?php echo (int)$p['size']  ?> bytes]</p>
                            </div>
                        <?php } ?>
                    <div class="clear"></div>
                    </div>
                <?php } ?>
                DataStore is <a href="http://code.google.com/p/redis/">Redis</a>, Redis PHP Interface is <a href="http://github.com/nrk/predis">Predis</a>, CSS is <a href="http://www.blueprintcss.org/">Blueprint</a>, Code Coloring is <a href="http://alexgorbatchev.com/wiki/SyntaxHighlighter">SyntaxHighlighter</a>
            <?php } ?>
        </div>
        </body>
</html>