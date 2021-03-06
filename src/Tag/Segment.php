<?php

namespace Kdubuc\Odt\Tag;

use Kdubuc\Odt\Odt;
use Adbar\Dot as ArrayDot;

final class Segment extends Tag
{
    /*
     * Regex to isolate tag inside Odt content.
     */
    protected function getRegex() : string
    {
        return "/\[SEGMENT\s(?'key'[\w.]+)\](?'content'.*?)\[\/SEGMENT \k'key'\]/s";
    }

    /*
     * Render process : Within odt, edit tag with data bag.
     */
    protected function render(Odt $odt, ArrayDot $data, array $tag_infos) : Odt
    {
        // Get infos from block
        $block         = preg_quote($tag_infos[0], '/');
        $key           = $tag_infos['key'];
        $block_content = $tag_infos['content'];

        // Get all odt content for archive
        $content = $odt->getEntryContents('content.xml');

        // Render block content using sub content.xml rendering process
        $new_block_content = '';
        foreach ($data->get($key) as $row) {
            $odt->addFromString('content.xml', $block_content);
            $new_block_content .= $odt->render([$row], [], ['page_break' => false])->getEntryContents('content.xml');
        }

        // Update odt
        $odt->addFromString('content.xml', preg_replace("/$block/", $new_block_content, $content));

        return $odt;
    }
}
