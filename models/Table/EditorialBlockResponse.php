<?php

class Table_EditorialBlockResponse extends Omeka_Db_Table
{
    public function findResponsesForBlock($block)
    {
        $responses = array();
        $options = $block->getOptions();
        if (isset($options['response_ids'])) {
            $responseIds = $options['response_ids'];
        } else {
            return array();
        }
        foreach ($responseIds as $key => $responseId) {
            // it's possible that a response could be deleted,
            // but hasn't been removed from the block's list of response ids
            $response = $this->find($responseId);
            if ($response) {
                $responses[] = $response;
            } else {
                // remove from the list in the block
                unset($responseIds[$key]);
            }
        }

        $options['response_ids'] = $responseIds;
        $block->setOptions($options);

        return $responses;
    }
}
