/**
 * Grows the paste textarea depending on the
 * size of the content pasted
 */
function FitToContent(id, maxHeight)
{
   var text = id && id.style ? id : document.getElementById(id);
   if ( !text )
      return;

   var adjustedHeight = text.clientHeight;
   if ( !maxHeight || maxHeight > adjustedHeight )
   {
      adjustedHeight = Math.max(text.scrollHeight, adjustedHeight);
      if ( maxHeight )
         adjustedHeight = Math.min(maxHeight, adjustedHeight);
      if ( adjustedHeight > text.clientHeight )
         text.style.height = adjustedHeight + "px";
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
    document.getElementById("paste_body").onkeyup = function() {
      FitToContent( this, 1000 )
    };
}
