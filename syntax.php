<?php
/**
 * DokuWiki Plugin ExtTab3 (Syntax component)
 *
 * Allows extended (MediaWiki-style) tables inside DokuWiki
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Satoshi Sahara <sahara.satoshi@gmail.com>
 */
 
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
 
class syntax_plugin_exttab3 extends DokuWiki_Syntax_Plugin {

    protected $mode;
    protected $stack = array();  // stack of current open tag - used by handle() method
    protected $tagsmap  = array();
    protected $attrsmap = array();

    function __construct() {
        $this->mode = substr(get_class($this), 7);

        // define name, prefix and postfix of tags
        $this->tagsmap = array(
                'table'    => array("", "\n" ),     // table start  : {|
                '/table'   => array("", "\n"),      // table end    : |}
                'caption'  => array("", ""),        // caption      : |+
                '/caption' => array("", "\n"),
                'tr'       => array("", "\n"),      // table row    : |-
                '/tr'      => array("", "\n"),
                'th'       => array("", ""),        // table header : !
                '/th'      => array("", "\n"),
                'td'       => array("", ""),        // table data   : |
                '/td'      => array("", "\n"),
        );

        // define allowable attibutes for table tags
        $this->attrsmap = array(
            # html5 HTML Global Attributes
            'accesskey', 'class', 'contenteditable', 'contextmenu',
            'dir', 'draggable', 'dropzone', 'hidden', 'id', 'lang',
            'spellcheck', 'style', 'tabindex', 'title', 'translate',
            'xml:lang',
            # html5 table tag
            'border', 'sortable',
            # html5 th and td tag
            'abbr', 'colspan', 'headers', 'rowspan', 'scope', 'sorted',
            # deprecated in html5
            'align', 'valign', 'width', 'height', 'bgcolor', 'nowrap',
        );
    }

    function getType(){  return 'container';}
    function getPType(){ return 'block';}
    function getSort(){  return 59; } // = Doku_Parser_Mode_table-1
    function getAllowedTypes() { 
        return array('container', 'formatting', 'substition', 'disabled', 'protected', 'paragraphs');
    }
    // override default accepts() method to allow nesting
    public function accepts($mode) {
        if ($mode == $this->mode) return true;
        return parent::accepts($mode);
    }

    /**
     * Exttab3 syntax match patterns for parser
     * modified from original exttab2 code
     */
    function connectTo($mode) {
        // table start:  {| attrs
        $this->Lexer->addEntryPattern('\n\{\|[^\n]*',$mode, $this->mode);
    }
    function postConnect() {
        // table end:    |}
        $this->Lexer->addExitPattern('[ \t]*\n\|\}', $this->mode);

        // match pattern for attributes
        $attrs = '[^\n\{\|\!\[]+';

        // caption:      |+ attrs | caption
        $this->Lexer->addPattern("\n\|\+ *(?:$attrs\|(?!\|))?", $this->mode);
        // table row:    |- attrs
        $this->Lexer->addPattern(' *?\n\|\-+[^\n]*', $this->mode);
        // table header: ! attrs |
        $this->Lexer->addPattern("(?: *?\n|\!)\!(?:$attrs\|(?!\|))?", $this->mode);
        // table data:   | attrs |
        $this->Lexer->addPattern("(?: *?\n|\|)\|(?:$attrs\|(?!\|))?", $this->mode);
    }


    /**
     * helper function to simplify writing plugin calls to the instruction list
     * first three arguments are passed to function render as $data
     */
    protected function _writeCall($tag, $attr, $state, $pos, $match, $handler) {
        $handler->addPluginCall($this->getPluginName(),
            array($state, $tag, $attr), $state, $pos, $match);
    }

    protected function _open($tag, $attr, $pos, $match, $handler) {
        $this->_writeCall($tag,$attr,DOKU_LEXER_ENTER, $pos,$match,$handler);
    }

    protected function _close($tag, $pos, $match, $handler) {
        $this->_writeCall($tag,'',DOKU_LEXER_EXIT, $pos,$match,$handler);
    }

    /**
     * helper function for exttab syntax translation to html
     *
     * @param string $match       matched string
     * @return array              tag name and attributes
     */
    protected function _interpret($match='') {
        $markup = ltrim($match);
        $len = 2;
        switch (substr($markup, 0, $len)) {
            case '{|': $tag = 'table';   break;
            case '|}': $tag = '/table';  break;
            case '|+': $tag = 'caption'; break;
            case '|-': $tag = 'tr';      break;
            case '||': $tag = 'td';      break;
            case '!!': $tag = 'th';      break;
            default:
                $len = 1;
                switch (substr($markup, 0, $len)) {
                    case '!': $tag = 'th'; break;
                    case '|': $tag = 'td'; break;
                }
        }
        if (isset($tag)) {
            $attrs = substr($markup, $len);
            return array($tag, $attrs);
        } else {
            msg($this->getPluginName().' ERROR: unknown syntax: '.hsc($markup) ,-1);
            return false;
        }
    }

    /**
     * append specified class name to attributes
     *
     * @param string $class       class name
     * @param string $attr        attributes of html tag
     * @return string             modified $attr
     */
    private function _appendClass($class, $attr) {
        $regex = "/\b(?:class=\")(.*?\b($class)?\b.*?)\"/";
        preg_match($regex, $attr, $matches);
        if ($matches[2]) {
            // $class found in the class attribute
            return $attr;
        } elseif (empty($matches[0])) {
            // class attribute is not specified
            return $attr.' class="'.$class.'"';
        } else {
            // class attribute is specified, but include $class
            $items = explode(' ',$matches[1]);
            $items[] = $class;
            $replace = '$class="'.implode(' ',$items).'"';
            return str_replace($matches[0], $replace, $attr);
        }
    }


    /**
     * Handle the match
     */
    function handle($match, $state, $pos, Doku_Handler $handler) {

        //error_log('ExtTable handle: state='.$state.' match="'.str_replace("\n","_",$match).'"');

        switch ($state) {
            case DOKU_LEXER_ENTER:
                // table start
                list($tag, $attr) = $this->_interpret($match);
                // ensure that class attribute cotains "exttable"
                $attr = $this->_appendClass('exttable', $attr);
                array_push($this->stack, $tag);
                $this->_open($tag, $attr, $pos,$match,$handler);
                break;
            case DOKU_LEXER_EXIT:
                do { // rewind table
                    $oldtag = array_pop($this->stack);
                    $this->_close($oldtag, $pos,$match,$handler);
                } while ($oldtag != 'table');
                break;
            case DOKU_LEXER_MATCHED:
                $tag_prev = end($this->stack);
                list($tag, $attr) = $this->_interpret($match);
                switch ($tag_prev) {
                    case 'caption':
                                $oldtag = array_pop($this->stack);
                                $this->_close($oldtag, $pos,$match,$handler);
                    case 'table':
                        switch ($tag) {
                            case 'caption':
                            case 'tr':
                                array_push($this->stack, $tag);
                                $this->_open($tag, $attr, $pos,$match,$handler);
                                break;
                            case 'th':
                            case 'td':
                                array_push($this->stack, 'tr');
                                $this->_open('tr', '', $pos,$match,$handler);
                                array_push($this->stack, $tag);
                                $this->_open($tag, $attr, $pos,$match,$handler);
                                break;
                        }
                        break;
                    case 'tr':
                        switch ($tag) {
                            case 'caption':
                                msg($this->getPluginName().' Syntax ERROR: match='.hsc(trim($match)) ,-1);
                                break;
                            case 'tr':
                                $oldtag = array_pop($this->stack);
                                $this->_close($oldtag, $pos,$match,$handler);
                                array_push($this->stack, $tag);
                                $this->_open($tag, $attr, $pos,$match,$handler);
                                break;
                            case 'th':
                            case 'td':
                                array_push($this->stack, $tag);
                                $this->_open($tag, $attr, $pos,$match,$handler);
                                break;
                        }
                        break;
                    case 'th':
                    case 'td':
                        switch ($tag) {
                            case 'caption':
                                msg($this->getPluginName().' Syntax ERROR: match='.hsc(trim($match)) ,-1);
                                break;
                            case 'tr':
                                do { // rewind old row prior to start new row
                                    $oldtag = array_pop($this->stack);
                                    $this->_close($oldtag, $pos,$match,$handler);
                                } while ($oldtag != 'tr');
                                array_push($this->stack, $tag);
                                $this->_open($tag, $attr, $pos,$match,$handler);
                                break;
                            case 'th':
                            case 'td':
                                $oldtag = array_pop($this->stack);
                                $this->_close($oldtag, $pos,$match,$handler);
                                array_push($this->stack, $tag);
                                $this->_open($tag, $attr, $pos,$match,$handler);
                                break;
                        }
                        break;
                }
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
                                $this->_open('tr','', $pos,$match,$handler);
                    case 'tr':
                                array_push($this->stack, 'td');
                                $this->_open('td','', $pos,$match,$handler);
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
            case 'xhtml' :
                return $this->render_xhtml($renderer, $data);
            case 'odt'   :
            case 'odt_pdf':
                $odt = $this->loadHelper('exttab3_odt');
                return $odt->render($renderer, $data);
            default:
                return false;
        }
    }

    protected function render_xhtml(Doku_Renderer $renderer, $data) {
        //list($tag, $state, $match) = $data;
        list($state, $tag, $attr) = $data;

        switch ( $state ) {
            case DOKU_LEXER_ENTER:    // open tag
                $renderer->doc.= $this->_tag_open($tag, $attr);
                break;
            case DOKU_LEXER_MATCHED:  // defensive, shouldn't occur
            case DOKU_LEXER_UNMATCHED:
                $renderer->cdata($tag);
                break;
            case DOKU_LEXER_EXIT:     // close tag
                $renderer->doc.= $this->_tag_close($tag);
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
    protected function _tag_open($tag, $attr=NULL) {
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
    protected function _tag_close($tag) {
        $before = $this->tagsmap['/'.$tag][0];
        $after  = $this->tagsmap['/'.$tag][1];
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
    protected function _cleanAttrString($attr='', $allowed_keys) {
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
