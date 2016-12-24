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

    protected $_browseLinks = array(
        array('Item Type Metadata', 'Creation Location'),
        array('Item Type Metadata', 'Current Location'),
        array('Item Type Metadata', 'Cultural Context'),
        array('Dublin Core',        'Format'),
        array('Dublin Core',        'Language'),
        array('Item Type Metadata', 'Gender'),
        array('Item Type Metadata', 'Genre'),
        array('Dublin Core',        'Publisher'),
        array('Item Type Metadata', 'Style/Period'),
        array('Item Type Metadata', 'Original Format'),
        array('Item Type Metadata', 'Original Material'),
        array('Item Type Metadata', 'Role of Creator'),
        array('Item Type Metadata', 'Role of Contributor'),
    );

    protected $_relation = array('Display', 'Item', 'Dublin Core', 'Relation');

    protected $_titleId = null;

    public function hookInitialize()
    {
        $this->_titleId = $this->fetchTitleId();
        $base = array('Display', 'Item');
        foreach ($this->_titleElems as $elem) {
            add_filter(array_merge($base, $elem), array($this, 'linkifyTitle'));
        }
        foreach ($this->_linkElems as $linkElem) {
            add_filter(array_merge($base, $linkElem), array($this, 'linkify'));
        }
        foreach ($this->_browseLinks as $browseLink) {
            add_filter(array_merge($base, $browseLink),
                       array($this, 'browseLink'));
        }
        add_filter($this->_relation, array($this, 'linkifyRelation'));
    }

    public function getTitleId()
    {
        if (!isset($this->_titleId)) {
            $this->_titleId = $this->fetchTitleId();
        }
        return $this->_titleId;
    }

    public function fetchTitleId()
    {
        $db = $this->_db;
        $id = $db->fetchOne(
            "SELECT id FROM {$db->Element} WHERE name LIKE 'Title' LIMIT 1");

        if (!$id) {
            $id = null;
        }

        return $id;
    }

    /**
    * Make the text into a link to the record with a matching Title.
    * If the multilanguage plugin is installed, it also translates
    * the element text.
    */
    public function linkifyTitle($text, $args) {
        // Get the original element text before any filtering
        $elementText = $args['element_text']['text'];
        if (trim($elementText) == '' OR !isset($this->_titleId)) {
            return $text;
        }

        $titleId = $this->getTitleId();
        $db = get_db();
        $res = $db->query("
            SELECT record_id FROM {$db->ElementText}
            WHERE element_id = $titleId AND text LIKE '$elementText'
            ")->fetchAll();

        if (count($res) != 1) {
            return $text;
        }

        $record_id = $res[0]['record_id'];

        $translationTable = $db->getTable('MultilanguageTranslation');
        if ($translationTable) {
            $record = $args['record'];
            $session = new Zend_Session_Namespace;
            if (isset($session->lang)) {
                $locale = $session->lang;
                // Fetch the translation as if this were the Title field
                $translation = $translationTable->getTranslation($record_id,
                    get_class($record), $titleId, $locale, $elementText);
                if ($translation) {
                    $text = $translation->translation;
                }
            }
        }
        $url = url("items/show/$record_id");
        $link = "<a href='$url'>$text</a>";
        return $link;
    }
    /*
    * For elements that are only a URL.
    */
    public function linkify($text, $args)
    {
        return "<a href='$text'>$text</a>";
    }

    /*
    * Replace bare URLs with clickable links.
    */
    public function linkifyRelation($text, $args)
    {
        return preg_replace(
            '!(((f|ht)tp(s)?://)[-a-zA-Zа-яА-Я()0-9@:%_+.~#?&;//=]+)!i',
            '<a href="$1">$1</a>', $text);
    }

    /*
    * Make the text a link to a search query of the text.
    */
    public function browseLink($text, $args)
    {
        $elementText = $args['element_text']['text'];
        $queryUrl = url("items/browse?search=$elementText");
        return "<a href='$queryUrl'>$text</a>";
    }

}
