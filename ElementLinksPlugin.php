<?php

class ElementLinksPlugin extends Omeka_Plugin_AbstractPlugin
{
    protected $_hooks = array(
        'initialize',
    );

    protected $_elems = array(
        array('Dublin Core', 'Creator'),
        array('Dublin Core', 'Contributor'),
    );

    protected $_titleId = null;

    public function hookInitialize()
    {
        $this->_titleId = $this->recordTitleId();
        $base = array('Display', 'Item');
        foreach ($this->_elems as $elem) {
            add_filter(array_merge($base, $elem), array($this, 'linkify'));
        }
    }

    public function getTitleId()
    {
        return $this->_titleId;
    }

    public function recordTitleId()
    {
        $db = $this->_db;
        $res = $db->query(
            "SELECT id FROM {$db->Element} WHERE name LIKE 'Title' LIMIT 1"
        )->fetch();

        if ($res) {
            $titleId = $res['id'];
        } else {
            $titleId = null;
        }

        return $titleId;
    }

    /**
    * Make the text into a link to the record with a matching Title.
    */
    public function linkify($text, $args) {
        // Get the original element text before any filtering
        $elementText = $args['element_text']['text'];
        if (trim($elementText) == '') {
            return $text;
        }

        $titleId = $this->getTitleId();
        $db = get_db();
        $res = $db->query("
            SELECT record_id FROM {$db->ElementText}
            WHERE element_id = $titleId AND text LIKE '$elementText'
            ")->fetch();

        if (count($res) != 1) {
            return $text;
        }

        $record_id = $res['record_id'];
        $url = url("items/show/$record_id");
        $link = "<a href='$url'>$text</a>";
        return $link;
    }

}
