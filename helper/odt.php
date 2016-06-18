<?php
/**
 * ODT (Open Document format) export for Exttab3 plugin
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Lars (LarsDW223)
 */

if(!defined('DOKU_INC')) die();

class helper_plugin_exttab3_odt extends DokuWiki_Plugin {

    function render(Doku_Renderer $renderer, $data) {
        $properties = array();

        // Return if installed ODT plugin version is too old.
        if ( method_exists($renderer, 'getODTProperties') == false ||
             method_exists($renderer, '_odtTableAddColumnUseProperties') == false ) {
            return false;
        }

        //list($tag, $state, $match) = $data;
        list($state, $tag, $attr) = $data;

        // Get style content
        $style = '';
        if (preg_match('/style=".*"/', $attr, $matches) === 1) {
            $style = substr($matches[0], 6);
            $style = trim($style, ' "');
        }

        // class to get CSS Properties by $render->getODTProperties()
        $class = 'exttable';

        switch ( $state ) {
            case DOKU_LEXER_ENTER:    // open tag
                if (!class_exists('ODTDocument')) {
                    // Code for backwards compatibility to older ODT versions
                    // Get CSS properties for ODT export.
                    $renderer->getODTProperties($properties, $tag, $class, $style);

                    switch ($tag) {
                        case 'table':
                            $renderer->_odtTableOpenUseProperties($properties);
                            break;
                        case 'caption':
                            // There is no caption in ODT table format.
                            // So we emulate it by creating a header row spaning over all columns.
                            $renderer->_odtTableRowOpenUseProperties($properties);

                            // Parameter 'colspan=0' indicates span across all columns!
                            $renderer->_odtTableHeaderOpenUseProperties($properties, 0, 1);
                            break;
                        case 'th':
                            $renderer->_odtTableHeaderOpenUseProperties($properties);
                            $renderer->_odtTableAddColumnUseProperties($properties);
                            break;
                        case 'tr':
                            $renderer->_odtTableRowOpenUseProperties($properties);
                            break;
                        case 'td':
                            $renderer->_odtTableCellOpenUseProperties($properties);
                            break;
                    }
                } else {
                    switch ($tag) {
                        case 'table':
                            $renderer->_odtTableOpenUseCSS(NULL, NULL, $tag, $attr);
                            break;
                        case 'caption':
                            // There is no caption in ODT table format.
                            // So we emulate it by creating a header row spaning over all columns.
                            $renderer->_odtTableRowOpenUseCSS($tag, $attr);

                            // Parameter 'colspan=0' indicates span across all columns!
                            $renderer->_odtTableHeaderOpenUseCSS(0, 1, $tag, $attr);
                            break;
                        case 'th':
                            $renderer->_odtTableHeaderOpenUseCSS(1, 1, $tag, $attr);
                            break;
                        case 'tr':
                            $renderer->_odtTableRowOpenUseCSS($tag, $attr);
                            break;
                        case 'td':
                            $renderer->_odtTableCellOpenUseCSS(1, 1, $tag, $attr);
                            break;
                    }
                }
                break;
            case DOKU_LEXER_MATCHED:  // defensive, shouldn't occur
                break;
            case DOKU_LEXER_UNMATCHED:
                $renderer->cdata($tag);
                break;
            case DOKU_LEXER_EXIT:     // close tag
                switch ($tag) {
                    case 'table':
                        //$renderer->table_close();
                        $renderer->_odtTableClose();
                        break;
                    case 'caption':
                        $renderer->tableheader_close();
                        $renderer->tablerow_close();
                        break;
                    case 'th':
                        $renderer->tableheader_close();
                        break;
                    case 'tr':
                        $renderer->tablerow_close();
                        break;
                    case 'td':
                        $renderer->p_close();
                        $renderer->tablecell_close();
                        break;
                }
                break;
        }
    }
}
