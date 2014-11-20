<?php
/**
 * DokuWiki Plugin ExtTab3 (Syntax component)
 *
 * Allows extended (MediaWiki-style) tables inside DokuWiki
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Satoshi Sahara <sahara.satoshi@gmail.com>
 * @date       2014-11-20
 */
 
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
 
class syntax_plugin_exttab3 extends DokuWiki_Syntax_Plugin {

    protected $stack = array();  // stack of current open tag - used by handle() method
    protected $tableDepth;       // table depth counter
    protected $tagsmap  = array();
    protected $attrsmap = array();

    function __construct() {
        $this->tableDepth = 0;

        // define name, prefix and postfix of tags
        $this->tagsmap = array(
                'table'   => array("", "\n" ),        // table start  : {|
                '/table'  => array("", ""),           // table end    : |}
                'caption' => array("\t", "\n" ),      // caption      : |+
                'tr'      => array("\t", "\n" ),      // table row    : |-
                'th'      => array("\t"."\t", "\n" ), // table header : !
                'td'      => array("\t"."\t", "\n" ), // table data   : |
                'div'     => array("", "\n" ),        // wrapper
        );

        // define allowable attibutes for table tags
        $this->attrsmap = array(
            # simple ones (value is a single word)
            'align', 'border', 'cellpadding', 'cellspacing', 'frame', 
            'rules', 'width', 'class', 'dir', 'id', 'lang', 'xml:lang',
            # more complex ones (value is a string or style)
            'bgcolor', 'summary', 'title', 'style',
            # additional tr, thead, tbody, tfoot attributes
            'char', 'charoff', 'valign',
            # additional td attributes
            'abbr', 'colspan', 'axis', 'headers', 'rowspan', 'scope',
            'height', 'width', 'nowrap',
        );
    }

    function getType(){  return 'container';}
    function getPType(){ return 'block';}
    function getSort(){  return 59; } // = Doku_Parser_Mode_table-1
    function getAllowedTypes() { 
        return array('container', 'formatting', 'substition', 'disabled', 'protected'); 
    }

    /**
     * Exttab3 syntax match patterns for parser
     * modified from original exttab2 code
     */
    function connectTo($mode) {
        $pluginMode = 'plugin_'.$this->getPluginName();
        $this->Lexer->addEntryPattern('\n\{\|[^\n]*',$mode, $pluginMode); 
    }
    function postConnect() {
        $pluginMode = 'plugin_'.$this->getPluginName();
        $attrs = '[^\n\{\|\!\[]+'; // match pattern for attributes

        // terminale = Exit Pattren: table end markup + extra brank line
        $this->Lexer->addExitPattern(' *?\n\|\}(?=\n\n)', $pluginMode);

        // caption:      |+ attrs | caption
        $this->Lexer->addPattern("\n\|\+ *(?:$attrs\|(?!\|))?", $pluginMode);
        // table row:    |- attrs
        $this->Lexer->addPattern(' *?\n\|\-+[^\n]*', $pluginMode);
        // table start:  {| attrs
        $this->Lexer->addPattern(' *?\n\{\|[^\n]*', $pluginMode);
        // table end:    |}
        $this->Lexer->addPattern(' *?\n\|\}', $pluginMode);
        // table header: ! attrs |
        $this->Lexer->addPattern("(?: *?\n|\!)\!(?:$attrs\|(?!\|))?", $pluginMode);
        // table data:   | attrs |
        $this->Lexer->addPattern("(?: *?\n|\|)\|(?:$attrs\|(?!\|))?", $pluginMode);
    }


    /**
     * helper function to simplify writing plugin calls to the instruction list
     * first three arguments are passed to function render as $data
     */
    protected function _writeCall($tag, $attr, $state, $pos, $match, &$handler) {
        $handler->addPluginCall($this->getPluginName(),
            array($state, $tag, $attr), $state, $pos, $match);
    }

    /**
     * helper function for exttab syntax translation to html
     *
     * @param string $match       matched string
     * @return array              tag name, and attributes
     */
    protected function _resolve_markup($match='') {
        $markup = substr(trim($match), 0, 2);
        if ($markup       == '{|') { // table_start
            return array('table', substr($match, 2));
        } elseif ($markup == '|}') { // table_end
            return array('/table', '');
        } elseif ($markup == '|+') { // table_caption
            return array('caption', trim(substr($match, 2), '|'));
        } elseif ($markup == '|-') { // table_row
            return array('tr', trim(substr($match, 2), '-'));
        }
        $markup = substr(trim($match), 0, 1);
        if ($markup       == '!') {  // table_header
            return array('th', trim($match, '!|'));
        } elseif ($markup == '|') {  // table_data
            return array('td', trim($match, '|'));
        } else {
            msg($this->getPluginName().' ERROR: unknown syntax: '.hsc($match) ,-1);
            return false;
        }
    }


    /**
     * Handle the match
     */
    function handle($match, $state, $pos, Doku_Handler $handler) {

        // msg('handle: state='.$state.' match="'.str_replace("\n","_",$match).'"', 0);

        switch ($state) {
            case DOKU_LEXER_ENTER:
                // wrapper open
                $this->_writeCall('div', 'class="exttab"', DOKU_LEXER_ENTER, $pos,$match,$handler);
                // table start
                list($tag, $attr) = $this->_resolve_markup($match);
                array_push($this->stack, $tag);
                $this->_writeCall($tag, $attr, DOKU_LEXER_ENTER, $pos,$match,$handler);
                $this->tableDepth = $this->tableDepth +1; // increment table depth counter
                break;
            case DOKU_LEXER_MATCHED:
                $tag_prev = end($this->stack);
                list($tag, $attr) = $this->_resolve_markup($match);
                switch ($tag_prev) {
                    case 'caption':
                                $oldtag = array_pop($this->stack);
                                $this->_writeCall($oldtag,'',DOKU_LEXER_EXIT, $pos,$match,$handler);
                    case 'table':
                        switch ($tag) {
                            case 'table':
                                msg($this->getPluginName().' Syntax ERROR: match='.hsc($match) ,-1);
                                break;
                            case 'caption':
                            case 'tr':
                                array_push($this->stack, $tag);
                                $this->_writeCall($tag, $attr, DOKU_LEXER_ENTER, $pos,$match,$handler);
                                break;
                            case 'th':
                            case 'td':
                                array_push($this->stack, 'tr');
                                $this->_writeCall('tr', '', DOKU_LEXER_ENTER, $pos,$match,$handler);
                                array_push($this->stack, $tag);
                                $this->_writeCall($tag, $attr, DOKU_LEXER_ENTER, $pos,$match,$handler);
                                break;
                            case '/table':
                                array_pop($this->stack);
                                $this->_writeCall('table', '', DOKU_LEXER_EXIT, $pos,$match,$handler);
                                $this->tableDepth = $this->tableDepth -1;
                                break;
                        }
                        break;
                    case 'tr':
                        switch ($tag) {
                            case 'table':
                            case 'caption':
                                msg($this->getPluginName().' Syntax ERROR: match='.hsc($match) ,-1);
                                break;
                            case 'tr':
                                $oldtag = array_pop($this->stack);
                                $this->_writeCall($oldtag, '', DOKU_LEXER_EXIT, $pos,$match,$handler); 
                                array_push($this->stack, $tag);
                                $this->_writeCall($tag, $attr, DOKU_LEXER_ENTER, $pos,$match,$handler);
                                break;
                            case 'th':
                            case 'td':
                                array_push($this->stack, $tag);
                                $this->_writeCall($tag, $attr, DOKU_LEXER_ENTER, $pos,$match,$handler);
                                break;
                            case '/table':
                                do { // rewind table
                                    $oldtag = array_pop($this->stack);
                                    $this->_writeCall($oldtag,'',DOKU_LEXER_EXIT, $pos,$match,$handler);
                                } while ($oldtag != 'table');
                                $this->tableDepth = $this->tableDepth -1;
                                break;
                        }
                        break;
                    case 'th':
                    case 'td':
                        switch ($tag) {
                            case 'table':   // a table within a table
                                array_push($this->stack, $tag);
                                $this->_writeCall($tag, $attr, DOKU_LEXER_ENTER, $pos,$match,$handler);
                                $this->tableDepth = $this->tableDepth +1;
                                break;
                            case 'caption':
                                msg($this->getPluginName().' Syntax ERROR: match='.hsc($match) ,-1);
                                break;
                            case 'tr':
                                do { // rewind old row prior to start new row
                                    $oldtag = array_pop($this->stack);
                                    $this->_writeCall($oldtag,'',DOKU_LEXER_EXIT, $pos,$match,$handler);
                                } while ($oldtag != 'tr');
                                array_push($this->stack, $tag);
                                $this->_writeCall($tag, $attr, DOKU_LEXER_ENTER, $pos,$match,$handler);
                                break;
                            case 'th':
                            case 'td':
                                $oldtag = array_pop($this->stack);
                                $this->_writeCall($oldtag,'',DOKU_LEXER_EXIT, $pos,$match,$handler); 
                                array_push($this->stack, $tag);
                                $this->_writeCall($tag, $attr, DOKU_LEXER_ENTER, $pos,$match,$handler);
                                break;
                            case '/table':
                                do { // rewind table
                                    $oldtag = array_pop($this->stack);
                                    $this->_writeCall($oldtag,'',DOKU_LEXER_EXIT, $pos,$match,$handler);
                                } while ($oldtag != 'table');
                                $this->tableDepth = $this->tableDepth -1;
                                break;
                        }
                        break;
                }
                break;
            case DOKU_LEXER_EXIT:
                if ($this->tableDepth > 1) {
                    msg($this->getPluginName().': missing table end markup "|}" '.$this->tableDepth, -1);
                }
                while ($tag = array_pop($this->stack)) {
                    $this->_writeCall($tag,'',DOKU_LEXER_EXIT, $pos,$match,$handler);
                }
                $this->tableDepth = 0;
                // wrapper close
                $this->_writeCall('div', '', DOKU_LEXER_EXIT, $pos,$match,$handler);
                break;
            case DOKU_LEXER_UNMATCHED:
                $tag_prev = end($this->stack);
                switch ($tag_prev) {
                    case 'caption':
                                // cdata --- use base() instead of $this->_writeCall()
                                $handler->base($match, $state, $pos);
                                break;
                    case 'table':
                                array_push($this->stack, 'tr');
                                $this->_writeCall('tr','',DOKU_LEXER_ENTER, $pos,$match,$handler);
                    case 'tr':
                                array_push($this->stack, 'td');
                                $this->_writeCall('td','',DOKU_LEXER_ENTER, $pos,$match,$handler);
                    case 'th':
                    case 'td':
                                // cdata --- use base() instead of $this->_writeCall()
                                $handler->base($match, $state, $pos);
                                break;
                }
                break;
        }
    }


   /**
    * Create output
    */
    function render($format, Doku_Renderer $renderer, $data) {
        if (empty($data)) return false;

        switch ($format) {
            case 'xhtml' : return $this->render_xhtml($renderer, $data);
            default:
                return true;
        }
        return false;
    }

    protected function render_xhtml(&$renderer, $data) {
        //list($tag, $state, $match) = $data;
        list($state, $tag, $attr) = $data;

        switch ( $state ) {
            case DOKU_LEXER_ENTER:    // open tag
                $renderer->doc.= $this->_open($tag, $attr);
                break;
            case DOKU_LEXER_MATCHED:  // defensive, shouldn't occur
            case DOKU_LEXER_UNMATCHED:
                $renderer->cdata($tag);
                break;
            case DOKU_LEXER_EXIT:     // close tag
                $renderer->doc.= $this->_close($tag);
                break;
        }
    }

    /**
     * open a exttab tag, used by render_xhtml()
     *
     * @param  string $tag        'table','caption','tr','th' or 'td'
     * @param  string $attr       attibutes of tag element
     * @return string             html used to open the tag
     */
    protected function _open($tag, $attr=NULL) {
        $before = $this->tagsmap[$tag][0];
        $after  = $this->tagsmap[$tag][1];
        $attr = $this->_cleanAttrString($attr, $this->attrsmap);
        return $before.'<'.$tag.$attr.'>'.$after;
    }

    /**
     * close a exttab tag, used by render_xhtml()
     *
     * @param  string $tag        'table','caption','tr','th' or 'td'
     * @return string             html used to close the tag
     */
    protected function _close($tag) {
        $before = $this->tagsmap[$tag][0];
        $after  = $this->tagsmap[$tag][1];
        return $before.'</'.$tag.'>'.$after;
    }



    /**
     * Make the attribute string safe to avoid XSS attacks.
     *
     * @author Ashish Myles <marcianx@gmail.com>
     *
     * @param  string $attr           attibutes to be checked
     * @param  array  $allowed_keys   allowed attribute name map
     *                                ex: array('border','bgcolor');
     * @return string                 cleaned attibutes
     *
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
    function _cleanAttrString($attr='', $allowed_keys) {
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
            if (in_array($attrname, $allowed_keys, true)) {
                # make sure that style attributes do not have a url in them
                if ($attrname != 'style' ||
                      (stristr($attrval, 'url') === FALSE &&
                      stristr($attrval, 'import') === FALSE)) {
                    $clean_attr.= " $attrname=$attrval";
                }
            }
        }
        return $clean_attr;
    }

}
