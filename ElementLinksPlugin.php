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

    public function linkify($text, $args) {
        if (trim($text) == '') {
            return $text;
        }
        
        $titleId = $this->getTitleId();
        $db = get_db();
        $sql = "SELECT record_id FROM {$db->ElementText} "
             . "WHERE element_id = $titleId AND text LIKE '$text'";
        $statement = $db->query($sql);
        
        if ($statement->rowCount() != 1) {
            return $text;
        }

        $res = $statement->fetch();
        $record_id = $res['record_id'];
        $url = url("items/show/$record_id");
        $link = "<a href='$url'>$text</a>"; 
        return $link;
    }

}

