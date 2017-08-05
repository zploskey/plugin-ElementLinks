<?php

class ElementLinksPlugin extends Omeka_Plugin_AbstractPlugin
{
    protected $_hooks = array(
        'initialize',
    );

    protected $_browse = array(
        array('Item Type Metadata', 'Cultural Context'),
        array('Dublin Core',        'Format'),
        array('Item Type Metadata', 'Gender'),
        array('Item Type Metadata', 'Genre'),
        array('Dublin Core',        'Language'),
        array('Item Type Metadata', 'Original Format'),
        array('Item Type Metadata', 'Original Material'),
        array('Dublin Core',        'Publisher'),
        array('Item Type Metadata', 'Style/Period'),
    );

    protected $_links = array(
        array('Item Type Metadata', 'URI LOC'),
        array('Item Type Metadata', 'URI ULAN'),
    );

    protected $_locations = array(
        array('Item Type Metadata', 'Creation Location'),
        array('Item Type Metadata', 'Current Location'),
    );

    protected $_roles = array(
        array('Item Type Metadata', 'Role of Creator'),
        array('Item Type Metadata', 'Role of Contributor'),
    );

    protected $_titles = array(
        array('Dublin Core', 'Creator'),
        array('Dublin Core', 'Contributor'),
        array('Dublin Core', 'Is Part Of'),
    );

    protected $_relation = array('Display', 'Item', 'Dublin Core', 'Relation');

    protected $_titleId = null;

    public function hookInitialize()
    {
        $this->_titleId = $this->fetchTitleId();
        $base = array('Display', 'Item');
        foreach ($this->_browse as $browse) {
            add_filter(array_merge($base, $browse), array($this, 'linkBrowse'));
        }
        foreach ($this->_titles as $title) {
            add_filter(array_merge($base, $title), array($this, 'linkTitle'));
        }
        foreach ($this->_links as $link) {
            add_filter(array_merge($base, $link), array($this, 'link'));
        }
        foreach ($this->_locations as $loc) {
            add_filter(array_merge($base, $loc), array($this, 'linkLocation'));
        }
        foreach ($this->_roles as $role) {
            add_filter(array_merge($base, $role), array($this, 'linkRole'));
        }
        add_filter($this->_relation, array($this, 'linkRelation'));
    }

    public function getTitleId()
    {
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
    public function linkTitle($text, $args) {
        // Get the original element text before any filtering
        $elementText = $args['element_text']['text'];
        if (trim($elementText) == '') {
            return $text;
        }

        $titleId = $this->getTitleId();

        $db = $this->_db;
        // TODO: don't include non-public items
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
    public function link($text, $args)
    {
        return "<a href='$text'>$text</a>";
    }

    /*
    * Replace bare URLs with clickable links.
    */
    public function linkRelation($text, $args)
    {
        return preg_replace(
            '!(((f|ht)tp(s)?://)[-a-zA-Zа-яА-Я()0-9@:%_+.~#?&;//=]+)!i',
            '<a href="$1">$1</a>', $text);
    }

    /*
    * Make the text a link to a search query of the text on the same element.
    */
    public function linkBrowse($text, $args)
    {
        $elementText = $args['element_text'];

        if (trim($text) == '' OR !$elementText) {
            return $text;
        }

        $params = array('advanced' => array(array(
            'element_id' => $elementText->element_id,
            'type' => 'is exactly',
            'terms' => $elementText->text,
        )));

        $url = url('items/browse', $params);
        return "<a href='$url'>$text</a>";
    }

    public function linkMultiElement($text, $args, $elements)
    {
        $elementText = $args['element_text'];

        if (trim($text) == '' OR !$elementText) {
            return $text;
        }

        $db = $this->_db;

        $advanced = array();
        $index = 0;
        foreach ($elements as $el) {
            $element = $db->getTable('Element')
                ->findByElementSetNameAndElementName($el[0], $el[1]);
            $triplet = array(
                'element_id' => $element->id,
                'type' => 'is exactly',
                'terms' => $elementText->text,
            );
            if ($index++ !== 0) {
                $triplet['joiner'] = 'or';
            }
            $advanced[] = $triplet;
        }

        $params = array('advanced' => $advanced);

        $url = url('items/browse', $params);
        return "<a href='$url'>$text</a>";
    }

    public function linkLocation($text, $args)
    {
        return $this->linkMultiElement($text, $args, $this->_locations);
    }

    public function linkRole($text, $args)
    {
        return $this->linkMultiElement($text, $args, $this->_roles);
    }

}
