<?php

namespace Kdubuc\Odt;

use DOMDocument;
use PhpZip\ZipFile as Zip;

/*
 * Render ODT with a ZIP handler (remember, OpenDocument is basically a zip file
 * according to the specs https://en.wikipedia.org/wiki/OpenDocument_technical_specification).
 */
final class Odt extends Zip
{
    /*
     * Start the rendering process with data array to fill document tags.
     */
    public function render(array $pages, array $pipeline = [], array $options = []) : self
    {
        // Init XML I/O
        $xml = new DOMDocument();

        // Init options array with defaults values
        $options = array_merge([
            'page_break' => true,
        ], $options);

        // Default pipeline renderer
        if (empty($pipeline)) {
            $pipeline = [
                new Tag\Block\Segment(),
                new Tag\Block\Conditional(),
                new Tag\Image(),
                new Tag\Qrcode(),
                new Tag\Date(),
                new Tag\Field(),
            ];
        }

        // Initialize and sort pipeline items renderer using priority value.
        // The blocks have higher priority over the tags, because blocks like
        // IF or SEGMENT must be processed BEFORE any fields for correct
        // context assignation.
        uasort($pipeline, function (Tag\Tag $tag_a, Tag\Tag $tag_b) : int {
            if ($tag_a->getPriority() == $tag_b->getPriority()) {
                return 0;
            }

            return $tag_a->getPriority() > $tag_b->getPriority() ? -1 : 1;
        });

        // Add page break style (if needed)
        if (\count($pages) > 1) {
            $pagebreak_style = $xml->createElement('style:style');
            $pagebreak_style->setAttribute('style:name', 'pagebreak');
            $pagebreak_style->setAttribute('style:family', 'paragraph');
            $pagebreak_style->setAttribute('style:parent-style-name', 'Text_20_body');
            $pagebreak_style_properties = $xml->createElement('style:paragraph-properties');
            $pagebreak_style_properties->setAttribute('fo:break-before', 'page');
            $pagebreak_style->appendChild($pagebreak_style_properties);
            $xml->loadXML($this->getEntryContents('styles.xml'));
            $xml->getElementsByTagName('styles')->item(0)->appendChild($xml->importNode($pagebreak_style, true));
            $this->addFromString('styles.xml', $xml->saveHTML());
            $pagebreak = $xml->createElement('text:p');
            $pagebreak->setAttribute('text:style-name', 'pagebreak');
        }

        // Get template body (disable error reporting)
        @$xml->loadXML($this->getEntryContents('content.xml'));
        $template = $xml->getElementsByTagName('text')->item(0);

        // Prepare ODT
        $odt = $this;

        // Build all document pages
        foreach ($pages as $index => $page) {
            // Provide an easy access to data with dot notation
            $data = dot($page);

            // Duplicate and append new page if index > 0
            if ($index > 0 && true === $options['page_break']) {
                $xml->loadXML($this->getEntryContents('content.xml'));
                $xml->getElementsByTagName('text')->item(0)->appendChild($xml->importNode($pagebreak, true));
                foreach ($template->childNodes as $new_page_child_node) {
                    $xml->getElementsByTagName('text')->item(0)->appendChild($xml->importNode($new_page_child_node, true));
                }
                $this->addFromString('content.xml', $xml->saveXML());
            }

            // ODT multiple rendering pass (pipeline process)
            foreach ($pipeline as $rendering_process) {
                // Catch all tags matches rendering process regex
                // Isolate rendering action
                preg_match_all($rendering_process->getRegex(), $this->getEntryContents('content.xml'), $tags_infos, PREG_SET_ORDER);

                // Apply render process for all tags found
                foreach ($tags_infos as $tag_info) {
                    $odt = $rendering_process->render($this, $data, $tag_info);
                }
            }
        }

        return $odt;
    }
}