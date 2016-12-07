<?php

class ElementLinksPlugin extends Omeka_Plugin_AbstractPlugin
{
    protected $_hooks = array(
        'initialize',
    );

    protected $_titleElems = array(
        array('Dublin Core', 'Creator'),
        array('Dublin Core', 'Contributor'),
        array('Dublin Core', 'Is Part Of'),
    );

    protected $_linkElems = array(
        array('Item Type Metadata', 'URI LOC'),
        array('Item Type Metadata', 'URI ULAN'),
    );

    protected $_titleId = null;

    public function hookInitialize()
    {
        $this->_titleId = $this->recordTitleId();
        $base = array('Display', 'Item');
        foreach ($this->_titleElems as $elem) {
            add_filter(array_merge($base, $elem), array($this, 'linkifyTitle'));
        }
        foreach ($this->_linkElems as $linkElem) {
            add_filter(array_merge($base, $linkElem), array($this, 'linkify'));
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
    public function linkifyTitle($text, $args) {
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
    /*
    * For elements that are only a URL.
    */
    public function linkify($text, $args) {
        return "<a href='$text'>$text</a>";
    }

}
