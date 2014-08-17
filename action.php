<?php
/**
 * DokuWiki Plugin ExtTab3 (Action component)
 *
 * Allows extended (MediaWiki-style) tables inside DokuWiki 
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Satoshi Sahara <sahara.satoshi@gmail.com>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');

class action_plugin_exttab3 extends DokuWiki_Action_Plugin {

    /**
     * register the eventhandlers
     */
    public function register(Doku_Event_Handler $controller){
        $controller->register_hook('TOOLBAR_DEFINE', 'AFTER', $this, 'handle_toolbar', array ());
    }

    public function handle_toolbar(&$event, $param) {
        $event->data[] = array (
            'type' => 'picker',
            'title' => 'extended table typical patterns',
            'icon' => DOKU_BASE.'lib/plugins/exttab3/images/table.png',
            'list' => array(
                array(
                    'type'   => 'format',
                    'title'  => 'Definition table',
                    'icon'   => DOKU_BASE.'lib/plugins/exttab3/images/d.png',
                    'sample' => 'term',
                    'open'   => '\n{|\n|-\n! ',
                    'close'  => ' || description\n|}\n',
                    'block'  => true
                ),
                array(
                    'type'   => 'format',
                    'title'  => 'longer cell content',
                    'icon'   => DOKU_BASE.'lib/plugins/exttab3/images/table.png',
                    'sample' => 'table caption',
                    'open'   => '\n{| style=""\n|+ ',
                    'close'  => '\n!\nA1\n!\nB1\n|-\n|\nA2\n|\nB2\n|}\n',
                    'block'  => true
                ),
            )
        );
    }
}

