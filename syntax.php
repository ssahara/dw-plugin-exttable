<?php
//ini_set("display_errors", "On"); // for debugging
/**
 * exttab2-Plugin: Parses extended tables (like MediaWiki) 
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     disorde chang <disorder.chang@gmail.com>
 * @date       2010-08-28
 */
 
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
 
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
 
/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_exttab2 extends DokuWiki_Syntax_Plugin {
 
  var $stack = array();
 
  function syntax_plugin_exttab2(){
    define("EXTTAB2_TABLE", 0);
    define("EXTTAB2_CAPTION", 1);
    define("EXTTAB2_TR", 2);
    define("EXTTAB2_TD", 3);
    define("EXTTAB2_TH", 4);
    $this->tagsmap = array(
                  EXTTAB2_TABLE=>   array("table", "", "\n" ),
                  EXTTAB2_CAPTION=> array("caption", "\t", "\n" ),
                  EXTTAB2_TR=>      array("tr", "\t", "\n" ),
                  EXTTAB2_TD=>      array("td", "\t"."\t", "\n" ),
                  EXTTAB2_TH=>      array("th", "\t"."\t", "\n" ),
 
/* // DOKU constant not work when preview
                  EXTTAB2_TABLE=>   array("table", "", DOKU_LF ),
                  EXTTAB2_CAPTION=> array("caption", DOKU_TAB, DOKU_LF ),
                  EXTTAB2_TR=>      array("tr", DOKU_TAB, DOKU_LF ),
                  EXTTAB2_TD=>      array("td", DOKU_TAB.DOKU_TAB, DOKU_LF ),
                  EXTTAB2_TH=>      array("th", DOKU_TAB.DOKU_TAB, DOKU_LF ),
*/                  );

    /* attribute whose value is a single word */
    $this->attrsmap = array(
      # table attributes
      # simple ones (value is a single word)
      'align', 'border', 'cellpadding', 'cellspacing', 'frame', 'rules', 'width', 'class', 'dir', 'id', 'lang', 'xml:lang',
      # more complex ones (value is a string or style)
      'bgcolor', 'summary', 'title', 'style',
      # additional tr, thead, tbody, tfoot attributes
      'char', 'charoff', 'valign',
      # additional td attributes
      'abbr', 'colspan', 'axis', 'headers', 'rowspan', 'scope',
      'height', 'width', 'nowrap',
    );
  }
 
  function getInfo(){
    return array(
          'author' => 'Disorder Chang',
          'email'  => 'disorder.chang@gmail.com',
          'date'   => '2010-08-28',
          'name'   => 'exttab2 Plugin',
          'desc'   => 'parses MediaWiki-like tables',
          'url'    => 'http://www.dokuwiki.org/plugin:exttab2',
    );
  }
 
  function getType(){
    return 'container';
  }
 
  function getPType(){
    return 'block';
  }
 
  function getAllowedTypes() { 
    return array('container', 'formatting', 'substition', 'disabled', 'protected'); 
  }
 
  function getSort(){ 
    return 50; 
  }
 
  function connectTo($mode) { 
     $this->Lexer->addEntryPattern('\n\{\|[^\n]*',$mode,'plugin_exttab2'); 
  }
 
  function postConnect() { 
    $para = "[^\|\n\[\{\!]+"; // parametes
 
    // caption: |+ params | caption
    $this->Lexer->addPattern("\n\|\+(?:$para\|(?!\|))?",'plugin_exttab2');
 
    // row: |- params
    $this->Lexer->addPattern('\n\|\-[^\n]*','plugin_exttab2');
 
    // table open
    $this->Lexer->addPattern('\n\{\|[^\n]*','plugin_exttab2');
 
    // table close
    $this->Lexer->addPattern('\n\|\}','plugin_exttab2');
 
    // header
    $this->Lexer->addPattern("(?:\n|\!)\!(?:$para\|(?!\|))?",'plugin_exttab2');
 
    //cell
    $this->Lexer->addPattern("(?:\n|\|)\|(?:$para\|(?!\|))?",'plugin_exttab2');
 
    //end
//    $this->Lexer->addExitPattern('\n','plugin_exttab2'); 
//    $this->Lexer->addExitPattern("(?<!\|)\n",'plugin_exttab2'); // not work
    $this->Lexer->addExitPattern("\n(?=\n)",'plugin_exttab2'); 
  }
 
  /**
   * Handle the match
   */
  function handle($match, $state, $pos, &$handler){
    if($state == DOKU_LEXER_EXIT) {
      return array($state, "end");
    }
    else if($state == DOKU_LEXER_UNMATCHED){
      return array($state, "", $match);
    }
    else{
      $para = "[^\|\n]+"; // parametes
      if(preg_match ( '/\{\|([^\n]*)/', $match, $m)){ // table open
        $func = "table_open";
        $params = $this->_cleanAttrString($m[1]);
        return array($state, $func, $params);
      }
      else if($match == "\n|}"){ // table close
        $func = "table_close";
        $params = "";
        return array($state, $func, $params);
      }
      else if(preg_match ("/^\n\|\+(?:(?:($para)\|)?)$/", $match, $m)){ // caption
        $func = "caption";
        $params = $this->_cleanAttrString($m[1]);
        return array($state, $func, $params);
      }
      else if(preg_match ( '/\|-([^\n]*)/', $match, $m)){ // row
        $func = "row";
        $params = $this->_cleanAttrString($m[1]);
        return array($state, $func, $params);
      }
      else if(preg_match("/^(?:\n|\!)\!(?:(?:([^\|\n\!]+)\|)?)$/", $match, $m)){ // header
        $func = "header";
        $params = $this->_cleanAttrString($m[1]);
        return array($state, $func, $params);
      }
      else if(preg_match("/^(?:\n|\|)\|(?:(?:($para)\|)?)$/", $match, $m)){ // cell
        $func = "cell";
        $params = $this->_cleanAttrString($m[1]);
        return array($state, $func, $params);
      }
      else{
        die("what? ".$match);  // for debugging
      }
    }
  }
 
  /**
   * Create output
   */
 
  function render($mode, &$renderer, $data) {
 
    if($mode == 'xhtml'){
      list($state, $func, $params) = $data;
 
      switch ($state) {
        case DOKU_LEXER_UNMATCHED :  
          $r = $renderer->_xmlEntities($params); 
          $renderer->doc .= $r; 
          break;
        case DOKU_LEXER_ENTER :
        case DOKU_LEXER_MATCHED:
          $r = $this->$func($params);
          $renderer->doc .= $r;
          break;
        case DOKU_LEXER_EXIT :       
          $r = $this->$func($params);
          $renderer->doc .= $r; 
          break;
      }
      return true;
 
    }
    return false;
  }
 
 
  /**
   * Make the attribute string safe to avoid XSS attacks.
   * WATCH OUT FOR
   * - event handlers (e.g. onclick="javascript:...", etc)
   * - CSS (e.g. background: url(javascript:...))
   * - closing the tag and opening a new one
   * WHAT IS DONE
   * - turn all whitespace into ' ' (to protect from removal)
   * - remove all non-printable characters and < and >
   * - parse and filter attributes using a whitelist
   * - styles with 'url' in them are altogether removed
   * (I know this is brutally aggressive and doesn't allow
   * some safe stuff, but better safe than sorry.)
   * NOTE: Attribute values MUST be in quotes now.
   */
  function _cleanAttrString($attr=""){
    if (is_null($attr)) return NULL;
    # Keep spaces simple
    $attr = trim(preg_replace('/\s+/', ' ', $attr));
    # Remove non-printable characters and angle brackets
    $attr = preg_replace('/[<>[:^print:]]+/', '', $attr);
    # This regular expression parses the value of an attribute and
    # the quotation marks surrounding it.
    # It assumes that all quotes within the value itself must be escaped, 
    # which is not technically true.
    # To keep the parsing simple (no look-ahead), the value must be in 
    # quotes.
    $val = "([\"'`])(?:[^\\\\\"'`]|\\\\.)*\g{-1}";

    $nattr = preg_match_all("/(\w+)\s*=\s*($val)/", $attr, $matches, PREG_SET_ORDER);
    if (!$nattr) return NULL;

    $clean_attr = '';
    for ($i = 0; $i < $nattr; ++$i) {
      $m = $matches[$i];
      $attrname = strtolower($m[1]);
      $attrval  = $m[2];
      # allow only recognized attributes
      if (in_array($attrname, $this->attrsmap, true)) {
        # make sure that style attributes do not have a url in them
        if ($attrname != 'style' ||
            (stristr($attrval, 'url'   ) === FALSE &&
             stristr($attrval, 'import') === FALSE))
        {
          $clean_attr .= " $attrname=$attrval";
        }
      }
    }

    return $clean_attr;
  }
 
  function _attrString($attr="", $before=" "){
    if(is_null($attr) || trim($attr)=="") $attr = "";
    else $attr = $before.trim($attr);
    return $attr;
  }
 
  var $tagsmap = array();
 
  function _starttag($tag, $params=NULL, $before="", $after=""){
    $tagstr = $this->tagsmap[$tag][0];
    $before = $this->tagsmap[$tag][1].$before;
    $after = $this->tagsmap[$tag][2].$after;
    $r = $before."<".$tagstr.$this->_attrString($params).">". $after;
    return $r;
  }
 
  function _endtag($tag, $before="", $after=""){
    $tagstr = $this->tagsmap[$tag][0];
    $before = $this->tagsmap[$tag][1].$before;
    $after = $this->tagsmap[$tag][2].$after;
 
    $r = $before."</".$tagstr.">". $after;
    return $r;
  }
 
  function table_open($params=NULL){
    $r .= $this->_closetags(EXTTAB2_TABLE);
    $r .= $this->_starttag(EXTTAB2_TABLE, $params);
    $this->stack[] = EXTTAB2_TABLE;
    return $r;
  }
 
  function table_close($params=NULL){
    $t = end($this->stack);
    switch($t){
      case EXTTAB2_TABLE:
        array_push($this->stack, EXTTAB2_TR, EXTTAB2_TD);
        $r .= $this->_starttag(EXTTAB2_TR, $params);
        $r .= $this->_starttag(EXTTAB2_TD, $params);
        break;
      case EXTTAB2_CAPTION:
        $r .= $this->_endtag(EXTTAB2_CAPTION);
        array_pop($this->stack);
        array_push($this->stack, EXTTAB2_TR, EXTTAB2_TD);
        $r .= $this->_starttag(EXTTAB2_TR, $params);
        $r .= $this->_starttag(EXTTAB2_TD, $params);
        break;
      case EXTTAB2_TR:
        array_push($this->stack, EXTTAB2_TD);
        $r = $this->_starttag(EXTTAB2_TD, $params);
        break;
      case EXTTAB2_TD:
      case EXTTAB2_TH:
        break;
    }
 
    while(($t = end($this->stack)) != EXTTAB2_TABLE){
      $r .= $this->_endtag($t);
      array_pop($this->stack);
    }
    array_pop($this->stack);
    $r .= $this->_endtag(EXTTAB2_TABLE);
    return $r;   
 
  }
 
  function end($params=NULL){
    while(!empty($this->stack)){
      $r .= $this->table_close();
    }
    return $r;
  }
 
  function caption($params=NULL){
    if(($r = $this->_closetags(EXTTAB2_CAPTION)) === FALSE){
      return ""; 
    }
    $r .= $this->_starttag(EXTTAB2_CAPTION, $params);
    $this->stack[] = EXTTAB2_CAPTION;
    return $r;
  }
 
  function row($params=NULL){
    $r .= $this->_closetags(EXTTAB2_TR);
    $r .= $this->_starttag(EXTTAB2_TR, $params);
     $this->stack[] = EXTTAB2_TR;
    return $r;
  }
 
  function header($params=NULL){
    $r .= $this->_closetags(EXTTAB2_TH);
    $r .= $this->_starttag(EXTTAB2_TH, $params);
    $this->stack[] = EXTTAB2_TH;
    return $r;
  }
 
  function cell($params=NULL){
    $r .= $this->_closetags(EXTTAB2_TD);
    $r .= $this->_starttag(EXTTAB2_TD, $params);
    $this->stack[] = EXTTAB2_TD;
    return $r;
  }
 
  function _closetags($tag){
    $r = "";
    switch($tag){
      case EXTTAB2_TD:
      case EXTTAB2_TH:
        $t = end($this->stack);
 
        switch($t){
          case EXTTAB2_TABLE:
            array_push($this->stack, EXTTAB2_TR);
            $r .= $this->_starttag(EXTTAB2_TR, $params);
            break;
          case EXTTAB2_CAPTION:
            $r .= $this->_endtag(EXTTAB2_CAPTION);
            array_pop($this->stack);
            array_push($this->stack, EXTTAB2_TR);
            $r .= $this->_starttag(EXTTAB2_TR, $params);
            break;
          case EXTTAB2_TR:
            break;
          case EXTTAB2_TD:
          case EXTTAB2_TH:
            $r .= $this->_endtag($t);
            array_pop($this->stack);
            break;
        }
        break;
      case EXTTAB2_TR:
        $t = end($this->stack);
 
        switch($t){
          case EXTTAB2_TABLE:
            break;
          case EXTTAB2_CAPTION:
            $r .= $this->_endtag(EXTTAB2_CAPTION);
            array_pop($this->stack);
            break;
          case EXTTAB2_TR:
            $r .= $this->_starttag(EXTTAB2_TD);
            $r .= $this->_endtag(EXTTAB2_TD);
            $r .= $this->_endtag(EXTTAB2_TR);
            array_pop($this->stack);
            break;
          case EXTTAB2_TD:
          case EXTTAB2_TH:
            $r .= $this->_endtag($t);
            $r .= $this->_endtag(EXTTAB2_TR);
            array_pop($this->stack);
            array_pop($this->stack);
            break;
        }
        break;        
      case EXTTAB2_TABLE:
        $t = end($this->stack);
        if($t === FALSE) break;
        switch($t){
          case EXTTAB2_TABLE:
            array_push($this->stack, EXTTAB2_TR, EXTTAB2_TD);
            $r .= $this->_starttag(EXTTAB2_TR, $params);
            $r .= $this->_starttag(EXTTAB2_TD, $params);
            break;
          case EXTTAB2_CAPTION:
            $r .= $this->_endtag(EXTTAB2_CAPTION);
            array_pop($this->stack);
            array_push($this->stack, EXTTAB2_TR, EXTTAB2_TD);
            $r .= $this->_starttag(EXTTAB2_TR, $params);
            $r .= $this->_starttag(EXTTAB2_TD, $params);
            break;
          case EXTTAB2_TR:
            array_push($this->stack, EXTTAB2_TD);
            $r = $this->_starttag(EXTTAB2_TD, $params);
            break;
          case EXTTAB2_TD:
          case EXTTAB2_TH:
            break;
        }
        break;
      case EXTTAB2_CAPTION:
        $t = end($this->stack);
        if($t==EXTTAB2_TABLE){
        }
        else{
          return false ; // ignore this, or should echo error?
        }
        break;
    }
    return $r;
  } 
}
 
?>